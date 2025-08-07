<?php
/**
 * PostgreSQL API Sync for Master Critic
 * Connects to existing ZipBusiness API for restaurant data
 *
 * @package ZipPicks_Master_Critic
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ZipPicks_Master_Critic_API_Sync
 */
class ZipPicks_Master_Critic_API_Sync {
    
    /**
     * API client instance
     *
     * @var ZipPicks_Master_Critic_API_Client
     */
    private $api_client;
    
    /**
     * Logger instance
     *
     * @var object|null
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Get API client instance
        $this->api_client = ZipPicks_Master_Critic_API_Client::get_instance();
        
        // Use Foundation logger if available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $this->logger = zippicks()->get('logger');
        }
    }
    
    /**
     * Sync restaurants from PostgreSQL to WordPress
     *
     * @param string $zip_code ZIP code to sync
     * @param int $limit Number of restaurants to sync
     * @return array|WP_Error Sync results or error
     */
    public function sync_restaurants($zip_code, $limit = 50) {
        // Log sync start
        if ($this->logger) {
            $this->logger->info('Starting restaurant sync from PostgreSQL', [
                'zip_code' => $zip_code,
                'limit' => $limit
            ]);
        }
        
        // Get restaurants from API
        $response = $this->api_client->get_restaurants_by_zip($zip_code, $limit);
        
        if (is_wp_error($response)) {
            if ($this->logger) {
                $this->logger->error('Failed to fetch restaurants from API', [
                    'error' => $response->get_error_message(),
                    'zip_code' => $zip_code
                ]);
            }
            return $response;
        }
        
        // Check response structure
        $restaurants = isset($response['restaurants']) ? $response['restaurants'] : $response;
        
        if (empty($restaurants)) {
            return new WP_Error('no_restaurants', 'No restaurants found for ZIP code ' . $zip_code);
        }
        
        // Sync each restaurant
        $results = [
            'synced' => 0,
            'failed' => 0,
            'errors' => []
        ];
        
        foreach ($restaurants as $restaurant) {
            $post_id = $this->create_or_update_business($restaurant);
            
            if (is_wp_error($post_id)) {
                $results['failed']++;
                $results['errors'][] = [
                    'restaurant' => $restaurant['name'] ?? 'Unknown',
                    'error' => $post_id->get_error_message()
                ];
            } else {
                $results['synced']++;
            }
        }
        
        // Log results
        if ($this->logger) {
            $this->logger->info('Restaurant sync completed', $results);
        }
        
        return $results;
    }
    
    /**
     * Create or update business post from restaurant data
     *
     * @param array $restaurant_data Restaurant data from API
     * @return int|WP_Error Post ID or error
     */
    public function create_or_update_business($restaurant_data) {
        // Validate required fields
        if (empty($restaurant_data['zpid'])) {
            return new WP_Error('missing_zpid', 'Restaurant data missing ZPID');
        }
        
        // Check if business exists by ZPID
        $existing = get_posts([
            'post_type' => 'zippicks_business',
            'meta_key' => 'zpid',
            'meta_value' => $restaurant_data['zpid'],
            'posts_per_page' => 1,
            'post_status' => 'any'
        ]);
        
        // Prepare post data
        $post_data = [
            'post_type' => 'zippicks_business',
            'post_title' => $restaurant_data['name'] ?? 'Restaurant',
            'post_status' => 'publish',
            'post_content' => $restaurant_data['description'] ?? '',
            'meta_input' => [
                'zpid' => $restaurant_data['zpid'],
                'place_id' => $restaurant_data['place_id'] ?? '',
                'address' => $restaurant_data['address'] ?? '',
                'city' => $restaurant_data['city'] ?? '',
                'state' => $restaurant_data['state'] ?? '',
                'zip_code' => $restaurant_data['zip_code'] ?? '',
                'phone' => $restaurant_data['phone'] ?? '',
                'website' => $restaurant_data['website'] ?? '',
                'cuisine_type' => $restaurant_data['cuisine_type'] ?? '',
                'price_level' => $restaurant_data['price_level'] ?? '',
                'rating' => $restaurant_data['rating'] ?? '',
                'review_count' => $restaurant_data['review_count'] ?? 0,
                'taste_graph_score' => $restaurant_data['taste_graph_score'] ?? 0,
                'vibe_scores' => json_encode($restaurant_data['vibe_attributes'] ?? []),
                'hours' => json_encode($restaurant_data['hours'] ?? []),
                'michelin_stars' => $restaurant_data['michelin_stars'] ?? 0,
                'michelin_bib' => $restaurant_data['michelin_bib_gourmand'] ?? false,
                'follower_count' => $restaurant_data['follower_count'] ?? 0,
                'master_critic_summary' => $restaurant_data['master_critic_summary'] ?? '',
                'last_synced' => current_time('mysql')
            ]
        ];
        
        // Update or create
        if (!empty($existing)) {
            $post_data['ID'] = $existing[0]->ID;
            $post_id = wp_update_post($post_data);
            
            if ($this->logger) {
                $this->logger->debug('Updated business post', [
                    'post_id' => $post_id,
                    'zpid' => $restaurant_data['zpid']
                ]);
            }
        } else {
            $post_id = wp_insert_post($post_data);
            
            if ($this->logger) {
                $this->logger->debug('Created business post', [
                    'post_id' => $post_id,
                    'zpid' => $restaurant_data['zpid']
                ]);
            }
        }
        
        return $post_id;
    }
    
