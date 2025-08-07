<?php
/**
 * Core plugin class
 *
 * @package ZipPicks_Master_Critic
 */

class ZipPicks_Master_Critic {
    
    protected $plugin_name;
    protected $version;
    
    public function __construct() {
        $this->version = ZPMC_VERSION;
        $this->plugin_name = 'zippicks-master-critic';
        
        $this->load_dependencies();
        $this->define_hooks();
    }
    
    private function load_dependencies() {
        // Database
        require_once ZPMC_PLUGIN_DIR . 'includes/class-database.php';
        require_once ZPMC_PLUGIN_DIR . 'includes/class-database-migrator.php';
        
        // Import/Export
        require_once ZPMC_PLUGIN_DIR . 'includes/class-importer.php';
        
        // REST API
        require_once ZPMC_PLUGIN_DIR . 'includes/class-rest-controller.php';
        
        // Schema Integration
        require_once ZPMC_PLUGIN_DIR . 'includes/class-schema-integration.php';
        
        // Public (Frontend)
        require_once ZPMC_PLUGIN_DIR . 'public/class-public.php';
        
        // Admin
        require_once ZPMC_PLUGIN_DIR . 'admin/class-admin.php';
    }
    
    private function define_hooks() {
        // Initialize classes
        $plugin_admin = new ZipPicks_Master_Critic_Admin($this->get_plugin_name(), $this->get_version());
        $plugin_public = new ZipPicks_Master_Critic_Public($this->get_plugin_name(), $this->get_version());
        
        // Admin hooks
        add_action('admin_enqueue_scripts', [$plugin_admin, 'enqueue_styles']);
        add_action('admin_enqueue_scripts', [$plugin_admin, 'enqueue_scripts']);
        add_action('admin_menu', [$plugin_admin, 'add_admin_menu']);
        
        // AJAX handlers
        add_action('wp_ajax_zpmc_import_json', [$plugin_admin, 'ajax_import_json']);
        add_action('wp_ajax_zpmc_delete_set', [$plugin_admin, 'ajax_delete_set']);
        
        // Admin notices
        add_action('admin_notices', [$plugin_admin, 'display_admin_notices']);
        
        // Public hooks (shortcode and assets are registered in the Public class constructor)
        // No additional hooks needed here - the Public class handles its own initialization
        
        // REST API
        add_action('rest_api_init', [$this, 'init_rest_api']);
        
        // Schema integration
        $schema_integration = new ZipPicks_Master_Critic_Schema_Integration();
        add_filter('zippicks_schema_generated', [$schema_integration, 'enhance_schema'], 10, 2);
        add_filter('zippicks_master_list_schema', [$schema_integration, 'generate_list_schema'], 10, 2);
        add_action('wp_head', [$schema_integration, 'output_schema'], 20);
    }
    
    public function run() {
        // Plugin is initialized via constructor hooks
        // Nothing needed here anymore
    }
    
    /**
     * Initialize REST API endpoints
     */
    public function init_rest_api() {
        $controller = new ZipPicks_Master_Critic_REST_Controller();
        $controller->register_routes();
    }
    
    public function get_plugin_name() {
        return $this->plugin_name;
    }
    
    public function get_version() {
        return $this->version;
    }
}