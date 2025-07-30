<?php
/**
 * ZipPicks Core - Function Compatibility Layer
 * 
 * This file ensures proper function loading order and prevents conflicts
 * between theme and plugin function declarations.
 * 
 * @package ZipPicks\Core\Compatibility
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize compatibility layer
 * Runs early to establish function precedence
 */
add_action('after_setup_theme', 'zippicks_core_init_compatibility', 1);

function zippicks_core_init_compatibility() {
    // Load core compatibility functions
    zippicks_core_load_compatibility_functions();
    
    // Set up function aliases if needed
    zippicks_core_setup_function_aliases();
}

/**
 * Load compatibility functions with proper checks
 */
function zippicks_core_load_compatibility_functions() {
    // Define the primary plugin check function if not exists
    if (!function_exists('zippicks_is_plugin_active')) {
        /**
         * Check if a ZipPicks plugin is active
         * 
         * This is the primary implementation that handles all plugin checks.
         * It uses multiple methods for maximum compatibility:
         * 1. Constant checks for known plugins (fastest)
         * 2. Function existence checks
         * 3. Active plugins array check (fallback)
         * 
         * @param string $plugin Plugin identifier (e.g., 'core', 'foundation', 'vibes')
         * @return bool True if plugin is active, false otherwise
         */
        function zippicks_is_plugin_active($plugin) {
            // Use constant checks for known plugins (fastest method)
            $constant_checks = [
                'core' => 'ZIPPICKS_CORE_VERSION',
                'foundation' => 'ZIPPICKS_FOUNDATION_VERSION',
                'vibes' => 'ZIPPICKS_VIBES_VERSION',
                'monetization' => 'ZIPPICKS_MONETIZATION_VERSION',
                'email' => 'ZIPPICKS_EMAIL_VERSION',
                'api' => 'ZIPPICKS_API_VERSION',
                'analytics' => 'ZIPPICKS_ANALYTICS_VERSION',
            ];
            
            if (isset($constant_checks[$plugin]) && defined($constant_checks[$plugin])) {
                return true;
            }
            
            // Function existence checks for foundation
            if ($plugin === 'foundation' && function_exists('zippicks')) {
                return true;
            }
            
            // Fallback to checking active plugins array
            if (!function_exists('get_option')) {
                return false; // Too early in load process
            }
            
            $active_plugins = get_option('active_plugins', []);
            $plugin_file = 'zippicks-' . $plugin . '/zippicks-' . $plugin . '.php';
            
            // Check both possible plugin file patterns
            foreach ($active_plugins as $active_plugin) {
                if ($active_plugin === $plugin_file || 
                    strpos($active_plugin, 'zippicks-' . $plugin) !== false) {
                    return true;
                }
            }
            
            // Check multisite network activated plugins
            if (is_multisite()) {
                $network_plugins = get_site_option('active_sitewide_plugins', []);
                if (isset($network_plugins[$plugin_file])) {
                    return true;
                }
            }
            
            return false;
        }
    }
    
    // Define additional compatibility functions
    if (!function_exists('zippicks_get_plugin_version')) {
        /**
         * Get the version of a ZipPicks plugin
         * 
         * @param string $plugin Plugin identifier
         * @return string|false Version string or false if not active
         */
        function zippicks_get_plugin_version($plugin) {
            if (!zippicks_is_plugin_active($plugin)) {
                return false;
            }
            
            $version_constants = [
                'core' => 'ZIPPICKS_CORE_VERSION',
                'foundation' => 'ZIPPICKS_FOUNDATION_VERSION',
                'vibes' => 'ZIPPICKS_VIBES_VERSION',
                'monetization' => 'ZIPPICKS_MONETIZATION_VERSION',
                'email' => 'ZIPPICKS_EMAIL_VERSION',
                'api' => 'ZIPPICKS_API_VERSION',
                'analytics' => 'ZIPPICKS_ANALYTICS_VERSION',
            ];
            
            if (isset($version_constants[$plugin]) && defined($version_constants[$plugin])) {
                return constant($version_constants[$plugin]);
            }
            
            return '1.0.0'; // Default version if constant not found
        }
    }
    
    // Define plugin dependency checker
    if (!function_exists('zippicks_check_plugin_dependencies')) {
        /**
         * Check if all required plugin dependencies are met
         * 
         * @param array $required_plugins Array of required plugin identifiers
         * @return bool|array True if all dependencies met, array of missing plugins otherwise
         */
        function zippicks_check_plugin_dependencies($required_plugins) {
            $missing = [];
            
            foreach ($required_plugins as $plugin) {
                if (!zippicks_is_plugin_active($plugin)) {
                    $missing[] = $plugin;
                }
            }
            
            return empty($missing) ? true : $missing;
        }
    }
}

/**
 * Set up function aliases for backward compatibility
 */
function zippicks_core_setup_function_aliases() {
    // No function aliases needed - WordPress core already provides is_plugin_active()
    // Our custom function is zippicks_is_plugin_active() to avoid conflicts
}

/**
 * Log function conflicts for debugging (enterprise feature)
 */
if (!function_exists('zippicks_log_function_conflict')) {
    /**
     * Log function redeclaration attempts for debugging
     * 
     * @param string $function_name Function that caused conflict
     * @param string $file File where conflict occurred
     * @param int $line Line number of conflict
     */
    function zippicks_log_function_conflict($function_name, $file, $line) {
        if (!defined('ZIPPICKS_DEBUG') || !ZIPPICKS_DEBUG) {
            return;
        }
        
        $logger = null;
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
        }
        
        $message = sprintf(
            'Function conflict detected: %s already declared. Attempted redeclaration in %s on line %d',
            $function_name,
            $file,
            $line
        );
        
        if ($logger) {
            $logger->warning($message, [
                'function' => $function_name,
                'file' => $file,
                'line' => $line,
                'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
            ]);
        } else {
            error_log('[ZipPicks] ' . $message);
        }
    }
}