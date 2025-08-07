<?php
/**
 * Ranking Engine for Master Critic items
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Ranking_Engine {
    
    private $logger = null;
    
    // Tier score boundaries
    const TIER_BOUNDARIES = [
        'essential' => 9.0,
        'notable' => 8.0,
        'worthy' => 7.0,
        'honorable' => 6.0,
        'unverified' => 0.0
    ];
    
    public function __construct() {
        // Use Core logger if available
        if (function_exists('zippicks') && zippicks()->has('core.logger')) {
            $this->logger = zippicks()->get('core.logger');
        }
    }
    
    /**
     * Sort items by tier and score
     *
     * @param array $items Array of items to sort
     * @param string $order Sort order ('desc' or 'asc')
     * @return array Sorted items
     */
    public function sort_items($items, $order = 'desc') {
        if (!is_array($items) || empty($items)) {
            return [];
        }
        
        // Define tier priority order
        $tier_priority = [
            'essential' => 1,
            'notable' => 2,
            'worthy' => 3,
            'honorable' => 4,
            'unverified' => 5
        ];
        
        usort($items, function($a, $b) use ($tier_priority, $order) {
            // Get tier priorities
            $tier_a = $tier_priority[$a['tier']] ?? 999;
            $tier_b = $tier_priority[$b['tier']] ?? 999;
            
            // First sort by tier (essential always first)
            if ($tier_a !== $tier_b) {
                return $tier_a - $tier_b;
            }
            
            // Within same tier, sort by score
            $score_a = (float) $a['score'];
            $score_b = (float) $b['score'];
            
            if ($order === 'asc') {
                return $score_a <=> $score_b;
            } else {
                return $score_b <=> $score_a; // desc (default)
            }
        });
        
        return $items;
    }
    
    /**
     * Assign tier based on score
     *
     * @param float $score Item score
     * @return string Tier name
     */
    public function get_tier_by_score($score) {
        $score = (float) $score;
        
        if ($score >= self::TIER_BOUNDARIES['essential']) {
            return 'essential';
        } elseif ($score >= self::TIER_BOUNDARIES['notable']) {
            return 'notable';
        } elseif ($score >= self::TIER_BOUNDARIES['worthy']) {
            return 'worthy';
        } elseif ($score >= self::TIER_BOUNDARIES['honorable']) {
            return 'honorable';
        } else {
            return 'unverified';
        }
    }
    
    /**
     * Group items by tier
     *
     * @param array $items Array of items to group
     * @return array Items grouped by tier
     */
    public function group_items_by_tier($items) {
        if (!is_array($items)) {
            return [];
        }
        
        $grouped = [
            'essential' => [],
            'notable' => [],
            'worthy' => [],
            'honorable' => [],
            'unverified' => []
        ];
        
        foreach ($items as $item) {
            $tier = $item['tier'] ?? 'unverified';
            
            // Validate tier exists
            if (!array_key_exists($tier, $grouped)) {
                $tier = 'unverified';
            }
            
            $grouped[$tier][] = $item;
        }
        
        // Sort items within each tier by score (desc)
        foreach ($grouped as $tier => $tier_items) {
            if (!empty($tier_items)) {
                $grouped[$tier] = $this->sort_items_by_score($tier_items, 'desc');
            }
        }
        
        return $grouped;
    }
    
    /**
     * Re-rank items if scores have changed
     *
     * @param int $set_id Master set ID
     * @return bool Success status
     */
    public function rerank_set_items($set_id) {
        global $wpdb;
        
        if ($this->logger) {
            $this->logger->info('Starting item re-ranking', ['set_id' => $set_id]);
        }
        
        $items_table = ZipPicks_Master_Critic_Database::get_items_table();
        
        // Get all items for the set
        $items_query = $wpdb->prepare(
            "SELECT id, score, tier FROM {$items_table} 
             WHERE set_id = %d AND status = 'active'
             ORDER BY score DESC",
            $set_id
        );
        
        $items = $wpdb->get_results($items_query, ARRAY_A);
        
        if ($items === null) {
            if ($this->logger) {
                $this->logger->error('Database error retrieving items for re-ranking', [
                    'set_id' => $set_id,
                    'error' => $wpdb->last_error
                ]);
            }
            return false;
        }
        
        if (!is_array($items) || empty($items)) {
            return true; // No items to rank
        }
        
        $updates_made = 0;
        $position = 1;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            foreach ($items as $item) {
                $new_tier = $this->get_tier_by_score($item['score']);
                $updates = [];
                
                // Update tier if changed
                if ($item['tier'] !== $new_tier) {
                    $updates['tier'] = $new_tier;
                    $updates_made++;
                }
                
                // Always update position based on current order
                $updates['position'] = $position++;
                
                // Update item if changes needed
                if (!empty($updates)) {
                    $result = $wpdb->update(
                        $items_table,
                        $updates,
                        ['id' => $item['id']],
                        array_fill(0, count($updates), '%s'),
                        ['%d']
                    );
                    
                    if ($result === false) {
                        throw new Exception("Failed to update item {$item['id']}");
                    }
                }
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            if ($this->logger) {
                $this->logger->info('Item re-ranking completed successfully', [
                    'set_id' => $set_id,
                    'items_processed' => count($items),
                    'tier_updates' => $updates_made
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');
            
            if ($this->logger) {
                $this->logger->error('Item re-ranking failed', [
                    'set_id' => $set_id,
                    'error' => $e->getMessage()
                ]);
            }
            
            return false;
        }
    }
    
    /**
     * Get tier statistics for a set
     *
     * @param int $set_id Master set ID
     * @return array Tier statistics
     */
    public function get_tier_statistics($set_id) {
        global $wpdb;
        
        $items_table = ZipPicks_Master_Critic_Database::get_items_table();
        
        $stats_query = $wpdb->prepare(
            "SELECT tier, COUNT(*) as count, AVG(score) as avg_score, 
                    MIN(score) as min_score, MAX(score) as max_score
             FROM {$items_table}
             WHERE set_id = %d AND status = 'active'
             GROUP BY tier
             ORDER BY 
                CASE tier
                    WHEN 'essential' THEN 1
                    WHEN 'notable' THEN 2  
                    WHEN 'worthy' THEN 3
                    WHEN 'honorable' THEN 4
                    WHEN 'unverified' THEN 5
                    ELSE 6
                END",
            $set_id
        );
        
        $results = $wpdb->get_results($stats_query, ARRAY_A);
        
        if ($results === null) {
            if ($this->logger) {
                $this->logger->error('Database error retrieving tier statistics', [
                    'set_id' => $set_id,
                    'error' => $wpdb->last_error
                ]);
            }
            return [];
        }
        
        // Ensure we return an array
        $results = is_array($results) ? $results : [];
        
        // Format the statistics
        $formatted_stats = [];
        foreach ($results as $stat) {
            $formatted_stats[] = [
                'tier' => $stat['tier'],
                'count' => (int) $stat['count'],
                'avg_score' => round((float) $stat['avg_score'], 1),
                'min_score' => (float) $stat['min_score'],
                'max_score' => (float) $stat['max_score'],
                'tier_boundary' => self::TIER_BOUNDARIES[$stat['tier']] ?? 0.0
            ];
        }
        
        return $formatted_stats;
    }
    
    /**
     * Sort items by score only (helper method)
     *
     * @param array $items Items to sort
     * @param string $order Sort order ('desc' or 'asc')
     * @return array Sorted items
     */
    private function sort_items_by_score($items, $order = 'desc') {
        if (!is_array($items)) {
            return [];
        }
        
        usort($items, function($a, $b) use ($order) {
            $score_a = (float) $a['score'];
            $score_b = (float) $b['score'];
            
            if ($order === 'asc') {
                return $score_a <=> $score_b;
            } else {
                return $score_b <=> $score_a; // desc (default)
            }
        });
        
        return $items;
    }
    
    /**
     * Validate tier assignment for all items in a set
     *
     * @param int $set_id Master set ID
     * @return array Validation results
     */
    public function validate_tier_assignments($set_id) {
        global $wpdb;
        
        $items_table = ZipPicks_Master_Critic_Database::get_items_table();
        
        $items_query = $wpdb->prepare(
            "SELECT id, business_name, score, tier FROM {$items_table}
             WHERE set_id = %d AND status = 'active'",
            $set_id
        );
        
        $items = $wpdb->get_results($items_query, ARRAY_A);
        
        if ($items === null) {
            return [
                'valid' => false,
                'error' => 'Database error: ' . $wpdb->last_error
            ];
        }
        
        if (!is_array($items)) {
            $items = [];
        }
        
        $validation_results = [
            'valid' => true,
            'total_items' => count($items),
            'mismatched_items' => [],
            'tier_counts' => []
        ];
        
        foreach ($items as $item) {
            $current_tier = $item['tier'];
            $expected_tier = $this->get_tier_by_score($item['score']);
            
            // Count items per tier
            if (!isset($validation_results['tier_counts'][$current_tier])) {
                $validation_results['tier_counts'][$current_tier] = 0;
            }
            $validation_results['tier_counts'][$current_tier]++;
            
            // Check for tier mismatches
            if ($current_tier !== $expected_tier) {
                $validation_results['valid'] = false;
                $validation_results['mismatched_items'][] = [
                    'id' => $item['id'],
                    'name' => $item['business_name'],
                    'score' => (float) $item['score'],
                    'current_tier' => $current_tier,
                    'expected_tier' => $expected_tier
                ];
            }
        }
        
        return $validation_results;
    }
}