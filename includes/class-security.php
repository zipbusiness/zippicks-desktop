<?php
/**
 * ZipPicks Core - Enterprise Security Manager
 * 
 * Provides comprehensive security features including:
 * - Nonce management with automatic rotation
 * - Input sanitization and validation
 * - Rate limiting
 * - Security headers
 * - XSS/CSRF protection
 * 
 * @package ZipPicks\Core\Security
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Manager class
 */
class ZipPicks_Security {
    
    /**
     * Instance
     *
     * @var ZipPicks_Security
     */
    private static $instance = null;
    
    /**
     * Rate limit data
     *
     * @var array
     */
    private $rate_limits = [];
    
    /**
     * Get instance
     *
     * @return ZipPicks_Security
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
        $this->init();
    }
    
    /**
     * Initialize security features
     */
    private function init() {
        // Add security headers
        add_action('send_headers', [$this, 'add_security_headers']);
        
        // Add nonce verification to admin requests
        add_action('admin_init', [$this, 'verify_admin_request']);
        
        // Add rate limiting
        add_action('init', [$this, 'init_rate_limiting']);
        
        // Clean expired rate limit data
        add_action('zippicks_hourly_cron', [$this, 'clean_rate_limit_data']);
        
        // Add content security policy
        add_action('wp_head', [$this, 'add_csp_meta'], 1);
        add_action('admin_head', [$this, 'add_csp_meta'], 1);
    }
    
    /**
     * Add security headers
     */
    public function add_security_headers() {
        // Only add headers if not already set
        if (!headers_sent()) {
            // X-Frame-Options
            header('X-Frame-Options: SAMEORIGIN');
            
            // X-Content-Type-Options
            header('X-Content-Type-Options: nosniff');
            
            // X-XSS-Protection
            header('X-XSS-Protection: 1; mode=block');
            
            // Referrer-Policy
            header('Referrer-Policy: strict-origin-when-cross-origin');
            
            // Permissions-Policy
            header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
            
            // For admin AJAX requests, add cache control
            if (wp_doing_ajax()) {
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
            }
        }
    }
    
    /**
     * Add Content Security Policy meta tag
     */
    public function add_csp_meta() {
        $csp = $this->get_csp_policy();
        if ($csp) {
            echo '<meta http-equiv="Content-Security-Policy" content="' . esc_attr($csp) . '">' . "\n";
        }
    }
    
    /**
     * Get CSP policy
     *
     * @return string
     */
    private function get_csp_policy() {
        $policy = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' *.wordpress.com *.wp.com",
            "style-src 'self' 'unsafe-inline' fonts.googleapis.com",
            "img-src 'self' data: https: *.wp.com *.gravatar.com",
            "font-src 'self' fonts.gstatic.com",
            "connect-src 'self'",
            "frame-ancestors 'self'"
        ];
        
