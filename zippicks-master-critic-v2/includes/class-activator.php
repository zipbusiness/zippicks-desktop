<?php
/**
 * Plugin Activator
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Activator {
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Load dependencies
        require_once ZPMC_PLUGIN_DIR . 'includes/class-database.php';
        require_once ZPMC_PLUGIN_DIR . 'includes/class-database-migrator.php';
        
        // Run database migrations
        $migration_result = ZipPicks_Master_Critic_Database_Migrator::run_migrations();
        
        // If migrations failed, try direct table creation
        if ($migration_result['status'] !== 'success' && $migration_result['status'] !== 'up_to_date') {
            ZipPicks_Master_Critic_Database::create_tables();
        }
        
        // Set default options
        self::set_default_options();
        
        // Create required directories
        self::create_directories();
        
        // Register with Foundation if available
        self::register_with_foundation();
        
        // Schedule cron events
        self::schedule_events();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        update_option('zpmc_activated', true);
        update_option('zpmc_activation_time', current_time('mysql'));
    }
    
    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $defaults = [
            'zpmc_enable_schema' => true,
            'zpmc_items_per_page' => 20,
            'zpmc_enable_frontend' => true,
            'zpmc_default_status' => 'draft',
            'zpmc_require_verification' => false,
            'zpmc_enable_debug' => false
        ];
        
        // Note: Cache settings removed - now handled by Core cache service
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                update_option($option, $value);
            }
        }
    }
    
    /**
     * Create required directories
     */
    private static function create_directories() {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];
        
        $directories = [
            $base_dir . '/zippicks-master-critic',
            $base_dir . '/zippicks-master-critic/imports',
            $base_dir . '/zippicks-master-critic/exports',
            $base_dir . '/zippicks-master-critic/cache'
        ];
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                
                // Add .htaccess for security
                $htaccess = $dir . '/.htaccess';
                if (!file_exists($htaccess)) {
                    file_put_contents($htaccess, 'Deny from all');
                }
            }
        }
    }
    
    /**
     * Register with Foundation plugin
     */
    private static function register_with_foundation() {
        if (function_exists('zippicks') && zippicks()->has('database.installer')) {
            $installer = zippicks()->get('database.installer');
            $installer->register_schema(
                'master-critic-v2',
                function() {
                    return ZipPicks_Master_Critic_Database::get_schema_sql();
                },
                ZipPicks_Master_Critic_Database_Migrator::CURRENT_SCHEMA_VERSION
            );
        }
    }
    
    /**
     * Schedule cron events
     */
    private static function schedule_events() {
        // Schedule daily cleanup
        if (!wp_next_scheduled('zpmc_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'zpmc_daily_cleanup');
        }
        
        // Note: Cache management is now handled by Core cache service
        // No need to schedule cache purge - Core handles cache lifecycle
    }
    
    /**
     * Check if tables exist
     */
    public static function tables_exist() {
        return ZipPicks_Master_Critic_Database::verify_tables();
    }
}