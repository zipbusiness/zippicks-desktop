<?php
/**
 * ZipBusiness API Client for Master Critic
 * Based on Social plugin's working implementation
 *
 * @package ZipPicks_Master_Critic
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ZipPicks_Master_Critic_API_Client
 * 
 * Handles all communication with ZipBusiness PostgreSQL API
 * for restaurant data, master lists, and attribution tracking
 */
class ZipPicks_Master_Critic_API_Client {
    
    /**
     * API base URL
     *
     * @var string
     */
    private $api_url;
    
    /**
     * Request timeout in seconds
     *
     * @var int
     */
    private $timeout = 30;
    
    /**
     * Logger instance
     *
     * @var object|null
     */
    private $logger;
    
    /**
     * Singleton instance
     *
     * @var ZipPicks_Master_Critic_API_Client
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return ZipPicks_Master_Critic_API_Client
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Use same configuration as Social plugin
        if (defined('ZIPPICKS_API_URL')) {
            $this->api_url = rtrim(ZIPPICKS_API_URL, '/');
        } else {
            $this->api_url = get_option('zippicks_api_url', 'https://zipbusiness-api.onrender.com');
        }
        
        // Use Foundation logger if available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $this->logger = zippicks()->get('logger');
        }
        
        // Log initialization
        if ($this->logger) {
            $this->logger->info('Master Critic API Client initialized', [
                'api_url' => $this->api_url
            ]);
        }
    }
    
    /**
     * Make API request (following Social's pattern exactly)
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param array $headers Additional headers
     * @return array|WP_Error
     */
    private function request($method, $endpoint, $data = [], $headers = []) {
        // Build URL
        $url = trailingslashit($this->api_url) . ltrim($endpoint, '/');
        
        // Build headers
        $request_headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Client' => 'zippicks-master-critic/' . ZIPPICKS_MASTER_CRITIC_VERSION,
            'X-WordPress-Site' => home_url()
        ];
        
        // Add API key if defined
        if (defined('ZIPPICKS_API_KEY')) {
            $request_headers['X-API-Key'] = ZIPPICKS_API_KEY;
        }
        
