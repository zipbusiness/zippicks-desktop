<?php
/**
 * ZipPicks Core Authentication Manager
 * 
 * Enterprise-grade authentication service supporting JWT, HMAC, WP nonce, and cookie sessions.
 * Provides unified authentication across all ZipPicks plugins.
 * 
 * @package ZipPicks_Core
 * @subpackage Auth
 * @since 1.0.0
 */

namespace ZipPicks\Core\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Exception;

class ZP_Auth_Manager {
    
    /**
     * JWT algorithm
     */
    const JWT_ALGORITHM = 'HS256';
    
    /**
     * Token expiration time in seconds (1 hour)
     */
    const TOKEN_EXPIRY = 3600;
    
    /**
     * Soft refresh window (10 minutes before expiry)
     */
    const REFRESH_WINDOW = 600;
    
    /**
     * HMAC request validity window (5 minutes)
     */
    const HMAC_WINDOW = 300;
    
    /**
     * Cache key prefix
     */
    const CACHE_PREFIX = 'zp_auth_';
    
    /**
     * JWT Secret
     * @var string
     */
    private $jwt_secret;
    
    /**
     * HMAC Secret
     * @var string
     */
    private $hmac_secret;
    
    /**
     * Cache instance
     * @var mixed
     */
    private $cache;
    
    /**
     * Logger instance
     * @var mixed
     */
    private $logger;
    
    /**
     * Current authenticated user
     * @var ZipPicksUser|null
     */
    private $current_user = null;
    
    /**
     * Authentication method used
     * @var string|null
     */
    private $auth_method = null;
    
    /**
     * Singleton instance
     * @var self|null
     */
    private static $instance = null;
    
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
        // Get secrets from wp-config.php or use defaults
        $this->jwt_secret = defined('ZIPPICKS_JWT_SECRET') 
            ? ZIPPICKS_JWT_SECRET 
            : $this->generate_fallback_secret('jwt');
            
        $this->hmac_secret = defined('ZIPPICKS_HMAC_SECRET')
            ? ZIPPICKS_HMAC_SECRET
            : $this->generate_fallback_secret('hmac');
        
        // Get Foundation services
        if (function_exists('zippicks')) {
            $this->cache = zippicks()->get('cache');
            $this->logger = zippicks()->get('logger');
        }
        
