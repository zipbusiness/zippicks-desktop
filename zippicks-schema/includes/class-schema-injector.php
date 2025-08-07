<?php
/**
 * Schema Injector
 * 
 * Injects Schema.org structured data into page headers
 *
 * @package ZipPicks_Schema
 * @since 1.0.0
 */

class ZipPicks_Schema_Injector {
    
    private $generator;
    private $validator;
    private $injected_schemas = [];
    
    /**
     * Constructor
     * 
     * @param ZipPicks_Schema_Generator $generator Schema generator instance
     * @param ZipPicks_Schema_Validator $validator Schema validator instance
     */
    public function __construct($generator, $validator = null) {
        $this->generator = $generator;
        $this->validator = $validator;
        
        // Hook into WordPress
        add_action('wp_head', [$this, 'inject_schema'], 1);
        add_action('wp_footer', [$this, 'inject_deferred_schema'], 1);
        
        // Hook into post save to clear cache
        add_action('save_post', [$this, 'clear_post_cache']);
        add_action('delete_post', [$this, 'clear_post_cache']);
        add_action('trash_post', [$this, 'clear_post_cache']);
        add_action('before_delete_post', [$this, 'clear_post_cache']);
        add_action('transition_post_status', [$this, 'clear_post_cache_on_status_change'], 10, 3);
        
        // Add filter for manual schema injection
        add_filter('zippicks_inject_schema', [$this, 'inject_custom_schema'], 10, 2);
    }
    
    /**
     * Inject schema into page head
     */
    public function inject_schema() {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        
        $schemas = $this->get_page_schemas();
        
        if (empty($schemas)) {
            return;
        }
        
        // Always inject organization schema on all pages
        $org_schema = $this->generator->generate_organization_schema();
        if ($org_schema) {
            $schemas[] = $org_schema;
        }
        
        // Validate and output schemas
        foreach ($schemas as $schema) {
            if ($this->should_inject_schema($schema)) {
                $this->output_schema_json_ld($schema);
            }
        }
    }
    
    /**
     * Inject deferred schema in footer (for complex schemas that might need more data)
     */
    public function inject_deferred_schema() {
        // Currently not used, but available for future complex schemas
        // that might need to wait for other plugins to load data
    }
    
    /**
     * Get schemas for current page
     * 
     * @return array Array of schema objects
     */
    private function get_page_schemas() {
        global $wp_query;
        $schemas = [];
        
        if (is_singular()) {
            // Single post/page
            $post = get_queried_object();
            $schema = $this->generator->generate_schema_for_post($post);
            
            if ($schema) {
                $schemas[] = $schema;
            }
            
            // Add breadcrumb schema for single pages
            $breadcrumb_schema = $this->generate_breadcrumb_schema();
            if ($breadcrumb_schema) {
                $schemas[] = $breadcrumb_schema;
            }
            
        } elseif (is_home() || is_front_page()) {
            // Homepage - just organization schema (added in inject_schema)
            
        } elseif (is_archive()) {
            // Archive pages
            $archive_schema = $this->generate_archive_schema();
            if ($archive_schema) {
                $schemas[] = $archive_schema;
            }
            
        } elseif (is_search()) {
            // Search results page
            $search_schema = $this->generate_search_results_schema();
            if ($search_schema) {
                $schemas[] = $search_schema;
            }
        }
        
        // Allow other plugins to add schemas
        $schemas = apply_filters('zippicks_page_schemas', $schemas, $wp_query);
        
        return $schemas;
    }
    
