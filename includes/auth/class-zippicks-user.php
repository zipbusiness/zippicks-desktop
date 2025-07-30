<?php
/**
 * ZipPicksUser - Canonical User Object
 * 
 * Provides a unified user representation across all ZipPicks services
 * with caching, custom properties, and role management.
 * 
 * @package ZipPicks_Core
 * @subpackage Auth
 * @since 1.0.0
 */

namespace ZipPicks\Core\Auth;

use WP_User;

class ZipPicksUser {
    
    /**
     * WordPress user ID
     * @var int
     */
    private $id;
    
    /**
     * WordPress user object
     * @var WP_User|null
     */
    private $wp_user;
    
    /**
     * User metadata cache
     * @var array
     */
    private $metadata = [];
    
    /**
     * Capabilities cache
     * @var array
     */
    private $capabilities = [];
    
    /**
     * ZipPicks-specific properties
     * @var array
     */
    private $zp_properties = [];
    
    /**
     * Cache instance
     * @var mixed
     */
    private $cache;
    
    /**
     * Cache key prefix
     */
    const CACHE_PREFIX = 'zp_user_';
    
    /**
     * Cache TTL (1 hour)
     */
    const CACHE_TTL = 3600;
    
    /**
     * Role hierarchy for comparisons
     */
    const ROLE_HIERARCHY = [
        'administrator' => 100,
        'critic' => 80,
        'business_owner' => 60,
        'subscriber' => 40,
        'anonymous' => 20
    ];
    
    /**
     * Constructor
     * 
     * @param int $user_id WordPress user ID
     */
    public function __construct($user_id) {
        $this->id = intval($user_id);
        
        // Get cache service if available
        if (function_exists('zippicks') && zippicks()->has('cache')) {
            $this->cache = zippicks()->get('cache');
        }
        
        // Load user data
        $this->load_user_data();
    }
    
    /**
     * Load user data with caching
     */
    private function load_user_data() {
        // Try cache first
        $cache_key = self::CACHE_PREFIX . $this->id;
        
        if ($this->cache) {
            $cached_data = $this->cache->get($cache_key);
            if ($cached_data && is_array($cached_data)) {
                $this->metadata = $cached_data['metadata'] ?? [];
                $this->capabilities = $cached_data['capabilities'] ?? [];
                $this->zp_properties = $cached_data['zp_properties'] ?? [];
                return;
            }
        }
        
        // Load WordPress user
        if ($this->id > 0) {
            $this->wp_user = get_user_by('id', $this->id);
            
            if ($this->wp_user) {
                // Load metadata
                $this->load_metadata();
                
                // Load capabilities
                $this->load_capabilities();
                
                // Load ZipPicks properties
                $this->load_zp_properties();
                
                // Cache the data
                if ($this->cache) {
                    $cache_data = [
                        'metadata' => $this->metadata,
                        'capabilities' => $this->capabilities,
                        'zp_properties' => $this->zp_properties
                    ];
                    $this->cache->put($cache_key, $cache_data, self::CACHE_TTL);
                }
            }
        }
    }
    
    /**
     * Load user metadata
     */
    private function load_metadata() {
        if (!$this->wp_user) {
            return;
        }
        
        // Basic user data
        $this->metadata = [
            'email' => $this->wp_user->user_email,
            'display_name' => $this->wp_user->display_name,
            'username' => $this->wp_user->user_login,
            'registered' => $this->wp_user->user_registered,
            'roles' => $this->wp_user->roles,
            'first_name' => get_user_meta($this->id, 'first_name', true),
            'last_name' => get_user_meta($this->id, 'last_name', true),
            'profile_picture' => get_avatar_url($this->id)
        ];
    }
    
    /**
     * Load user capabilities
     */
    private function load_capabilities() {
        if (!$this->wp_user) {
            return;
        }
        
        // Get all capabilities
        $this->capabilities = [];
        foreach ($this->wp_user->allcaps as $cap => $granted) {
            if ($granted) {
                $this->capabilities[] = $cap;
            }
        }
        
        // Add ZipPicks custom capabilities
        $this->load_custom_capabilities();
    }
    
