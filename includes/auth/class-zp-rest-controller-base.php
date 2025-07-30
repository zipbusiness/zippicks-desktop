<?php
/**
 * ZP REST Controller Base
 * 
 * Base class for all ZipPicks REST API controllers with built-in
 * authentication, authorization, and common functionality.
 * 
 * @package ZipPicks_Core
 * @subpackage Auth
 * @since 1.0.0
 */

namespace ZipPicks\Core\Auth;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Exception;

abstract class ZP_REST_Controller_Base extends WP_REST_Controller {
    
    /**
     * Namespace for routes
     * @var string
     */
    protected $namespace = 'zippicks/v1';
    
    /**
     * Auth manager instance
     * @var ZP_Auth_Manager
     */
    protected $auth;
    
    /**
     * Logger instance
     * @var mixed
     */
    protected $logger;
    
    /**
     * Cache instance
     * @var mixed
     */
    protected $cache;
    
    /**
     * Current authenticated user
     * @var ZipPicksUser|null
     */
    protected $current_user = null;
    
    /**
     * Rate limit configuration
     * @var array
     */
    protected $rate_limits = [
        'default' => [
            'requests' => 60,
            'window' => 60 // seconds
        ],
        'authenticated' => [
            'requests' => 120,
            'window' => 60
        ],
        'premium' => [
            'requests' => 300,
            'window' => 60
        ]
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        // Get auth manager
        $this->auth = ZP_Auth_Manager::get_instance();
        
        // Get Foundation services
        if (function_exists('zippicks')) {
            $this->logger = zippicks()->get('logger');
            $this->cache = zippicks()->get('cache');
        }
    }
    
    /**
     * Register routes - must be implemented by subclasses
     */
    abstract public function register_routes();
    
    /**
     * Get route with auth requirements
     * 
     * @param string $route Route path
     * @param array $args Route arguments
     * @param array $auth_config Authentication configuration
     * @return array
     */
    protected function get_route_args($route, $args = [], $auth_config = []) {
        $defaults = [
            'methods' => 'GET',
            'callback' => [$this, 'handle_request'],
            'permission_callback' => [$this, 'check_permissions'],
            'args' => $this->get_default_args()
        ];
        
        // Merge with provided args
        $route_args = array_merge($defaults, $args);
        
        // Add auth configuration to args
        if (!empty($auth_config)) {
            $route_args['auth_config'] = $auth_config;
        }
        
        return $route_args;
    }
    
    /**
     * Default permission callback
     * 
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_permissions($request) {
        // Get auth configuration from route
        $route = $request->get_route();
        $auth_config = $this->get_route_auth_config($request);
        
        // Log request
        if ($this->logger) {
            $this->logger->debug('REST request', [
                'route' => $route,
                'method' => $request->get_method(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        }
        
        // Check rate limiting first
        $rate_limit_check = $this->check_rate_limit($request);
        if (is_wp_error($rate_limit_check)) {
            return $rate_limit_check;
        }
        
        // No auth required
        if (isset($auth_config['public']) && $auth_config['public'] === true) {
            return true;
        }
        
        // Authenticate user
        $this->current_user = $this->auth->authenticate([
            'headers' => $this->get_headers_from_request($request)
        ]);
        
        // Authentication required but failed
        if (!$this->current_user && (!isset($auth_config['optional']) || !$auth_config['optional'])) {
            return new WP_Error(
                'rest_unauthorized',
                __('Authentication required', 'zippicks-core'),
                ['status' => 401]
            );
        }
        
        // Check required capabilities
        if (isset($auth_config['capability'])) {
            if (!$this->current_user || !$this->current_user->can($auth_config['capability'])) {
                return new WP_Error(
                    'rest_forbidden',
                    __('Insufficient permissions', 'zippicks-core'),
                    ['status' => 403]
                );
            }
        }
        
        // Check required roles
        if (isset($auth_config['roles']) && is_array($auth_config['roles'])) {
            $has_role = false;
            foreach ($auth_config['roles'] as $role) {
                if ($this->current_user && $this->current_user->has_role($role)) {
                    $has_role = true;
                    break;
                }
            }
            
            if (!$has_role) {
                return new WP_Error(
                    'rest_forbidden',
                    __('Required role not found', 'zippicks-core'),
                    ['status' => 403]
                );
            }
        }
        
        // Check minimum role level
        if (isset($auth_config['min_role_level'])) {
            $user_level = $this->current_user ? $this->current_user->get_role_level() : 0;
            if ($user_level < $auth_config['min_role_level']) {
                return new WP_Error(
                    'rest_forbidden',
                    __('Insufficient role level', 'zippicks-core'),
                    ['status' => 403]
                );
            }
        }
        
        // Custom permission callback
        if (isset($auth_config['callback']) && is_callable($auth_config['callback'])) {
            return call_user_func($auth_config['callback'], $this->current_user, $request);
        }
        
        return true;
    }
    
    /**
     * Handle request with error handling
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_request($request) {
        try {
            // Get the actual callback method
            $route = $request->get_route();
            $method = $request->get_method();
            $callback_method = $this->get_callback_method($route, $method);
            
            if (!method_exists($this, $callback_method)) {
                throw new Exception("Callback method {$callback_method} not found");
            }
            
            // Call the method
            $response = $this->$callback_method($request);
            
            // Ensure it's a proper response
            if (!($response instanceof WP_REST_Response) && !is_wp_error($response)) {
                $response = new WP_REST_Response($response);
            }
            
            // Add common headers
            if ($response instanceof WP_REST_Response) {
                $response->header('X-ZipPicks-Version', ZIPPICKS_CORE_VERSION);
                
                // Add auth method if authenticated
                if ($this->current_user) {
                    $response->header('X-Auth-Method', $this->auth->get_auth_method());
                }
            }
            
            return $response;
            
        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('REST request failed', [
                    'route' => $route,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            return new WP_Error(
                'rest_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }
    
    /**
     * Check rate limiting
     * 
     * @param WP_REST_Request $request
     * @return true|WP_Error
     */
    protected function check_rate_limit($request) {
        if (!$this->cache) {
            return true; // No cache, can't rate limit
        }
        
        // Determine rate limit tier
        $tier = 'default';
        if ($this->current_user) {
            $tier = $this->current_user->has_premium_subscription() ? 'premium' : 'authenticated';
        }
        
        $limits = $this->rate_limits[$tier] ?? $this->rate_limits['default'];
        
        // Get identifier (IP for anonymous, user ID for authenticated)
        $identifier = $this->current_user 
            ? 'user_' . $this->current_user->get_id()
            : 'ip_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        
        // Rate limit key
        $key = 'rate_limit_' . $identifier . '_' . $request->get_route();
        
        // Get current count
        $current = intval($this->cache->get($key, 0));
        
        if ($current >= $limits['requests']) {
            return new WP_Error(
                'rest_rate_limited',
                __('Rate limit exceeded', 'zippicks-core'),
                [
                    'status' => 429,
                    'retry_after' => $limits['window']
                ]
            );
        }
        
        // Increment counter
        $this->cache->put($key, $current + 1, $limits['window']);
        
        return true;
    }
    
