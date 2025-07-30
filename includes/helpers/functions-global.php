<?php
/**
 * Global helper functions
 *
 * @package ZipPicks\Core
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get ZipPicks logger instance
 *
 * @return ZipPicks_Logger|null
 */
if (!function_exists('zippicks_get_logger')) {
    function zippicks_get_logger() {
        static $logger = null;
        
        if ($logger === null && class_exists('ZipPicks_Logger')) {
            $logger = new ZipPicks_Logger();
        }
        
        return $logger;
    }
}

/**
 * Log an error message
 *
 * @param string $message
 * @param array $context
 */
if (!function_exists('zippicks_log_error')) {
    function zippicks_log_error($message, $context = []) {
        $logger = zippicks_get_logger();
        if ($logger) {
            $logger->log_error($message, $context);
        }
    }
}

/**
 * Log performance metrics
 *
 * @param string $action
 * @param float $duration
 * @param array $context
 */
if (!function_exists('zippicks_log_performance')) {
    function zippicks_log_performance($action, $duration, $context = []) {
        $logger = zippicks_get_logger();
        if ($logger) {
            $logger->log_performance($action, $duration, $context);
        }
    }
}

/**
 * Render a follow button
 *
 * @param array $args
 * @return string
 */
if (!function_exists('zippicks_render_follow_button')) {
    function zippicks_render_follow_button($args = []) {
        ob_start();
        include ZIPPICKS_CORE_PATH . 'includes/ui/card-follow.php';
        return ob_get_clean();
    }
}

/**
 * Get ZipPicks asset URL
 *
 * @param string $path
 * @param string $plugin
 * @return string
 */
if (!function_exists('zippicks_asset_url')) {
    function zippicks_asset_url($path, $plugin = 'core') {
        if ($plugin === 'core' && defined('ZIPPICKS_CORE_URL')) {
            return ZIPPICKS_CORE_URL . 'assets/' . ltrim($path, '/');
        }
        
        // Allow other plugins to register their asset URLs
        return apply_filters('zippicks_asset_url', '', $path, $plugin);
    }
}

// Note: zippicks_is_plugin_active() and zippicks_get_plugin_version() 
// are now defined in includes/compatibility/functions-compatibility.php
// to prevent conflicts with theme functions

/**
 * Generate a unique ID
 *
 * @param string $prefix
 * @return string
 */
if (!function_exists('zippicks_generate_id')) {
    function zippicks_generate_id($prefix = 'zp') {
        return $prefix . '_' . wp_generate_password(12, false);
    }
}

/**
 * Sanitize and validate ZIP code
 *
 * @param string $zip
 * @return string|false
 */
if (!function_exists('zippicks_sanitize_zip')) {
    function zippicks_sanitize_zip($zip) {
        $zip = preg_replace('/[^0-9-]/', '', $zip);
        
        // US ZIP code patterns
        if (preg_match('/^\d{5}(-\d{4})?$/', $zip)) {
            return $zip;
        }
        
        return false;
    }
}

/**
 * Format a number with suffix (1K, 1M, etc)
 *
 * @param int $number
 * @return string
 */
if (!function_exists('zippicks_format_number')) {
    function zippicks_format_number($number) {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        }
        
        return (string) $number;
    }
}

/**
 * Get client IP address
 *
 * @return string
 */
if (!function_exists('zippicks_get_client_ip')) {
    function zippicks_get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

/**
 * Check if request is AJAX
 *
 * @return bool
 */
if (!function_exists('zippicks_is_ajax')) {
    function zippicks_is_ajax() {
        return defined('DOING_AJAX') && DOING_AJAX;
    }
}

/**
 * Check if request is REST API
 *
 * @return bool
 */
if (!function_exists('zippicks_is_rest')) {
    function zippicks_is_rest() {
        return defined('REST_REQUEST') && REST_REQUEST;
    }
}

/**
 * Get current environment
 *
 * @return string
 */
if (!function_exists('zippicks_get_environment')) {
    function zippicks_get_environment() {
        if (defined('WP_ENV')) {
            return WP_ENV;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return 'development';
        }
        
        return 'production';
    }
}

/**
 * Trigger a ZipPicks action
 *
 * @param string $action
 * @param mixed ...$args
 */
if (!function_exists('zippicks_do_action')) {
    function zippicks_do_action($action, ...$args) {
        do_action('zippicks_' . $action, ...$args);
        
        // Log action if in debug mode
        if (defined('ZIPPICKS_DEBUG') && ZIPPICKS_DEBUG) {
            zippicks_log_performance('action_' . $action, 0, [
                'args_count' => count($args)
            ]);
        }
    }
}

/**
 * Apply ZipPicks filters
 *
 * @param string $filter
 * @param mixed $value
 * @param mixed ...$args
 * @return mixed
 */
if (!function_exists('zippicks_apply_filters')) {
    function zippicks_apply_filters($filter, $value, ...$args) {
        return apply_filters('zippicks_' . $filter, $value, ...$args);
    }
}