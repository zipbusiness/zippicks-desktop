<?php
/**
 * Token Verifier
 * 
 * Shared token verification service with Authorization header parsing
 * and fallback support for multiple authentication methods.
 * 
 * @package ZipPicks_Core
 * @subpackage Auth
 * @since 1.0.0
 */

namespace ZipPicks\Core\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class Token_Verifier {
    
    /**
     * Supported token types
     */
    const TYPE_BEARER = 'Bearer';
    const TYPE_BASIC = 'Basic';
    const TYPE_API_KEY = 'ApiKey';
    const TYPE_HMAC = 'HMAC';
    
    /**
     * Header names to check
     */
    const HEADERS_TO_CHECK = [
        'Authorization',
        'X-Authorization',
        'X-Auth-Token',
        'X-API-Key',
        'X-WP-Nonce'
    ];
    
    /**
     * Extract and parse authentication token from request
     * 
     * @param array|null $headers Optional headers array
     * @return array|null Token info [type, value, scheme] or null
     */
    public function extract_token($headers = null) {
        $headers = $this->normalize_headers($headers);
        
        // Check each possible header
        foreach (self::HEADERS_TO_CHECK as $header_name) {
            $header_key = $this->find_header_key($headers, $header_name);
            if ($header_key && isset($headers[$header_key])) {
                $token_info = $this->parse_header_value($headers[$header_key], $header_name);
                if ($token_info) {
                    return $token_info;
                }
            }
        }
        
        // Check for nonce in request parameters as fallback
        if (isset($_REQUEST['_wpnonce'])) {
            return [
                'type' => 'nonce',
                'value' => $_REQUEST['_wpnonce'],
                'source' => 'request_param'
            ];
        }
        
        return null;
    }
    
    /**
     * Verify extracted token
     * 
     * @param array $token_info Token information from extract_token
     * @param array $options Verification options
     * @return array|false Decoded token data or false
     */
    public function verify_token($token_info, $options = []) {
        if (!$token_info || !isset($token_info['type']) || !isset($token_info['value'])) {
            return false;
        }
        
        switch ($token_info['type']) {
            case 'jwt':
            case self::TYPE_BEARER:
                return $this->verify_jwt($token_info['value'], $options);
                
            case self::TYPE_HMAC:
                return $this->verify_hmac($token_info['value'], $options);
                
            case self::TYPE_API_KEY:
                return $this->verify_api_key($token_info['value'], $options);
                
            case 'nonce':
                return $this->verify_nonce($token_info['value'], $options);
                
            case self::TYPE_BASIC:
                return $this->verify_basic_auth($token_info['value'], $options);
                
            default:
                return false;
        }
    }
    
