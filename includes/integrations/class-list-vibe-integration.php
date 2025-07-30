<?php
/**
 * List-Vibe Integration Service
 * 
 * Bridges Master Critic Top 10 lists with Vibes for seamless cross-plugin functionality
 * 
 * @package ZipPicks_Core
 * @subpackage Integrations
 * @since 1.0.0
 */

namespace ZipPicks\Core\Integrations;

use WP_Query;
use WP_Error;

/**
 * Class ListVibeIntegration
 * 
 * Provides methods to associate and query Top 10 lists by vibes
 */
class ListVibeIntegration {
    
    /**
     * Logger instance
     * 
     * @var object|null
     */
    private $logger;
    
    /**
     * Cache instance
     * 
     * @var object|null
     */
    private $cache;
    
    /**
     * Cache group identifier
     * 
     * @var string
     */
    private const CACHE_GROUP = 'zippicks_list_vibe';
    
    /**
     * Cache TTL (1 hour)
     * 
     * @var int
     */
    private const CACHE_TTL = 3600;
    
    /**
     * Constructor
     * 
     * @param object|null $logger Logger instance
     * @param object|null $cache Cache instance
     */
    public function __construct($logger = null, $cache = null) {
        $this->logger = $logger;
        $this->cache = $cache;
    }
    