        // Build request args
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => array_merge($request_headers, $headers)
        ];
        
        // Add body for POST/PUT/PATCH requests
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($data)) {
            $args['body'] = json_encode($data);
        }
        
        // Add query params for GET requests
        if ($method === 'GET' && !empty($data)) {
            $url = add_query_arg($data, $url);
        }
        
        // Log request if logger available
        if ($this->logger) {
            $this->logger->debug('Master Critic API request', [
                'method' => $method,
                'endpoint' => $endpoint,
                'has_data' => !empty($data)
            ]);
        }
        
        // Make the request
        $response = wp_remote_request($url, $args);
        
        // Handle errors
        if (is_wp_error($response)) {
            if ($this->logger) {
                $this->logger->error('Master Critic API request failed', [
                    'error' => $response->get_error_message(),
                    'endpoint' => $endpoint
                ]);
            }
            return $response;
        }
        
        // Parse response
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        
        // Check status
        if ($status_code >= 200 && $status_code < 300) {
            return $decoded ?: [];
        }
        
        // Return error
        $error_message = isset($decoded['detail']) ? $decoded['detail'] : 'API request failed';
        return new WP_Error('api_error', $error_message, ['status' => $status_code]);
    }
    
    // ===========================
    // Restaurant Data Endpoints
    // ===========================
    
    /**
     * Get restaurants from PostgreSQL
     *
     * @param array $params Query parameters
     * @return array|WP_Error
     */
    public function get_restaurants($params = []) {
        $defaults = [
            'limit' => 50,
            'offset' => 0,
            'include_vibes' => true
        ];
        
        $params = array_merge($defaults, $params);
        
        return $this->request('GET', '/wp/restaurants', $params);
    }
    
    /**
     * Search restaurants by name
     *
     * @param string $name Restaurant name
     * @param string $zip_code ZIP code (optional)
     * @return array|WP_Error
     */
    public function search_restaurants($name, $zip_code = null) {
        $params = ['name' => $name];
        
        if ($zip_code) {
            $params['zip_code'] = $zip_code;
        }
        
        return $this->request('GET', '/wp/restaurants/search', $params);
    }
    
    /**
     * Get restaurant by ZPID
     *
     * @param string $zpid Restaurant ZPID
     * @return array|WP_Error
     */
    public function get_restaurant($zpid) {
        return $this->request('GET', "/wp/restaurants/{$zpid}");
    }
    
    /**
     * Get restaurants by ZIP code
     *
     * @param string $zip_code ZIP code
     * @param int $limit Limit
     * @return array|WP_Error
     */
    public function get_restaurants_by_zip($zip_code, $limit = 100) {
        return $this->request('GET', '/wp/restaurants', [
            'zip_code' => $zip_code,
            'limit' => $limit,
            'include_vibes' => true
        ]);
    }
    
    // ===========================
    // Master List Endpoints
    // ===========================
    
    /**
     * Get master lists for a ZIP code
     *
     * @param string $zip_code ZIP code
     * @param string $category Category (optional)
     * @param int $limit Number of results
     * @return array|WP_Error
     */
    public function get_master_lists($zip_code, $category = null, $limit = 10) {
        $params = [
            'zip_code' => $zip_code,
            'limit' => $limit
        ];
        
        if ($category) {
            $params['category'] = $category;
        }
        
        return $this->request('GET', '/wp/master-lists', $params);
    }
    
    /**
     * Create or update a master list
     *
     * @param array $list_data List data
     * @return array|WP_Error
     */
    public function save_master_list($list_data) {
        return $this->request('POST', '/wp/master-lists', $list_data);
    }
    
    // ===========================
    // Attribution Tracking
    // ===========================
    
    /**
     * Track click for attribution
     *
     * @param array $data Click data
     * @return array|WP_Error
     */
    public function track_click($data) {
        // Add context
        $data['user_id'] = get_current_user_id() ?: null;
        $data['session_id'] = session_id() ?: null;
        $data['timestamp'] = current_time('mysql');
        $data['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;
        $data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        return $this->request('POST', '/wp/analytics/click', $data);
    }
    
    /**
     * Get business metrics
     *
     * @param string $zpid Business ZPID
     * @param array $params Additional parameters
     * @return array|WP_Error
     */
    public function get_business_metrics($zpid, $params = []) {
        return $this->request('GET', "/wp/business/{$zpid}/metrics", $params);
    }
    
    // ===========================
    // Vibe Data Endpoints
    // ===========================
    
    /**
     * Get all vibes
     *
     * @return array|WP_Error
     */
    public function get_vibes() {
        return $this->request('GET', '/wp/vibes');
    }
    
    /**
     * Get restaurants by vibe
     *
     * @param string $vibe_slug Vibe slug
     * @param array $params Additional parameters
     * @return array|WP_Error
     */
    public function get_restaurants_by_vibe($vibe_slug, $params = []) {
        $defaults = [
            'limit' => 50,
            'offset' => 0
        ];
        
        $params = array_merge($defaults, $params);
        
        return $this->request('GET', "/wp/vibes/{$vibe_slug}/restaurants", $params);
    }
    
    // ===========================
    // Sync Operations
    // ===========================
    
    /**
     * Sync restaurant data to WordPress
     *
     * @param string $zpid Restaurant ZPID
     * @return int|WP_Error Post ID or error
     */
    public function sync_restaurant_to_wordpress($zpid) {
        // Get restaurant data from API
        $restaurant = $this->get_restaurant($zpid);
        
        if (is_wp_error($restaurant)) {
            return $restaurant;
        }
        
        // Check if business post exists
        $existing = get_posts([
            'post_type' => 'zippicks_business',
            'meta_key' => 'zpid',
            'meta_value' => $zpid,
            'posts_per_page' => 1,
            'post_status' => 'any'
        ]);
        
        // Prepare post data
        $post_data = [
            'post_type' => 'zippicks_business',
            'post_title' => $restaurant['name'],
            'post_status' => 'publish',
            'post_content' => $restaurant['description'] ?? '',
            'meta_input' => [
                'zpid' => $zpid,
                'place_id' => $restaurant['place_id'] ?? '',
                'address' => $restaurant['address'] ?? '',
                'city' => $restaurant['city'] ?? '',
                'state' => $restaurant['state'] ?? '',
                'zip_code' => $restaurant['zip_code'] ?? '',
                'phone' => $restaurant['phone'] ?? '',
                'website' => $restaurant['website'] ?? '',
                'cuisine_type' => $restaurant['cuisine_type'] ?? '',
                'price_level' => $restaurant['price_level'] ?? '',
                'rating' => $restaurant['rating'] ?? '',
                'review_count' => $restaurant['review_count'] ?? 0,
                'taste_graph_score' => $restaurant['taste_graph_score'] ?? 0,
                'vibe_scores' => json_encode($restaurant['vibe_attributes'] ?? []),
                'hours' => json_encode($restaurant['hours'] ?? []),
                'michelin_stars' => $restaurant['michelin_stars'] ?? 0,
                'michelin_bib' => $restaurant['michelin_bib_gourmand'] ?? false,
                'follower_count' => $restaurant['follower_count'] ?? 0,
                'master_critic_summary' => $restaurant['master_critic_summary'] ?? ''
            ]
        ];
        
        // Update or create
        if (!empty($existing)) {
            $post_data['ID'] = $existing[0]->ID;
            $post_id = wp_update_post($post_data);
            
            if ($this->logger) {
                $this->logger->info('Updated business from PostgreSQL', [
                    'post_id' => $post_id,
                    'zpid' => $zpid
                ]);
            }
        } else {
            $post_id = wp_insert_post($post_data);
            
            if ($this->logger) {
                $this->logger->info('Created business from PostgreSQL', [
                    'post_id' => $post_id,
                    'zpid' => $zpid
                ]);
            }
        }
        
        return $post_id;
    }
    
    /**
     * Bulk sync restaurants
     *
     * @param array $zpids Array of ZPIDs
     * @return array Results array
     */
    public function bulk_sync_restaurants($zpids) {
        $results = [
            'success' => [],
            'failed' => []
        ];
        
        foreach ($zpids as $zpid) {
            $result = $this->sync_restaurant_to_wordpress($zpid);
            
            if (is_wp_error($result)) {
                $results['failed'][] = [
                    'zpid' => $zpid,
                    'error' => $result->get_error_message()
                ];
            } else {
                $results['success'][] = [
                    'zpid' => $zpid,
                    'post_id' => $result
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Test API connection
     *
     * @return bool Whether connection is successful
     */
    public function test_connection() {
        $response = $this->request('GET', '/health');
        
        if (is_wp_error($response)) {
            if ($this->logger) {
                $this->logger->error('API connection test failed', [
                    'error' => $response->get_error_message()
                ]);
            }
            return false;
        }
        
        if ($this->logger) {
            $this->logger->info('API connection test successful');
        }
        
        return true;
    }
    
    /**
     * Get API status
     *
     * @return array Status information
     */
    public function get_status() {
        return [
            'api_url' => $this->api_url,
            'has_api_key' => defined('ZIPPICKS_API_KEY'),
            'connection_test' => $this->test_connection()
        ];
    }
}