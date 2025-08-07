<?php
/**
 * JSON Importer for Master Critic
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Importer {
    
    private $errors = [];
    private $imported_count = 0;
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
     * Import JSON file
     *
     * @param array $file Uploaded file array from $_FILES
     * @return array Result with success status and message
     */
    public function import_from_file($file) {
        // Validate file
        if (!$this->validate_file($file)) {
            return [
                'success' => false,
                'message' => implode(', ', $this->errors)
            ];
        }
        
        // Read and parse JSON
        $json_content = file_get_contents($file['tmp_name']);
        $data = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => 'Invalid JSON format: ' . json_last_error_msg()
            ];
        }
        
        // Import the data
        return $this->import_data($data);
    }
    
    /**
     * Import JSON data
     *
     * @param array $data Parsed JSON data
     * @return array Result with success status and message
     */
    public function import_data($data) {
        global $wpdb;
        
        if ($this->logger) {
            $this->logger->info('Starting Master Set import', ['set_name' => $data['set_name'] ?? 'Unknown']);
        }
        
        // Validate required fields
        if (!$this->validate_data_structure($data)) {
            if ($this->logger) {
                $this->logger->error('Import validation failed', ['errors' => $this->errors]);
            }
            return [
                'success' => false,
                'message' => 'Invalid data structure: ' . implode(', ', $this->errors)
            ];
        }
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Create master set record
            $set_id = $this->create_master_set($data);
            
            if (!$set_id) {
                throw new Exception('Failed to create master set');
            }
            
            // Import items by tier
            $total_imported = 0;
            $position = 1;
            
            $tiers = ['essential', 'notable', 'worthy', 'honorable', 'unverified'];
            
            foreach ($tiers as $tier) {
                if (isset($data['categories'][$tier]) && is_array($data['categories'][$tier])) {
                    foreach ($data['categories'][$tier] as $item) {
                        $item['tier'] = $tier;
                        $item['position'] = $position++;
                        
                        if ($this->create_master_item($set_id, $item)) {
                            $total_imported++;
                        }
                    }
                }
            }
            
            // Update total items count
            $wpdb->update(
                ZipPicks_Master_Critic_Database::get_sets_table(),
                ['total_items' => $total_imported],
                ['id' => $set_id]
            );
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Clear relevant caches if available
            if ($this->cache) {
                $this->cache->delete('master_sets_list');
                $this->cache->delete("master_set_{$set_id}");
            }
            
            // Trigger action for other plugins
            do_action('zpmc_master_set_imported', $set_id, $data);
            
            if ($this->logger) {
                $this->logger->info('Master Set import completed successfully', [
                    'set_id' => $set_id,
                    'items_count' => $total_imported
                ]);
            }
            
            return [
                'success' => true,
                'message' => sprintf('Successfully imported "%s" with %d items', $data['set_name'], $total_imported),
                'set_id' => $set_id,
                'items_count' => $total_imported
            ];
            
        } catch (Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');
            
            if ($this->logger) {
                $this->logger->error('Master Set import failed', [
                    'error' => $e->getMessage(),
                    'set_name' => $data['set_name'] ?? 'Unknown'
                ]);
            }
            
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Create master set record
     */
    private function create_master_set($data) {
        global $wpdb;
        
        $set_data = [
            'set_name' => sanitize_text_field($data['set_name']),
            'set_slug' => sanitize_title($data['set_name']),
            'zip_code' => sanitize_text_field($data['zip_code'] ?? ''),
            'category' => sanitize_text_field($data['category'] ?? ''),
            'vertical' => sanitize_text_field($data['vertical'] ?? 'Restaurants'),
            'radius' => intval($data['radius'] ?? 5),
            'metadata' => wp_json_encode([
                'editor_notes' => $data['editor_notes'] ?? '',
                'generated_at' => $data['generated_at'] ?? '',
                'generation_time_minutes' => $data['generation_time_minutes'] ?? 0,
                'prompt_used' => $data['prompt_used'] ?? ''
            ]),
            'import_source' => 'desktop_tool',
            'status' => 'draft',
            'created_by' => get_current_user_id()
        ];
        
        // Check for duplicate slug
        $sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$sets_table} WHERE set_slug = %s",
            $set_data['set_slug']
        ));
        
        if ($existing) {
            // Append timestamp to make unique
            $set_data['set_slug'] .= '-' . time();
        }
        
        $wpdb->insert(
            ZipPicks_Master_Critic_Database::get_sets_table(),
            $set_data
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Create master item record
     */
    private function create_master_item($set_id, $item) {
        global $wpdb;
        
        $item_data = [
            'set_id' => $set_id,
            'business_name' => sanitize_text_field($item['name']),
            'business_slug' => sanitize_title($item['name']),
            'score' => floatval($item['score']),
            'tier' => sanitize_text_field($item['tier']),
            'price_tier' => sanitize_text_field($item['price_tier'] ?? ''),
            'neighborhood' => sanitize_text_field($item['neighborhood'] ?? ''),
            'summary' => wp_kses_post($item['summary'] ?? ''),
            'top_dishes' => wp_json_encode($item['top_dishes'] ?? []),
            'pillar_scores' => wp_json_encode($item['pillar_scores'] ?? []),
            'vibes' => wp_json_encode($item['vibes'] ?? []),
            'schema_payload' => wp_json_encode($item['schema_payload'] ?? []),
            'verified' => intval($item['verified'] ?? 0),
            'business_id' => sanitize_text_field($item['business_id'] ?? ''),
            'position' => intval($item['position']),
            'status' => 'active'
        ];
        
        // Extract address and phone from schema if available
        if (!empty($item['schema_payload'])) {
            $schema = is_array($item['schema_payload']) ? $item['schema_payload'] : json_decode($item['schema_payload'], true);
            
            if (!empty($schema['address'])) {
                $address_parts = [];
                if (!empty($schema['address']['streetAddress'])) {
                    $address_parts[] = $schema['address']['streetAddress'];
                }
                if (!empty($schema['address']['addressLocality'])) {
                    $address_parts[] = $schema['address']['addressLocality'];
                }
                if (!empty($schema['address']['addressRegion'])) {
                    $address_parts[] = $schema['address']['addressRegion'];
                }
                if (!empty($schema['address']['postalCode'])) {
                    $address_parts[] = $schema['address']['postalCode'];
                }
                $item_data['address'] = implode(', ', $address_parts);
            }
            
            if (!empty($schema['telephone'])) {
                $item_data['phone'] = sanitize_text_field($schema['telephone']);
            }
        }
        
        return $wpdb->insert(
            ZipPicks_Master_Critic_Database::get_items_table(),
            $item_data
        );
    }
    
    /**
     * Validate uploaded file
     */
    private function validate_file($file) {
        $this->errors = [];
        
        // Check file upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = 'File upload failed';
            return false;
        }
        
        // Check file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'json') {
            $this->errors[] = 'File must be JSON format';
            return false;
        }
        
        // Check file size (max 10MB)
        if ($file['size'] > 10485760) {
            $this->errors[] = 'File size exceeds 10MB limit';
            return false;
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, ['application/json', 'text/plain', 'text/json'])) {
            $this->errors[] = 'Invalid file type';
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate data structure
     */
    private function validate_data_structure($data) {
        $this->errors = [];
        
        // Check required top-level fields
        if (empty($data['set_name'])) {
            $this->errors[] = 'Missing set_name';
        }
        
        if (empty($data['categories']) || !is_array($data['categories'])) {
            $this->errors[] = 'Missing or invalid categories';
        }
        
        // Check for at least one item
        $has_items = false;
        foreach (['essential', 'notable', 'worthy', 'honorable', 'unverified'] as $tier) {
            if (!empty($data['categories'][$tier]) && is_array($data['categories'][$tier])) {
                $has_items = true;
                break;
            }
        }
        
        if (!$has_items) {
            $this->errors[] = 'No items found in any tier';
        }
        
        return empty($this->errors);
    }
}