    /**
     * Generate breadcrumb schema
     * 
     * @return array|null Breadcrumb schema
     */
    private function generate_breadcrumb_schema() {
        if (!function_exists('yoast_breadcrumb') && !function_exists('rank_math_the_breadcrumbs')) {
            // Generate simple breadcrumb for single posts
            if (is_singular()) {
                $post = get_queried_object();
                $breadcrumbs = [];
                
                // Home
                $breadcrumbs[] = [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => get_bloginfo('name'),
                    'item' => home_url()
                ];
                
                // Post type archive
                $post_type_obj = get_post_type_object($post->post_type);
                if ($post_type_obj && $post_type_obj->has_archive) {
                    $breadcrumbs[] = [
                        '@type' => 'ListItem', 
                        'position' => 2,
                        'name' => $post_type_obj->labels->name,
                        'item' => get_post_type_archive_link($post->post_type)
                    ];
                }
                
                // Current post
                $position = count($breadcrumbs) + 1;
                $breadcrumbs[] = [
                    '@type' => 'ListItem',
                    'position' => $position,
                    'name' => get_the_title($post),
                    'item' => get_permalink($post)
                ];
                
                return [
                    '@context' => ZipPicks_Schema_Types::CONTEXT,
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => $breadcrumbs
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Generate archive schema
     * 
     * @return array|null Archive schema
     */
    private function generate_archive_schema() {
        if (is_post_type_archive()) {
            $post_type = get_query_var('post_type');
            $post_type_obj = get_post_type_object($post_type);
            
            if ($post_type_obj) {
                $schema = [
                    '@context' => ZipPicks_Schema_Types::CONTEXT,
                    '@type' => 'CollectionPage',
                    'name' => $post_type_obj->labels->name,
                    'description' => $post_type_obj->description ?: sprintf('Archive of %s', $post_type_obj->labels->name),
                    'url' => get_post_type_archive_link($post_type)
                ];
                
                // Add mainEntity for business archives
                if ($post_type === 'zippicks_business') {
                    $schema['mainEntity'] = [
                        '@type' => 'ItemList',
                        'name' => 'Local Businesses',
                        'description' => 'Directory of local restaurants and businesses'
                    ];
                }
                
                return $schema;
            }
        }
        
        return null;
    }
    
    /**
     * Generate search results schema
     * 
     * @return array|null Search results schema
     */
    private function generate_search_results_schema() {
        $search_query = get_search_query();
        if (empty($search_query)) {
            return null;
        }
        
        global $wp_query;
        
        return [
            '@context' => ZipPicks_Schema_Types::CONTEXT,
            '@type' => 'SearchResultsPage',
            'name' => sprintf('Search Results for "%s"', $search_query),
            'url' => get_search_link($search_query),
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => $wp_query->found_posts,
                'name' => sprintf('Search results for "%s"', $search_query)
            ]
        ];
    }
    
    /**
     * Check if schema should be injected
     * 
     * @param array $schema Schema data
     * @return bool True if should inject
     */
    private function should_inject_schema($schema) {
        if (empty($schema) || !is_array($schema)) {
            return false;
        }
        
        // Check if validation is enabled and schema is valid
        if ($this->validator && get_option('zippicks_schema_validate_before_injection', true)) {
            $validation = $this->validator->validate($schema);
            if (!$validation['valid']) {
                // Log validation errors in debug mode
                if (get_option('zippicks_schema_enable_debug_mode', false)) {
                    error_log('ZipPicks Schema: Validation failed for schema injection: ' . 
                             json_encode($validation['errors']));
                }
                return false;
            }
        }
        
        // Check if schema type is enabled
        if (isset($schema['@type'])) {
            $type = $schema['@type'];
            switch ($type) {
                case ZipPicks_Schema_Types::TYPE_RESTAURANT:
                case ZipPicks_Schema_Types::TYPE_LOCAL_BUSINESS:
                    return get_option('zippicks_schema_enable_business_schema', true);
                    
                case ZipPicks_Schema_Types::TYPE_ITEM_LIST:
                    return get_option('zippicks_schema_enable_list_schema', true);
                    
                case ZipPicks_Schema_Types::TYPE_REVIEW:
                    return get_option('zippicks_schema_enable_review_schema', true);
                    
                case ZipPicks_Schema_Types::TYPE_ORGANIZATION:
                    return get_option('zippicks_schema_enable_organization_schema', true);
                    
                default:
                    return true; // Allow unknown types by default
            }
        }
        
        return true;
    }
    
    /**
     * Output schema as JSON-LD
     * 
     * @param array $schema Schema data
     */
    private function output_schema_json_ld($schema) {
        // Prevent duplicate schemas
        $schema_hash = md5(json_encode($schema));
        if (in_array($schema_hash, $this->injected_schemas)) {
            return;
        }
        
        $this->injected_schemas[] = $schema_hash;
        
        // Clean and encode schema
        $json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json_error = json_last_error_msg();
            if (get_option('zippicks_schema_enable_debug_mode', false)) {
                error_log("ZipPicks Schema: Failed to encode schema as JSON - {$json_error}");
                error_log("ZipPicks Schema: Problem schema data - " . print_r($schema, true));
            }
            return;
        }
        
        // Output JSON-LD script tag
        echo "\n<!-- ZipPicks Schema -->\n";
        echo '<script type="application/ld+json">';
        echo $json;
        echo "</script>\n";
        
        // Add debug comment if enabled
        if (get_option('zippicks_schema_enable_debug_mode', false)) {
            echo "<!-- Schema Type: " . ($schema['@type'] ?? 'Unknown') . " -->\n";
        }
    }
    
    /**
     * Clear post cache when post is saved/deleted
     * 
     * @param int $post_id Post ID
     */
    public function clear_post_cache($post_id) {
        if ($this->generator) {
            $this->generator->clear_cache($post_id);
        }
        
        // Clear page cache if caching plugin is active
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Clear W3 Total Cache
        if (function_exists('w3tc_flush_posts')) {
            w3tc_flush_posts();
        }
        
        // Clear WP Rocket
        if (function_exists('rocket_clean_post')) {
            rocket_clean_post($post_id);
        }
        
        // Clear LiteSpeed Cache
        if (class_exists('LiteSpeed_Cache_API')) {
            LiteSpeed_Cache_API::purge_post($post_id);
        }
    }
    
    /**
     * Clear post cache when post status changes
     * 
     * @param string $new_status New post status
     * @param string $old_status Old post status  
     * @param WP_Post $post Post object
     */
    public function clear_post_cache_on_status_change($new_status, $old_status, $post) {
        // Only clear cache for relevant post types
        if (in_array($post->post_type, ['zippicks_business', 'master_critic_list', 'zippicks_review'])) {
            // Clear when transitioning to or from published
            if ($new_status === 'publish' || $old_status === 'publish') {
                $this->clear_post_cache($post->ID);
            }
        }
    }
    
    /**
     * Inject custom schema via filter
     * 
     * @param array $schema Schema to inject
     * @param array $context Optional context data
     * @return bool True if injected successfully
     */
    public function inject_custom_schema($schema, $context = []) {
        if (empty($schema) || !is_array($schema)) {
            return false;
        }
        
        if ($this->should_inject_schema($schema)) {
            $this->output_schema_json_ld($schema);
            return true;
        }
        
        return false;
    }
    
    /**
     * Add schema to be injected on current page
     * 
     * @param array $schema Schema data
     * @param string $priority Priority (header|footer)
     */
    public function add_schema($schema, $priority = 'header') {
        static $added_schemas = [];
        
        if ($priority === 'header') {
            $added_schemas[] = $schema;
            add_filter('zippicks_page_schemas', function($schemas) use ($added_schemas) {
                return array_merge($schemas, $added_schemas);
            });
        } else {
            // For footer injection
            add_action('wp_footer', function() use ($schema) {
                if ($this->should_inject_schema($schema)) {
                    $this->output_schema_json_ld($schema);
                }
            }, 1);
        }
    }
    
    /**
     * Get all injected schemas for debugging
     * 
     * @return array Injected schema hashes
     */
    public function get_injected_schemas() {
        return $this->injected_schemas;
    }
    
    /**
     * Enable/disable automatic injection
     * 
     * @param bool $enabled True to enable
     */
    public function set_auto_injection($enabled) {
        if ($enabled) {
            add_action('wp_head', [$this, 'inject_schema'], 1);
        } else {
            remove_action('wp_head', [$this, 'inject_schema'], 1);
        }
    }
    
    /**
     * Manually inject schema for a specific post
     * 
     * @param int|WP_Post $post Post ID or object
     * @param bool $validate Whether to validate before injection
     * @return bool True if injected successfully
     */
    public function inject_post_schema($post, $validate = true) {
        if (is_numeric($post)) {
            $post = get_post($post);
        }
        
        if (!$post || is_wp_error($post)) {
            return false;
        }
        
        $schema = $this->generator->generate_schema_for_post($post);
        if (!$schema) {
            return false;
        }
        
        if ($validate && !$this->should_inject_schema($schema)) {
            return false;
        }
        
        $this->output_schema_json_ld($schema);
        return true;
    }
    
    /**
     * Get schema for current page without injecting
     * 
     * @return array Schema data
     */
    public function get_current_page_schema() {
        return $this->get_page_schemas();
    }
    
    /**
     * Test schema injection for a URL
     * 
     * @param string $url URL to test
     * @return array Test result with schemas found
     */
    public function test_url_schema($url) {
        // This would need to fetch the URL and parse the schema
        // Implementation depends on requirements
        return [
            'url' => $url,
            'schemas_found' => [],
            'valid_schemas' => 0,
            'invalid_schemas' => 0,
            'errors' => []
        ];
    }
}