        // Register with Foundation
        $this->register_with_foundation();
    }
    
    /**
     * Register auth service with Foundation
     */
    private function register_with_foundation() {
        if (function_exists('zippicks')) {
            zippicks()->bind('auth', $this);
        }
    }
    
    /**
     * Generate fallback secret for development
     */
    private function generate_fallback_secret($type) {
        $site_url = get_site_url();
        $auth_key = defined('AUTH_KEY') ? AUTH_KEY : 'zippicks-default';
        return hash('sha256', $site_url . $auth_key . $type);
    }
    
    /**
     * Authenticate request using any available method
     * 
     * @param array $request Optional request data (headers, cookies, etc.)
     * @return ZipPicksUser|null
     */
    public function authenticate($request = null) {
        // If already authenticated in this request, return cached user
        if ($this->current_user !== null) {
            return $this->current_user;
        }
        
        // Try authentication methods in order of preference
        $auth_methods = [
            'jwt' => [$this, 'authenticate_jwt'],
            'hmac' => [$this, 'authenticate_hmac'],
            'nonce' => [$this, 'authenticate_nonce'],
            'cookie' => [$this, 'authenticate_cookie']
        ];
        
        foreach ($auth_methods as $method => $callback) {
            try {
                $user = call_user_func($callback, $request);
                if ($user instanceof ZipPicksUser) {
                    $this->current_user = $user;
                    $this->auth_method = $method;
                    
                    if ($this->logger) {
                        $this->logger->debug('Authentication successful', [
                            'method' => $method,
                            'user_id' => $user->get_id()
                        ]);
                    }
                    
                    return $user;
                }
            } catch (Exception $e) {
                if ($this->logger) {
                    $this->logger->warning('Authentication failed', [
                        'method' => $method,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Authenticate using JWT token
     */
    private function authenticate_jwt($request = null) {
        $token = $this->extract_bearer_token($request);
        
        if (!$token) {
            return null;
        }
        
        try {
            // Check cache first
            $cache_key = self::CACHE_PREFIX . 'jwt_' . md5($token);
            if ($this->cache) {
                $cached_user = $this->cache->get($cache_key);
                if ($cached_user instanceof ZipPicksUser) {
                    return $cached_user;
                }
            }
            
            // Decode JWT
            $decoded = JWT::decode($token, new Key($this->jwt_secret, self::JWT_ALGORITHM));
            
            // Validate claims
            if (!isset($decoded->user_id) || !isset($decoded->exp)) {
                throw new Exception('Invalid JWT claims');
            }
            
            // Check if token needs refresh
            $time_until_expiry = $decoded->exp - time();
            if ($time_until_expiry > 0 && $time_until_expiry < self::REFRESH_WINDOW) {
                // Issue new token with sliding window
                $this->issue_refreshed_token($decoded->user_id);
            }
            
            // Create ZipPicksUser from JWT data
            $user = new ZipPicksUser($decoded->user_id);
            
            // Cache the user object
            if ($this->cache && $time_until_expiry > 0) {
                $this->cache->put($cache_key, $user, $time_until_expiry);
            }
            
            return $user;
            
        } catch (ExpiredException $e) {
            throw new Exception('JWT token expired');
        } catch (Exception $e) {
            throw new Exception('JWT validation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Authenticate using HMAC signature
     */
    private function authenticate_hmac($request = null) {
        $headers = $this->get_request_headers($request);
        
        // Check for required HMAC headers
        $required_headers = ['X-Auth-Timestamp', 'X-Auth-Signature', 'X-Auth-User'];
        foreach ($required_headers as $header) {
            if (!isset($headers[$header])) {
                return null;
            }
        }
        
        $timestamp = intval($headers['X-Auth-Timestamp']);
        $signature = $headers['X-Auth-Signature'];
        $user_id = intval($headers['X-Auth-User']);
        
        // Validate timestamp (prevent replay attacks)
        if (abs(time() - $timestamp) > self::HMAC_WINDOW) {
            throw new Exception('HMAC timestamp outside valid window');
        }
        
        // Get request data for signature
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $body = file_get_contents('php://input') ?: '';
        
        // Generate expected signature
        $message = implode('|', [$method, $path, $timestamp, $user_id, $body]);
        $expected_signature = hash_hmac('sha256', $message, $this->hmac_secret);
        
        // Validate signature
        if (!hash_equals($expected_signature, $signature)) {
            throw new Exception('Invalid HMAC signature');
        }
        
        // Return user
        return new ZipPicksUser($user_id);
    }
    
    /**
     * Authenticate using WordPress nonce
     */
    private function authenticate_nonce($request = null) {
        // Check for nonce in request
        $nonce = $_REQUEST['_wpnonce'] ?? $_SERVER['HTTP_X_WP_NONCE'] ?? null;
        
        if (!$nonce) {
            return null;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            throw new Exception('Invalid nonce');
        }
        
        // Get current user from WordPress
        $wp_user_id = get_current_user_id();
        if (!$wp_user_id) {
            return null;
        }
        
        return new ZipPicksUser($wp_user_id);
    }
    
    /**
     * Authenticate using WordPress cookie session
     */
    private function authenticate_cookie($request = null) {
        // Check if user is logged in via WordPress
        if (!is_user_logged_in()) {
            return null;
        }
        
        $wp_user_id = get_current_user_id();
        return new ZipPicksUser($wp_user_id);
    }
    
    /**
     * Generate JWT token for a user
     */
    public function generate_token($user_id, $additional_claims = []) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            throw new Exception('Invalid user ID');
        }
        
        $issued_at = time();
        $expiration = $issued_at + self::TOKEN_EXPIRY;
        
        // Build claims
        $claims = array_merge([
            'iss' => get_site_url(),
            'iat' => $issued_at,
            'exp' => $expiration,
            'user_id' => $user_id,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'roles' => $user->roles,
            'capabilities' => $this->get_user_capabilities($user_id)
        ], $additional_claims);
        
        // Generate token
        $token = JWT::encode($claims, $this->jwt_secret, self::JWT_ALGORITHM);
        
        // Cache token
        if ($this->cache) {
            $cache_key = self::CACHE_PREFIX . 'user_token_' . $user_id;
            $this->cache->put($cache_key, $token, self::TOKEN_EXPIRY - 60);
        }
        
        return $token;
    }
    
    /**
     * Generate HMAC signature for a request
     */
    public function generate_hmac_signature($user_id, $method, $path, $body = '') {
        $timestamp = time();
        $message = implode('|', [$method, $path, $timestamp, $user_id, $body]);
        $signature = hash_hmac('sha256', $message, $this->hmac_secret);
        
        return [
            'signature' => $signature,
            'timestamp' => $timestamp,
            'user_id' => $user_id
        ];
    }
    
    /**
     * Get current authenticated user
     */
    public function get_current_user() {
        if ($this->current_user === null) {
            $this->authenticate();
        }
        return $this->current_user;
    }
    
    /**
     * Get current user ID
     */
    public function get_current_user_id() {
        $user = $this->get_current_user();
        return $user ? $user->get_id() : 0;
    }
    
    /**
     * Check if current user is authenticated
     */
    public function is_authenticated() {
        return $this->get_current_user() !== null;
    }
    
    /**
     * Check if current user is admin
     */
    public function is_admin() {
        $user = $this->get_current_user();
        return $user && $user->has_role('administrator');
    }
    
    /**
     * Check if current user has capability
     */
    public function can($capability) {
        $user = $this->get_current_user();
        return $user && $user->can($capability);
    }
    
    /**
     * Get authentication method used
     */
    public function get_auth_method() {
        return $this->auth_method;
    }
    
    /**
     * Extract bearer token from request
     */
    private function extract_bearer_token($request = null) {
        $headers = $this->get_request_headers($request);
        
        // Check Authorization header
        if (isset($headers['Authorization'])) {
            if (preg_match('/Bearer\s+(.+)$/i', $headers['Authorization'], $matches)) {
                return $matches[1];
            }
        }
        
        // Check X-Auth-Token header (alternative)
        if (isset($headers['X-Auth-Token'])) {
            return $headers['X-Auth-Token'];
        }
        
        return null;
    }
    
    /**
     * Get request headers
     */
    private function get_request_headers($request = null) {
        if ($request && isset($request['headers'])) {
            return $request['headers'];
        }
        
        // Get headers from $_SERVER
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$header] = $value;
            }
        }
        
        return $headers;
    }
    
    /**
     * Get user capabilities for token
     */
    private function get_user_capabilities($user_id) {
        $user = new ZipPicksUser($user_id);
        return $user->get_capabilities();
    }
    
    /**
     * Issue refreshed token
     */
    private function issue_refreshed_token($user_id) {
        try {
            $new_token = $this->generate_token($user_id);
            
            // Set response header with new token
            if (!headers_sent()) {
                header('X-Refreshed-Token: ' . $new_token);
            }
            
            if ($this->logger) {
                $this->logger->info('Issued refreshed token', ['user_id' => $user_id]);
            }
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('Failed to issue refreshed token', [
                    'user_id' => $user_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Invalidate user tokens
     */
    public function invalidate_user_tokens($user_id) {
        if ($this->cache) {
            // Clear cached tokens
            $this->cache->deletePattern(self::CACHE_PREFIX . 'user_token_' . $user_id . '*');
            $this->cache->deletePattern(self::CACHE_PREFIX . 'jwt_*');
        }
        
        if ($this->logger) {
            $this->logger->info('Invalidated user tokens', ['user_id' => $user_id]);
        }
    }
    
    /**
     * Clear authentication state
     */
    public function clear_auth() {
        $this->current_user = null;
        $this->auth_method = null;
    }
}