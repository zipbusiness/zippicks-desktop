<?php
/**
 * Master Set Schema
 * 
 * Specialized schema handling for Master Critic lists and Top 10 sets
 *
 * @package ZipPicks_Schema
 * @since 1.0.0
 */

class ZipPicks_Master_Set_Schema {
    
    private $generator;
    
    /**
     * Constructor
     * 
     * @param ZipPicks_Schema_Generator $generator Schema generator instance
     */
    public function __construct($generator) {
        $this->generator = $generator;
        
        // Hook into Master Critic list display
        add_filter('zippicks_master_list_schema', [$this, 'enhance_master_list_schema'], 10, 2);
        add_action('wp_head', [$this, 'inject_list_schema'], 20);
        
        // Add schema to Master Critic REST API
        add_filter('rest_prepare_master_critic_list', [$this, 'add_schema_to_rest_response'], 10, 2);
        
        // Handle schema for single list items
        add_filter('zippicks_list_item_schema', [$this, 'enhance_list_item_schema'], 10, 3);
    }
    
    /**
     * Enhance master list schema with additional context
     * 
     * @param array $schema Base schema
     * @param WP_Post $post List post
     * @return array Enhanced schema
     */
    public function enhance_master_list_schema($schema, $post) {
        if ($post->post_type !== 'master_critic_list') {
            return $schema;
        }
        
        // Add enhanced metadata
        $enhancements = [];
        
        // Geographic context
        $location = get_post_meta($post->ID, '_mc_location', true);
        $city_slug = get_post_meta($post->ID, 'city_slug', true);
        if ($location || $city_slug) {
            $enhancements['city_context'] = $location ?: $city_slug;
        }
        
        // Topic/cuisine context
        $topic = get_post_meta($post->ID, '_mc_topic', true);
        $dish_slug = get_post_meta($post->ID, 'dish_slug', true);
        if ($topic || $dish_slug) {
            $enhancements['list_type'] = $topic ?: $dish_slug;
        }
        
        // Generation metadata
        $generation_id = get_post_meta($post->ID, '_mc_generation_id', true);
        if ($generation_id) {
            $enhancements['generation_id'] = (int) $generation_id;
        }
        
        $ai_provider = get_post_meta($post->ID, '_mc_ai_provider', true);
        if ($ai_provider) {
            $enhancements['ai_provider'] = $ai_provider;
        }
        
        // Add associated vibes
        $vibe_ids = get_post_meta($post->ID, '_mc_vibe_ids', true);
        if ($vibe_ids && is_array($vibe_ids)) {
            $vibes = $this->get_vibes_by_ids($vibe_ids);
            if (!empty($vibes)) {
                $enhancements['vibes'] = $vibes;
            }
        }
        
        // Add curatorial information
        $enhancements = array_merge($enhancements, $this->get_curatorial_context($post));
        
        // Add geographic specificity
        $enhancements = array_merge($enhancements, $this->get_geographic_context($post));
        
        return ZipPicks_Schema_Types::add_zippicks_extensions($schema, $enhancements);
    }
    
    /**
     * Inject list schema on single list pages
     */
    public function inject_list_schema() {
        if (!is_singular('master_critic_list')) {
            return;
        }
        
        global $post;
        $schema = $this->generator->generate_master_list_schema($post);
        
        if (!$schema) {
            return;
        }
        
        // Enhance with Master Set specific data
        $schema = $this->enhance_master_list_schema($schema, $post);
        
        // Add FAQPage schema if list has detailed descriptions
        $faq_schema = $this->generate_list_faq_schema($post);
        if ($faq_schema) {
            $this->output_schema($faq_schema);
        }
        
        // Add local business collection schema
        $collection_schema = $this->generate_business_collection_schema($post);
        if ($collection_schema) {
            $this->output_schema($collection_schema);
        }
    }
    
    /**
     * Add schema to REST API response
     * 
     * @param WP_REST_Response $response Response object
     * @param WP_Post $post Post object
     * @return WP_REST_Response Modified response
     */
    public function add_schema_to_rest_response($response, $post) {
        $schema = $this->generator->generate_master_list_schema($post);
        
        if ($schema) {
            $schema = $this->enhance_master_list_schema($schema, $post);
            $response->data['schema'] = $schema;
        }
        
        return $response;
    }
    
