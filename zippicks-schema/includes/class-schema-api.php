<?php
/**
 * Schema API
 * 
 * REST API endpoints for schema generation and validation
 *
 * @package ZipPicks_Schema
 * @since 1.0.0
 */

class ZipPicks_Schema_API {
    
    private $generator;
    private $validator;
    private $namespace = 'zippicks/v1';
    
    /**
     * Constructor
     * 
     * @param ZipPicks_Schema_Generator $generator Schema generator instance
     * @param ZipPicks_Schema_Validator $validator Schema validator instance
     */
    public function __construct($generator, $validator = null) {
        $this->generator = $generator;
        $this->validator = $validator;
        
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('wp_ajax_zippicks_schema_test', [$this, 'handle_schema_test']);
        add_action('wp_ajax_zippicks_schema_health', [$this, 'handle_schema_health']);
        add_action('wp_ajax_zippicks_schema_preview', [$this, 'handle_schema_preview']);
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Get business schema
        register_rest_route($this->namespace, '/schema/business/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_business_schema'],
            'permission_callback' => [$this, 'check_read_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
        
        // Get list schema
        register_rest_route($this->namespace, '/schema/list/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_list_schema'],
            'permission_callback' => [$this, 'check_read_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);
        
        // Validate schema
        register_rest_route($this->namespace, '/schema/validate', [
            'methods' => 'POST',
            'callback' => [$this, 'validate_schema'],
            'permission_callback' => [$this, 'check_edit_permission'],
            'args' => [
                'schema' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_array($param) || is_string($param);
                    }
                ]
            ]
        ]);
        
        // Preview schema for any post
        register_rest_route($this->namespace, '/schema/preview/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'preview_schema'],
            'permission_callback' => [$this, 'check_edit_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
                'format' => [
                    'default' => 'json',
                    'validate_callback' => function($param) {
                        return in_array($param, ['json', 'pretty', 'debug']);
                    }
                ]
            ]
        ]);
        
        // Get schema health status
        register_rest_route($this->namespace, '/schema/health', [
            'methods' => 'GET',
            'callback' => [$this, 'get_schema_health'],
            'permission_callback' => [$this, 'check_edit_permission']
        ]);
        