    /**
     * Get headers from request
     * 
     * @param WP_REST_Request $request
     * @return array
     */
    protected function get_headers_from_request($request) {
        $headers = [];
        
        // Get all headers
        foreach ($request->get_headers() as $key => $value) {
            $headers[$key] = is_array($value) ? $value[0] : $value;
        }
        
        return $headers;
    }
    
    /**
     * Get route auth configuration
     * 
     * @param WP_REST_Request $request
     * @return array
     */
    protected function get_route_auth_config($request) {
        $route = $request->get_route();
        $method = $request->get_method();
        
        // This should be overridden in subclasses to provide route-specific config
        return [];
    }
    
    /**
     * Get callback method name
     * 
     * @param string $route
     * @param string $method
     * @return string
     */
    protected function get_callback_method($route, $method) {
        // This should be overridden in subclasses
        return strtolower($method) . '_item';
    }
    
    /**
     * Get default argument validation
     * 
     * @return array
     */
    protected function get_default_args() {
        return [
            'page' => [
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
                'sanitize_callback' => 'absint'
            ],
            'per_page' => [
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100,
                'sanitize_callback' => 'absint'
            ],
            'search' => [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'orderby' => [
                'type' => 'string',
                'default' => 'date',
                'enum' => ['date', 'title', 'id', 'modified'],
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'order' => [
                'type' => 'string',
                'default' => 'desc',
                'enum' => ['asc', 'desc'],
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ];
    }
    
    /**
     * Format success response
     * 
     * @param mixed $data
     * @param string $message
     * @param int $status
     * @return WP_REST_Response
     */
    protected function success($data = null, $message = '', $status = 200) {
        $response = [
            'success' => true,
            'data' => $data
        ];
        
        if ($message) {
            $response['message'] = $message;
        }
        
        return new WP_REST_Response($response, $status);
    }
    
    /**
     * Format error response
     * 
     * @param string $message
     * @param string $code
     * @param int $status
     * @param array $data
     * @return WP_Error
     */
    protected function error($message, $code = 'rest_error', $status = 400, $data = []) {
        $error_data = array_merge(['status' => $status], $data);
        return new WP_Error($code, $message, $error_data);
    }
    
    /**
     * Get current authenticated user
     * 
     * @return ZipPicksUser|null
     */
    protected function get_current_user() {
        return $this->current_user;
    }
    
    /**
     * Require authentication
     * 
     * @return ZipPicksUser|WP_Error
     */
    protected function require_auth() {
        if (!$this->current_user) {
            return $this->error(
                __('Authentication required', 'zippicks-core'),
                'rest_unauthorized',
                401
            );
        }
        
        return $this->current_user;
    }
    
    /**
     * Require capability
     * 
     * @param string $capability
     * @return true|WP_Error
     */
    protected function require_capability($capability) {
        $user = $this->require_auth();
        if (is_wp_error($user)) {
            return $user;
        }
        
        if (!$user->can($capability)) {
            return $this->error(
                __('Insufficient permissions', 'zippicks-core'),
                'rest_forbidden',
                403
            );
        }
        
        return true;
    }
    
    /**
     * Log API action
     * 
     * @param string $action
     * @param array $data
     */
    protected function log_action($action, $data = []) {
        if (!$this->logger) {
            return;
        }
        
        $log_data = array_merge([
            'action' => $action,
            'user_id' => $this->current_user ? $this->current_user->get_id() : 0,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ], $data);
        
        $this->logger->info('API action', $log_data);
    }
}