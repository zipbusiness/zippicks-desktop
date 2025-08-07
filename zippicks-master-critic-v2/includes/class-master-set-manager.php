<?php
/**
 * Master Set Manager for CRUD operations
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Master_Set_Manager {
    
    private $logger = null;
    private $cache = null;
    
    public function __construct() {
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
     * Get master set by ID
     *
     * @param int $set_id Master set ID
     * @param bool $include_items Whether to include items
     * @return array|false Master set data or false on failure
     */
    public function get_set($set_id, $include_items = false) {
        global $wpdb;
        
        $cache_key = "master_set_{$set_id}_" . ($include_items ? 'with_items' : 'no_items');
        
        // Try cache first
        if ($this->cache) {
            $cached_data = $this->cache->get($cache_key);
            if ($cached_data !== false) {
                return is_array($cached_data) ? $cached_data : [];
            }
        }
        
        // Get master set data
        $sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
        
        $set_query = $wpdb->prepare(
            "SELECT * FROM {$sets_table} WHERE id = %d",
            $set_id
        );
        
        $set = $wpdb->get_row($set_query, ARRAY_A);
        
        if (!$set || $set === null) {
            if ($this->logger && $wpdb->last_error) {
                $this->logger->error('Database error retrieving master set', [
                    'set_id' => $set_id,
                    'error' => $wpdb->last_error
                ]);
            }
            return false;
        }
        
        // Parse metadata
        $metadata = [];
        if (!empty($set['metadata'])) {
            $parsed_metadata = json_decode($set['metadata'], true);
            if (is_array($parsed_metadata)) {
                $metadata = $parsed_metadata;
            }
        }
        $set['metadata'] = $metadata;
        
        // Include items if requested
        if ($include_items) {
            $items = $this->get_set_items($set_id);
            $set['items'] = is_array($items) ? $items : [];
        }
        
        // Cache the result
        if ($this->cache) {
            $this->cache->set($cache_key, $set, 600); // 10 minutes
        }
        
        return $set;
    }
    
    /**
     * Get master set by slug
     *
     * @param string $slug Master set slug
     * @param bool $include_items Whether to include items
     * @return array|false Master set data or false on failure
     */
    public function get_set_by_slug($slug, $include_items = false) {
        global $wpdb;
        
        $sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
        
        $set_query = $wpdb->prepare(
            "SELECT id FROM {$sets_table} WHERE set_slug = %s",
            $slug
        );
        
        $set_id = $wpdb->get_var($set_query);
        
        if (!$set_id || $set_id === null) {
            return false;
        }
        
        return $this->get_set((int) $set_id, $include_items);
    }
    
    /**
     * Get items for a master set
     *
     * @param int $set_id Master set ID
     * @return array Items data (empty array if none found)
     */
    public function get_set_items($set_id) {
        global $wpdb;
        
        $items_table = ZipPicks_Master_Critic_Database::get_items_table();
        
        $items_query = $wpdb->prepare(
            "SELECT * FROM {$items_table} 
             WHERE set_id = %d AND status = 'active'
             ORDER BY position ASC, score DESC",
            $set_id
        );
        
        $items = $wpdb->get_results($items_query, ARRAY_A);
        
        if ($items === null) {
            if ($this->logger) {
                $this->logger->error('Database error retrieving master set items', [
                    'set_id' => $set_id,
                    'error' => $wpdb->last_error
                ]);
            }
            return [];
        }
        
        // Ensure we return an array
        $items = is_array($items) ? $items : [];
        
        // Decode JSON fields for each item
        $formatted_items = [];
        foreach ($items as $item) {
            $formatted_items[] = $this->format_item_data($item);
        }
        
        return $formatted_items;
    }
    
    /**
     * Update master set status
     *
     * @param int $set_id Master set ID
     * @param string $status New status ('draft', 'published', 'archived')
     * @return bool Success status
     */
    public function update_set_status($set_id, $status) {
        global $wpdb;
        
        $valid_statuses = ['draft', 'published', 'archived'];
        if (!in_array($status, $valid_statuses)) {
            if ($this->logger) {
                $this->logger->error('Invalid status provided', [
                    'set_id' => $set_id,
                    'status' => $status
                ]);
            }
            return false;
        }
        
        $sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
        
        $result = $wpdb->update(
            $sets_table,
            ['status' => $status],
            ['id' => $set_id],
            ['%s'],
            ['%d']
        );
        
        if ($result === false) {
            if ($this->logger) {
                $this->logger->error('Failed to update master set status', [
                    'set_id' => $set_id,
                    'status' => $status,
                    'error' => $wpdb->last_error
                ]);
            }
            return false;
        }
        
        // Clear related caches
        $this->clear_set_caches($set_id);
        
        if ($this->logger) {
            $this->logger->info('Master set status updated', [
                'set_id' => $set_id,
                'status' => $status
            ]);
        }
        
        return true;
    }
    
    /**
     * Update master set metadata
     *
     * @param int $set_id Master set ID
     * @param array $metadata Metadata array to update
     * @return bool Success status
     */
    public function update_set_metadata($set_id, $metadata) {
        global $wpdb;
        
        if (!is_array($metadata)) {
            return false;
        }
        
        $sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
        
        $result = $wpdb->update(
            $sets_table,
            ['metadata' => wp_json_encode($metadata)],
            ['id' => $set_id],
            ['%s'],
            ['%d']
        );
        
        if ($result === false) {
            if ($this->logger) {
                $this->logger->error('Failed to update master set metadata', [
                    'set_id' => $set_id,
                    'error' => $wpdb->last_error
                ]);
            }
            return false;
        }
        
        // Clear related caches
        $this->clear_set_caches($set_id);
        
        if ($this->logger) {
            $this->logger->info('Master set metadata updated', ['set_id' => $set_id]);
        }
        
        return true;
    }
    
    /**
     * Delete master set and all related items
     *
     * @param int $set_id Master set ID
     * @return bool Success status
     */
    public function delete_set($set_id) {
        global $wpdb;
        
        if ($this->logger) {
            $this->logger->info('Starting master set deletion', ['set_id' => $set_id]);
        }
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            $sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
            $items_table = ZipPicks_Master_Critic_Database::get_items_table();
            $meta_table = ZipPicks_Master_Critic_Database::get_meta_table();
            
            // Delete meta records
            $meta_result = $wpdb->delete(
                $meta_table,
                ['set_id' => $set_id],
                ['%d']
            );
            
            // Delete items
            $items_result = $wpdb->delete(
                $items_table,
                ['set_id' => $set_id],
                ['%d']
            );
            
            // Delete master set
            $set_result = $wpdb->delete(
                $sets_table,
                ['id' => $set_id],
                ['%d']
            );
            
            if ($set_result === false) {
                throw new Exception('Failed to delete master set record');
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Clear related caches
            $this->clear_set_caches($set_id);
            $this->clear_list_caches();
            
            if ($this->logger) {
                $this->logger->info('Master set deleted successfully', [
                    'set_id' => $set_id,
                    'items_deleted' => $items_result !== false ? $items_result : 0,
                    'meta_deleted' => $meta_result !== false ? $meta_result : 0
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');
            
            if ($this->logger) {
                $this->logger->error('Master set deletion failed', [
                    'set_id' => $set_id,
                    'error' => $e->getMessage()
                ]);
            }
            
            return false;
        }
    }
    
    /**
     * Get list of all master sets
     *
     * @param array $args Optional arguments (limit, offset, status)
     * @return array List of master sets (empty array if none found)
     */
    public function get_sets_list($args = []) {
        global $wpdb;
        
        $defaults = [
            'limit' => 20,
            'offset' => 0,
            'status' => null,
            'order_by' => 'created_at',
            'order' => 'DESC'
        ];
        $args = wp_parse_args($args, $defaults);
        
        $sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
        
        // Build query
        $where_clause = '';
        $where_values = [];
        
        if ($args['status']) {
            $where_clause = ' WHERE status = %s';
            $where_values[] = $args['status'];
        }
        
        $order_by = in_array($args['order_by'], ['created_at', 'updated_at', 'set_name', 'total_items']) 
            ? $args['order_by'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $query = "SELECT id, set_name, set_slug, zip_code, category, vertical, 
                         radius, total_items, status, created_by, created_at, updated_at
                  FROM {$sets_table}
                  {$where_clause}
                  ORDER BY {$order_by} {$order}
                  LIMIT %d OFFSET %d";
        
        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];
        
        $prepared_query = $wpdb->prepare($query, $where_values);
        $results = $wpdb->get_results($prepared_query, ARRAY_A);
        
        if ($results === null) {
            if ($this->logger) {
                $this->logger->error('Database error retrieving master sets list', [
                    'error' => $wpdb->last_error
                ]);
            }
            return [];
        }
        
        // Ensure we return an array
        return is_array($results) ? $results : [];
    }
    
    /**
     * Format item data by decoding JSON fields
     *
     * @param array $item Raw item data from database
     * @return array Formatted item data
     */
    private function format_item_data($item) {
        $json_fields = ['top_dishes', 'pillar_scores', 'vibes', 'schema_payload'];
        
        foreach ($json_fields as $field) {
            if (!empty($item[$field])) {
                $decoded = json_decode($item[$field], true);
                if (is_array($decoded)) {
                    $item[$field] = $decoded;
                } else {
                    $item[$field] = [];
                }
            } else {
                $item[$field] = [];
            }
        }
        
        // Convert numeric fields
        $item['id'] = (int) $item['id'];
        $item['set_id'] = (int) $item['set_id'];
        $item['score'] = (float) $item['score'];
        $item['position'] = (int) $item['position'];
        $item['verified'] = (bool) $item['verified'];
        
        return $item;
    }
    
    /**
     * Clear caches for a specific set
     *
     * @param int $set_id Master set ID
     */
    private function clear_set_caches($set_id) {
        if ($this->cache) {
            $this->cache->delete("master_set_{$set_id}_with_items");
            $this->cache->delete("master_set_{$set_id}_no_items");
        }
    }
    
    /**
     * Clear list caches
     */
    private function clear_list_caches() {
        if ($this->cache) {
            // Clear common list cache keys
            for ($page = 1; $page <= 5; $page++) {
                $this->cache->delete("master_sets_list_{$page}_20");
            }
        }
    }
}