    /**
     * Load ZipPicks custom capabilities
     */
    private function load_custom_capabilities() {
        // Critic capabilities
        if ($this->has_role('critic')) {
            $this->capabilities[] = 'edit_businesses';
            $this->capabilities[] = 'submit_reviews';
            $this->capabilities[] = 'create_lists';
            $this->capabilities[] = 'moderate_reviews';
        }
        
        // Business owner capabilities
        if ($this->has_role('business_owner')) {
            $this->capabilities[] = 'edit_own_business';
            $this->capabilities[] = 'view_business_analytics';
            $this->capabilities[] = 'respond_to_reviews';
        }
        
        // Remove duplicates
        $this->capabilities = array_unique($this->capabilities);
    }
    
    /**
     * Load ZipPicks-specific properties
     */
    private function load_zp_properties() {
        if (!$this->id) {
            return;
        }
        
        // Critic status
        $this->zp_properties['is_critic'] = $this->has_role('critic');
        $this->zp_properties['critic_profile_id'] = get_user_meta($this->id, 'zippicks_critic_profile_id', true);
        
        // Business owner status
        $this->zp_properties['is_business_owner'] = $this->has_role('business_owner');
        $this->zp_properties['owned_businesses'] = get_user_meta($this->id, 'zippicks_owned_businesses', true) ?: [];
        
        // Taste profile data
        $this->zp_properties['taste_profile'] = [
            'vector_id' => get_user_meta($this->id, 'zippicks_taste_vector_id', true),
            'preferences' => get_user_meta($this->id, 'zippicks_taste_preferences', true) ?: [],
            'favorite_vibes' => get_user_meta($this->id, 'zippicks_favorite_vibes', true) ?: [],
            'dietary_restrictions' => get_user_meta($this->id, 'zippicks_dietary_restrictions', true) ?: []
        ];
        
        // Social data
        $this->zp_properties['following_count'] = intval(get_user_meta($this->id, 'zippicks_following_count', true));
        $this->zp_properties['followers_count'] = intval(get_user_meta($this->id, 'zippicks_followers_count', true));
        $this->zp_properties['review_count'] = intval(get_user_meta($this->id, 'zippicks_review_count', true));
        
        // Subscription status
        $this->zp_properties['subscription'] = [
            'tier' => get_user_meta($this->id, 'zippicks_subscription_tier', true) ?: 'free',
            'expires' => get_user_meta($this->id, 'zippicks_subscription_expires', true),
            'features' => $this->get_subscription_features()
        ];
    }
    
    /**
     * Get user ID
     */
    public function get_id() {
        return $this->id;
    }
    
    /**
     * Get WordPress user object
     */
    public function get_wp_user() {
        return $this->wp_user;
    }
    
    /**
     * Get user email
     */
    public function get_email() {
        return $this->metadata['email'] ?? '';
    }
    
    /**
     * Get display name
     */
    public function get_display_name() {
        return $this->metadata['display_name'] ?? '';
    }
    
    /**
     * Get username
     */
    public function get_username() {
        return $this->metadata['username'] ?? '';
    }
    
    /**
     * Get profile picture URL
     */
    public function get_profile_picture($size = 96) {
        return get_avatar_url($this->id, ['size' => $size]);
    }
    
    /**
     * Get user roles
     */
    public function get_roles() {
        return $this->metadata['roles'] ?? [];
    }
    
    /**
     * Check if user has role
     */
    public function has_role($role) {
        $roles = $this->get_roles();
        return in_array($role, $roles, true);
    }
    
    /**
     * Get highest role in hierarchy
     */
    public function get_highest_role() {
        $roles = $this->get_roles();
        $highest_role = 'anonymous';
        $highest_level = 0;
        
        foreach ($roles as $role) {
            $level = self::ROLE_HIERARCHY[$role] ?? 0;
            if ($level > $highest_level) {
                $highest_level = $level;
                $highest_role = $role;
            }
        }
        
        return $highest_role;
    }
    
    /**
     * Get role hierarchy level
     */
    public function get_role_level() {
        $highest_role = $this->get_highest_role();
        return self::ROLE_HIERARCHY[$highest_role] ?? 0;
    }
    
    /**
     * Check if user has capability
     */
    public function can($capability) {
        return in_array($capability, $this->capabilities, true);
    }
    
    /**
     * Get all capabilities
     */
    public function get_capabilities() {
        return $this->capabilities;
    }
    