    /**
     * Enhance individual list item schema
     * 
     * @param array $schema Base item schema
     * @param array $restaurant Restaurant data
     * @param int $position Position in list
     * @return array Enhanced schema
     */
    public function enhance_list_item_schema($schema, $restaurant, $position) {
        // Add position-specific enhancements
        $enhancements = [];
        
        // Classification based on position
        if ($position <= 3) {
            $enhancements['classification'] = ZipPicks_Schema_Types::CLASSIFICATION_ESSENTIAL;
        } elseif ($position <= 6) {
            $enhancements['classification'] = ZipPicks_Schema_Types::CLASSIFICATION_OUTSTANDING;
        } else {
            $enhancements['classification'] = ZipPicks_Schema_Types::CLASSIFICATION_RECOMMENDED;
        }
        
        // Add detailed restaurant information if available
        if (isset($restaurant['zpid'])) {
            $business_post = $this->find_business_by_zpid($restaurant['zpid']);
            if ($business_post) {
                $business_schema = $this->generator->generate_business_schema($business_post);
                if ($business_schema) {
                    // Merge business details into list item
                    $schema['item'] = array_merge($schema['item'], $business_schema);
                }
            }
        }
        
        // Add ranking context
        $enhancements['ranking_position'] = $position;
        $enhancements['ranking_context'] = $this->get_ranking_context($position);
        
        return ZipPicks_Schema_Types::add_zippicks_extensions($schema, $enhancements);
    }
    
    /**
     * Generate FAQ schema for detailed lists
     * 
     * @param WP_Post $post List post
     * @return array|null FAQ schema
     */
    private function generate_list_faq_schema($post) {
        $restaurants_json = get_post_meta($post->ID, '_mc_restaurants', true);
        if (!$restaurants_json) {
            return null;
        }
        
        $restaurants = json_decode($restaurants_json, true);
        if (!is_array($restaurants) || count($restaurants) < 3) {
            return null;
        }
        
        $faqs = [];
        $location = get_post_meta($post->ID, '_mc_location', true);
        $topic = get_post_meta($post->ID, '_mc_topic', true);
        
        // Generate common questions about the list
        if ($location && $topic) {
            $faqs[] = [
                '@type' => 'Question',
                'name' => "What are the best {$topic} restaurants in {$location}?",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $this->generate_top_restaurants_answer($restaurants, $location, $topic)
                ]
            ];
            
            $faqs[] = [
                '@type' => 'Question', 
                'name' => "How are these {$topic} restaurants ranked?",
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => "Our rankings are based on comprehensive analysis including taste quality, service excellence, speed of service, and overall value. Each restaurant is scored on our 10-point ZipPicks scale."
                ]
            ];
            