    /**
     * Normalize headers array
     * 
     * @param array|null $headers
     * @return array
     */
    private function normalize_headers($headers = null) {
        if (is_array($headers)) {
            return $headers;
        }
        
        // Get headers from server globals
        $normalized = [];
        
        // Apache and nginx
        if (function_exists('getallheaders')) {
            $normalized = getallheaders();
        }
        
        // Fallback to $_SERVER
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $normalized[$header] = $value;
            }
        }
        
        // Special cases
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $normalized['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $normalized['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }
        
        return $normalized;
    }
    
    /**
     * Find header key (case-insensitive)
     * 
     * @param array $headers
     * @param string $header_name
     * @return string|null
     */
    private function find_header_key($headers, $header_name) {
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, $header_name) === 0) {
                return $key;
            }
        }
        return null;
    }
    
    /**
     * Parse header value to extract token
     * 
     * @param string $header_value
     * @param string $header_name
     * @return array|null
     */
    private function parse_header_value($header_value, $header_name) {
        $header_value = trim($header_value);
        
        // Bearer token
        if (preg_match('/^Bearer\s+(.+)$/i', $header_value, $matches)) {
            return [
                'type' => self::TYPE_BEARER,
                'value' => $matches[1],
                'source' => $header_name
            ];
        }
        
        // Basic auth
        if (preg_match('/^Basic\s+(.+)$/i', $header_value, $matches)) {
            return [
                'type' => self::TYPE_BASIC,
                'value' => $matches[1],
                'source' => $header_name
            ];
        }
        
        // HMAC signature
        if (preg_match('/^HMAC\s+(.+)$/i', $header_value, $matches)) {
            return [
                'type' => self::TYPE_HMAC,
                'value' => $matches[1],
                'source' => $header_name
            ];
        }
        
        // API Key (various formats)
        if ($header_name === 'X-API-Key' || preg_match('/^ApiKey\s+(.+)$/i', $header_value, $matches)) {
            return [
                'type' => self::TYPE_API_KEY,
                'value' => isset($matches[1]) ? $matches[1] : $header_value,
                'source' => $header_name
            ];
        }
        
        // WordPress nonce
        if ($header_name === 'X-WP-Nonce') {
            return [
                'type' => 'nonce',
                'value' => $header_value,
                'source' => $header_name
            ];
        }
        
        // Assume JWT for unspecified format in auth headers
        if (in_array($header_name, ['Authorization', 'X-Authorization', 'X-Auth-Token'])) {
            return [
                'type' => 'jwt',
                'value' => $header_value,
                'source' => $header_name
            ];
        }
        
        return null;
    }
    
    /**
     * Verify JWT token
     * 
     * @param string $token
     * @param array $options
     * @return array|false
     */
    private function verify_jwt($token, $options) {
        $secret = $options['jwt_secret'] ?? null;
        if (!$secret) {
            return false;
        }
        
        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return (array) $decoded;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Verify HMAC signature
     * 
     * @param string $signature
     * @param array $options
     * @return array|false
     */
    private function verify_hmac($signature, $options) {
        $secret = $options['hmac_secret'] ?? null;
        if (!$secret) {
            return false;
        }
        
        // HMAC verification requires additional headers
        $headers = $this->normalize_headers();
        $timestamp = $headers['X-Auth-Timestamp'] ?? null;
        $user_id = $headers['X-Auth-User'] ?? null;
        
        if (!$timestamp || !$user_id) {
            return false;
        }
        
        // Verify timestamp is recent
        if (abs(time() - intval($timestamp)) > 300) {
            return false;
        }
        
        // Recreate signature
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $body = file_get_contents('php://input') ?: '';
        
        $message = implode('|', [$method, $path, $timestamp, $user_id, $body]);
        $expected = hash_hmac('sha256', $message, $secret);
        
        if (hash_equals($expected, $signature)) {
            return [
                'user_id' => $user_id,
                'timestamp' => $timestamp
            ];
        }
        
        return false;
    }
    
    /**
     * Verify API key
     * 
     * @param string $key
     * @param array $options
     * @return array|false
     */
    private function verify_api_key($key, $options) {
        $valid_keys = $options['api_keys'] ?? [];
        
        if (isset($valid_keys[$key])) {
            return $valid_keys[$key];
        }
        
        // Check database if callback provided
        if (isset($options['api_key_callback']) && is_callable($options['api_key_callback'])) {
            return call_user_func($options['api_key_callback'], $key);
        }
        
        return false;
    }
    
    /**
     * Verify WordPress nonce
     * 
     * @param string $nonce
     * @param array $options
     * @return array|false
     */
    private function verify_nonce($nonce, $options) {
        $action = $options['nonce_action'] ?? 'wp_rest';
        
        if (wp_verify_nonce($nonce, $action)) {
            return [
                'user_id' => get_current_user_id(),
                'method' => 'nonce'
            ];
        }
        
        return false;
    }
    
    /**
     * Verify basic authentication
     * 
     * @param string $credentials
     * @param array $options
     * @return array|false
     */
    private function verify_basic_auth($credentials, $options) {
        $decoded = base64_decode($credentials);
        if (!$decoded || !strpos($decoded, ':')) {
            return false;
        }
        
        list($username, $password) = explode(':', $decoded, 2);
        
        // Verify with WordPress
        $user = wp_authenticate($username, $password);
        
        if (!is_wp_error($user)) {
            return [
                'user_id' => $user->ID,
                'method' => 'basic'
            ];
        }
        
        return false;
    }
    
    /**
     * Get token from cookie
     * 
     * @param string $cookie_name
     * @return string|null
     */
    public function get_token_from_cookie($cookie_name = 'zippicks_auth_token') {
        return $_COOKIE[$cookie_name] ?? null;
    }
    
    /**
     * Set token in cookie
     * 
     * @param string $token
     * @param int $expiry
     * @param string $cookie_name
     * @return bool
     */
    public function set_token_cookie($token, $expiry = 3600, $cookie_name = 'zippicks_auth_token') {
        return setcookie(
            $cookie_name,
            $token,
            time() + $expiry,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true // httponly
        );
    }
}