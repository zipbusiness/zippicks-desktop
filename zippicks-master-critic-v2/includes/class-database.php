<?php
/**
 * Database handler for Master Critic
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Database {
    
    // Table name constants
    const TABLE_MASTER_SETS = 'zippicks_master_sets';
    const TABLE_MASTER_ITEMS = 'zippicks_master_items';
    const TABLE_MASTER_META = 'zippicks_master_meta';
    
    /**
     * Get master sets table name
     */
    public static function get_sets_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_MASTER_SETS;
    }
    
    /**
     * Get master items table name
     */
    public static function get_items_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_MASTER_ITEMS;
    }
    
    /**
     * Get master meta table name
     */
    public static function get_meta_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_MASTER_META;
    }
    
    /**
     * Create all tables
     */
    public static function create_tables() {
        global $wpdb;
        
        // Use Core logger if available
        $logger = null;
        if (function_exists('zippicks') && zippicks()->has('core.logger')) {
            $logger = zippicks()->get('core.logger');
            $logger->info('Creating Master Critic database tables');
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = [];
        
        // Master Sets table
        $sql[] = self::get_sets_table_sql();
        
        // Master Items table
        $sql[] = self::get_items_table_sql();
        
        // Master Meta table
        $sql[] = self::get_meta_table_sql();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($sql as $query) {
            $result = dbDelta($query);
            if ($logger && !empty($wpdb->last_error)) {
                $logger->error('Database table creation error', ['error' => $wpdb->last_error]);
            }
        }
        
        // Verify tables were created
        $success = self::verify_tables();
        
        if ($logger) {
            if ($success) {
                $logger->info('Master Critic database tables created successfully');
            } else {
                $logger->error('Failed to create Master Critic database tables');
            }
        }
        
        return $success;
    }
    
    /**
     * Get SQL for sets table
     */
    public static function get_sets_table_sql() {
        global $wpdb;
        $table_name = self::get_sets_table();
        $charset_collate = $wpdb->get_charset_collate();
        
        return "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            set_name varchar(255) NOT NULL,
            set_slug varchar(255) NOT NULL,
            zip_code varchar(10) NOT NULL,
            category varchar(100) NOT NULL,
            vertical varchar(100) DEFAULT 'Restaurants',
            radius int(11) DEFAULT 5,
            total_items int(11) DEFAULT 0,
            metadata longtext,
            schema_config longtext,
            import_source varchar(50) DEFAULT 'manual',
            status varchar(20) DEFAULT 'draft',
            created_by bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY set_slug (set_slug),
            KEY zip_code (zip_code),
            KEY category (category),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
    }
    
    /**
     * Get SQL for items table
     */
    public static function get_items_table_sql() {
        global $wpdb;
        $table_name = self::get_items_table();
        $charset_collate = $wpdb->get_charset_collate();
        
        return "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            set_id bigint(20) UNSIGNED NOT NULL,
            business_name varchar(255) NOT NULL,
            business_slug varchar(255) NOT NULL,
            score decimal(3,1) NOT NULL,
            tier varchar(20) NOT NULL,
            price_tier varchar(10),
            neighborhood varchar(100),
            address varchar(255),
            phone varchar(20),
            summary text,
            top_dishes text,
            pillar_scores text,
            vibes text,
            schema_payload longtext,
            verified tinyint(1) DEFAULT 0,
            business_id varchar(50),
            position int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY set_id (set_id),
            KEY business_slug (business_slug),
            KEY score (score),
            KEY tier (tier),
            KEY position (position),
            KEY status (status)
        ) $charset_collate;";
    }
    
    /**
     * Get SQL for meta table
     */
    public static function get_meta_table_sql() {
        global $wpdb;
        $table_name = self::get_meta_table();
        $charset_collate = $wpdb->get_charset_collate();
        
        return "CREATE TABLE $table_name (
            meta_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            set_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            item_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
            meta_key varchar(255) DEFAULT NULL,
            meta_value longtext,
            PRIMARY KEY (meta_id),
            KEY set_id (set_id),
            KEY item_id (item_id),
            KEY meta_key (meta_key(191))
        ) $charset_collate;";
    }
    
    /**
     * Verify all tables exist
     */
    public static function verify_tables() {
        global $wpdb;
        
        $tables = [
            self::get_sets_table(),
            self::get_items_table(),
            self::get_meta_table()
        ];
        
        foreach ($tables as $table) {
            $result = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            ));
            
            if ($result !== $table) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Drop all tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = [
            self::get_sets_table(),
            self::get_items_table(),
            self::get_meta_table()
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }
    
    /**
     * Get schema for Foundation registration
     */
    public static function get_schema_sql() {
        return [
            'master_sets' => self::get_sets_table_sql(),
            'master_items' => self::get_items_table_sql(),
            'master_meta' => self::get_meta_table_sql()
        ];
    }
}