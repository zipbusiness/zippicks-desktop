<?php
/**
 * ZipPicks Core - Enterprise Error Handler
 * 
 * Provides comprehensive error handling, logging, and debugging
 * capabilities for the ZipPicks platform.
 * 
 * @package ZipPicks\Core
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enterprise Error Handler class
 */
class ZipPicks_Error_Handler {
    
    /**
     * Logger instance
     *
     * @var object
     */
    private $logger;
    
    /**
     * Error levels to capture
     *
     * @var int
     */
    private $error_levels;
    
    /**
     * Previously registered error handler
     *
     * @var callable|null
     */
    private $previous_error_handler;
    
    /**
     * Previously registered exception handler
     *
     * @var callable|null
     */
    private $previous_exception_handler;
    
    /**
     * Error count by type
     *
     * @var array
     */
    private $error_counts = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->error_levels = E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED;
        $this->init();
    }
    
    /**
     * Initialize error handler
     */
    private function init() {
        // Get logger instance
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $this->logger = zippicks()->get('logger');
        } elseif (function_exists('zippicks_get_logger')) {
            $this->logger = zippicks_get_logger();
        }
        
        // Set up error handlers
        $this->register_handlers();
        
        // Register shutdown function
        register_shutdown_function([$this, 'handle_shutdown']);
        
        // Add admin notices for critical errors
        add_action('admin_notices', [$this, 'display_error_notices']);
        
        // Add AJAX endpoint for error reporting
        add_action('wp_ajax_zippicks_report_js_error', [$this, 'handle_js_error']);
        add_action('wp_ajax_nopriv_zippicks_report_js_error', [$this, 'handle_js_error']);
    }
    
    /**
     * Register error and exception handlers
     */
    private function register_handlers() {
        // Only register in non-production or debug mode
        if (defined('ZIPPICKS_DEBUG') && ZIPPICKS_DEBUG) {
            $this->previous_error_handler = set_error_handler([$this, 'handle_error'], $this->error_levels);
            $this->previous_exception_handler = set_exception_handler([$this, 'handle_exception']);
        }
    }
    
    /**
     * Custom error handler
     *
     * @param int $errno Error level
     * @param string $errstr Error message
     * @param string $errfile File where error occurred
     * @param int $errline Line number
     * @return bool
     */
    public function handle_error($errno, $errstr, $errfile, $errline) {
        // Check if error reporting is disabled
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        // Increment error count
        $error_type = $this->get_error_type($errno);
        if (!isset($this->error_counts[$error_type])) {
            $this->error_counts[$error_type] = 0;
        }
        $this->error_counts[$error_type]++;
        
        // Build error context
        $context = [
            'type' => $error_type,
            'code' => $errno,
            'file' => $errfile,
            'line' => $errline,
            'trace' => $this->get_safe_backtrace(),
            'memory' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];
        
        // Check if it's a ZipPicks-related error
        if ($this->is_zippicks_error($errfile)) {
            $this->log_error($errstr, $context);
            
            // Store critical errors for admin notice
            if ($errno === E_ERROR || $errno === E_PARSE) {
                $this->store_critical_error($errstr, $context);
            }
        }
        
        // Call previous handler if exists
        if ($this->previous_error_handler) {
            return call_user_func($this->previous_error_handler, $errno, $errstr, $errfile, $errline);
        }
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    /**
     * Custom exception handler
     *
     * @param Throwable $exception
     */
    public function handle_exception($exception) {
        $context = [
            'type' => 'exception',
            'class' => get_class($exception),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];
        
        // Check if it's a ZipPicks-related exception
        if ($this->is_zippicks_error($exception->getFile())) {
            $this->log_error($exception->getMessage(), $context);
            $this->store_critical_error($exception->getMessage(), $context);
        }
        
        // Call previous handler if exists
        if ($this->previous_exception_handler) {
            call_user_func($this->previous_exception_handler, $exception);
        }
    }
    
    /**
     * Handle fatal errors on shutdown
     */
    public function handle_shutdown() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $context = [
                'type' => 'fatal',
                'code' => $error['type'],
                'file' => $error['file'],
                'line' => $error['line'],
            ];
            
            if ($this->is_zippicks_error($error['file'])) {
                $this->log_error($error['message'], $context);
                $this->store_critical_error($error['message'], $context);
            }
        }
    }
    
    /**
     * Handle JavaScript errors via AJAX
     */
    public function handle_js_error() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'zippicks_ajax')) {
            wp_die('Invalid request');
        }
        
        $message = sanitize_text_field($_POST['message'] ?? 'Unknown JS error');
        $file = sanitize_text_field($_POST['file'] ?? 'unknown');
        $line = intval($_POST['line'] ?? 0);
        $column = intval($_POST['column'] ?? 0);
        $stack = sanitize_textarea_field($_POST['stack'] ?? '');
        
        $context = [
            'type' => 'javascript',
            'file' => $file,
            'line' => $line,
            'column' => $column,
            'stack' => $stack,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'url' => $_POST['url'] ?? '',
        ];
        
        $this->log_error($message, $context);
        
        wp_send_json_success(['logged' => true]);
    }
    
    /**
     * Check if error is from ZipPicks code
     *
     * @param string $file
     * @return bool
     */
    private function is_zippicks_error($file) {
        return strpos($file, 'zippicks') !== false || 
               strpos($file, 'themes/zippicks') !== false;
    }
    
    /**
     * Log error with appropriate method
     *
     * @param string $message
     * @param array $context
     */
    private function log_error($message, $context) {
        if (!$this->logger) {
            error_log('[ZipPicks Error] ' . $message . ' - ' . json_encode($context));
            return;
        }
        
        // Determine log level based on error type
        $method = 'error';
        if (isset($context['type'])) {
            switch ($context['type']) {
                case 'warning':
                    $method = 'warning';
                    break;
                case 'notice':
                    $method = 'notice';
                    break;
                case 'deprecated':
                    $method = 'info';
                    break;
            }
        }
        
        $this->logger->$method($message, $context);
    }
    
    /**
     * Store critical error for admin notice
     *
     * @param string $message
     * @param array $context
     */
    private function store_critical_error($message, $context) {
        $errors = get_transient('zippicks_critical_errors') ?: [];
        
        $errors[] = [
            'message' => $message,
            'context' => $context,
            'timestamp' => current_time('timestamp'),
        ];
        
        // Keep only last 10 errors
        $errors = array_slice($errors, -10);
        
        set_transient('zippicks_critical_errors', $errors, HOUR_IN_SECONDS);
    }
    
    /**
     * Display error notices in admin
     */
    public function display_error_notices() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $errors = get_transient('zippicks_critical_errors');
        if (!$errors) {
            return;
        }
        
        $count = count($errors);
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong>ZipPicks Critical Errors:</strong>
                <?php printf(_n('%d critical error detected', '%d critical errors detected', $count, 'zippicks-core'), $count); ?>
            </p>
            <p>
                <a href="<?php echo admin_url('admin.php?page=zippicks-error-log'); ?>" class="button">
                    View Error Log
                </a>
                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=zippicks_clear_errors'), 'clear_errors'); ?>" class="button">
                    Clear Errors
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Get error type string
     *
     * @param int $errno
     * @return string
     */
    private function get_error_type($errno) {
        $types = [
            E_ERROR => 'error',
            E_WARNING => 'warning',
            E_PARSE => 'parse',
            E_NOTICE => 'notice',
            E_CORE_ERROR => 'core_error',
            E_CORE_WARNING => 'core_warning',
            E_COMPILE_ERROR => 'compile_error',
            E_COMPILE_WARNING => 'compile_warning',
            E_USER_ERROR => 'user_error',
            E_USER_WARNING => 'user_warning',
            E_USER_NOTICE => 'user_notice',
            E_STRICT => 'strict',
            E_RECOVERABLE_ERROR => 'recoverable_error',
            E_DEPRECATED => 'deprecated',
            E_USER_DEPRECATED => 'user_deprecated',
        ];
        
        return $types[$errno] ?? 'unknown';
    }
    
    /**
     * Get safe backtrace (without sensitive data)
     *
     * @return array
     */
    private function get_safe_backtrace() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $safe_trace = [];
        
        foreach ($trace as $frame) {
            $safe_trace[] = [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'class' => $frame['class'] ?? '',
            ];
        }
        
        return $safe_trace;
    }
    
    /**
     * Get error statistics
     *
     * @return array
     */
    public function get_error_stats() {
        return [
            'counts' => $this->error_counts,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];
    }
}