        return apply_filters('zippicks_csp_policy', implode('; ', $policy));
    }
    
    /**
     * Verify admin request
     */
    public function verify_admin_request() {
        // Skip for AJAX requests (handled separately)
        if (wp_doing_ajax()) {
            return;
        }
        
        // Skip for GET requests
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return;
        }
        
        // Verify nonce for POST requests in admin
        if (!empty($_POST) && isset($_POST['_wpnonce'])) {
            $action = $_POST['action'] ?? 'zippicks_admin_action';
            if (!wp_verify_nonce($_POST['_wpnonce'], $action)) {
                wp_die('Security check failed. Please try again.');
            }
        }
    }
    
    /**
     * Create secure nonce
     *
     * @param string $action
     * @return string
     */
    public function create_nonce($action = 'zippicks_action') {
        // Add user context for better security
        $action = $this->add_nonce_context($action);
        return wp_create_nonce($action);
    }
    
    /**
     * Verify secure nonce
     *
     * @param string $nonce
     * @param string $action
     * @return bool
     */
    public function verify_nonce($nonce, $action = 'zippicks_action') {
        // Add user context for verification
        $action = $this->add_nonce_context($action);
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Add context to nonce action
     *
     * @param string $action
     * @return string
     */
    private function add_nonce_context($action) {
        $user_id = get_current_user_id();
        $session_token = wp_get_session_token();
        return $action . '_' . $user_id . '_' . substr($session_token, 0, 10);
    }
    
    /**
     * Sanitize input data
     *
     * @param mixed $data
     * @param string $type
     * @return mixed
     */
    public function sanitize_input($data, $type = 'text') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return $this->sanitize_input($item, $type);
            }, $data);
        }
        
        switch ($type) {
            case 'email':
                return sanitize_email($data);
                
            case 'url':
                return esc_url_raw($data);
                
            case 'int':
                return intval($data);
                
            case 'float':
                return floatval($data);
                
            case 'bool':
                return filter_var($data, FILTER_VALIDATE_BOOLEAN);
                
            case 'html':
                return wp_kses_post($data);
                
            case 'textarea':
                return sanitize_textarea_field($data);
                
            case 'key':
                return sanitize_key($data);
                
            case 'filename':
                return sanitize_file_name($data);
                
            case 'sql':
                global $wpdb;
                return $wpdb->prepare('%s', $data);
                
            case 'json':
                return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                
            case 'text':
            default:
                return sanitize_text_field($data);
        }
    }
    
    /**
     * Validate input data
     *
     * @param mixed $data
     * @param array $rules
     * @return array Validation result [valid => bool, errors => array]
     */
    public function validate_input($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            // Required check
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = sprintf(__('%s is required', 'zippicks-core'), $field);
                continue;
            }
            
            // Skip validation if not required and empty
            if (empty($value) && (!isset($rule['required']) || !$rule['required'])) {
                continue;
            }
            
            // Type validation
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!is_email($value)) {
                            $errors[$field] = sprintf(__('%s must be a valid email', 'zippicks-core'), $field);
                        }
                        break;
                        
                    case 'url':
                        if (!filter_var($value, FILTER_VALIDATE_URL)) {
                            $errors[$field] = sprintf(__('%s must be a valid URL', 'zippicks-core'), $field);
                        }
                        break;
                        
                    case 'int':
                        if (!is_numeric($value) || intval($value) != $value) {
                            $errors[$field] = sprintf(__('%s must be an integer', 'zippicks-core'), $field);
                        }
                        break;
                        
                    case 'float':
                        if (!is_numeric($value)) {
                            $errors[$field] = sprintf(__('%s must be a number', 'zippicks-core'), $field);
                        }
                        break;
                }
            }
            
            // Min/Max validation
            if (isset($rule['min']) && $value < $rule['min']) {
                $errors[$field] = sprintf(__('%s must be at least %s', 'zippicks-core'), $field, $rule['min']);
            }
            
            if (isset($rule['max']) && $value > $rule['max']) {
                $errors[$field] = sprintf(__('%s must be at most %s', 'zippicks-core'), $field, $rule['max']);
            }
            
            // Length validation
            if (isset($rule['minlength']) && strlen($value) < $rule['minlength']) {
                $errors[$field] = sprintf(__('%s must be at least %d characters', 'zippicks-core'), $field, $rule['minlength']);
            }
            
            if (isset($rule['maxlength']) && strlen($value) > $rule['maxlength']) {
                $errors[$field] = sprintf(__('%s must be at most %d characters', 'zippicks-core'), $field, $rule['maxlength']);
            }
            
            // Pattern validation
            if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                $errors[$field] = sprintf(__('%s format is invalid', 'zippicks-core'), $field);
            }
            
            // Custom validation
            if (isset($rule['callback']) && is_callable($rule['callback'])) {
                $result = call_user_func($rule['callback'], $value, $data);
                if ($result !== true) {
                    $errors[$field] = is_string($result) ? $result : sprintf(__('%s is invalid', 'zippicks-core'), $field);
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Initialize rate limiting
     */
    public function init_rate_limiting() {
        // Load rate limit data from transients
        $this->rate_limits = get_transient('zippicks_rate_limits') ?: [];
    }
    
    /**
     * Check rate limit
     *
     * @param string $action Action to check
     * @param string $identifier User identifier (IP, user ID, etc.)
     * @param int $max_attempts Maximum attempts allowed
     * @param int $window Time window in seconds
     * @return bool True if within limits, false if exceeded
     */
    public function check_rate_limit($action, $identifier, $max_attempts = 10, $window = 60) {
        $key = $action . '_' . $identifier;
        $now = time();
        
        // Clean old entries
        if (isset($this->rate_limits[$key])) {
            $this->rate_limits[$key] = array_filter($this->rate_limits[$key], function($timestamp) use ($now, $window) {
                return ($now - $timestamp) < $window;
            });
        }
        
        // Check limit
        $attempts = isset($this->rate_limits[$key]) ? count($this->rate_limits[$key]) : 0;
        
        if ($attempts >= $max_attempts) {
            // Log rate limit exceeded
            if (function_exists('zippicks_get_logger')) {
                $logger = zippicks_get_logger();
                $logger->warning('Rate limit exceeded', [
                    'action' => $action,
                    'identifier' => $identifier,
                    'attempts' => $attempts
                ]);
            }
            return false;
        }
        
        // Record attempt
        if (!isset($this->rate_limits[$key])) {
            $this->rate_limits[$key] = [];
        }
        $this->rate_limits[$key][] = $now;
        
        // Save to transient
        set_transient('zippicks_rate_limits', $this->rate_limits, HOUR_IN_SECONDS);
        
        return true;
    }
    
    /**
     * Clean expired rate limit data
     */
    public function clean_rate_limit_data() {
        $this->rate_limits = get_transient('zippicks_rate_limits') ?: [];
        $now = time();
        $cleaned = false;
        
        foreach ($this->rate_limits as $key => $timestamps) {
            // Remove entries older than 1 hour
            $this->rate_limits[$key] = array_filter($timestamps, function($timestamp) use ($now) {
                return ($now - $timestamp) < HOUR_IN_SECONDS;
            });
            
            // Remove empty keys
            if (empty($this->rate_limits[$key])) {
                unset($this->rate_limits[$key]);
                $cleaned = true;
            }
        }
        
        if ($cleaned) {
            set_transient('zippicks_rate_limits', $this->rate_limits, HOUR_IN_SECONDS);
        }
    }
    
    /**
     * Generate secure token
     *
     * @param int $length
     * @return string
     */
    public function generate_token($length = 32) {
        if (function_exists('wp_generate_password')) {
            return wp_generate_password($length, false, false);
        }
        
        // Fallback
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Hash sensitive data
     *
     * @param string $data
     * @param string $context
     * @return string
     */
    public function hash_data($data, $context = '') {
        $salt = defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : 'zippicks_default_salt';
        return hash_hmac('sha256', $data . $context, $salt);
    }
    
    /**
     * Encrypt sensitive data
     *
     * @param string $data
     * @return string
     */
    public function encrypt($data) {
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($data); // Fallback
        }
        
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     *
     * @param string $data
     * @return string|false
     */
    public function decrypt($data) {
        if (!function_exists('openssl_decrypt')) {
            return base64_decode($data); // Fallback
        }
        
        $data = base64_decode($data);
        $key = $this->get_encryption_key();
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Get encryption key
     *
     * @return string
     */
    private function get_encryption_key() {
        if (defined('SECURE_AUTH_KEY')) {
            return substr(hash('sha256', SECURE_AUTH_KEY), 0, 32);
        }
        
        // Fallback - should never be used in production
        return substr(hash('sha256', 'zippicks_default_key'), 0, 32);
    }
    
    /**
     * Check if request is from trusted source
     *
     * @return bool
     */
    public function is_trusted_request() {
        // Check if user is logged in and has appropriate capabilities
        if (is_user_logged_in() && current_user_can('manage_options')) {
            return true;
        }
        
        // Check for API key if implemented
        $api_key = $_SERVER['HTTP_X_ZIPPICKS_API_KEY'] ?? '';
        if ($api_key && $this->verify_api_key($api_key)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Verify API key
     *
     * @param string $key
     * @return bool
     */
    private function verify_api_key($key) {
        $valid_keys = get_option('zippicks_api_keys', []);
        
        foreach ($valid_keys as $valid_key) {
            if (hash_equals($valid_key['key'], $key)) {
                // Check if key is not expired
                if (!isset($valid_key['expires']) || $valid_key['expires'] > time()) {
                    return true;
                }
            }
        }
        
        return false;
    }
}

/**
 * Get security instance
 *
 * @return ZipPicks_Security
 */
function zippicks_security() {
    return ZipPicks_Security::get_instance();
}