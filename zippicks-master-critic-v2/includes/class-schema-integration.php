<?php
/**
 * Schema.org Integration
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Schema_Integration {
    
    /**
     * Enhance schema with Master Critic data
     */
    public function enhance_schema($schema, $post) {
        // Check if this is a Master Critic related post
        $master_set_id = get_post_meta($post->ID, '_zpmc_set_id', true);
        
        if (!$master_set_id) {
            return $schema;
        }
        
        // Get Master Set data
        global $wpdb;
        $sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
        $set = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$sets_table} WHERE id = %d",
            $master_set_id
        ));
        
        if (!$set) {
            return $schema;
        }
        
        // Add Master Critic context
        $schema['isPartOf'] = [
            '@type' => 'ItemList',
            'name' => $set->set_name,
            'url' => home_url('/master-set/' . $set->set_slug),
            'numberOfItems' => $set->total_items
        ];
        
        return $schema;
    }
    
    /**
     * Generate schema for Master List
     */
    public function generate_list_schema($schema, $post) {
        // Get set ID from post meta or parameter
        $set_id = get_post_meta($post->ID, '_zpmc_set_id', true);
        
        if (!$set_id && isset($_GET['set_id'])) {
            $set_id = intval($_GET['set_id']);
        }
        
        if (!$set_id) {
            return $schema;
        }
        
        global $wpdb;
        
        // Get set data
        $sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
        $set = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$sets_table} WHERE id = %d",
            $set_id
        ));
        
        if (!$set) {
            return $schema;
        }
        
        // Get items
        $items_table = ZipPicks_Master_Critic_Database::get_items_table();
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$items_table} WHERE set_id = %d ORDER BY position ASC",
            $set_id
        ));
        
        // Build ItemList schema
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $set->set_name,
            'description' => sprintf('Master Critic curated list of %s in %s', $set->category, $this->get_location_name($set->zip_code)),
            'url' => home_url('/master-set/' . $set->set_slug),
            'numberOfItems' => count($items),
            'itemListElement' => []
        ];
        
        // Add each item with its schema
        foreach ($items as $index => $item) {
            $item_schema = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'url' => home_url('/business/' . $item->business_slug)
            ];
            
            // Add the restaurant schema if available
            if (!empty($item->schema_payload)) {
                $restaurant_schema = json_decode($item->schema_payload, true);
                
                if ($restaurant_schema) {
                    // Remove @context from nested schema
                    unset($restaurant_schema['@context']);
                    $item_schema['item'] = $restaurant_schema;
                } else {
                    // Fallback to basic schema
                    $item_schema['item'] = [
                        '@type' => 'Restaurant',
                        'name' => $item->business_name,
                        'aggregateRating' => [
                            '@type' => 'AggregateRating',
                            'ratingValue' => $item->score,
                            'bestRating' => 10,
                            'worstRating' => 0
                        ]
                    ];
                }
            }
            
            $schema['itemListElement'][] = $item_schema;
        }
        
        // Add breadcrumb
        $schema['breadcrumb'] = [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => home_url()
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Master Sets',
                    'item' => home_url('/master-sets')
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $set->set_name
                ]
            ]
        ];
        
        return $schema;
    }
    
    /**
     * Output schema in page head
     */
    public function output_schema() {
        // Check if we're on a Master Set page
        if (!is_singular() && !isset($_GET['zpmc_set'])) {
            return;
        }
        
        global $post;
        $schema = null;
        
        // Check for Master Set shortcode or parameter
        if (isset($_GET['zpmc_set'])) {
            $set_slug = sanitize_text_field($_GET['zpmc_set']);
            $schema = $this->get_set_schema_by_slug($set_slug);
        } elseif (has_shortcode($post->post_content, 'master_critic_list')) {
            // Extract set ID from shortcode
            preg_match('/\[master_critic_list.*?id="(\d+)".*?\]/', $post->post_content, $matches);
            if (!empty($matches[1])) {
                $schema = $this->get_set_schema_by_id($matches[1]);
            }
        }
        
        if ($schema) {
            echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
        }
    }
    
    /**
     * Get schema for a set by ID
     */
    private function get_set_schema_by_id($set_id) {
        global $wpdb;
        
        $sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
        $set = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$sets_table} WHERE id = %d AND status = 'published'",
            $set_id
        ));
        
        if (!$set) {
            return null;
        }
        
        return $this->build_set_schema($set);
    }
    
    /**
     * Get schema for a set by slug
     */
    private function get_set_schema_by_slug($set_slug) {
        global $wpdb;
        
        $sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
        $set = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$sets_table} WHERE set_slug = %s AND status = 'published'",
            $set_slug
        ));
        
        if (!$set) {
            return null;
        }
        
        return $this->build_set_schema($set);
    }
    
    /**
     * Build schema for a set
     */
    private function build_set_schema($set) {
        global $wpdb;
        
        // Get items
        $items_table = ZipPicks_Master_Critic_Database::get_items_table();
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$items_table} WHERE set_id = %d AND status = 'active' ORDER BY position ASC",
            $set->id
        ));
        
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $set->set_name,
            'url' => home_url('/master-set/' . $set->set_slug),
            'numberOfItems' => count($items),
            'itemListElement' => []
        ];
        
        foreach ($items as $index => $item) {
            $item_element = [
                '@type' => 'ListItem',
                'position' => $index + 1
            ];
            
            // Use stored schema payload if available
            if (!empty($item->schema_payload)) {
                $restaurant_schema = json_decode($item->schema_payload, true);
                if ($restaurant_schema) {
                    unset($restaurant_schema['@context']);
                    $item_element['item'] = $restaurant_schema;
                }
            }
            
            // Fallback to basic schema
            if (empty($item_element['item'])) {
                $item_element['item'] = [
                    '@type' => 'Restaurant',
                    'name' => $item->business_name,
                    'aggregateRating' => [
                        '@type' => 'AggregateRating',
                        'ratingValue' => $item->score,
                        'bestRating' => 10
                    ]
                ];
            }
            
            $schema['itemListElement'][] = $item_element;
        }
        
        return $schema;
    }
    
    /**
     * Get location name from ZIP
     */
    private function get_location_name($zip_code) {
        $zip_map = [
            '94566' => 'Pleasanton, CA',
            '94588' => 'Pleasanton, CA',
            '94568' => 'Dublin, CA',
            '94550' => 'Livermore, CA',
            '90210' => 'Beverly Hills, CA'
        ];
        
        return $zip_map[$zip_code] ?? $zip_code;
    }
}