<?php
/**
 * ZipPicks Core - Enterprise Cache Manager
 * 
 * Provides intelligent caching with multiple backend support:
 * - WordPress transients (default)
 * - Object cache (if available)
 * - Redis/Memcached (if configured)
 * 
 * @package ZipPicks\Core\Cache
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cache Manager class
 */
class ZipPicks_Cache {
    
    /**
     * Cache group prefix
     *
     * @var string
     */
    private $cache_group = 'zippicks';
    
    /**
     * Default expiration time (1 hour)
     *
     * @var int
     */
    private $default_expiration = 3600;
    
    /**
     * Cache statistics
     *
     * @var array
     */
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0
    ];
    
    /**
     * Whether object cache is available
     *
     * @var bool
     */
    private $has_object_cache = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize cache manager
     */
    private function init() {
        // Check if object cache is available
        $this->has_object_cache = wp_using_ext_object_cache();
        
        // Add cache clear hooks
        add_action('zippicks_clear_cache', [$this, 'clear_all']);
        add_action('switch_theme', [$this, 'clear_all']);
        add_action('activated_plugin', [$this, 'clear_all']);
        add_action('deactivated_plugin', [$this, 'clear_all']);
        
        // Add shutdown hook to save stats
        add_action('shutdown', [$this, 'save_stats']);
    }
    
    /**
     * Get cached value
     *
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @param string $group Cache group
     * @return mixed Cached value or default
     */
    public function get($key, $default = null, $group = null) {
        $cache_key = $this->build_key($key);
        $cache_group = $this->get_group($group);
        
        // Try object cache first
        if ($this->has_object_cache) {
            $value = wp_cache_get($cache_key, $cache_group);
            if ($value !== false) {
                $this->stats['hits']++;
                return $this->maybe_unserialize($value);
            }
        }
        
        // Fallback to transients
        $value = get_transient($cache_key);
        
        if ($value !== false) {
            $this->stats['hits']++;
            
            // If object cache is available, populate it
            if ($this->has_object_cache) {
                wp_cache_set($cache_key, $value, $cache_group, $this->default_expiration);
            }
            
            return $this->maybe_unserialize($value);
        }
        
        $this->stats['misses']++;
        return $default;
    }
    
    /**
     * Set cache value
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $expiration Expiration time in seconds
     * @param string $group Cache group
     * @return bool Success
     */
    public function set($key, $value, $expiration = null, $group = null) {
        $cache_key = $this->build_key($key);
        $cache_group = $this->get_group($group);
        $expiration = $expiration ?: $this->default_expiration;
        
        // Serialize if needed
        $value = $this->maybe_serialize($value);
        
        $this->stats['writes']++;
        
        // Set in object cache if available
        if ($this->has_object_cache) {
            $result = wp_cache_set($cache_key, $value, $cache_group, $expiration);
            
            // Also set transient as backup
            set_transient($cache_key, $value, $expiration);
            
            return $result;
        }
        
        // Use transients
        return set_transient($cache_key, $value, $expiration);
    }
    
    /**
     * Delete cached value
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool Success
     */
    public function delete($key, $group = null) {
        $cache_key = $this->build_key($key);
        $cache_group = $this->get_group($group);
        
        $this->stats['deletes']++;
        
        // Delete from object cache
        if ($this->has_object_cache) {
            wp_cache_delete($cache_key, $cache_group);
        }
        
        // Delete transient
        return delete_transient($cache_key);
    }
    
    /**
     * Check if cache key exists
     *
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool
     */
    public function exists($key, $group = null) {
        return $this->get($key, null, $group) !== null;
    }
    
    /**
     * Remember value with callback
     *
     * @param string $key Cache key
     * @param callable $callback Callback to generate value
     * @param int $expiration Expiration time
     * @param string $group Cache group
     * @return mixed
     */
    public function remember($key, $callback, $expiration = null, $group = null) {
        $value = $this->get($key, null, $group);
        
        if ($value === null) {
            $value = call_user_func($callback);
            $this->set($key, $value, $expiration, $group);
        }
        
        return $value;
    }
    
    /**
     * Increment numeric value
     *
     * @param string $key Cache key
     * @param int $offset Amount to increment
     * @param string $group Cache group
     * @return int|false New value or false on failure
     */
    public function increment($key, $offset = 1, $group = null) {
        $value = $this->get($key, 0, $group);
        
        if (!is_numeric($value)) {
            return false;
        }
        
        $new_value = intval($value) + intval($offset);
        $this->set($key, $new_value, null, $group);
        
        return $new_value;
    }
    
    /**
     * Decrement numeric value
     *
     * @param string $key Cache key
     * @param int $offset Amount to decrement
     * @param string $group Cache group
     * @return int|false New value or false on failure
     */
    public function decrement($key, $offset = 1, $group = null) {
        return $this->increment($key, -$offset, $group);
    }
    
    /**
     * Clear all cache
     *
     * @param string $group Specific group to clear
     * @return bool
     */
    public function clear_all($group = null) {
        global $wpdb;
        
        if ($group) {
            $cache_group = $this->get_group($group);
            
            // Clear object cache group
            if ($this->has_object_cache && function_exists('wp_cache_delete_group')) {
                wp_cache_delete_group($cache_group);
            }
            
            // Clear transients with prefix
            $prefix = $this->cache_group . '_' . $group . '_';
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $prefix . '%',
                '_transient_timeout_' . $prefix . '%'
            ));
        } else {
            // Clear all ZipPicks cache
            if ($this->has_object_cache) {
                wp_cache_flush();
            }
            
            // Clear all ZipPicks transients
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $this->cache_group . '_%',
                '_transient_timeout_' . $this->cache_group . '_%'
            ));
        }
        
        return true;
    }
    
    /**
     * Build cache key
     *
     * @param string $key
     * @return string
     */
    private function build_key($key) {
        // Ensure key is valid
        $key = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        
        // Add prefix
        return $this->cache_group . '_' . $key;
    }
    
    /**
     * Get cache group
     *
     * @param string|null $group
     * @return string
     */
    private function get_group($group = null) {
        return $group ? $this->cache_group . '_' . $group : $this->cache_group;
    }
    
    /**
     * Maybe serialize value
     *
     * @param mixed $value
     * @return mixed
     */
    private function maybe_serialize($value) {
        if (is_array($value) || is_object($value)) {
            return serialize($value);
        }
        return $value;
    }
    
    /**
     * Maybe unserialize value
     *
     * @param mixed $value
     * @return mixed
     */
    private function maybe_unserialize($value) {
        if (is_serialized($value)) {
            return unserialize($value);
        }
        return $value;
    }
    
    /**
     * Get cache statistics
     *
     * @return array
     */
    public function get_stats() {
        $saved_stats = get_transient('zippicks_cache_stats') ?: [];
        
        return array_merge($saved_stats, $this->stats);
    }
    
    /**
     * Save statistics
     */
    public function save_stats() {
        $saved_stats = get_transient('zippicks_cache_stats') ?: [
            'hits' => 0,
            'misses' => 0,
            'writes' => 0,
            'deletes' => 0
        ];
        
        // Merge with current stats
        foreach ($this->stats as $key => $value) {
            $saved_stats[$key] += $value;
        }
        
        // Save for 24 hours
        set_transient('zippicks_cache_stats', $saved_stats, DAY_IN_SECONDS);
    }
    
    /**
     * Get cache info
     *
     * @return array
     */
    public function get_info() {
        global $_wp_using_ext_object_cache;
        
        $info = [
            'backend' => 'transients',
            'object_cache' => $this->has_object_cache,
            'stats' => $this->get_stats(),
            'groups' => []
        ];
        
        if ($this->has_object_cache) {
            $info['backend'] = 'object_cache';
            
            // Try to detect specific backend
            if (function_exists('wp_cache_get_info')) {
                $cache_info = wp_cache_get_info();
                if (isset($cache_info['engine'])) {
                    $info['backend'] = $cache_info['engine'];
                }
            }
        }
        
        return $info;
    }
    
    /**
     * Preload cache values
     *
     * @param array $keys Array of cache keys
     * @param string $group Cache group
     * @param callable $generator Callback to generate values
     * @return array
     */
    public function preload($keys, $group = null, $generator = null) {
        $values = [];
        
        foreach ($keys as $key) {
            $value = $this->get($key, null, $group);
            
            if ($value === null && $generator) {
                $value = call_user_func($generator, $key);
                $this->set($key, $value, null, $group);
            }
            
            $values[$key] = $value;
        }
        
        return $values;
    }
}

/**
 * Get cache instance
 *
 * @return ZipPicks_Cache
 */
function zippicks_cache() {
    static $instance = null;
    
    if (null === $instance) {
        $instance = new ZipPicks_Cache();
    }
    
    return $instance;
}