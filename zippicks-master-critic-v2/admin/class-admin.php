<?php
/**
 * Admin functionality
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic_Admin {
    
    private $plugin_name;
    private $version;
    private $logger = null;
    private $cache = null;
    
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
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
     * Register admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            'Master Critic',
            'Master Critic',
            'manage_options',
            'zpmc-dashboard',
            [$this, 'display_dashboard_page'],
            'dashicons-star-filled',
            30
        );
        
        // Dashboard (rename first submenu)
        add_submenu_page(
            'zpmc-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'zpmc-dashboard',
            [$this, 'display_dashboard_page']
        );
        
        // Import
        add_submenu_page(
            'zpmc-dashboard',
            'Import JSON',
            'Import',
            'manage_options',
            'zpmc-import',
            [$this, 'display_import_page']
        );
        
        // All Sets
        add_submenu_page(
            'zpmc-dashboard',
            'Master Sets',
            'All Sets',
            'manage_options',
            'zpmc-sets',
            [$this, 'display_sets_page']
        );
        
        // Settings
        add_submenu_page(
            'zpmc-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'zpmc-settings',
            [$this, 'display_settings_page']
        );
    }
    
    /**
     * Display dashboard page
     */
    public function display_dashboard_page() {
        global $wpdb;
        
        // Get statistics
        $sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
        $items_table = ZipPicks_Master_Critic_Database::get_items_table();
        
        $total_sets = $wpdb->get_var("SELECT COUNT(*) FROM $sets_table");
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $items_table");
        $published_sets = $wpdb->get_var("SELECT COUNT(*) FROM $sets_table WHERE status = 'published'");
        $draft_sets = $wpdb->get_var("SELECT COUNT(*) FROM $sets_table WHERE status = 'draft'");
        
        // Recent imports
        $recent_sets = $wpdb->get_results(
            "SELECT * FROM $sets_table ORDER BY created_at DESC LIMIT 5"
        );
        
        include ZPMC_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    /**
     * Display import page
     */
    public function display_import_page() {
        include ZPMC_PLUGIN_DIR . 'admin/views/import-page.php';
    }
    
    /**
     * Display sets page
     */
    public function display_sets_page() {
        global $wpdb;
        
        $sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
        
        // Handle actions
        if (isset($_GET['action'])) {
            $this->handle_set_action($_GET['action'], $_GET['set_id'] ?? 0);
        }
        
        // Get all sets
        $sets = $wpdb->get_results(
            "SELECT s.*, 
                    (SELECT COUNT(*) FROM " . ZipPicks_Master_Critic_Database::get_items_table() . " WHERE set_id = s.id) as item_count
             FROM $sets_table s 
             ORDER BY s.created_at DESC"
        );
        
        include ZPMC_PLUGIN_DIR . 'admin/views/sets-page.php';
    }
    
    /**
     * Display settings page
     */
    public function display_settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        include ZPMC_PLUGIN_DIR . 'admin/views/settings-page.php';
    }
    
    /**
     * AJAX handler for JSON import
     */
    public function ajax_import_json() {
        // Check nonce
        if (!check_ajax_referer('zpmc_import_nonce', 'nonce', false)) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Check file upload
        if (empty($_FILES['import_file'])) {
            wp_send_json_error(['message' => 'No file uploaded']);
        }
        
        // Import the file
        require_once ZPMC_PLUGIN_DIR . 'includes/class-importer.php';
        $importer = new ZipPicks_Master_Critic_Importer();
        $result = $importer->import_from_file($_FILES['import_file']);
        
        if ($this->logger) {
            if ($result['success']) {
                $this->logger->info('Admin JSON import successful', ['file' => $_FILES['import_file']['name']]);
            } else {
                $this->logger->error('Admin JSON import failed', ['file' => $_FILES['import_file']['name'], 'error' => $result['message']]);
            }
        }
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX handler for deleting a set
     */
    public function ajax_delete_set() {
        // Check nonce
        if (!check_ajax_referer('zpmc_delete_nonce', 'nonce', false)) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $set_id = intval($_POST['set_id'] ?? 0);
        
        if (!$set_id) {
            wp_send_json_error(['message' => 'Invalid set ID']);
        }
        
        global $wpdb;
        
        // Delete items first
        $wpdb->delete(
            ZipPicks_Master_Critic_Database::get_items_table(),
            ['set_id' => $set_id]
        );
        
        // Delete meta
        $wpdb->delete(
            ZipPicks_Master_Critic_Database::get_meta_table(),
            ['set_id' => $set_id]
        );
        
        // Delete set
        $result = $wpdb->delete(
            ZipPicks_Master_Critic_Database::get_sets_table(),
            ['id' => $set_id]
        );
        
        if ($result) {
            wp_send_json_success(['message' => 'Set deleted successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete set']);
        }
    }
    
    /**
     * Handle set actions
     */
    private function handle_set_action($action, $set_id) {
        global $wpdb;
        
        $set_id = intval($set_id);
        if (!$set_id) return;
        
        $sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
        
        switch ($action) {
            case 'publish':
                $wpdb->update(
                    $sets_table,
                    ['status' => 'published'],
                    ['id' => $set_id]
                );
                break;
                
            case 'draft':
                $wpdb->update(
                    $sets_table,
                    ['status' => 'draft'],
                    ['id' => $set_id]
                );
                break;
                
            case 'delete':
                if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
                    // Delete items
                    $wpdb->delete(
                        ZipPicks_Master_Critic_Database::get_items_table(),
                        ['set_id' => $set_id]
                    );
                    
                    // Delete set
                    $wpdb->delete(
                        $sets_table,
                        ['id' => $set_id]
                    );
                }
                break;
        }
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        // Verify nonce
        if (!isset($_POST['zpmc_settings_nonce']) || 
            !wp_verify_nonce($_POST['zpmc_settings_nonce'], 'zpmc_settings')) {
            return;
        }
        
        // Save settings (cache settings removed - now handled by Core)
        $settings = [
            'zpmc_enable_schema',
            'zpmc_items_per_page',
            'zpmc_enable_frontend',
            'zpmc_default_status',
            'zpmc_require_verification',
            'zpmc_enable_debug'
        ];
        
        foreach ($settings as $setting) {
            if (isset($_POST[$setting])) {
                update_option($setting, sanitize_text_field($_POST[$setting]));
            }
        }
        
        // Add success message
        add_settings_error(
            'zpmc_settings',
            'settings_updated',
            'Settings saved successfully',
            'success'
        );
    }
    
    /**
     * Enqueue admin styles
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            ZPMC_PLUGIN_URL . 'admin/css/admin.css',
            [],
            $this->version,
            'all'
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            ZPMC_PLUGIN_URL . 'admin/js/admin.js',
            ['jquery'],
            $this->version,
            false
        );
        
        wp_localize_script($this->plugin_name, 'zpmc_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'import_nonce' => wp_create_nonce('zpmc_import_nonce'),
            'delete_nonce' => wp_create_nonce('zpmc_delete_nonce')
        ]);
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        // Check if tables exist
        if (!ZipPicks_Master_Critic_Database::verify_tables()) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong>Master Critic:</strong>
                    Database tables are missing. Please deactivate and reactivate the plugin.
                </p>
            </div>
            <?php
        }
    }
}