            // Add question about top restaurant
            if (!empty($restaurants[0])) {
                $top_restaurant = $restaurants[0];
                $faqs[] = [
                    '@type' => 'Question',
                    'name' => "What makes {$top_restaurant['name']} the #1 {$topic} restaurant in {$location}?",
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $top_restaurant['summary'] ?? "This restaurant excels in all categories with exceptional {$topic} and outstanding service."
                    ]
                ];
            }
        }
        
        if (empty($faqs)) {
            return null;
        }
        
        return [
            '@context' => ZipPicks_Schema_Types::CONTEXT,
            '@type' => 'FAQPage',
            'mainEntity' => $faqs
        ];
    }
    
    /**
     * Generate business collection schema
     * 
     * @param WP_Post $post List post
     * @return array|null Collection schema
     */
    private function generate_business_collection_schema($post) {
        $restaurants_json = get_post_meta($post->ID, '_mc_restaurants', true);
        if (!$restaurants_json) {
            return null;
        }
        
        $restaurants = json_decode($restaurants_json, true);
        if (!is_array($restaurants)) {
            return null;
        }
        
        $location = get_post_meta($post->ID, '_mc_location', true);
        $topic = get_post_meta($post->ID, '_mc_topic', true);
        
        return [
            '@context' => ZipPicks_Schema_Types::CONTEXT,
            '@type' => 'Collection',
            'name' => get_the_title($post),
            'description' => "Curated collection of the best {$topic} restaurants in {$location}",
            'url' => get_permalink($post),
            'numberOfItems' => count($restaurants),
            'collectionSize' => count($restaurants),
            'about' => [
                '@type' => 'Thing',
                'name' => $topic,
                'description' => "{$topic} restaurants and dining"
            ],
            'spatial' => [
                '@type' => 'Place',
                'name' => $location
            ]
        ];
    }
    
    /**
     * Get curatorial context for the list
     * 
     * @param WP_Post $post List post
     * @return array Curatorial data
     */
    private function get_curatorial_context($post) {
        $context = [];
        
        // Editorial standards
        $context['editorial_standards'] = 'ZipPicks Master Critic standards';
        $context['curation_method'] = 'AI-assisted with human oversight';
        
        // Temporal context
        $context['list_freshness'] = $this->calculate_list_freshness($post);
        $context['last_updated'] = get_the_modified_date('c', $post);
        
        // Scope and coverage
        $restaurants_json = get_post_meta($post->ID, '_mc_restaurants', true);
        if ($restaurants_json) {
            $restaurants = json_decode($restaurants_json, true);
            if (is_array($restaurants)) {
                $context['coverage_scope'] = count($restaurants) . ' carefully selected restaurants';
                $context['selection_criteria'] = 'Quality, authenticity, service excellence, and value';
                
                // Add score range
                $scores = array_filter(array_column($restaurants, 'score'));
                if (!empty($scores)) {
                    $context['score_range'] = [
                        'min' => min($scores),
                        'max' => max($scores),
                        'average' => round(array_sum($scores) / count($scores), 1)
                    ];
                }
            }
        }
        
        return $context;
    }
    
    /**
     * Get geographic context for the list
     * 
     * @param WP_Post $post List post
     * @return array Geographic data
     */
    private function get_geographic_context($post) {
        $context = [];
        
        $location = get_post_meta($post->ID, '_mc_location', true);
        $city_slug = get_post_meta($post->ID, 'city_slug', true);
        
        if ($location) {
            $context['geographic_scope'] = $location;
            $context['local_expertise'] = "Local knowledge of {$location} dining scene";
        }
        
        if ($city_slug) {
            $context['city_identifier'] = $city_slug;
        }
        
        // Add regional cuisine context if applicable
        $topic = get_post_meta($post->ID, '_mc_topic', true);
        if ($topic && $location) {
            $context['regional_specialization'] = "{$topic} in {$location}";
        }
        
        return $context;
    }
    
    /**
     * Get vibes by IDs
     * 
     * @param array $vibe_ids Array of vibe IDs
     * @return array Vibe names
     */
    private function get_vibes_by_ids($vibe_ids) {
        if (!is_array($vibe_ids) || empty($vibe_ids)) {
            return [];
        }
        
        $vibes = [];
        $cache_key = 'zippicks_schema_vibes_' . md5(serialize($vibe_ids));
        $cached_vibes = get_transient($cache_key);
        
        if ($cached_vibes !== false) {
            return is_array($cached_vibes) ? $cached_vibes : [];
        }
        
        foreach ($vibe_ids as $vibe_id) {
            // Ensure vibe_id is numeric to prevent injection
            if (!is_numeric($vibe_id)) {
                continue;
            }
            
            $vibe_term = get_term((int) $vibe_id, 'zippicks_vibe');
            if ($vibe_term && !is_wp_error($vibe_term)) {
                $vibes[] = sanitize_text_field($vibe_term->name);
            }
        }
        
        // Cache for 1 hour
        set_transient($cache_key, $vibes, HOUR_IN_SECONDS);
        
        return $vibes;
    }
    
    /**
     * Find business post by ZPID
     * 
     * @param string $zpid ZipBusiness ID
     * @return WP_Post|null Business post
     */
    private function find_business_by_zpid($zpid) {
        if (empty($zpid)) {
            return null;
        }
        
        // Use WP_Query for better performance and caching
        $query = new WP_Query([
            'post_type' => 'zippicks_business',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'no_found_rows' => true, // Skip pagination queries
            'update_post_term_cache' => false, // Skip term cache updates
            'update_post_meta_cache' => false, // Skip meta cache updates (we only need the post)
            'meta_query' => [
                [
                    'key' => 'zpid',
                    'value' => sanitize_text_field($zpid),
                    'compare' => '='
                ]
            ]
        ]);
        
        return $query->have_posts() ? $query->posts[0] : null;
    }
    
    /**
     * Generate top restaurants answer for FAQ
     * 
     * @param array $restaurants Restaurant list
     * @param string $location Location name
     * @param string $topic Topic/cuisine
     * @return string Answer text
     */
    private function generate_top_restaurants_answer($restaurants, $location, $topic) {
        $top_3 = array_slice($restaurants, 0, 3);
        $names = array_column($top_3, 'name');
        
        if (count($names) >= 3) {
            $answer = "The top 3 {$topic} restaurants in {$location} are {$names[0]}, {$names[1]}, and {$names[2]}. ";
        } else {
            $answer = "The best {$topic} restaurants in {$location} include " . implode(', ', $names) . ". ";
        }
        
        $answer .= "Each restaurant has been carefully evaluated on our comprehensive ZipPicks rating system covering taste, service, speed, and value.";
        
        return $answer;
    }
    
    /**
     * Get ranking context for position
     * 
     * @param int $position List position
     * @return string Context description
     */
    private function get_ranking_context($position) {
        if ($position === 1) {
            return 'Top ranked - exceptional in all categories';
        } elseif ($position <= 3) {
            return 'Elite tier - outstanding quality and service';  
        } elseif ($position <= 6) {
            return 'Highly recommended - excellent choice';
        } else {
            return 'Solid choice - good quality and value';
        }
    }
    
    /**
     * Calculate list freshness
     * 
     * @param WP_Post $post List post
     * @return string Freshness indicator
     */
    private function calculate_list_freshness($post) {
        $modified_time = strtotime($post->post_modified);
        $days_ago = floor((time() - $modified_time) / (24 * 60 * 60));
        
        if ($days_ago <= 7) {
            return 'fresh';
        } elseif ($days_ago <= 30) {
            return 'recent';
        } elseif ($days_ago <= 90) {
            return 'current';
        } else {
            return 'established';
        }
    }
    
    /**
     * Output schema as JSON-LD
     * 
     * @param array $schema Schema data
     */
    private function output_schema($schema) {
        $json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json) {
            echo "\n<script type=\"application/ld+json\">{$json}</script>\n";
        }
    }
    
    /**
     * Generate carousel schema for top restaurants
     * 
     * @param WP_Post $post List post
     * @return array|null Carousel schema
     */
    public function generate_carousel_schema($post) {
        $restaurants_json = get_post_meta($post->ID, '_mc_restaurants', true);
        if (!$restaurants_json) {
            return null;
        }
        
        $restaurants = json_decode($restaurants_json, true);
        if (!is_array($restaurants) || count($restaurants) < 3) {
            return null;
        }
        
        $carousel_items = [];
        
        foreach (array_slice($restaurants, 0, 10) as $index => $restaurant) {
            $position = $index + 1;
            
            $item = [
                '@type' => 'ListItem',
                'position' => $position,
                'item' => [
                    '@type' => ZipPicks_Schema_Types::TYPE_RESTAURANT,
                    'name' => $restaurant['name'],
                    'description' => $restaurant['summary'] ?? $restaurant['description'] ?? '',
                    'url' => $this->get_restaurant_url($restaurant)
                ]
            ];
            
            // Add image if available
            if (isset($restaurant['image'])) {
                $item['item']['image'] = $restaurant['image'];
            }
            
            // Add rating
            if (isset($restaurant['score'])) {
                $item['item']['aggregateRating'] = ZipPicks_Schema_Types::format_aggregate_rating(
                    (float) $restaurant['score']
                );
            }
            
            $carousel_items[] = $item;
        }
        
        return [
            '@context' => ZipPicks_Schema_Types::CONTEXT,
            '@type' => 'ItemList',
            'name' => get_the_title($post) . ' - Top Picks',
            'itemListElement' => $carousel_items
        ];
    }
    
    /**
     * Get restaurant URL from data
     * 
     * @param array $restaurant Restaurant data
     * @return string URL
     */
    private function get_restaurant_url($restaurant) {
        // Try to find existing business post
        if (isset($restaurant['zpid'])) {
            $business_post = $this->find_business_by_zpid($restaurant['zpid']);
            if ($business_post) {
                return get_permalink($business_post);
            }
        }
        
        // Fallback to external URL if available
        return $restaurant['url'] ?? '#';
    }
}