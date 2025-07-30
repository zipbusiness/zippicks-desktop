<?php
/**
 * ZipPicks Logger
 *
 * @package ZipPicks\Core
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger class for system-wide logging
 */
class ZipPicks_Logger {
    
    /**
     * Log levels
     */
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_INFO = 'INFO';
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_PERFORMANCE = 'PERFORMANCE';
    
    /**
     * Log directory path
     *
     * @var string
     */
    private $log_dir;
    
    /**
     * Current log file path
     *
     * @var string
     */
    private $log_file;
    
    /**
     * Whether to log to database
     *
     * @var bool
     */
    private $use_db = false;
    
    /**
     * Constructor
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/zippicks-logs';
        $this->log_file = $this->log_dir . '/zippicks-' . date('Y-m-d') . '.log';
        
        // Create log directory if it doesn't exist
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
            
            // Protect directory
            $htaccess = $this->log_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Order Allow,Deny\nDeny from all");
            }
        }
        
        // Check if database logging is available
        $this->check_db_availability();
    }
    
    /**
     * Check if database logging is available
     */
    private function check_db_availability() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'zippicks_logs';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        $this->use_db = $table_exists;
    }
    
    /**
     * Log an error
     *
     * @param string $message
     * @param array $context
     */
    public function log_error($message, $context = []) {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Log a warning
     *
     * @param string $message
     * @param array $context
     */
    public function log_warning($message, $context = []) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * PSR-3 compatible methods
     */
    public function error($message, array $context = []) {
        $this->log_error($message, $context);
    }
    
    public function warning($message, array $context = []) {
        $this->log_warning($message, $context);
    }
    
    public function notice($message, array $context = []) {
        $this->log_info($message, $context);
    }
    
    public function info($message, array $context = []) {
        $this->log_info($message, $context);
    }
    
    public function debug($message, array $context = []) {
        $this->log_debug($message, $context);
    }
    
    /**
     * Log info
     *
     * @param string $message
     * @param array $context
     */
    public function log_info($message, $context = []) {
        $this->log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log debug information
     *
     * @param string $message
     * @param array $context
     */
    public function log_debug($message, $context = []) {
        if (defined('ZIPPICKS_DEBUG') && ZIPPICKS_DEBUG) {
            $this->log(self::LEVEL_DEBUG, $message, $context);
        }
    }
    
    /**
     * Log performance metrics
     *
     * @param string $action
     * @param float $duration Duration in milliseconds
     * @param array $context
     */
    public function log_performance($action, $duration, $context = []) {
        $context['action'] = $action;
        $context['duration_ms'] = $duration;
        
        $this->log(self::LEVEL_PERFORMANCE, "Performance: $action ({$duration}ms)", $context);
    }
    
    /**
     * General logging method
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function log($level, $message, $context = []) {
        // Add common context
        $context = array_merge($context, [
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'ip' => $this->get_client_ip(),
            'url' => $this->get_current_url(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        // Format log entry
        $log_entry = $this->format_log_entry($level, $message, $context);
        
        // Write to file
        $this->write_to_file($log_entry);
        
        // Write to database if available
        if ($this->use_db) {
            $this->write_to_db($level, $message, $context);
        }
        
        // Trigger action for external handlers
        do_action('zippicks_log_entry', $level, $message, $context);
    }
    
    /**
     * Format log entry
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return string
     */
    private function format_log_entry($level, $message, $context) {
        $timestamp = date('Y-m-d H:i:s');
        $formatted_context = $this->format_context($context);
        
        return sprintf(
            "[%s] [%s] %s %s\n",
            $timestamp,
            $level,
            $message,
            $formatted_context ? '| ' . $formatted_context : ''
        );
    }
    
    /**
     * Format context for logging
     *
     * @param array $context
     * @return string
     */
    private function format_context($context) {
        // Remove verbose fields for file logging
        unset($context['timestamp'], $context['user_agent']);
        
        if (empty($context)) {
            return '';
        }
        
        $formatted = [];
        foreach ($context as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }
            $formatted[] = "$key: $value";
        }
        
        return implode(' | ', $formatted);
    }
    
    /**
     * Write log entry to file
     *
     * @param string $entry
     */
    private function write_to_file($entry) {
        // Rotate logs if file is too large (10MB)
        if (file_exists($this->log_file) && filesize($this->log_file) > 10485760) {
            $this->rotate_log();
        }
        
        // Write to file
        error_log($entry, 3, $this->log_file);
    }
    
    /**
     * Write log entry to database
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function write_to_db($level, $message, $context) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'zippicks_logs';
        
        $wpdb->insert(
            $table_name,
            [
                'level' => $level,
                'message' => $message,
                'context' => json_encode($context),
                'user_id' => $context['user_id'],
                'ip_address' => $context['ip'],
                'created_at' => $context['timestamp']
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s']
        );
    }
    
    /**
     * Rotate log file
     */
    private function rotate_log() {
        $timestamp = date('Y-m-d-H-i-s');
        $rotated_file = $this->log_dir . '/zippicks-' . $timestamp . '-rotated.log';
        
        rename($this->log_file, $rotated_file);
        
        // Clean up old rotated logs (keep last 5)
        $this->cleanup_old_logs();
    }
    
    /**
     * Clean up old log files
     */
    private function cleanup_old_logs() {
        $files = glob($this->log_dir . '/zippicks-*-rotated.log');
        
        if (count($files) > 5) {
            // Sort by modification time
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remove oldest files
            $to_remove = array_slice($files, 0, count($files) - 5);
            foreach ($to_remove as $file) {
                unlink($file);
            }
        }
    }
    
    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip() {
        if (function_exists('zippicks_get_client_ip')) {
            return zippicks_get_client_ip();
        }
        
        // Check for forwarded IP (behind proxy/load balancer)
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get current URL
     *
     * @return string
     */
    private function get_current_url() {
        if (is_admin()) {
            return admin_url($_SERVER['REQUEST_URI'] ?? '');
        }
        
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        return $protocol . '://' . $host . $uri;
    }
    
    /**
     * Get logs for a specific date
     *
     * @param string $date
     * @return string
     */
    public function get_logs($date = null) {
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $log_file = $this->log_dir . '/zippicks-' . $date . '.log';
        
        if (file_exists($log_file)) {
            return file_get_contents($log_file);
        }
        
        return '';
    }
    
    /**
     * Clear logs older than specified days
     *
     * @param int $days
     */
    public function clear_old_logs($days = 30) {
        $files = glob($this->log_dir . '/zippicks-*.log');
        $cutoff = time() - ($days * 86400);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
        
        // Clear old DB logs if available
        if ($this->use_db) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'zippicks_logs';
            $cutoff_date = date('Y-m-d H:i:s', $cutoff);
            
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name WHERE created_at < %s",
                $cutoff_date
            ));
        }
    }
}