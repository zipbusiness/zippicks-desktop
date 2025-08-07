<?php
/**
 * Schema Generator
 * 
 * Generates Schema.org structured data for ZipPicks content
 *
 * @package ZipPicks_Schema
 * @since 1.0.0
 */

class ZipPicks_Schema_Generator {
    
    private $cache_duration;
    private $debug_mode;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->cache_duration = get_option('zippicks_schema_cache_schema_duration', DAY_IN_SECONDS);
        $this->debug_mode = get_option('zippicks_schema_enable_debug_mode', false);
    }
    
    /**
     * Generate schema for a post
     * 
     * @param int|WP_Post $post Post ID or object
     * @return array|null Schema data or null if not applicable
     */
    public function generate_schema_for_post($post) {
        if (is_numeric($post)) {
            $post = get_post($post);
        }
        
        if (!$post || is_wp_error($post)) {
            return null;
        }
        
        // Check cache first
        $cache_key = 'zippicks_schema_' . $post->ID . '_' . $post->post_modified;
        $cached = get_transient($cache_key);
        
        if ($cached !== false && !$this->debug_mode) {
            return is_array($cached) ? $cached : null;
        }
        
        $schema = null;
        
        // Generate schema based on post type
        switch ($post->post_type) {
            case 'zippicks_business':
                $schema = $this->generate_business_schema($post);
                break;
                
            case 'master_critic_list':
                $schema = $this->generate_master_list_schema($post);
                break;
                
            case 'zippicks_review':
                $schema = $this->generate_review_schema($post);
                break;
                
            default:
                // Check if post has business or list metadata
                if (get_post_meta($post->ID, '_zp_score', true)) {
                    $schema = $this->generate_business_schema($post);
                } elseif (get_post_meta($post->ID, '_mc_restaurants', true)) {
                    $schema = $this->generate_master_list_schema($post);
                }
                break;
        }
        
        // Apply filters to allow customization
        $schema = apply_filters('zippicks_schema_generated', $schema, $post);
        
        // Cache the result
        if ($schema && !$this->debug_mode) {
            set_transient($cache_key, $schema, $this->cache_duration);
        }
        
        return $schema;
    }
    
    /**
     * Generate business/restaurant schema
     * 
     * @param WP_Post $post Business post
     * @return array|null Business schema
     */
    public function generate_business_schema($post) {
        $schema = ZipPicks_Schema_Types::get_restaurant_template();
        
        // Basic information
        $schema[ZipPicks_Schema_Types::PROP_NAME] = get_the_title($post);
        $schema[ZipPicks_Schema_Types::PROP_DESCRIPTION] = $this->get_post_description($post);
        $schema[ZipPicks_Schema_Types::PROP_URL] = get_permalink($post);
        
        // Images
        $images = $this->get_post_images($post);
        if (!empty($images)) {
            $schema[ZipPicks_Schema_Types::PROP_IMAGE] = $images;
        }
        
        // Address
        $address = $this->get_business_address($post);
        if ($address) {
            $schema[ZipPicks_Schema_Types::PROP_ADDRESS] = $address;
        }
        
        // Coordinates
        $coordinates = $this->get_business_coordinates($post);
        if ($coordinates) {
            $schema[ZipPicks_Schema_Types::PROP_GEO] = $coordinates;
        }
        
        // Contact information
        $phone = get_post_meta($post->ID, '_zp_phone', true);
        if ($phone) {
            $schema[ZipPicks_Schema_Types::PROP_TELEPHONE] = $phone;
        }
        
        // Cuisine
        $cuisine = $this->get_business_cuisine($post);
        if ($cuisine) {
            $schema[ZipPicks_Schema_Types::PROP_SERVES_CUISINE] = $cuisine;
        }
        
        // Price range
        $price_range = get_post_meta($post->ID, '_zp_price_tier', true);
        if ($price_range && isset(ZipPicks_Schema_Types::PRICE_RANGES[$price_range])) {
            $schema[ZipPicks_Schema_Types::PROP_PRICE_RANGE] = ZipPicks_Schema_Types::PRICE_RANGES[$price_range];
        }
        
        // Rating
        $rating = $this->get_business_rating($post);
        if ($rating) {
            $schema[ZipPicks_Schema_Types::PROP_AGGREGATE_RATING] = $rating;
        }
        
        // Opening hours
        $hours = $this->get_business_hours($post);
        if ($hours) {
            $schema[ZipPicks_Schema_Types::PROP_OPENING_HOURS] = $hours;
        }
        
        // ZipPicks extensions
        $extensions = $this->get_business_extensions($post);
        $schema = ZipPicks_Schema_Types::add_zippicks_extensions($schema, $extensions);
        
        return $this->clean_schema($schema);
    }
    
    /**
     * Generate Master Critic list schema
     * 
     * @param WP_Post $post Master Critic list post
     * @return array|null List schema
     */
    public function generate_master_list_schema($post) {
        $schema = ZipPicks_Schema_Types::get_item_list_template();
        
        // Basic information
        $schema[ZipPicks_Schema_Types::PROP_NAME] = get_the_title($post);
        $schema[ZipPicks_Schema_Types::PROP_DESCRIPTION] = $this->get_post_description($post);
        $schema[ZipPicks_Schema_Types::PROP_URL] = get_permalink($post);
        
        // List ordering (Top 10 lists are typically ranked)
        $schema[ZipPicks_Schema_Types::PROP_ITEM_LIST_ORDER] = ZipPicks_Schema_Types::ORDER_DESCENDING;
        
        // Get restaurants data
        $restaurants_json = get_post_meta($post->ID, '_mc_restaurants', true);
        if ($restaurants_json) {
            $restaurants = json_decode($restaurants_json, true);
            if (is_array($restaurants)) {
                $list_items = $this->generate_list_items_from_restaurants($restaurants);
                if (!empty($list_items)) {
                    $schema[ZipPicks_Schema_Types::PROP_ITEM_LIST_ELEMENT] = $list_items;
                }
            }
        }
        
        // ZipPicks extensions for lists
        $extensions = [
            'list_type' => 'master_critic',
            'city_context' => get_post_meta($post->ID, '_mc_location', true),
            'generation_id' => get_post_meta($post->ID, '_mc_generation_id', true),
            'ai_provider' => get_post_meta($post->ID, '_mc_ai_provider', true)
        ];
        
        $schema = ZipPicks_Schema_Types::add_zippicks_extensions($schema, $extensions);
        
        return $this->clean_schema($schema);
    }
    
    /**
     * Generate review schema
     * 
     * @param WP_Post $post Review post
     * @return array|null Review schema
     */
    public function generate_review_schema($post) {
        $schema = ZipPicks_Schema_Types::get_review_template();
        
        // Author information
        $author = get_userdata($post->post_author);
        if ($author) {
            $schema[ZipPicks_Schema_Types::PROP_AUTHOR] = [
                '@type' => ZipPicks_Schema_Types::TYPE_PERSON,
                ZipPicks_Schema_Types::PROP_NAME => $author->display_name
            ];
        }
        
        // Review content
        $schema[ZipPicks_Schema_Types::PROP_REVIEW_BODY] = $this->get_post_description($post);
        
        // Dates
        $schema[ZipPicks_Schema_Types::PROP_DATE_PUBLISHED] = get_the_date('c', $post);
        $schema[ZipPicks_Schema_Types::PROP_DATE_MODIFIED] = get_the_modified_date('c', $post);
        
        // Rating
        $rating_value = get_post_meta($post->ID, '_review_rating', true);
        if ($rating_value) {
            $schema[ZipPicks_Schema_Types::PROP_REVIEW_RATING] = [
                '@type' => ZipPicks_Schema_Types::TYPE_RATING,
                ZipPicks_Schema_Types::PROP_RATING_VALUE => (float) $rating_value,
                ZipPicks_Schema_Types::PROP_BEST_RATING => 10,
                ZipPicks_Schema_Types::PROP_WORST_RATING => 0
            ];
        }
        
        // Item being reviewed
        $business_id = get_post_meta($post->ID, '_reviewed_business_id', true);
        if ($business_id) {
            $business_post = get_post($business_id);
            if ($business_post) {
                $schema['itemReviewed'] = [
                    '@type' => ZipPicks_Schema_Types::TYPE_RESTAURANT,
                    ZipPicks_Schema_Types::PROP_NAME => get_the_title($business_post),
                    ZipPicks_Schema_Types::PROP_URL => get_permalink($business_post)
                ];
            }
        }
        
        return $this->clean_schema($schema);
    }
    
    /**
     * Generate organization schema for ZipPicks
     * 
     * @return array Organization schema
     */
    public function generate_organization_schema() {
        $schema = ZipPicks_Schema_Types::get_organization_template();
        
        // Logo
        $logo_url = get_option('zippicks_schema_organization_logo');
        if ($logo_url) {
            $schema['logo'] = [
                '@type' => 'ImageObject',
                'url' => $logo_url
            ];
        }
        
        // Social media profiles
        $social_profiles = get_option('zippicks_schema_social_profiles', []);
        if (!empty($social_profiles)) {
            $schema['sameAs'] = array_values($social_profiles);
        }
        
        return $schema;
    }
    
    /**
     * Get business address from post meta
     * 
     * @param WP_Post $post Business post
     * @return array|null Address schema
     */
    private function get_business_address($post) {
        $address = get_post_meta($post->ID, '_zp_address', true);
        if (!$address) {
            return null;
        }
        
        // Try to parse structured address
        $parsed = $this->parse_address($address);
        if ($parsed) {
            return ZipPicks_Schema_Types::format_address($parsed);
        }
        
        // Fallback to simple street address
        return [
            '@type' => ZipPicks_Schema_Types::TYPE_POSTAL_ADDRESS,
            ZipPicks_Schema_Types::PROP_STREET_ADDRESS => $address,
            ZipPicks_Schema_Types::PROP_ADDRESS_COUNTRY => 'US'
        ];
    }
    
    /**
     * Get business coordinates
     * 
     * @param WP_Post $post Business post
     * @return array|null Coordinates schema
     */
    private function get_business_coordinates($post) {
        $lat = get_post_meta($post->ID, '_zp_latitude', true);
        $lng = get_post_meta($post->ID, '_zp_longitude', true);
        
        if ($lat && $lng && is_numeric($lat) && is_numeric($lng)) {
            return ZipPicks_Schema_Types::format_coordinates($lat, $lng);
        }
        
        return null;
    }
    
    /**
     * Get business cuisine types
     * 
     * @param WP_Post $post Business post
     * @return array|null Cuisine array
     */
    private function get_business_cuisine($post) {
        // Check taxonomy terms
        $cuisine_terms = wp_get_object_terms($post->ID, 'zippicks_cuisine');
        if (!empty($cuisine_terms) && !is_wp_error($cuisine_terms)) {
            return array_map(function($term) {
                return ZipPicks_Schema_Types::normalize_cuisine($term->name);
            }, $cuisine_terms);
        }
        
        // Check meta field
        $cuisine = get_post_meta($post->ID, '_zp_cuisine', true);
        if ($cuisine) {
            if (is_string($cuisine)) {
                return [ZipPicks_Schema_Types::normalize_cuisine($cuisine)];
            } elseif (is_array($cuisine)) {
                return array_map([ZipPicks_Schema_Types::class, 'normalize_cuisine'], $cuisine);
            }
        }
        
        return null;
    }
    
    /**
     * Get business rating
     * 
     * @param WP_Post $post Business post
     * @return array|null Rating schema
     */
    private function get_business_rating($post) {
        $score = get_post_meta($post->ID, '_zp_score', true);
        $review_count = get_post_meta($post->ID, '_zp_review_count', true);
        
        if ($score) {
            return ZipPicks_Schema_Types::format_aggregate_rating(
                (float) $score,
                (int) $review_count ?: 1
            );
        }
        
        return null;
    }
    
    /**
     * Get business hours
     * 
     * @param WP_Post $post Business post
     * @return array|null Opening hours
     */
    private function get_business_hours($post) {
        $hours = get_post_meta($post->ID, '_zp_hours', true);
        if (!$hours) {
            return null;
        }
        
        // If hours is a structured array, format it
        if (is_array($hours)) {
            return $this->format_opening_hours($hours);
        }
        
        // If it's a simple string, return as-is
        if (is_string($hours)) {
            return [$hours];
        }
        
        return null;
    }
    
    /**
     * Get ZipPicks extension data
     * 
     * @param WP_Post $post Business post
     * @return array Extension data
     */
    private function get_business_extensions($post) {
        $extensions = [];
        
        // Master score
        $score = get_post_meta($post->ID, '_zp_score', true);
        if ($score) {
            $extensions['master_score'] = (float) $score;
            $extensions['classification'] = ZipPicks_Schema_Types::get_classification_from_score($score);
        }
        
        // Pillar scores
        $pillar_scores = get_post_meta($post->ID, 'pillar_scores', true);
        if ($pillar_scores) {
            $extensions['pillar_scores'] = $pillar_scores;
        }
        
        // ZipBusiness ID
        $zpid = get_post_meta($post->ID, 'zpid', true);
        if ($zpid) {
            $extensions['zpid'] = $zpid;
        }
        
        // Verification date
        $verified_at = get_post_meta($post->ID, '_zp_verified_at', true);
        if ($verified_at) {
            $extensions['verified_date'] = $verified_at;
        }
        
        // Vibes (if available)
        $vibes = $this->get_business_vibes($post);
        if ($vibes) {
            $extensions['vibes'] = $vibes;
        }
        
        return $extensions;
    }
    
    /**
     * Get business vibes
     * 
     * @param WP_Post $post Business post
     * @return array|null Vibes array
     */
    private function get_business_vibes($post) {
        // Check for vibe taxonomy terms
        $vibe_terms = wp_get_object_terms($post->ID, 'zippicks_vibe');
        if (!empty($vibe_terms) && !is_wp_error($vibe_terms)) {
            return array_map(function($term) {
                return $term->name;
            }, $vibe_terms);
        }
        
        // Check meta field
        $vibes = get_post_meta($post->ID, 'api_vibes', true);
        if ($vibes) {
            $decoded = json_decode($vibes, true);
            if (is_array($decoded)) {
                return array_column($decoded, 'name');
            }
        }
        
        return null;
    }
    
    /**
     * Generate list items from restaurants data
     * 
     * @param array $restaurants Restaurant data array
     * @return array List items schema
     */
    private function generate_list_items_from_restaurants($restaurants) {
        $list_items = [];
        
        foreach ($restaurants as $index => $restaurant) {
            $position = $index + 1;
            
            // Create restaurant schema for the list item
            $restaurant_schema = ZipPicks_Schema_Types::get_base_schema(ZipPicks_Schema_Types::TYPE_RESTAURANT);
            $restaurant_schema[ZipPicks_Schema_Types::PROP_NAME] = $restaurant['name'] ?? '';
            $restaurant_schema[ZipPicks_Schema_Types::PROP_DESCRIPTION] = $restaurant['summary'] ?? $restaurant['description'] ?? '';
            
            // Add address if available
            if (isset($restaurant['address'])) {
                $restaurant_schema[ZipPicks_Schema_Types::PROP_ADDRESS] = [
                    '@type' => ZipPicks_Schema_Types::TYPE_POSTAL_ADDRESS,
                    ZipPicks_Schema_Types::PROP_STREET_ADDRESS => $restaurant['address']
                ];
            }
            
            // Add rating if available
            if (isset($restaurant['score'])) {
                $restaurant_schema[ZipPicks_Schema_Types::PROP_AGGREGATE_RATING] = 
                    ZipPicks_Schema_Types::format_aggregate_rating((float) $restaurant['score']);
            }
            
            // Add ZipPicks extensions
            $extensions = [];
            if (isset($restaurant['score'])) {
                $extensions['master_score'] = (float) $restaurant['score'];
                $extensions['classification'] = ZipPicks_Schema_Types::get_classification_from_score($restaurant['score']);
            }
            
            if (isset($restaurant['zpid'])) {
                $extensions['zpid'] = $restaurant['zpid'];
            }
            
            $restaurant_schema = ZipPicks_Schema_Types::add_zippicks_extensions($restaurant_schema, $extensions);
            
            // Create list item
            $list_item = ZipPicks_Schema_Types::get_list_item_template();
            $list_item[ZipPicks_Schema_Types::PROP_POSITION] = $position;
            $list_item[ZipPicks_Schema_Types::PROP_ITEM] = $restaurant_schema;
            
            $list_items[] = $list_item;
        }
        
        return $list_items;
    }
    
    /**
     * Get post description for schema
     * 
     * @param WP_Post $post Post object
     * @return string Description
     */
    private function get_post_description($post) {
        // Try excerpt first
        if ($post->post_excerpt) {
            return wp_strip_all_tags($post->post_excerpt);
        }
        
        // Generate excerpt from content
        $content = wp_strip_all_tags($post->post_content);
        return wp_trim_words($content, 30);
    }
    
    /**
     * Get post images
     * 
     * @param WP_Post $post Post object
     * @return array Image URLs
     */
    private function get_post_images($post) {
        $images = [];
        
        // Featured image
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        if ($thumbnail_id) {
            $image_url = wp_get_attachment_image_url($thumbnail_id, 'large');
            if ($image_url) {
                $images[] = $image_url;
            }
        }
        
        // Gallery images
        $gallery = get_post_meta($post->ID, '_zp_gallery', true);
        if (is_array($gallery)) {
            foreach ($gallery as $image_id) {
                $image_url = wp_get_attachment_image_url($image_id, 'large');
                if ($image_url) {
                    $images[] = $image_url;
                }
            }
        }
        
        return $images;
    }
    
    /**
     * Parse address string into components
     * 
     * @param string $address Address string
     * @return array|null Address components
     */
    private function parse_address($address) {
        // Simple parsing - can be enhanced with geocoding service
        $parts = explode(',', $address);
        if (count($parts) < 2) {
            return null;
        }
        
        $parsed = [
            'street' => trim($parts[0]),
            'city' => trim($parts[1] ?? ''),
            'state' => '',
            'zip' => '',
            'country' => 'US'
        ];
        
        // Try to extract state and zip from last part
        if (isset($parts[2])) {
            $last_part = trim($parts[2]);
            if (preg_match('/^([A-Z]{2})\s+(\d{5}(-\d{4})?)$/', $last_part, $matches)) {
                $parsed['state'] = $matches[1];
                $parsed['zip'] = $matches[2];
            }
        }
        
        return $parsed;
    }
    
    /**
     * Format opening hours array
     * 
     * @param array $hours Hours array
     * @return array Formatted hours
     */
    private function format_opening_hours($hours) {
        $formatted = [];
        
        $day_mapping = [
            'monday' => 'Mo',
            'tuesday' => 'Tu', 
            'wednesday' => 'We',
            'thursday' => 'Th',
            'friday' => 'Fr',
            'saturday' => 'Sa',
            'sunday' => 'Su'
        ];
        
        foreach ($hours as $day => $times) {
            if (isset($day_mapping[$day]) && !empty($times)) {
                $day_code = $day_mapping[$day];
                if (is_array($times)) {
                    foreach ($times as $time_range) {
                        $formatted[] = $day_code . ' ' . $time_range;
                    }
                } else {
                    $formatted[] = $day_code . ' ' . $times;
                }
            }
        }
        
        return $formatted;
    }
    
    /**
     * Clean schema by removing empty values
     * 
     * @param array $schema Schema array
     * @return array Cleaned schema
     */
    private function clean_schema($schema) {
        if (!is_array($schema)) {
            return $schema;
        }
        
        foreach ($schema as $key => $value) {
            if (is_array($value)) {
                $schema[$key] = $this->clean_schema($value);
                if (empty($schema[$key])) {
                    unset($schema[$key]);
                }
            } elseif ($value === '' || $value === null) {
                unset($schema[$key]);
            }
        }
        
        return $schema;
    }
    
    /**
     * Clear schema cache for a post
     * 
     * @param int $post_id Post ID
     */
    public function clear_cache($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        $cache_key = 'zippicks_schema_' . $post->ID . '_' . $post->post_modified;
        delete_transient($cache_key);
    }
    
    /**
     * Clear all schema cache
     */
    public function clear_all_cache() {
        global $wpdb;
        
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_zippicks_schema_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_zippicks_schema_%'");
    }
}