    /**
     * Get all Top 10 lists associated with a specific vibe
     * 
     * @param int   $vibe_id The vibe ID to query
     * @param array $args    Query arguments
     * @return array Array of post objects
     */
    public function get_lists_by_vibe($vibe_id, $args = []) {
        // Validate input
        $vibe_id = absint($vibe_id);
        if (!$vibe_id) {
            $this->log_error('Invalid vibe ID provided', ['vibe_id' => $vibe_id]);
            return [];
        }
        
        // Check cache first
        $cache_key = $this->get_cache_key('lists_by_vibe', $vibe_id, $args);
        if ($this->cache) {
            $cached = $this->cache->get($cache_key, self::CACHE_GROUP);
            if ($cached !== false) {
                $this->log_debug('Retrieved lists from cache', ['vibe_id' => $vibe_id]);
                return $cached;
            }
        }
        
        // Default query arguments
        $defaults = [
            'post_type'      => 'master_critic_list',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'     => '_mc_vibe_ids',
                    'value'   => $vibe_id,
                    'compare' => 'LIKE',
                ],
            ],
        ];
        
        // Merge with provided arguments
        $query_args = wp_parse_args($args, $defaults);
        
        // Handle limit parameter
        if (isset($args['limit'])) {
            $query_args['posts_per_page'] = absint($args['limit']);
            unset($query_args['limit']);
        }
        
        // Handle location filter
        if (!empty($args['location'])) {
            $query_args['meta_query'][] = [
                'key'     => '_mc_location',
                'value'   => sanitize_text_field($args['location']),
                'compare' => '=',
            ];
        }
        
        // Execute query
        $query = new WP_Query($query_args);
        $lists = $query->posts;
        
        // Cache the results
        if ($this->cache && !empty($lists)) {
            $this->cache->set($cache_key, $lists, self::CACHE_GROUP, self::CACHE_TTL);
        }
        
        $this->log_info('Retrieved lists for vibe', [
            'vibe_id' => $vibe_id,
            'count'   => count($lists),
        ]);
        
        return $lists;
    }
    
    /**
     * Get all vibes associated with a specific list
     * 
     * @param int $list_id The list post ID
     * @return array Array of vibe IDs
     */
    public function get_vibes_by_list($list_id) {
        $list_id = absint($list_id);
        if (!$list_id) {
            return [];
        }
        
        $vibe_ids = get_post_meta($list_id, '_mc_vibe_ids', false);
        
        // Flatten and ensure integers
        if (is_array($vibe_ids)) {
            $flat_ids = [];
            foreach ($vibe_ids as $id_set) {
                if (is_array($id_set)) {
                    $flat_ids = array_merge($flat_ids, $id_set);
                } else {
                    $flat_ids[] = $id_set;
                }
            }
            return array_unique(array_map('absint', $flat_ids));
        }
        
        return [];
    }
    
    /**
     * Assign vibes to a Top 10 list
     * 
     * @param int   $list_id  The list post ID
     * @param array $vibe_ids Array of vibe IDs to assign
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function assign_vibes_to_list($list_id, $vibe_ids) {
        $list_id = absint($list_id);
        if (!$list_id) {
            return new WP_Error('invalid_list_id', 'Invalid list ID provided');
        }
        
        // Verify post exists and is correct type
        $post = get_post($list_id);
        if (!$post || $post->post_type !== 'master_critic_list') {
            return new WP_Error('invalid_post', 'Post does not exist or is not a master critic list');
        }
        
        // Sanitize vibe IDs
        $vibe_ids = array_unique(array_map('absint', (array) $vibe_ids));
        $vibe_ids = array_filter($vibe_ids);
        
        // Clear existing vibe associations
        delete_post_meta($list_id, '_mc_vibe_ids');
        
        // Add new associations
        $success = true;
        foreach ($vibe_ids as $vibe_id) {
            if (!add_post_meta($list_id, '_mc_vibe_ids', $vibe_id)) {
                $success = false;
            }
        }
        
        // Clear relevant caches
        $this->clear_list_caches($list_id);
        
        if ($success) {
            $this->log_info('Assigned vibes to list', [
                'list_id'  => $list_id,
                'vibe_ids' => $vibe_ids,
            ]);
            return true;
        }
        
        return new WP_Error('assignment_failed', 'Failed to assign some or all vibes');
    }
    
    /**
     * Remove a vibe association from a list
     * 
     * @param int $list_id The list post ID
     * @param int $vibe_id The vibe ID to remove
     * @return bool True on success, false on failure
     */
    public function remove_vibe_from_list($list_id, $vibe_id) {
        $list_id = absint($list_id);
        $vibe_id = absint($vibe_id);
        
        if (!$list_id || !$vibe_id) {
            return false;
        }
        
        $result = delete_post_meta($list_id, '_mc_vibe_ids', $vibe_id);
        
        if ($result) {
            $this->clear_list_caches($list_id);
            $this->log_info('Removed vibe from list', [
                'list_id' => $list_id,
                'vibe_id' => $vibe_id,
            ]);
        }
        
        return $result;
    }
    
    /**
     * Get lists grouped by vibe
     * 
     * @param array $vibe_ids Array of vibe IDs
     * @param array $args     Query arguments
     * @return array Associative array with vibe IDs as keys
     */
    public function get_lists_grouped_by_vibes($vibe_ids, $args = []) {
        $grouped = [];
        
        foreach ($vibe_ids as $vibe_id) {
            $vibe_id = absint($vibe_id);
            if ($vibe_id) {
                $grouped[$vibe_id] = $this->get_lists_by_vibe($vibe_id, $args);
            }
        }
        
        return $grouped;
    }
    
    /**
     * Get count of lists for a vibe
     * 
     * @param int $vibe_id The vibe ID
     * @return int Count of associated lists
     */
    public function get_list_count_for_vibe($vibe_id) {
        $vibe_id = absint($vibe_id);
        if (!$vibe_id) {
            return 0;
        }
        
        // Check cache first
        $cache_key = $this->get_cache_key('list_count', $vibe_id);
        if ($this->cache) {
            $count = $this->cache->get($cache_key, self::CACHE_GROUP);
            if ($count !== false) {
                return (int) $count;
            }
        }
        
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'master_critic_list'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_mc_vibe_ids'
            AND pm.meta_value = %d
        ", $vibe_id));
        
        $count = (int) $count;
        
        // Cache the result
        if ($this->cache) {
            $this->cache->set($cache_key, $count, self::CACHE_GROUP, self::CACHE_TTL);
        }
        
        return $count;
    }
    
    /**
     * Get popular vibes based on list count
     * 
     * @param int $limit Number of vibes to return
     * @return array Array of vibe IDs with counts
     */
    public function get_popular_vibes_by_lists($limit = 10) {
        global $wpdb;
        
        $limit = absint($limit);
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT pm.meta_value as vibe_id, COUNT(DISTINCT p.ID) as list_count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'master_critic_list'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_mc_vibe_ids'
            GROUP BY pm.meta_value
            ORDER BY list_count DESC
            LIMIT %d
        ", $limit));
        
        $popular = [];
        foreach ($results as $result) {
            $popular[] = [
                'vibe_id' => absint($result->vibe_id),
                'count'   => (int) $result->list_count,
            ];
        }
        
        return $popular;
    }
    
    /**
     * Clear caches for a specific list
     * 
     * @param int $list_id The list post ID
     */
    private function clear_list_caches($list_id) {
        if (!$this->cache) {
            return;
        }
        
        // Get associated vibes to clear their caches
        $vibe_ids = $this->get_vibes_by_list($list_id);
        
        foreach ($vibe_ids as $vibe_id) {
            // Clear list cache for this vibe
            $cache_patterns = [
                $this->get_cache_key('lists_by_vibe', $vibe_id, '*'),
                $this->get_cache_key('list_count', $vibe_id),
            ];
            
            foreach ($cache_patterns as $pattern) {
                if (method_exists($this->cache, 'delete_by_pattern')) {
                    $this->cache->delete_by_pattern($pattern, self::CACHE_GROUP);
                }
            }
        }
        
        // Clear general caches
        if (method_exists($this->cache, 'flush_group')) {
            $this->cache->flush_group(self::CACHE_GROUP);
        }
    }
    
    /**
     * Generate cache key
     * 
     * @param string $type    Cache type
     * @param mixed  ...$args Additional arguments
     * @return string Cache key
     */
    private function get_cache_key($type, ...$args) {
        $parts = array_merge([$type], $args);
        $serialized = array_map(function($part) {
            return is_array($part) ? md5(serialize($part)) : $part;
        }, $parts);
        
        return implode('_', $serialized);
    }
    
    /**
     * Log debug message
     * 
     * @param string $message Message to log
     * @param array  $context Additional context
     */
    private function log_debug($message, $context = []) {
        if ($this->logger && method_exists($this->logger, 'debug')) {
            $this->logger->debug('[ListVibeIntegration] ' . $message, $context);
        }
    }
    
    /**
     * Log info message
     * 
     * @param string $message Message to log
     * @param array  $context Additional context
     */
    private function log_info($message, $context = []) {
        if ($this->logger && method_exists($this->logger, 'info')) {
            $this->logger->info('[ListVibeIntegration] ' . $message, $context);
        }
    }
    
    /**
     * Log error message
     * 
     * @param string $message Message to log
     * @param array  $context Additional context
     */
    private function log_error($message, $context = []) {
        if ($this->logger && method_exists($this->logger, 'error')) {
            $this->logger->error('[ListVibeIntegration] ' . $message, $context);
        }
    }
}