    /**
     * Check if user is critic
     */
    public function is_critic() {
        return $this->zp_properties['is_critic'] ?? false;
    }
    
    /**
     * Get critic profile ID
     */
    public function get_critic_profile_id() {
        return $this->zp_properties['critic_profile_id'] ?? null;
    }
    
    /**
     * Check if user is business owner
     */
    public function is_business_owner() {
        return $this->zp_properties['is_business_owner'] ?? false;
    }
    
    /**
     * Get owned businesses
     */
    public function get_owned_businesses() {
        return $this->zp_properties['owned_businesses'] ?? [];
    }
    
    /**
     * Check if user owns specific business
     */
    public function owns_business($business_id) {
        $owned = $this->get_owned_businesses();
        return in_array($business_id, $owned, true);
    }
    
    /**
     * Get taste profile
     */
    public function get_taste_profile() {
        return $this->zp_properties['taste_profile'] ?? [];
    }
    
    /**
     * Get taste vector ID
     */
    public function get_taste_vector_id() {
        $profile = $this->get_taste_profile();
        return $profile['vector_id'] ?? null;
    }
    
    /**
     * Get favorite vibes
     */
    public function get_favorite_vibes() {
        $profile = $this->get_taste_profile();
        return $profile['favorite_vibes'] ?? [];
    }
    
    /**
     * Get dietary restrictions
     */
    public function get_dietary_restrictions() {
        $profile = $this->get_taste_profile();
        return $profile['dietary_restrictions'] ?? [];
    }
    
    /**
     * Get following count
     */
    public function get_following_count() {
        return $this->zp_properties['following_count'] ?? 0;
    }
    
    /**
     * Get followers count
     */
    public function get_followers_count() {
        return $this->zp_properties['followers_count'] ?? 0;
    }
    
    /**
     * Get review count
     */
    public function get_review_count() {
        return $this->zp_properties['review_count'] ?? 0;
    }
    
    /**
     * Get subscription tier
     */
    public function get_subscription_tier() {
        $subscription = $this->zp_properties['subscription'] ?? [];
        return $subscription['tier'] ?? 'free';
    }
    
    /**
     * Check if user has premium subscription
     */
    public function has_premium_subscription() {
        $tier = $this->get_subscription_tier();
        return in_array($tier, ['premium', 'pro', 'enterprise'], true);
    }
    
    /**
     * Get subscription features
     */
    private function get_subscription_features() {
        $tier = $this->get_subscription_tier();
        
        $features = [
            'free' => [
                'max_favorites' => 50,
                'max_lists' => 5,
                'advanced_search' => false,
                'api_access' => false,
                'priority_support' => false
            ],
            'premium' => [
                'max_favorites' => 500,
                'max_lists' => 50,
                'advanced_search' => true,
                'api_access' => false,
                'priority_support' => true
            ],
            'pro' => [
                'max_favorites' => -1, // unlimited
                'max_lists' => -1,
                'advanced_search' => true,
                'api_access' => true,
                'priority_support' => true
            ]
        ];
        
        return $features[$tier] ?? $features['free'];
    }
    
    /**
     * Get subscription features
     */
    public function get_subscription_features() {
        $subscription = $this->zp_properties['subscription'] ?? [];
        return $subscription['features'] ?? [];
    }
    
    /**
     * Update cached data
     */
    public function refresh_cache() {
        if ($this->cache) {
            $cache_key = self::CACHE_PREFIX . $this->id;
            $this->cache->forget($cache_key);
        }
        
        // Reload data
        $this->load_user_data();
    }
    
    /**
     * Convert to array for serialization
     */
    public function to_array() {
        return [
            'id' => $this->id,
            'email' => $this->get_email(),
            'display_name' => $this->get_display_name(),
            'username' => $this->get_username(),
            'profile_picture' => $this->get_profile_picture(),
            'roles' => $this->get_roles(),
            'highest_role' => $this->get_highest_role(),
            'is_critic' => $this->is_critic(),
            'is_business_owner' => $this->is_business_owner(),
            'subscription_tier' => $this->get_subscription_tier(),
            'capabilities' => $this->get_capabilities()
        ];
    }
    
    /**
     * Convert to JSON
     */
    public function to_json() {
        return json_encode($this->to_array());
    }
}