        // Bulk generate schemas
        register_rest_route($this->namespace, '/schema/bulk-generate', [
            'methods' => 'POST',
            'callback' => [$this, 'bulk_generate_schemas'],
            'permission_callback' => [$this, 'check_edit_permission'],
            'args' => [
                'post_type' => [
                    'default' => 'zippicks_business',
                    'validate_callback' => function($param) {
                        return post_type_exists($param);
                    }
                ],
                'limit' => [
                    'default' => 50,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0 && $param <= 100;
                    }
                ]
            ]
        ]);
        
        // Clear schema cache
        register_rest_route($this->namespace, '/schema/cache/clear', [
            'methods' => 'POST',
            'callback' => [$this, 'clear_schema_cache'],
            'permission_callback' => [$this, 'check_edit_permission'],
            'args' => [
                'post_id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param) || $param === 'all';
                    }
                ]
            ]
        ]);
    }
    
    /**
     * Get business schema endpoint
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function get_business_schema($request) {
        $post_id = $request->get_param('id');
        $post = get_post($post_id);
        
        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found', ['status' => 404]);
        }
        
        if (!in_array($post->post_type, ['zippicks_business', 'post', 'page'])) {
            return new WP_Error('invalid_post_type', 'Post type not supported for business schema', ['status' => 400]);
        }
        
        $schema = $this->generator->generate_business_schema($post);
        
        if (!$schema) {
            return new WP_Error('schema_generation_failed', 'Failed to generate schema', ['status' => 500]);
        }
        
        // Validate if validator is available
        $validation = null;
        if ($this->validator) {
            $validation = $this->validator->validate($schema);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'schema' => $schema,
            'validation' => $validation,
            'post_info' => [
                'id' => $post->ID,
                'title' => $post->post_title,
                'type' => $post->post_type,
                'url' => get_permalink($post),
                'modified' => $post->post_modified
            ]
        ]);
    }
    
    /**
     * Get list schema endpoint
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function get_list_schema($request) {
        $post_id = $request->get_param('id');
        $post = get_post($post_id);
        
        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found', ['status' => 404]);
        }
        
        if ($post->post_type !== 'master_critic_list') {
            return new WP_Error('invalid_post_type', 'Post must be a master_critic_list', ['status' => 400]);
        }
        
        $schema = $this->generator->generate_master_list_schema($post);
        
        if (!$schema) {
            return new WP_Error('schema_generation_failed', 'Failed to generate list schema', ['status' => 500]);
        }
        
        // Validate if validator is available
        $validation = null;
        if ($this->validator) {
            $validation = $this->validator->validate($schema);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'schema' => $schema,
            'validation' => $validation,
            'list_info' => [
                'id' => $post->ID,
                'title' => $post->post_title,
                'location' => get_post_meta($post->ID, '_mc_location', true),
                'topic' => get_post_meta($post->ID, '_mc_topic', true),
                'url' => get_permalink($post),
                'item_count' => $this->get_list_item_count($post)
            ]
        ]);
    }
    
    /**
     * Validate schema endpoint
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function validate_schema($request) {
        if (!$this->validator) {
            return new WP_Error('validator_not_available', 'Schema validator not available', ['status' => 503]);
        }
        
        $schema_data = $request->get_param('schema');
        
        // Parse JSON string if needed
        if (is_string($schema_data)) {
            $parsed = json_decode($schema_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error('invalid_json', 'Invalid JSON format', ['status' => 400]);
            }
            $schema_data = $parsed;
        }
        
        if (!is_array($schema_data)) {
            return new WP_Error('invalid_schema', 'Schema must be an array or valid JSON', ['status' => 400]);
        }
        
        $validation = $this->validator->validate($schema_data);
        
        // Test with Google if requested
        $google_test = null;
        if ($request->get_param('test_google')) {
            $google_test = $this->validator->test_with_google($schema_data);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'validation' => $validation,
            'google_test' => $google_test,
            'schema_info' => [
                'type' => $schema_data['@type'] ?? 'Unknown',
                'size' => strlen(json_encode($schema_data)),
                'properties' => count($schema_data)
            ]
        ]);
    }
    
    /**
     * Preview schema endpoint
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response object
     */
    public function preview_schema($request) {
        $post_id = $request->get_param('id');
        $format = $request->get_param('format');
        
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', 'Post not found', ['status' => 404]);
        }
        
        $schema = $this->generator->generate_schema_for_post($post);
        
        if (!$schema) {
            return new WP_Error('no_schema', 'No schema available for this post type', ['status' => 404]);
        }
        
        $response_data = [
            'success' => true,
            'post_info' => [
                'id' => $post->ID,
                'title' => $post->post_title,
                'type' => $post->post_type,
                'url' => get_permalink($post)
            ]
        ];
        
        // Format response based on request
        switch ($format) {
            case 'pretty':
                $response_data['schema'] = $schema;
                $response_data['schema_json'] = wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                break;
                
            case 'debug':
                $validation = $this->validator ? $this->validator->validate($schema) : null;
                $response_data['schema'] = $schema;
                $response_data['validation'] = $validation;
                $response_data['debug_info'] = [
                    'generator_version' => ZIPPICKS_SCHEMA_VERSION,
                    'generation_time' => current_time('mysql'),
                    'cache_key' => 'zippicks_schema_' . $post->ID . '_' . $post->post_modified,
                    'schema_size' => strlen(json_encode($schema)),
                    'property_count' => count($schema)
                ];
                break;
                
            default:
                $response_data['schema'] = $schema;
                break;
        }
        
        return new WP_REST_Response($response_data);
    }
    
    /**
     * Get schema health endpoint
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function get_schema_health($request) {
        $health_data = $this->get_system_health();
        
        return new WP_REST_Response([
            'success' => true,
            'health' => $health_data,
            'timestamp' => current_time('mysql'),
            'plugin_version' => ZIPPICKS_SCHEMA_VERSION
        ]);
    }
    
    /**
     * Bulk generate schemas endpoint
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function bulk_generate_schemas($request) {
        $post_type = $request->get_param('post_type');
        $limit = $request->get_param('limit');
        
        $posts = get_posts([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'meta_query' => [
                [
                    'key' => '_schema_generated',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ]);
        
        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => []
        ];
        
        foreach ($posts as $post) {
            $results['processed']++;
            
            try {
                $schema = $this->generator->generate_schema_for_post($post);
                
                if ($schema) {
                    $validation = $this->validator ? $this->validator->validate($schema) : ['valid' => true];
                    
                    if ($validation['valid']) {
                        update_post_meta($post->ID, '_schema_generated', current_time('mysql'));
                        $results['successful']++;
                        
                        $results['details'][] = [
                            'post_id' => $post->ID,
                            'title' => $post->post_title,
                            'status' => 'success',
                            'schema_type' => $schema['@type'] ?? 'Unknown'
                        ];
                    } else {
                        $results['failed']++;
                        $results['details'][] = [
                            'post_id' => $post->ID,
                            'title' => $post->post_title,
                            'status' => 'validation_failed',
                            'errors' => $validation['errors']
                        ];
                    }
                } else {
                    $results['skipped']++;
                    $results['details'][] = [
                        'post_id' => $post->ID,
                        'title' => $post->post_title,
                        'status' => 'no_schema'
                    ];
                }
            } catch (Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'post_id' => $post->ID,
                    'title' => $post->post_title,
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return new WP_REST_Response([
            'success' => true,
            'results' => $results,
            'post_type' => $post_type,
            'limit' => $limit
        ]);
    }
    
    /**
     * Clear schema cache endpoint
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response object
     */
    public function clear_schema_cache($request) {
        $post_id = $request->get_param('post_id');
        
        if ($post_id === 'all') {
            $this->generator->clear_all_cache();
            $message = 'All schema cache cleared';
        } elseif (is_numeric($post_id)) {
            $this->generator->clear_cache($post_id);
            $message = "Schema cache cleared for post {$post_id}";
        } else {
            return new WP_Error('invalid_post_id', 'Invalid post ID', ['status' => 400]);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => $message,
            'timestamp' => current_time('mysql')
        ]);
    }
    
    /**
     * Handle AJAX schema test
     */
    public function handle_schema_test() {
        // Enhanced security checks
        if (!check_ajax_referer('zippicks_schema_admin', 'nonce', false)) {
            wp_send_json_error('Security check failed', 403);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        // Rate limiting - max 10 requests per minute per user
        $user_id = get_current_user_id();
        $rate_limit_key = 'zippicks_schema_test_' . $user_id;
        $requests = get_transient($rate_limit_key) ?: 0;
        
        if ($requests >= 10) {
            wp_send_json_error('Rate limit exceeded. Please wait a minute.', 429);
        }
        
        set_transient($rate_limit_key, $requests + 1, MINUTE_IN_SECONDS);
        
        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id || $post_id <= 0) {
            wp_send_json_error('Invalid post ID');
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
        }
        
        $schema = $this->generator->generate_schema_for_post($post);
        if (!$schema) {
            wp_send_json_error('No schema generated for this post');
        }
        
        $validation = $this->validator ? $this->validator->validate($schema) : null;
        
        wp_send_json_success([
            'schema' => $schema,
            'schema_json' => wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'validation' => $validation,
            'post_title' => $post->post_title
        ]);
    }
    
    /**
     * Handle AJAX schema health check
     */
    public function handle_schema_health() {
        if (!check_ajax_referer('zippicks_schema_admin', 'nonce', false)) {
            wp_send_json_error('Security check failed', 403);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $health = $this->get_system_health();
        
        wp_send_json_success($health);
    }
    
    /**
     * Handle AJAX schema preview
     */
    public function handle_schema_preview() {
        if (!check_ajax_referer('zippicks_schema_admin', 'nonce', false)) {
            wp_send_json_error('Security check failed', 403);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions', 403);
        }
        
        $url = sanitize_url($_POST['url'] ?? '');
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error('Invalid URL');
        }
        
        // Generate Google Rich Results Test URL
        $test_url = 'https://search.google.com/test/rich-results?url=' . urlencode($url);
        
        wp_send_json_success([
            'test_url' => $test_url,
            'message' => 'Click the link to test with Google Rich Results'
        ]);
    }
    
    /**
     * Check read permission
     * 
     * @return bool True if user can read
     */
    public function check_read_permission() {
        return true; // Public endpoint for schema data
    }
    
    /**
     * Check edit permission
     * 
     * @return bool True if user can edit
     */
    public function check_edit_permission() {
        return current_user_can('edit_posts');
    }
    
    /**
     * Get system health information
     * 
     * @return array Health data
     */
    private function get_system_health() {
        global $wpdb;
        
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'stats' => []
        ];
        
        // Check if generator is working
        if (!$this->generator) {
            $health['status'] = 'error';
            $health['issues'][] = 'Schema generator not available';
        }
        
        // Check if validator is working  
        if (!$this->validator) {
            $health['issues'][] = 'Schema validator not available';
            if ($health['status'] !== 'error') {
                $health['status'] = 'warning';
            }
        }
        
        // Check post type support
        $supported_types = ['zippicks_business', 'master_critic_list'];
        foreach ($supported_types as $type) {
            if (!post_type_exists($type)) {
                $health['issues'][] = "Post type '{$type}' not registered";
                $health['status'] = 'warning';
            }
        }
        
        // Get statistics (with proper null handling for PHP 8.3)
        $business_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'zippicks_business' AND post_status = 'publish'");
        $list_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'master_critic_list' AND post_status = 'publish'");
        
        $health['stats'] = [
            'total_businesses' => is_numeric($business_count) ? (int) $business_count : 0,
            'total_lists' => is_numeric($list_count) ? (int) $list_count : 0,
            'cached_schemas' => $this->count_cached_schemas(),
            'plugin_version' => ZIPPICKS_SCHEMA_VERSION
        ];
        
        return $health;
    }
    
    /**
     * Get list item count
     * 
     * @param WP_Post $post List post
     * @return int Item count
     */
    private function get_list_item_count($post) {
        $restaurants_json = get_post_meta($post->ID, '_mc_restaurants', true);
        if ($restaurants_json) {
            $restaurants = json_decode($restaurants_json, true);
            return is_array($restaurants) ? count($restaurants) : 0;
        }
        return 0;
    }
    
    /**
     * Count cached schemas
     * 
     * @return int Cached schema count
     */
    private function count_cached_schemas() {
        global $wpdb;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_zippicks_schema_%'");
        return is_numeric($count) ? (int) $count : 0;
    }
}