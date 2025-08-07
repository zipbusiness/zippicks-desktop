<?php
/**
 * Plugin Name: ZipPicks Schema
 * Plugin URI: https://zippicks.com
 * Description: Provides Schema.org structured data generation and injection for the entire ZipPicks platform. Generates Google-compliant rich snippets for restaurants, businesses, lists, and custom ZipPicks extensions.
 * Version: 1.0.1
 * Author: ZipPicks
 * Author URI: https://zippicks.com
 * Text Domain: zippicks-schema
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: Proprietary
 * 
 * Changelog:
 * v1.0.1 (Production Ready)
 * - Fixed PHP 8.3 strict typing issues with cache validation
 * - Enhanced security with rate limiting and improved nonce checks
 * - Optimized database queries with proper caching
 * - Added comprehensive error handling for JSON encoding
 * - Improved performance for vibe lookups and business searches
 * - All code now enterprise-grade and production-ready
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ZIPPICKS_SCHEMA_VERSION', '1.0.1');
define('ZIPPICKS_SCHEMA_FILE', __FILE__);
define('ZIPPICKS_SCHEMA_PATH', plugin_dir_path(__FILE__));
define('ZIPPICKS_SCHEMA_URL', plugin_dir_url(__FILE__));
define('ZIPPICKS_SCHEMA_BASENAME', plugin_basename(__FILE__));

/**
 * Load required files with enterprise-grade error handling
 */
class ZipPicks_Schema_Loader {
    private static $required_files = [
        'includes/class-schema-types.php' => ['critical' => true, 'type' => 'core'],
        'includes/class-schema-generator.php' => ['critical' => true, 'type' => 'core'],
        'includes/class-schema-validator.php' => ['critical' => true, 'type' => 'validation'],
        'includes/class-schema-injector.php' => ['critical' => true, 'type' => 'injection'],
        'includes/class-schema-api.php' => ['critical' => false, 'type' => 'api'],
        'includes/class-master-set-schema.php' => ['critical' => false, 'type' => 'master-sets'],
    ];
    
    private static $load_errors = [];
    
    /**
     * Load all required files with proper error handling
     * 
     * @return bool True if all critical files loaded successfully
     */
    public static function load_dependencies() {
        $critical_failure = false;
        
        foreach (self::$required_files as $file => $config) {
            $file_path = ZIPPICKS_SCHEMA_PATH . $file;
            
            if (file_exists($file_path)) {
                try {
                    require_once $file_path;
                } catch (Exception $e) {
                    self::$load_errors[] = [
                        'file' => $file,
                        'type' => $config['type'],
                        'critical' => $config['critical'],
                        'error' => $e->getMessage()
                    ];
                    
                    if ($config['critical']) {
                        $critical_failure = true;
                    }
                    
                    self::log_load_error($file, $e);
                }
            } else {
                self::$load_errors[] = [
                    'file' => $file,
                    'type' => $config['type'],
                    'critical' => $config['critical'],
                    'error' => 'File not found'
                ];
                
                if ($config['critical']) {
                    $critical_failure = true;
                }
                
                self::log_missing_file($file, $config);
            }
        }
        
        // Handle critical failures
        if ($critical_failure) {
            self::handle_critical_failure();
            return false;
        }
        
        return true;
    }
    
    /**
     * Log missing file error
     */
    private static function log_missing_file($file, $config) {
        $error_message = sprintf(
            'ZipPicks Schema: Required %s file missing: %s',
            $config['type'],
            $file
        );
        
        error_log($error_message);
        
        // Store error for admin notice
        $errors = get_transient('zippicks_schema_load_errors') ?: [];
        $errors[] = [
            'time' => current_time('mysql'),
            'file' => $file,
            'type' => $config['type'],
            'critical' => $config['critical'],
            'message' => $error_message
        ];
        set_transient('zippicks_schema_load_errors', $errors, HOUR_IN_SECONDS);
    }
    
    /**
     * Log file load error
     */
    private static function log_load_error($file, $exception) {
        $error_message = sprintf(
            'ZipPicks Schema: Error loading file %s: %s',
            $file,
            $exception->getMessage()
        );
        
        error_log($error_message);
        error_log($exception->getTraceAsString());
    }
    
    /**
     * Handle critical failure
     */
    private static function handle_critical_failure() {
        add_action('admin_notices', function() {
            if (!current_user_can('manage_options')) {
                return;
            }
            
            $errors = self::$load_errors;
            $critical_errors = array_filter($errors, function($error) {
                return $error['critical'] === true;
            });
            
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e('ZipPicks Schema Critical Error:', 'zippicks-schema'); ?></strong>
                    <?php _e('The plugin cannot load due to missing critical files:', 'zippicks-schema'); ?>
                </p>
                <ul>
                    <?php foreach ($critical_errors as $error): ?>
                        <li>
                            <?php echo esc_html($error['file']); ?>
                            (<?php echo esc_html($error['type']); ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p>
                    <?php _e('Please reinstall the plugin or contact support.', 'zippicks-schema'); ?>
                </p>
            </div>
            <?php
        });
        
        // Prevent further initialization
        remove_action('plugins_loaded', 'zippicks_schema_init', 5);
    }
    
    /**
     * Get load errors for diagnostics
     */
    public static function get_load_errors() {
        return self::$load_errors;
    }
}

// Load dependencies using the loader
$load_success = ZipPicks_Schema_Loader::load_dependencies();

/**
 * Main plugin initialization class
 */
class ZipPicks_Schema_Plugin {
    private static $instance = null;
    private $generator = null;
    private $injector = null;
    private $api = null;
    private $validator = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        register_activation_hook(ZIPPICKS_SCHEMA_FILE, [$this, 'activate']);
        register_deactivation_hook(ZIPPICKS_SCHEMA_FILE, [$this, 'deactivate']);
    }
    
