<?php
/**
 * Example Auth Controller
 * 
 * Demonstrates how to use the ZP_REST_Controller_Base with
 * various authentication configurations.
 * 
 * @package ZipPicks_Core
 * @subpackage Auth
 * @since 1.0.0
 */

namespace ZipPicks\Core\Auth;

class Example_Auth_Controller extends ZP_REST_Controller_Base {
    
    /**
     * Route base
     * @var string
     */
    protected $rest_base = 'auth-example';
    
    /**
     * Register routes
     */
    public function register_routes() {
        // Public endpoint - no auth required
        register_rest_route($this->namespace, '/' . $this->rest_base . '/public', [
            $this->get_route_args('public', [
                'methods' => 'GET',
                'callback' => [$this, 'get_public_data'],
            ], [
                'public' => true
            ])
        ]);
        
        // Authenticated endpoint - any logged in user
        register_rest_route($this->namespace, '/' . $this->rest_base . '/user', [
            $this->get_route_args('user', [
                'methods' => 'GET',
                'callback' => [$this, 'get_user_data'],
            ])
        ]);
        
        // Critic only endpoint
        register_rest_route($this->namespace, '/' . $this->rest_base . '/critic', [
            $this->get_route_args('critic', [
                'methods' => 'GET',
                'callback' => [$this, 'get_critic_data'],
            ], [
                'roles' => ['critic', 'administrator']
            ])
        ]);
        
        // Business owner endpoint
        register_rest_route($this->namespace, '/' . $this->rest_base . '/business', [
            $this->get_route_args('business', [
                'methods' => 'GET',
                'callback' => [$this, 'get_business_data'],
            ], [
                'capability' => 'edit_own_business'
            ])
        ]);
        
        // Admin only endpoint
        register_rest_route($this->namespace, '/' . $this->rest_base . '/admin', [
            $this->get_route_args('admin', [
                'methods' => 'GET',
                'callback' => [$this, 'get_admin_data'],
            ], [
                'capability' => 'manage_options'
            ])
        ]);
        
        // Premium users only
        register_rest_route($this->namespace, '/' . $this->rest_base . '/premium', [
            $this->get_route_args('premium', [
                'methods' => 'GET',
                'callback' => [$this, 'get_premium_data'],
            ], [
                'callback' => function($user) {
                    return $user && $user->has_premium_subscription();
                }
            ])
        ]);
        
        // Optional auth endpoint
        register_rest_route($this->namespace, '/' . $this->rest_base . '/optional', [
            $this->get_route_args('optional', [
                'methods' => 'GET',
                'callback' => [$this, 'get_optional_auth_data'],
            ], [
                'optional' => true
            ])
        ]);
    }
    
    /**
     * Get route auth configuration
     */
    protected function get_route_auth_config($request) {
        $route = $request->get_route();
        
        // Define auth configs for each route
        $configs = [
            '/zippicks/v1/auth-example/public' => ['public' => true],
            '/zippicks/v1/auth-example/user' => [],
            '/zippicks/v1/auth-example/critic' => ['roles' => ['critic', 'administrator']],
            '/zippicks/v1/auth-example/business' => ['capability' => 'edit_own_business'],
            '/zippicks/v1/auth-example/admin' => ['capability' => 'manage_options'],
            '/zippicks/v1/auth-example/premium' => [
                'callback' => function($user) {
                    return $user && $user->has_premium_subscription();
                }
            ],
            '/zippicks/v1/auth-example/optional' => ['optional' => true]
        ];
        
        return $configs[$route] ?? [];
    }
    
    /**
     * Get callback method name
     */
    protected function get_callback_method($route, $method) {
        $route_map = [
            '/zippicks/v1/auth-example/public' => 'get_public_data',
            '/zippicks/v1/auth-example/user' => 'get_user_data',
            '/zippicks/v1/auth-example/critic' => 'get_critic_data',
            '/zippicks/v1/auth-example/business' => 'get_business_data',
            '/zippicks/v1/auth-example/admin' => 'get_admin_data',
            '/zippicks/v1/auth-example/premium' => 'get_premium_data',
            '/zippicks/v1/auth-example/optional' => 'get_optional_auth_data'
        ];
        
        return $route_map[$route] ?? parent::get_callback_method($route, $method);
    }
    
    /**
     * Public endpoint handler
     */
    public function get_public_data($request) {
        $this->log_action('public_access');
        
        return $this->success([
            'message' => 'This is public data accessible to everyone',
            'timestamp' => current_time('mysql'),
            'auth_method' => $this->auth->get_auth_method() ?: 'none'
        ]);
    }
    
    /**
     * Authenticated user endpoint
     */
    public function get_user_data($request) {
        $user = $this->get_current_user();
        
        $this->log_action('user_data_access');
        
        return $this->success([
            'user' => $user->to_array(),
            'auth_method' => $this->auth->get_auth_method()
        ]);
    }
    
    /**
     * Critic endpoint
     */
    public function get_critic_data($request) {
        $user = $this->get_current_user();
        
        $this->log_action('critic_data_access');
        
        return $this->success([
            'critic_profile_id' => $user->get_critic_profile_id(),
            'review_count' => $user->get_review_count(),
            'following_count' => $user->get_following_count(),
            'followers_count' => $user->get_followers_count()
        ]);
    }
    
    /**
     * Business owner endpoint
     */
    public function get_business_data($request) {
        $user = $this->get_current_user();
        
        $this->log_action('business_data_access');
        
        return $this->success([
            'owned_businesses' => $user->get_owned_businesses(),
            'can_claim_business' => $user->can('claim_business'),
            'subscription_tier' => $user->get_subscription_tier()
        ]);
    }
    
    /**
     * Admin endpoint
     */
    public function get_admin_data($request) {
        $this->log_action('admin_data_access');
        
        // Get system stats
        $stats = [
            'total_users' => count_users()['total_users'],
            'total_critics' => count(get_users(['role' => 'critic'])),
            'total_business_owners' => count(get_users(['role' => 'business_owner'])),
            'auth_method' => $this->auth->get_auth_method()
        ];
        
        return $this->success($stats);
    }
    
    /**
     * Premium users endpoint
     */
    public function get_premium_data($request) {
        $user = $this->get_current_user();
        
        $this->log_action('premium_data_access');
        
        return $this->success([
            'subscription' => [
                'tier' => $user->get_subscription_tier(),
                'features' => $user->get_subscription_features()
            ],
            'taste_profile' => $user->get_taste_profile()
        ]);
    }
    
    /**
     * Optional auth endpoint
     */
    public function get_optional_auth_data($request) {
        $user = $this->get_current_user();
        
        $this->log_action('optional_auth_access', [
            'authenticated' => $user !== null
        ]);
        
        if ($user) {
            return $this->success([
                'personalized' => true,
                'user_id' => $user->get_id(),
                'display_name' => $user->get_display_name(),
                'taste_profile' => $user->get_taste_profile()
            ]);
        }
        
        return $this->success([
            'personalized' => false,
            'message' => 'Login for personalized data'
        ]);
    }
}