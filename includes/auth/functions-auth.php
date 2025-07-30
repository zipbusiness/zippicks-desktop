<?php
/**
 * Auth Helper Functions
 * 
 * Global helper functions for authentication throughout ZipPicks.
 * 
 * @package ZipPicks_Core
 * @subpackage Auth
 * @since 1.0.0
 */

use ZipPicks\Core\Auth\ZP_Auth_Manager;
use ZipPicks\Core\Auth\ZipPicksUser;

if (!function_exists('zippicks_auth')) {
    /**
     * Get the auth manager instance
     * 
     * @return ZP_Auth_Manager
     */
    function zippicks_auth() {
        return ZP_Auth_Manager::get_instance();
    }
}

if (!function_exists('zp_get_current_user')) {
    /**
     * Get current authenticated ZipPicks user
     * 
     * @return ZipPicksUser|null
     */
    function zp_get_current_user() {
        return zippicks_auth()->get_current_user();
    }
}

if (!function_exists('zp_get_current_user_id')) {
    /**
     * Get current authenticated user ID
     * 
     * @return int
     */
    function zp_get_current_user_id() {
        return zippicks_auth()->get_current_user_id();
    }
}

if (!function_exists('zp_is_authenticated')) {
    /**
     * Check if user is authenticated
     * 
     * @return bool
     */
    function zp_is_authenticated() {
        return zippicks_auth()->is_authenticated();
    }
}

if (!function_exists('zp_user_can')) {
    /**
     * Check if current user has capability
     * 
     * @param string $capability
     * @return bool
     */
    function zp_user_can($capability) {
        return zippicks_auth()->can($capability);
    }
}

if (!function_exists('zp_is_admin')) {
    /**
     * Check if current user is admin
     * 
     * @return bool
     */
    function zp_is_admin() {
        return zippicks_auth()->is_admin();
    }
}

if (!function_exists('zp_is_critic')) {
    /**
     * Check if current user is a critic
     * 
     * @return bool
     */
    function zp_is_critic() {
        $user = zp_get_current_user();
        return $user && $user->is_critic();
    }
}

if (!function_exists('zp_is_business_owner')) {
    /**
     * Check if current user is a business owner
     * 
     * @return bool
     */
    function zp_is_business_owner() {
        $user = zp_get_current_user();
        return $user && $user->is_business_owner();
    }
}

if (!function_exists('zp_user_owns_business')) {
    /**
     * Check if current user owns a specific business
     * 
     * @param int $business_id
     * @return bool
     */
    function zp_user_owns_business($business_id) {
        $user = zp_get_current_user();
        return $user && $user->owns_business($business_id);
    }
}

if (!function_exists('zp_has_premium_subscription')) {
    /**
     * Check if current user has premium subscription
     * 
     * @return bool
     */
    function zp_has_premium_subscription() {
        $user = zp_get_current_user();
        return $user && $user->has_premium_subscription();
    }
}

if (!function_exists('zp_generate_auth_token')) {
    /**
     * Generate auth token for user
     * 
     * @param int $user_id
     * @param array $additional_claims
     * @return string|false
     */
    function zp_generate_auth_token($user_id, $additional_claims = []) {
        return zippicks_auth()->generate_token($user_id, $additional_claims);
    }
}

if (!function_exists('zp_generate_hmac_signature')) {
    /**
     * Generate HMAC signature for request
     * 
     * @param int $user_id
     * @param string $method
     * @param string $path
     * @param string $body
     * @return array
     */
    function zp_generate_hmac_signature($user_id, $method, $path, $body = '') {
        return zippicks_auth()->generate_hmac_signature($user_id, $method, $path, $body);
    }
}

if (!function_exists('zp_invalidate_user_tokens')) {
    /**
     * Invalidate all tokens for a user
     * 
     * @param int $user_id
     */
    function zp_invalidate_user_tokens($user_id) {
        zippicks_auth()->invalidate_user_tokens($user_id);
    }
}

if (!function_exists('zp_get_auth_headers')) {
    /**
     * Get authorization headers for API requests
     * 
     * @param int|null $user_id Optional user ID, defaults to current WP user
     * @return array
     */
    function zp_get_auth_headers($user_id = null) {
        if ($user_id === null) {
            // Use WordPress function directly - NO auth cascade
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return [];
        }
        
        $token = zp_generate_auth_token($user_id);
        if (!$token) {
            return [];
        }
        
        return [
            'Authorization' => 'Bearer ' . $token,
            'X-ZipPicks-User' => $user_id
        ];
    }
}

if (!function_exists('zp_require_auth')) {
    /**
     * Require authentication or die with error
     * 
     * @param string $message Optional error message
     */
    function zp_require_auth($message = null) {
        if (!zp_is_authenticated()) {
            $message = $message ?: __('Authentication required', 'zippicks-core');
            wp_die($message, __('Unauthorized', 'zippicks-core'), ['response' => 401]);
        }
    }
}

if (!function_exists('zp_require_capability')) {
    /**
     * Require capability or die with error
     * 
     * @param string $capability
     * @param string $message Optional error message
     */
    function zp_require_capability($capability, $message = null) {
        zp_require_auth();
        
        if (!zp_user_can($capability)) {
            $message = $message ?: __('Insufficient permissions', 'zippicks-core');
            wp_die($message, __('Forbidden', 'zippicks-core'), ['response' => 403]);
        }
    }
}

if (!function_exists('zp_get_user_taste_profile')) {
    /**
     * Get user's taste profile
     * 
     * @param int|null $user_id Optional user ID, defaults to current user
     * @return array
     */
    function zp_get_user_taste_profile($user_id = null) {
        if ($user_id === null) {
            $user = zp_get_current_user();
            if (!$user) {
                return [];
            }
            return $user->get_taste_profile();
        }
        
        $user = new ZipPicksUser($user_id);
        return $user->get_taste_profile();
    }
}

if (!function_exists('zp_get_user_subscription_tier')) {
    /**
     * Get user's subscription tier
     * 
     * @param int|null $user_id Optional user ID, defaults to current user
     * @return string
     */
    function zp_get_user_subscription_tier($user_id = null) {
        if ($user_id === null) {
            $user = zp_get_current_user();
            if (!$user) {
                return 'free';
            }
            return $user->get_subscription_tier();
        }
        
        $user = new ZipPicksUser($user_id);
        return $user->get_subscription_tier();
    }
}