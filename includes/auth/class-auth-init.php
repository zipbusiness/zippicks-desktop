<?php
/**
 * Auth Service Initialization
 * 
 * Bootstraps the authentication service and integrates it with
 * WordPress and the ZipPicks Foundation.
 * 
 * @package ZipPicks_Core
 * @subpackage Auth
 * @since 1.0.0
 */

namespace ZipPicks\Core\Auth;

class Auth_Init {
    
    /**
     * Initialize the auth service
     */
    public static function init() {
        // Register autoloading for auth classes
        self::register_autoloader();
        
        // Load helper functions
        require_once dirname(__FILE__) . '/functions-auth.php';
        
        // Initialize auth manager
        self::init_auth_manager();
        
        // Register custom roles and capabilities
        self::register_roles_and_capabilities();
        
        // Hook into WordPress
        self::register_hooks();
        
        // Register REST API extensions
        self::register_rest_extensions();
    }
    
    /**
     * Register autoloader for auth namespace
     */
    private static function register_autoloader() {
        spl_autoload_register(function ($class) {
            // Check if it's our auth namespace
            if (strpos($class, 'ZipPicks\\Core\\Auth\\') !== 0) {
                return;
            }
            
            // Remove namespace prefix
            $class_name = str_replace('ZipPicks\\Core\\Auth\\', '', $class);
            
            // Convert to filename
            // Handle both ZipPicksUser and ZP_Auth_Manager style names
            $filename = 'class-' . strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', str_replace('_', '-', $class_name))) . '.php';
            
            // Build path
            $file = ZIPPICKS_CORE_PATH . 'includes/auth/' . $filename;
            
            // Load if exists
            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
    
    /**
     * Initialize auth manager
     */
    private static function init_auth_manager() {
        // Get instance to trigger initialization
        $auth = ZP_Auth_Manager::get_instance();
        
        // Make auth manager globally available
        if (!function_exists('zippicks_auth')) {
            function zippicks_auth() {
                return ZP_Auth_Manager::get_instance();
            }
        }
    }
    
    /**
     * Register custom roles and capabilities
     */
    private static function register_roles_and_capabilities() {
        add_action('init', function() {
            // Critic role
            if (!get_role('critic')) {
                add_role('critic', __('Critic', 'zippicks-core'), [
                    'read' => true,
                    'edit_posts' => false,
                    'delete_posts' => false,
                    'publish_posts' => false,
                    'upload_files' => true,
                    
                    // Custom capabilities
                    'edit_businesses' => true,
                    'submit_reviews' => true,
                    'create_lists' => true,
                    'moderate_reviews' => true,
                    'view_analytics' => true
                ]);
            }
            
            // Business Owner role
            if (!get_role('business_owner')) {
                add_role('business_owner', __('Business Owner', 'zippicks-core'), [
                    'read' => true,
                    'edit_posts' => false,
                    'delete_posts' => false,
                    'publish_posts' => false,
                    'upload_files' => true,
                    
                    // Custom capabilities
                    'edit_own_business' => true,
                    'view_business_analytics' => true,
                    'respond_to_reviews' => true,
                    'manage_business_staff' => true,
                    'claim_business' => true
                ]);
            }
            
            // Add capabilities to administrator
            $admin = get_role('administrator');
            if ($admin) {
                // Critic capabilities
                $admin->add_cap('edit_businesses');
                $admin->add_cap('submit_reviews');
                $admin->add_cap('create_lists');
                $admin->add_cap('moderate_reviews');
                
                // Business owner capabilities
                $admin->add_cap('edit_own_business');
                $admin->add_cap('view_business_analytics');
                $admin->add_cap('respond_to_reviews');
                $admin->add_cap('manage_business_staff');
                $admin->add_cap('claim_business');
                
                // Admin-only capabilities
                $admin->add_cap('manage_zippicks_settings');
                $admin->add_cap('view_all_analytics');
                $admin->add_cap('manage_critics');
                $admin->add_cap('manage_all_businesses');
            }
        });
    }
    
    /**
     * Register WordPress hooks
     */
    private static function register_hooks() {
        // Clear auth on logout
        add_action('wp_logout', function($user_id) {
            $auth = zippicks_auth();
            $auth->invalidate_user_tokens($user_id);
            $auth->clear_auth();
        });
        
        // Refresh user cache on profile update
        add_action('profile_update', function($user_id) {
            $cache_key = ZipPicksUser::CACHE_PREFIX . $user_id;
            if (function_exists('zippicks') && zippicks()->has('cache')) {
                zippicks()->get('cache')->forget($cache_key);
            }
        });
        
        // Add custom user meta fields
        add_action('show_user_profile', [__CLASS__, 'add_user_fields']);
        add_action('edit_user_profile', [__CLASS__, 'add_user_fields']);
        add_action('personal_options_update', [__CLASS__, 'save_user_fields']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_user_fields']);
        
        // Handle token refresh headers
        add_action('send_headers', function() {
            if (headers_sent()) {
                return;
            }
            
            // Check for refreshed token header
            $headers = headers_list();
            foreach ($headers as $header) {
                if (strpos($header, 'X-Refreshed-Token:') === 0) {
                    // Token was refreshed, add cache control
                    header('Cache-Control: no-cache, must-revalidate');
                    break;
                }
            }
        });
    }
    
    /**
     * Register REST API extensions
     */
    private static function register_rest_extensions() {
        // Add authentication to REST API
        add_filter('rest_authentication_errors', function($error) {
            // If error already set, return it
            if (!empty($error)) {
                return $error;
            }
            
            // Try to authenticate
            $auth = zippicks_auth();
            $user = $auth->authenticate();
            
            // Set current user if authenticated
            if ($user) {
                wp_set_current_user($user->get_id());
            }
            
            return $error;
        });
        
        // Add CORS headers for API requests
        add_action('rest_api_init', function() {
            remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
            add_filter('rest_pre_serve_request', function($value) {
                $origin = get_http_origin();
                
                // Add CORS headers
                header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
                header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
                header('Access-Control-Allow-Headers: Authorization, X-Authorization, X-Auth-Token, X-API-Key, X-WP-Nonce, Content-Type');
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Max-Age: 86400');
                
                // Handle preflight
                if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                    exit(0);
                }
                
                return $value;
            });
        });
    }
    
    /**
     * Add custom user fields
     */
    public static function add_user_fields($user) {
        ?>
        <h3><?php _e('ZipPicks Profile', 'zippicks-core'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="zippicks_critic_profile_id"><?php _e('Critic Profile ID', 'zippicks-core'); ?></label></th>
                <td>
                    <input type="text" 
                           name="zippicks_critic_profile_id" 
                           id="zippicks_critic_profile_id" 
                           value="<?php echo esc_attr(get_user_meta($user->ID, 'zippicks_critic_profile_id', true)); ?>" 
                           class="regular-text" />
                    <p class="description"><?php _e('Internal critic profile identifier', 'zippicks-core'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th><label for="zippicks_subscription_tier"><?php _e('Subscription Tier', 'zippicks-core'); ?></label></th>
                <td>
                    <select name="zippicks_subscription_tier" id="zippicks_subscription_tier">
                        <?php
                        $current_tier = get_user_meta($user->ID, 'zippicks_subscription_tier', true) ?: 'free';
                        $tiers = ['free' => 'Free', 'premium' => 'Premium', 'pro' => 'Pro'];
                        foreach ($tiers as $value => $label) {
                            printf(
                                '<option value="%s"%s>%s</option>',
                                esc_attr($value),
                                selected($current_tier, $value, false),
                                esc_html($label)
                            );
                        }
                        ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save custom user fields
     */
    public static function save_user_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        
        // Save critic profile ID
        if (isset($_POST['zippicks_critic_profile_id'])) {
            update_user_meta($user_id, 'zippicks_critic_profile_id', sanitize_text_field($_POST['zippicks_critic_profile_id']));
        }
        
        // Save subscription tier
        if (isset($_POST['zippicks_subscription_tier'])) {
            update_user_meta($user_id, 'zippicks_subscription_tier', sanitize_text_field($_POST['zippicks_subscription_tier']));
        }
        
        // Clear user cache
        $cache_key = ZipPicksUser::CACHE_PREFIX . $user_id;
        if (function_exists('zippicks') && zippicks()->has('cache')) {
            zippicks()->get('cache')->forget($cache_key);
        }
    }
}