<?php
/**
 * Plugin Deactivator
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Deactivator {
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('zpmc_daily_cleanup');
        wp_clear_scheduled_hook('zpmc_cache_purge');
        
        // Clear cached data using Core cache service if available
        self::clear_plugin_cache();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set deactivation flag
        update_option('zpmc_deactivated', true);
        update_option('zpmc_deactivation_time', current_time('mysql'));
    }
    
    /**
     * Clear plugin cache data
     */
    private static function clear_plugin_cache() {
        // Use Core cache service if available
        if (function_exists('zippicks') && zippicks()->has('cache')) {
            $cache = zippicks()->get('cache');
            
            // Clear common cache keys used by the plugin
            $cache_keys = [
                'master_sets_list_1_20',
                'zpmc_migration_lock'
            ];
            
            foreach ($cache_keys as $key) {
                $cache->delete($key);
            }
            
            // Clear sets cache (example pattern - would need specific set IDs in real cleanup)
            for ($i = 1; $i <= 100; $i++) {
                $cache->delete("master_set_{$i}_with_items");
                $cache->delete("master_set_{$i}_no_items");
            }
        }
        
        // Legacy cleanup for any remaining transients (fallback)
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_zpmc_%' 
             OR option_name LIKE '_transient_timeout_zpmc_%'"
        );
    }
}