<?php
/**
 * Database Migrator for Master Critic
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Database_Migrator {
    
    const CURRENT_SCHEMA_VERSION = '2.0.0';
    const VERSION_OPTION = 'zpmc_db_version';
    const LOCK_OPTION = 'zpmc_migration_lock';
    const LOCK_TIMEOUT = 300; // 5 minutes
    
    private static $cache = null;
    
    private static $migrations = [
        '1.0.0' => 'migrate_to_1_0_0',
        '2.0.0' => 'migrate_to_2_0_0'
    ];
    
    /**
     * Get cache service if available
     */
    private static function get_cache() {
        if (self::$cache === null) {
            if (function_exists('zippicks') && zippicks()->has('cache')) {
                self::$cache = zippicks()->get('cache');
            }
        }
        return self::$cache;
    }
    
    /**
     * Run all pending migrations
     */
    public static function run_migrations() {
        // Check for lock
        if (self::is_locked()) {
            return [
                'status' => 'locked',
                'message' => 'Another migration is in progress'
            ];
        }
        
        // Set lock
        self::set_lock();
        
        try {
            $current_version = get_option(self::VERSION_OPTION, '0.0.0');
            $target_version = self::CURRENT_SCHEMA_VERSION;
            
            if (version_compare($current_version, $target_version, '>=')) {
                self::release_lock();
                return [
                    'status' => 'up_to_date',
                    'message' => 'Database is already up to date',
                    'current_version' => $current_version
                ];
            }
            
            $migrations_run = [];
            $last_error = null;
            
            foreach (self::$migrations as $version => $method) {
                if (version_compare($current_version, $version, '<')) {
                    if (method_exists(__CLASS__, $method)) {
                        $result = self::$method();
                        
                        if ($result === true) {
                            $migrations_run[] = $version;
                            update_option(self::VERSION_OPTION, $version);
                            $current_version = $version;
                        } else {
                            $last_error = "Migration to version $version failed";
                            break;
                        }
                    }
                }
            }
            
            self::release_lock();
            
            if ($last_error) {
                return [
                    'status' => 'error',
                    'message' => $last_error,
                    'migrations_run' => $migrations_run
                ];
            }
            
            return [
                'status' => 'success',
                'message' => 'Migrations completed successfully',
                'migrations_run' => $migrations_run,
                'current_version' => $current_version,
                'target_version' => $target_version
            ];
            
        } catch (Exception $e) {
            self::release_lock();
            
            return [
                'status' => 'error',
                'message' => 'Migration failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Initial schema creation
     */
    private static function migrate_to_1_0_0() {
        global $wpdb;
        
        require_once ZPMC_PLUGIN_DIR . 'includes/class-database.php';
        
        // Create initial tables
        $result = ZipPicks_Master_Critic_Database::create_tables();
        
        if (!$result) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Version 2.0.0 migration - Add schema fields
     */
    private static function migrate_to_2_0_0() {
        global $wpdb;
        
        require_once ZPMC_PLUGIN_DIR . 'includes/class-database.php';
        
        // Add schema_config column to sets table if missing
        $sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
        $column_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW COLUMNS FROM %i LIKE 'schema_config'",
            $sets_table
        ));
        
        if (!$column_exists) {
            $wpdb->query($wpdb->prepare(
                "ALTER TABLE %i ADD COLUMN schema_config LONGTEXT AFTER metadata",
                $sets_table
            ));
        }
        
        // Add indexes for performance
        $wpdb->query($wpdb->prepare(
            "ALTER TABLE %i ADD INDEX idx_zip_category (zip_code, category)",
            $sets_table
        ));
        
        $items_table = ZipPicks_Master_Critic_Database::get_items_table();
        $wpdb->query($wpdb->prepare(
            "ALTER TABLE %i ADD INDEX idx_set_tier (set_id, tier)",
            $items_table
        ));
        
        return true;
    }
    
    /**
     * Check if migration is locked
     */
    private static function is_locked() {
        $cache = self::get_cache();
        if ($cache) {
            $lock = $cache->get(self::LOCK_OPTION);
            return !empty($lock);
        }
        
        // Fallback: No cache service available, assume not locked
        return false;
    }
    
    /**
     * Set migration lock
     */
    private static function set_lock() {
        $cache = self::get_cache();
        if ($cache) {
            $cache->set(self::LOCK_OPTION, current_time('timestamp'), self::LOCK_TIMEOUT);
        }
        // No fallback - if cache not available, migration continues without locking
    }
    
    /**
     * Release migration lock
     */
    private static function release_lock() {
        $cache = self::get_cache();
        if ($cache) {
            $cache->delete(self::LOCK_OPTION);
        }
        // No fallback - if cache not available, nothing to release
    }
    
    /**
     * Get migration status
     */
    public static function get_migration_status() {
        $current_version = get_option(self::VERSION_OPTION, '0.0.0');
        $target_version = self::CURRENT_SCHEMA_VERSION;
        
        return [
            'current_version' => $current_version,
            'target_version' => $target_version,
            'needs_migration' => version_compare($current_version, $target_version, '<'),
            'is_locked' => self::is_locked()
        ];
    }
    
    /**
     * Reset migrations (development only)
     */
    public static function reset_migrations() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return false;
        }
        
        delete_option(self::VERSION_OPTION);
        self::release_lock();
        
        return true;
    }
}