    /**
     * Initialize plugin components
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('zippicks-schema', false, dirname(plugin_basename(ZIPPICKS_SCHEMA_FILE)) . '/languages');
        
        // Initialize core components
        if (class_exists('ZipPicks_Schema_Generator')) {
            $this->generator = new ZipPicks_Schema_Generator();
        }
        
        if (class_exists('ZipPicks_Schema_Validator')) {
            $this->validator = new ZipPicks_Schema_Validator();
        }
        
        if (class_exists('ZipPicks_Schema_Injector')) {
            $this->injector = new ZipPicks_Schema_Injector($this->generator, $this->validator);
        }
        
        if (class_exists('ZipPicks_Schema_API')) {
            $this->api = new ZipPicks_Schema_API($this->generator, $this->validator);
        }
        
        // Initialize Master Set schema handler
        if (class_exists('ZipPicks_Master_Set_Schema')) {
            new ZipPicks_Master_Set_Schema($this->generator);
        }
        
        // Register with Core if available
        $this->register_with_core();
        
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }
    
    /**
     * Register services with ZipPicks Core if available
     */
    private function register_with_core() {
        if (function_exists('zippicks')) {
            try {
                // Register schema generator service
                zippicks()->bind('schema.generator', function() {
                    return $this->generator;
                });
                
                // Register schema validator service
                zippicks()->bind('schema.validator', function() {
                    return $this->validator;
                });
                
                // Register schema injector service
                zippicks()->bind('schema.injector', function() {
                    return $this->injector;
                });
                
                // Register schema API service
                zippicks()->bind('schema.api', function() {
                    return $this->api;
                });
                
                // Register schema plugin version
                zippicks()->bind('schema.version', ZIPPICKS_SCHEMA_VERSION);
                
            } catch (Exception $e) {
                error_log('ZipPicks Schema: Failed to register with Core - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Check if Core is active for menu location
        $parent_slug = function_exists('zippicks') && zippicks()->has('core.version') ? 'zippicks-system' : null;
        
        add_submenu_page(
            $parent_slug,
            __('Schema Manager', 'zippicks-schema'),
            __('Schema', 'zippicks-schema'),
            'manage_options',
            'zippicks-schema',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Render admin page
     */
    public function admin_page() {
        // Handle cache clear action
        if (isset($_GET['action']) && $_GET['action'] === 'clear-cache') {
            if (!current_user_can('manage_options')) {
                wp_die(__('Insufficient permissions'));
            }
            
            if (!wp_verify_nonce($_GET['_wpnonce'], 'clear_schema_cache')) {
                wp_die(__('Security check failed'));
            }
            
            // Clear all schema cache
            if ($this->generator) {
                $this->generator->clear_all_cache();
            }
            
            wp_redirect(admin_url('admin.php?page=zippicks-schema&cache-cleared=1'));
            exit;
        }
        
        // Show cache cleared notice
        if (isset($_GET['cache-cleared'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Schema cache has been cleared successfully!', 'zippicks-schema'); ?></p>
            </div>
            <?php
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('ZipPicks Schema Manager', 'zippicks-schema'); ?></h1>
            
            <div class="schema-admin-container">
                <!-- Cache Management -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Cache Management', 'zippicks-schema'); ?></h2>
                    <div class="inside">
                        <p><?php _e('Clear all cached schema data to force regeneration:', 'zippicks-schema'); ?></p>
                        <p>
                            <?php
                            $clear_cache_url = wp_nonce_url(
                                admin_url('admin.php?page=zippicks-schema&action=clear-cache'),
                                'clear_schema_cache'
                            );
                            ?>
                            <a href="<?php echo esc_url($clear_cache_url); ?>" 
                               class="button button-secondary"
                               onclick="return confirm('<?php _e('Are you sure you want to clear all schema cache? This will force regeneration on next page load.', 'zippicks-schema'); ?>')">
                                <?php _e('Clear All Schema Cache', 'zippicks-schema'); ?>
                            </a>
                        </p>
                        <p class="description">
                            <?php _e('Use this when schema data appears outdated or after updating business information.', 'zippicks-schema'); ?>
                        </p>
                    </div>
                </div>
                
                <!-- Schema Testing Tool -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Schema Testing', 'zippicks-schema'); ?></h2>
                    <div class="inside">
                        <form id="schema-test-form">
                            <p>
                                <label for="test-post-id"><?php _e('Post ID:', 'zippicks-schema'); ?></label>
                                <input type="number" id="test-post-id" name="post_id" min="1" required>
                                <button type="submit" class="button button-primary"><?php _e('Generate Schema', 'zippicks-schema'); ?></button>
                            </p>
                        </form>
                        <div id="schema-output"></div>
                    </div>
                </div>
                
                <!-- Schema Health Monitor -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Schema Health', 'zippicks-schema'); ?></h2>
                    <div class="inside">
                        <div id="schema-health-status">
                            <p><?php _e('Loading schema health status...', 'zippicks-schema'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Google Rich Results Preview -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Rich Results Preview', 'zippicks-schema'); ?></h2>
                    <div class="inside">
                        <p><?php _e('Test your schema with Google\'s Rich Results Test:', 'zippicks-schema'); ?></p>
                        <form id="rich-results-form">
                            <p>
                                <label for="test-url"><?php _e('Page URL:', 'zippicks-schema'); ?></label>
                                <input type="url" id="test-url" name="url" class="large-text" required>
                                <button type="submit" class="button"><?php _e('Test with Google', 'zippicks-schema'); ?></button>
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Schema is injected via JSON-LD, no frontend CSS/JS needed currently
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'zippicks-system_page_zippicks-schema' && $hook !== 'toplevel_page_zippicks-schema') {
            return;
        }
        
        wp_enqueue_script(
            'zippicks-schema-admin',
            ZIPPICKS_SCHEMA_URL . 'assets/js/schema-preview.js',
            ['jquery'],
            ZIPPICKS_SCHEMA_VERSION,
            true
        );
        
        wp_enqueue_style(
            'zippicks-schema-admin',
            ZIPPICKS_SCHEMA_URL . 'assets/css/admin.css',
            [],
            ZIPPICKS_SCHEMA_VERSION
        );
        
        // Localize script for AJAX
        wp_localize_script('zippicks-schema-admin', 'zipPicksSchema', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zippicks_schema_admin'),
            'strings' => [
                'error' => __('Error occurred', 'zippicks-schema'),
                'loading' => __('Loading...', 'zippicks-schema'),
                'validSchema' => __('Valid Schema', 'zippicks-schema'),
                'invalidSchema' => __('Invalid Schema', 'zippicks-schema'),
            ]
        ]);
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Initialize plugin options
        add_option('zippicks_schema_version', ZIPPICKS_SCHEMA_VERSION);
        add_option('zippicks_schema_activated', time());
        
        // Set default options
        $default_options = [
            'enable_business_schema' => true,
            'enable_list_schema' => true,
            'enable_review_schema' => true,
            'enable_organization_schema' => true,
            'cache_schema_duration' => DAY_IN_SECONDS,
            'enable_debug_mode' => false,
        ];
        
        foreach ($default_options as $option => $value) {
            add_option('zippicks_schema_' . $option, $value);
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log activation
        error_log('ZipPicks Schema: Plugin activated');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up transients
        delete_transient('zippicks_schema_cache');
        delete_transient('zippicks_schema_health');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        error_log('ZipPicks Schema: Plugin deactivated');
    }
    
    /**
     * Get generator instance
     */
    public function get_generator() {
        return $this->generator;
    }
    
    /**
     * Get validator instance
     */
    public function get_validator() {
        return $this->validator;
    }
    
    /**
     * Get injector instance
     */
    public function get_injector() {
        return $this->injector;
    }
    
    /**
     * Get API instance
     */
    public function get_api() {
        return $this->api;
    }
}

/**
 * Initialize the plugin
 */
function zippicks_schema_init() {
    // Only initialize if dependencies loaded successfully
    $load_errors = ZipPicks_Schema_Loader::get_load_errors();
    $has_critical_errors = array_filter($load_errors, function($error) {
        return isset($error['critical']) && $error['critical'] === true;
    });
    
    if (!empty($has_critical_errors)) {
        return;
    }
    
    ZipPicks_Schema_Plugin::get_instance();
}
add_action('plugins_loaded', 'zippicks_schema_init', 5);

/**
 * Global access function
 */
function zippicks_schema() {
    return ZipPicks_Schema_Plugin::get_instance();
}

/**
 * Admin notices
 */
function zippicks_schema_admin_notices() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check for load errors
    $load_errors = ZipPicks_Schema_Loader::get_load_errors();
    if (!empty($load_errors)) {
        $non_critical_errors = array_filter($load_errors, function($error) {
            return !isset($error['critical']) || $error['critical'] === false;
        });
        
        if (!empty($non_critical_errors)) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php _e('ZipPicks Schema Warning:', 'zippicks-schema'); ?></strong>
                    <?php _e('Some optional components could not be loaded:', 'zippicks-schema'); ?>
                </p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <?php foreach ($non_critical_errors as $error): ?>
                        <li>
                            <code><?php echo esc_html($error['file']); ?></code>
                            (<?php echo esc_html($error['type']); ?>)
                            <?php if (isset($error['error'])): ?>
                                - <?php echo esc_html($error['error']); ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
        }
    }
}
add_action('admin_notices', 'zippicks_schema_admin_notices');