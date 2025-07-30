<?php
/**
 * Plugin Name: ZipPicks Core
 * Plugin URI: https://zippicks.com
 * Description: Infrastructure foundation for the ZipPicks ecosystem. Provides system-wide helpers, logging, shared UI components, and plugin coordination.
 * Version: 1.0.0
 * Author: ZipPicks
 * Author URI: https://zippicks.com
 * Text Domain: zippicks-core
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: Proprietary
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ZIPPICKS_CORE_VERSION', '1.0.0');
define('ZIPPICKS_CORE_FILE', __FILE__);
define('ZIPPICKS_CORE_PATH', plugin_dir_path(__FILE__));
define('ZIPPICKS_CORE_URL', plugin_dir_url(__FILE__));
define('ZIPPICKS_CORE_BASENAME', plugin_basename(__FILE__));

// Load compatibility layer first to prevent conflicts
require_once ZIPPICKS_CORE_PATH . 'includes/compatibility/functions-compatibility.php';

// Load Composer autoloader for JWT library
if (file_exists(ZIPPICKS_CORE_PATH . 'vendor/autoload.php')) {
    require_once ZIPPICKS_CORE_PATH . 'vendor/autoload.php';
}

// Load dependencies
require_once ZIPPICKS_CORE_PATH . 'includes/class-core-init.php';
require_once ZIPPICKS_CORE_PATH . 'includes/helpers/functions-global.php';
require_once ZIPPICKS_CORE_PATH . 'includes/logging/logger.php';
require_once ZIPPICKS_CORE_PATH . 'includes/class-error-handler.php';
require_once ZIPPICKS_CORE_PATH . 'includes/class-security.php';
require_once ZIPPICKS_CORE_PATH . 'includes/class-cache.php';

// Load auth service
require_once ZIPPICKS_CORE_PATH . 'includes/auth/class-auth-init.php';

// Load integrations
require_once ZIPPICKS_CORE_PATH . 'includes/integrations/class-list-vibe-integration.php';

/**
 * Plugin activation hook
 */
function zippicks_core_activate() {
    // Initialize plugin options
    add_option('zippicks_core_version', ZIPPICKS_CORE_VERSION);
    add_option('zippicks_core_activated', time());
    
    // Create log directory if needed
    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/zippicks-logs';
    
    if (!file_exists($log_dir)) {
        wp_mkdir_p($log_dir);
        
        // Add .htaccess to protect logs
        $htaccess_content = "Order Allow,Deny\nDeny from all";
        file_put_contents($log_dir . '/.htaccess', $htaccess_content);
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Log activation
    if (class_exists('ZipPicks_Logger')) {
        $logger = new ZipPicks_Logger();
        $logger->log_performance('plugin_activation', 0);
    }
}
register_activation_hook(__FILE__, 'zippicks_core_activate');

/**
 * Plugin deactivation hook
 */
function zippicks_core_deactivate() {
    // Clean up transients
    delete_transient('zippicks_core_notices');
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Log deactivation
    if (class_exists('ZipPicks_Logger')) {
        $logger = new ZipPicks_Logger();
        $logger->log_performance('plugin_deactivation', 0);
    }
}
register_deactivation_hook(__FILE__, 'zippicks_core_deactivate');

/**
 * Initialize the plugin
 */
function zippicks_core_init() {
    // Load text domain
    load_plugin_textdomain('zippicks-core', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Initialize core functionality
    $core = new ZipPicks_Core_Init();
    $core->init();
    
    // Initialize auth service
    \ZipPicks\Core\Auth\Auth_Init::init();
    
    // Register with Foundation if available
    if (function_exists('zippicks')) {
        try {
            // Register core services
            zippicks()->bind('core.logger', function() {
                return new ZipPicks_Logger();
            });
            
            // Register security service
            zippicks()->bind('core.security', function() {
                return ZipPicks_Security::get_instance();
            });
            
            // Register cache service
            zippicks()->bind('core.cache', function() {
                return zippicks_cache();
            });
            
            // Register error handler
            zippicks()->bind('core.error_handler', function() {
                return new ZipPicks_Error_Handler();
            });
            
            // Register List-Vibe integration service
            zippicks()->singleton('list_vibe.integration', function() {
                $logger = zippicks()->get('core.logger');
                $cache = zippicks()->get('core.cache');
                return new \ZipPicks\Core\Integrations\ListVibeIntegration($logger, $cache);
            });
            
            // Register core version
            zippicks()->bind('core.version', ZIPPICKS_CORE_VERSION);
            
            // Register core path
            zippicks()->bind('core.path', ZIPPICKS_CORE_PATH);
            
        } catch (Exception $e) {
            // Log error if logger is available
            if (class_exists('ZipPicks_Logger')) {
                $logger = new ZipPicks_Logger();
                $logger->log_error('Failed to register with Foundation', ['error' => $e->getMessage()]);
            }
        }
    }
}
add_action('plugins_loaded', 'zippicks_core_init', 5);

/**
 * Admin notices
 */
function zippicks_core_admin_notices() {
    // Check if Foundation is active
    if (!function_exists('zippicks')) {
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong><?php _e('ZipPicks Core:', 'zippicks-core'); ?></strong>
                <?php _e('The ZipPicks Foundation plugin is recommended for enhanced functionality.', 'zippicks-core'); ?>
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'zippicks_core_admin_notices');

/**
 * Handle clear errors action
 */
function zippicks_core_handle_clear_errors() {
    // Check nonce and permissions
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'clear_errors')) {
        wp_die('Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions');
    }
    
    // Clear the errors
    delete_transient('zippicks_critical_errors');
    
    // Redirect back to error log page
    wp_redirect(admin_url('admin.php?page=zippicks-error-log&cleared=1'));
    exit;
}
add_action('admin_post_zippicks_clear_errors', 'zippicks_core_handle_clear_errors');