    /**
     * Generate Top 10 list from PostgreSQL data
     *
     * @param string $zip_code ZIP code
     * @param string $category Category
     * @param string $list_name List name
     * @return int|WP_Error Set ID or error
     */
    public function generate_top_10_list($zip_code, $category, $list_name = null) {
        // Default list name
        if (empty($list_name)) {
            $list_name = "Best {$category} in {$zip_code}";
        }
        
        // Log generation start
        if ($this->logger) {
            $this->logger->info('Generating Top 10 list from PostgreSQL', [
                'zip_code' => $zip_code,
                'category' => $category,
                'list_name' => $list_name
            ]);
        }
        
        // Get master list from API
        $response = $this->api_client->get_master_lists($zip_code, $category, 10);
        
        if (is_wp_error($response)) {
            if ($this->logger) {
                $this->logger->error('Failed to fetch master list from API', [
                    'error' => $response->get_error_message()
                ]);
            }
            return $response;
        }
        
        // Check response structure
        $restaurants = isset($response['restaurants']) ? $response['restaurants'] : $response;
        
        if (empty($restaurants)) {
            return new WP_Error('no_restaurants', 'No restaurants found for this category');
        }
        
        // Store in Master Critic tables
        global $wpdb;
        $sets_table = $wpdb->prefix . 'zippicks_master_sets';
        
        // Create the set
        $wpdb->insert($sets_table, [
            'set_name' => $list_name,
            'set_slug' => sanitize_title($list_name),
            'zip_code' => $zip_code,
            'category' => $category,
            'total_items' => count($restaurants),
            'status' => 'published',
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);
        
        $set_id = $wpdb->insert_id;
        
        if (!$set_id) {
            return new WP_Error('insert_failed', 'Failed to create master set');
        }
        
        // Add items to set
        $items_table = $wpdb->prefix . 'zippicks_master_items';
        
        foreach ($restaurants as $position => $restaurant) {
            // First sync the restaurant to WordPress
            $post_id = $this->create_or_update_business($restaurant);
            
            // Calculate tier based on score
            $tier = $this->calculate_tier($restaurant['taste_graph_score'] ?? 0);
            
            // Insert item
            $wpdb->insert($items_table, [
                'set_id' => $set_id,
                'business_name' => $restaurant['name'] ?? '',
                'business_slug' => sanitize_title($restaurant['name'] ?? ''),
                'score' => $restaurant['taste_graph_score'] ?? 0,
                'tier' => $tier,
                'summary' => $restaurant['master_critic_summary'] ?? '',
                'position' => $position + 1,
                'verified' => 1,
                'business_id' => $restaurant['zpid'] ?? '',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]);
        }
        
        // Log success
        if ($this->logger) {
            $this->logger->info('Top 10 list generated successfully', [
                'set_id' => $set_id,
                'total_items' => count($restaurants)
            ]);
        }
        
        return $set_id;
    }
    
    /**
     * Calculate tier based on score
     *
     * @param float $score Taste graph score
     * @return string Tier name
     */
    private function calculate_tier($score) {
        if ($score >= 9.0) {
            return 'essential';
        } elseif ($score >= 8.0) {
            return 'notable';
        } elseif ($score >= 7.0) {
            return 'worthy';
        } else {
            return 'honorable';
        }
    }
    
    /**
     * Get sync statistics
     *
     * @return array Statistics
     */
    public function get_sync_stats() {
        global $wpdb;
        
        // Count synced businesses
        $synced_count = $wpdb->get_var(
            "SELECT COUNT(DISTINCT meta_value) 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = 'zpid' 
             AND meta_value != ''"
        );
        
        // Count master sets
        $sets_count = $wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}zippicks_master_sets"
        );
        
        // Count master items
        $items_count = $wpdb->get_var(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}zippicks_master_items"
        );
        
        // Get last sync time
        $last_sync = $wpdb->get_var(
            "SELECT MAX(meta_value) 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = 'last_synced'"
        );
        
        return [
            'total_postgresql' => 31232, // Known total
            'synced_businesses' => intval($synced_count),
            'master_sets' => intval($sets_count),
            'master_items' => intval($items_count),
            'last_sync' => $last_sync,
            'sync_percentage' => round(($synced_count / 31232) * 100, 2)
        ];
    }
    
    /**
     * Test API connection
     *
     * @return array Connection status
     */
    public function test_connection() {
        $connected = $this->api_client->test_connection();
        
        return [
            'connected' => $connected,
            'api_url' => $this->api_client->get_status()['api_url'],
            'has_api_key' => $this->api_client->get_status()['has_api_key']
        ];
    }
}