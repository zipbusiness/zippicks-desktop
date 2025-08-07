<?php
/**
 * REST API Controller for Master Critic
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_REST_Controller extends WP_REST_Controller {
    
    protected $namespace;
    protected $rest_base;
    private $logger = null;
    private $cache = null;
    
    public function __construct() {
        $this->namespace = 'zippicks/v1';
        $this->rest_base = 'master-sets';
        
        // Use Core services if available
        if (function_exists('zippicks')) {
            if (zippicks()->has('core.logger')) {
                $this->logger = zippicks()->get('core.logger');
            }
            if (zippicks()->has('cache')) {
                $this->cache = zippicks()->get('cache');
            }
        }
    }
    
    /**
     * Register REST routes
     */
    public function register_routes() {
        // GET /wp-json/zippicks/v1/master-sets - List all sets
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_items'],
                'permission_callback' => [$this, 'get_items_permissions_check'],
                'args'                => $this->get_collection_params(),
            ],
        ]);
        
        // GET /wp-json/zippicks/v1/master-sets/{id} - Get single set with items
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_item'],
                'permission_callback' => [$this, 'get_item_permissions_check'],
                'args'                => [
                    'id' => [
                        'description' => 'Unique identifier for the master set.',
                        'type'        => 'integer',
                        'required'    => true,
                    ],
                    'include_items' => [
                        'description' => 'Whether to include items in the response.',
                        'type'        => 'boolean',
                        'default'     => true,
                    ],
                ],
            ],
        ]);
        
        // POST /wp-json/zippicks/v1/master-sets/import - Import JSON data
        register_rest_route($this->namespace, '/' . $this->rest_base . '/import', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'import_data'],
                'permission_callback' => [$this, 'import_permissions_check'],
                'args'                => [
                    'data' => [
                        'description' => 'JSON data to import.',
                        'type'        => 'object',
                        'required'    => true,
                    ],
                ],
            ],
        ]);
    }
    
    /**
     * Get collection of master sets
     */
    public function get_items($request) {
        // Check Core rate limiter if available
        if (function_exists('zippicks') && zippicks()->has('rate_limiter')) {
            $rate_limiter = zippicks()->get('rate_limiter');
            if (!$rate_limiter->check('master_critic_api_list')) {
                return new WP_REST_Response([
                    'code' => 'rate_limit_exceeded',
                    'message' => 'Rate limit exceeded for API requests.',
                    'data' => ['status' => 429]
                ], 429);
            }
        }
        
        global $wpdb;
        
        $page = $request->get_param('page') ?: 1;
        $per_page = min($request->get_param('per_page') ?: 20, 100);
        $offset = ($page - 1) * $per_page;
        
        $cache_key = "master_sets_list_{$page}_{$per_page}";
        
        // Try cache first
        if ($this->cache) {
            $cached_data = $this->cache->get($cache_key);
            if ($cached_data !== false) {
                if ($this->logger) {
                    $this->logger->debug('Serving master sets from cache', ['cache_key' => $cache_key]);
                }
                return new WP_REST_Response($cached_data, 200);
            }
        }
        
        // Build query
        $table = ZipPicks_Master_Critic_Database::get_sets_table();
        
        $query = $wpdb->prepare(
            "SELECT id, set_name, set_slug, zip_code, category, vertical, 
                    radius, total_items, status, created_by, created_at, updated_at
             FROM {$table}
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if ($results === null) {
            if ($this->logger) {
                $this->logger->error('Database query failed for master sets list', [
                    'error' => $wpdb->last_error
                ]);
            }
            return new WP_REST_Response([
                'code' => 'database_error',
                'message' => 'Failed to retrieve master sets.',
                'data' => ['status' => 500]
            ], 500);
        }
        
        // Get total count for pagination
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $total = $total !== null ? (int) $total : 0;
        
        // Format response data
        $formatted_results = [];
        foreach ($results as $set) {
            $formatted_results[] = $this->prepare_item_for_response($set, $request);
        }
        
        $response_data = [
            'sets' => $formatted_results,
            'pagination' => [
                'total' => $total,
                'pages' => ceil($total / $per_page),
                'page' => $page,
                'per_page' => $per_page
            ]
        ];
        
        // Cache the response
        if ($this->cache) {
            $this->cache->set($cache_key, $response_data, 300); // 5 minutes
        }
        
        if ($this->logger) {
            $this->logger->info('Master sets retrieved successfully', [
                'count' => count($formatted_results),
                'total' => $total,
                'page' => $page
            ]);
        }
        
        return new WP_REST_Response($response_data, 200);
    }
    
    /**
     * Get single master set with items
     */
    public function get_item($request) {
        // Check Core rate limiter if available
        if (function_exists('zippicks') && zippicks()->has('rate_limiter')) {
            $rate_limiter = zippicks()->get('rate_limiter');
            if (!$rate_limiter->check('master_critic_api_single')) {
                return new WP_REST_Response([
                    'code' => 'rate_limit_exceeded',
                    'message' => 'Rate limit exceeded for API requests.',
                    'data' => ['status' => 429]
                ], 429);
            }
        }
        
        global $wpdb;
        
        $id = (int) $request->get_param('id');
        $include_items = $request->get_param('include_items');
        
        $cache_key = "master_set_{$id}_" . ($include_items ? 'with_items' : 'no_items');
        
        // Try cache first
        if ($this->cache) {
            $cached_data = $this->cache->get($cache_key);
            if ($cached_data !== false) {
                if ($this->logger) {
                    $this->logger->debug('Serving master set from cache', ['set_id' => $id]);
                }
                return new WP_REST_Response($cached_data, 200);
            }
        }
        
        // Get the master set
        $sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
        
        $set_query = $wpdb->prepare(
            "SELECT * FROM {$sets_table} WHERE id = %d",
            $id
        );
        
        $set = $wpdb->get_row($set_query, ARRAY_A);
        
        if (!$set) {
            return new WP_REST_Response([
                'code' => 'not_found',
                'message' => 'Master set not found.',
                'data' => ['status' => 404]
            ], 404);
        }
        
        if ($set === null) {
            if ($this->logger) {
                $this->logger->error('Database query failed for master set', [
                    'set_id' => $id,
                    'error' => $wpdb->last_error
                ]);
            }
            return new WP_REST_Response([
                'code' => 'database_error',
                'message' => 'Failed to retrieve master set.',
                'data' => ['status' => 500]
            ], 500);
        }
        
        $formatted_set = $this->prepare_item_for_response($set, $request);
        
        // Include items if requested
        if ($include_items) {
            $items_table = ZipPicks_Master_Critic_Database::get_items_table();
            
            $items_query = $wpdb->prepare(
                "SELECT * FROM {$items_table} 
                 WHERE set_id = %d AND status = 'active'
                 ORDER BY position ASC, score DESC",
                $id
            );
            
            $items = $wpdb->get_results($items_query, ARRAY_A);
            
            if ($items === null) {
                if ($this->logger) {
                    $this->logger->error('Database query failed for master set items', [
                        'set_id' => $id,
                        'error' => $wpdb->last_error
                    ]);
                }
                return new WP_REST_Response([
                    'code' => 'database_error',
                    'message' => 'Failed to retrieve master set items.',
                    'data' => ['status' => 500]
                ], 500);
            }
            
            // Format items
            $formatted_items = [];
            foreach ($items as $item) {
                $formatted_items[] = $this->prepare_item_data($item);
            }
            
            $formatted_set['items'] = $formatted_items;
        }
        
        // Cache the response
        if ($this->cache) {
            $this->cache->set($cache_key, $formatted_set, 600); // 10 minutes
        }
        
        if ($this->logger) {
            $this->logger->info('Master set retrieved successfully', [
                'set_id' => $id,
                'include_items' => $include_items,
                'items_count' => isset($formatted_set['items']) ? count($formatted_set['items']) : 0
            ]);
        }
        
        return new WP_REST_Response($formatted_set, 200);
    }
    
    /**
     * Import JSON data using existing importer
     */
    public function import_data($request) {
        // Check Core rate limiter if available
        if (function_exists('zippicks') && zippicks()->has('rate_limiter')) {
            $rate_limiter = zippicks()->get('rate_limiter');
            if (!$rate_limiter->check('master_critic_import')) {
                return new WP_REST_Response([
                    'code' => 'rate_limit_exceeded',
                    'message' => 'Rate limit exceeded for import requests.',
                    'data' => ['status' => 429]
                ], 429);
            }
        }
        
        $data = $request->get_param('data');
        
        if (!is_array($data)) {
            return new WP_REST_Response([
                'code' => 'invalid_data',
                'message' => 'Data must be a valid JSON object.',
                'data' => ['status' => 400]
            ], 400);
        }
        
        if ($this->logger) {
            $this->logger->info('Master set import requested via REST API', [
                'set_name' => $data['set_name'] ?? 'Unknown',
                'user_id' => get_current_user_id()
            ]);
        }
        
        // Use existing importer
        $importer = new ZipPicks_Master_Critic_Importer();
        $result = $importer->import_data($data);
        
        if ($result['success']) {
            // Clear relevant caches
            if ($this->cache) {
                $this->cache->delete('master_sets_list_1_20');
                if (isset($result['set_id'])) {
                    $this->cache->delete("master_set_{$result['set_id']}_with_items");
                    $this->cache->delete("master_set_{$result['set_id']}_no_items");
                }
            }
            
            return new WP_REST_Response([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'set_id' => $result['set_id'] ?? null,
                    'items_count' => $result['items_count'] ?? 0
                ]
            ], 201);
        } else {
            return new WP_REST_Response([
                'code' => 'import_failed',
                'message' => $result['message'],
                'data' => ['status' => 400]
            ], 400);
        }
    }
    
    /**
     * Permission check for getting items (public access)
     */
    public function get_items_permissions_check($request) {
        return true; // Public access
    }
    
    /**
     * Permission check for getting single item (public access)
     */
    public function get_item_permissions_check($request) {
        return true; // Public access
    }
    
    /**
     * Permission check for import (requires manage_options)
     */
    public function import_permissions_check($request) {
        return current_user_can('manage_options');
    }
    
    /**
     * Prepare single item for response
     */
    protected function prepare_item_for_response($set, $request) {
        $data = [
            'id' => (int) $set['id'],
            'set_name' => $set['set_name'],
            'set_slug' => $set['set_slug'],
            'zip_code' => $set['zip_code'],
            'category' => $set['category'],
            'vertical' => $set['vertical'],
            'radius' => (int) $set['radius'],
            'total_items' => (int) $set['total_items'],
            'status' => $set['status'],
            'created_at' => $set['created_at'],
            'updated_at' => $set['updated_at']
        ];
        
        // Include metadata if available
        if (!empty($set['metadata'])) {
            $metadata = json_decode($set['metadata'], true);
            if (is_array($metadata)) {
                $data['metadata'] = $metadata;
            }
        }
        
        return $data;
    }
    
    /**
     * Prepare item data for response
     */
    protected function prepare_item_data($item) {
        $data = [
            'id' => (int) $item['id'],
            'business_name' => $item['business_name'],
            'business_slug' => $item['business_slug'],
            'score' => (float) $item['score'],
            'tier' => $item['tier'],
            'price_tier' => $item['price_tier'],
            'neighborhood' => $item['neighborhood'],
            'address' => $item['address'],
            'phone' => $item['phone'],
            'summary' => $item['summary'],
            'position' => (int) $item['position'],
            'verified' => (bool) $item['verified'],
            'business_id' => $item['business_id']
        ];
        
        // Decode JSON fields
        $json_fields = ['top_dishes', 'pillar_scores', 'vibes', 'schema_payload'];
        foreach ($json_fields as $field) {
            if (!empty($item[$field])) {
                $decoded = json_decode($item[$field], true);
                if (is_array($decoded)) {
                    $data[$field] = $decoded;
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Get collection parameters for the sets endpoint
     */
    public function get_collection_params() {
        return [
            'page' => [
                'description' => 'Current page of the collection.',
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
            ],
            'per_page' => [
                'description' => 'Maximum number of items to be returned in result set.',
                'type' => 'integer',
                'default' => 20,
                'minimum' => 1,
                'maximum' => 100,
            ],
        ];
    }
}