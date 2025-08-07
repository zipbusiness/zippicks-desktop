<?php
/**
 * JSON Exporter for Master Critic
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Exporter {
    
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
     * Export master set to JSON format
     *
     * @param int $set_id Master set ID to export
     * @return array|false Export data or false on failure
     */
    public function export_set($set_id) {
        global $wpdb;
        
        if ($this->logger) {
            $this->logger->info('Starting master set export', ['set_id' => $set_id]);
        }
        
        // Get master set data
        $sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
        
        $set_query = $wpdb->prepare(
            "SELECT * FROM {$sets_table} WHERE id = %d",
            $set_id
        );
        
        $set = $wpdb->get_row($set_query, ARRAY_A);
        
        if (!$set) {
            if ($this->logger) {
                $this->logger->error('Master set not found for export', ['set_id' => $set_id]);
            }
            return false;
        }
        
        if ($set === null) {
            if ($this->logger) {
                $this->logger->error('Database error retrieving master set', [
                    'set_id' => $set_id,
                    'error' => $wpdb->last_error
                ]);
            }
            return false;
        }
        
        // Get items data
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
            return false;
        }
        
        // Ensure we return an array even if no items
        $items = is_array($items) ? $items : [];
        
        // Build export data
        $export_data = $this->build_export_data($set, $items);
        
        if ($this->logger) {
            $this->logger->info('Master set export completed successfully', [
                'set_id' => $set_id,
                'items_count' => count($items)
            ]);
        }
        
        return $export_data;
    }
    
    /**
     * Export master set to JSON file
     *
     * @param int $set_id Master set ID to export
     * @param string $filename Optional filename (will be generated if not provided)
     * @return array Result with success status and file path
     */
    public function export_to_file($set_id, $filename = null) {
        $export_data = $this->export_set($set_id);
        
        if ($export_data === false) {
            return [
                'success' => false,
                'message' => 'Failed to export master set data'
            ];
        }
        
        // Generate filename if not provided
        if (!$filename) {
            $set_slug = $export_data['set_slug'] ?? 'master-set';
            $filename = $set_slug . '-export-' . date('Y-m-d-His') . '.json';
        }
        
        // Create uploads directory path
        $upload_dir = wp_upload_dir();
        $export_path = $upload_dir['basedir'] . '/zippicks-exports/';
        
        // Create directory if it doesn't exist
        if (!file_exists($export_path)) {
            wp_mkdir_p($export_path);
        }
        
        $file_path = $export_path . $filename;
        
        // Write JSON file
        $json_data = wp_json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($json_data === false) {
            return [
                'success' => false,
                'message' => 'Failed to encode JSON data'
            ];
        }
        
        $result = file_put_contents($file_path, $json_data);
        
        if ($result === false) {
            return [
                'success' => false,
                'message' => 'Failed to write export file'
            ];
        }
        
        if ($this->logger) {
            $this->logger->info('Master set exported to file successfully', [
                'set_id' => $set_id,
                'file_path' => $file_path,
                'file_size' => $result
            ]);
        }
        
        return [
            'success' => true,
            'message' => 'Export completed successfully',
            'file_path' => $file_path,
            'filename' => $filename,
            'file_size' => $result
        ];
    }
    
    /**
     * Build export data structure
     *
     * @param array $set Master set data
     * @param array $items Items data
     * @return array Export data in import-compatible format
     */
    private function build_export_data($set, $items) {
        // Parse metadata
        $metadata = [];
        if (!empty($set['metadata'])) {
            $parsed_metadata = json_decode($set['metadata'], true);
            if (is_array($parsed_metadata)) {
                $metadata = $parsed_metadata;
            }
        }
        
        // Build base export structure
        $export_data = [
            'set_name' => $set['set_name'],
            'set_slug' => $set['set_slug'],
            'zip_code' => $set['zip_code'],
            'category' => $set['category'],
            'vertical' => $set['vertical'],
            'radius' => (int) $set['radius'],
            'editor_notes' => $metadata['editor_notes'] ?? '',
            'generated_at' => $metadata['generated_at'] ?? $set['created_at'],
            'generation_time_minutes' => $metadata['generation_time_minutes'] ?? 0,
            'prompt_used' => $metadata['prompt_used'] ?? '',
            'export_date' => current_time('Y-m-d H:i:s'),
            'total_items' => (int) $set['total_items'],
            'categories' => []
        ];
        
        // Initialize tier categories
        $tiers = ['essential', 'notable', 'worthy', 'honorable', 'unverified'];
        foreach ($tiers as $tier) {
            $export_data['categories'][$tier] = [];
        }
        
        // Group items by tier
        foreach ($items as $item) {
            $tier = $item['tier'];
            
            // Skip invalid tiers
            if (!in_array($tier, $tiers)) {
                continue;
            }
            
            // Build item data
            $item_data = [
                'name' => $item['business_name'],
                'score' => (float) $item['score'],
                'tier' => $tier,
                'price_tier' => $item['price_tier'],
                'neighborhood' => $item['neighborhood'],
                'summary' => $item['summary'],
                'verified' => (bool) $item['verified'],
                'business_id' => $item['business_id'],
                'position' => (int) $item['position']
            ];
            
            // Decode JSON fields
            $json_fields = ['top_dishes', 'pillar_scores', 'vibes', 'schema_payload'];
            foreach ($json_fields as $field) {
                if (!empty($item[$field])) {
                    $decoded = json_decode($item[$field], true);
                    if (is_array($decoded)) {
                        $item_data[$field] = $decoded;
                    } else {
                        $item_data[$field] = [];
                    }
                } else {
                    $item_data[$field] = [];
                }
            }
            
            $export_data['categories'][$tier][] = $item_data;
        }
        
        return $export_data;
    }
    
    /**
     * Get list of available exports
     *
     * @return array List of export files
     */
    public function get_export_files() {
        $upload_dir = wp_upload_dir();
        $export_path = $upload_dir['basedir'] . '/zippicks-exports/';
        
        if (!file_exists($export_path)) {
            return [];
        }
        
        $files = [];
        $directory = new DirectoryIterator($export_path);
        
        foreach ($directory as $file_info) {
            if ($file_info->isDot() || $file_info->getExtension() !== 'json') {
                continue;
            }
            
            $files[] = [
                'filename' => $file_info->getFilename(),
                'size' => $file_info->getSize(),
                'created' => date('Y-m-d H:i:s', $file_info->getMTime()),
                'path' => $file_info->getPathname()
            ];
        }
        
        // Sort by creation time, newest first
        usort($files, function($a, $b) {
            return strcmp($b['created'], $a['created']);
        });
        
        return $files;
    }
}