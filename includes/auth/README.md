# ZipPicks Core Auth Service

Enterprise-grade authentication service providing JWT, HMAC, WP nonce, and cookie-based authentication for the ZipPicks platform.

## Quick Start

### Basic Usage

```php
// Get current authenticated user
$user = zp_get_current_user();
if ($user) {
    echo "Welcome, " . $user->get_display_name();
}

// Check if user is authenticated
if (zp_is_authenticated()) {
    // User is logged in
}

// Check capabilities
if (zp_user_can('edit_businesses')) {
    // User can edit businesses
}

// Check roles
if (zp_is_critic()) {
    // User is a critic
}
```

### REST API Controller

Extend `ZP_REST_Controller_Base` for automatic auth handling:

```php
use ZipPicks\Core\Auth\ZP_REST_Controller_Base;

class My_Controller extends ZP_REST_Controller_Base {
    
    protected $rest_base = 'my-endpoint';
    
    public function register_routes() {
        // Public endpoint
        register_rest_route($this->namespace, '/' . $this->rest_base . '/public', [
            $this->get_route_args('public', [
                'methods' => 'GET',
                'callback' => [$this, 'handle_public'],
            ], [
                'public' => true
            ])
        ]);
        
        // Authenticated endpoint
        register_rest_route($this->namespace, '/' . $this->rest_base . '/private', [
            $this->get_route_args('private', [
                'methods' => 'GET',
                'callback' => [$this, 'handle_private'],
            ])
        ]);
        
        // Role-specific endpoint
        register_rest_route($this->namespace, '/' . $this->rest_base . '/critic-only', [
            $this->get_route_args('critic', [
                'methods' => 'GET',
                'callback' => [$this, 'handle_critic'],
            ], [
                'roles' => ['critic', 'administrator']
            ])
        ]);
    }
}
```

## Authentication Methods

### 1. JWT (JSON Web Tokens)

```php
// Generate token for user
$token = zp_generate_auth_token($user_id);

// Use in API requests
$headers = [
    'Authorization' => 'Bearer ' . $token
];
```

### 2. HMAC Signatures

```php
// Generate HMAC signature
$signature_data = zp_generate_hmac_signature(
    $user_id,
    'POST',
    '/api/endpoint',
    $request_body
);

// Use in headers
$headers = [
    'X-Auth-User' => $signature_data['user_id'],
    'X-Auth-Timestamp' => $signature_data['timestamp'],
    'X-Auth-Signature' => $signature_data['signature']
];
```

### 3. WordPress Nonce

```php
// In JavaScript
wp.ajax.settings.beforeSend = function(xhr) {
    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
};

// Or in request
fetch('/wp-json/zippicks/v1/endpoint', {
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
});
```

### 4. Cookie Sessions

Automatically handled for logged-in WordPress users.

## User Object

The `ZipPicksUser` object provides comprehensive user information:

```php
$user = zp_get_current_user();

// Basic info
$user->get_id();
$user->get_email();
$user->get_display_name();
$user->get_profile_picture();

// Roles & capabilities
$user->get_roles();
$user->has_role('critic');
$user->can('edit_businesses');
$user->get_highest_role();

// ZipPicks-specific
$user->is_critic();
$user->is_business_owner();
$user->get_owned_businesses();
$user->owns_business($business_id);

// Taste profile
$user->get_taste_profile();
$user->get_favorite_vibes();
$user->get_dietary_restrictions();

// Social
$user->get_following_count();
$user->get_followers_count();
$user->get_review_count();

// Subscription
$user->get_subscription_tier();
$user->has_premium_subscription();
$user->get_subscription_features();
```

## Role Hierarchy

1. **Administrator** (100) - Full system access
2. **Critic** (80) - Can create reviews, lists, moderate
3. **Business Owner** (60) - Can manage own businesses
4. **Subscriber** (40) - Basic user
5. **Anonymous** (20) - Not logged in

## Custom Capabilities

### Critic Capabilities
- `edit_businesses` - Edit business information
- `submit_reviews` - Create and edit reviews
- `create_lists` - Create Top 10 lists
- `moderate_reviews` - Moderate other reviews
- `view_analytics` - View analytics data

### Business Owner Capabilities
- `edit_own_business` - Edit own business listing
- `view_business_analytics` - View business analytics
- `respond_to_reviews` - Respond to reviews
- `manage_business_staff` - Manage staff accounts
- `claim_business` - Claim unclaimed businesses

## REST API Authentication

### Route Configuration

```php
// Public route (no auth)
[
    'public' => true
]

// Authenticated route (any logged-in user)
[] // Empty config requires authentication

// Role-based route
[
    'roles' => ['critic', 'administrator']
]

// Capability-based route
[
    'capability' => 'edit_businesses'
]

// Minimum role level
[
    'min_role_level' => 60 // Business owner or higher
]

// Custom validation
[
    'callback' => function($user) {
        return $user && $user->has_premium_subscription();
    }
]

// Optional authentication
[
    'optional' => true // Works with or without auth
]
```

### Rate Limiting

Automatic rate limiting based on user tier:
- **Anonymous**: 60 requests/minute
- **Authenticated**: 120 requests/minute
- **Premium**: 300 requests/minute

## Helper Functions

```php
// Authentication checks
zp_is_authenticated()
zp_is_admin()
zp_is_critic()
zp_is_business_owner()

// User data
zp_get_current_user()
zp_get_current_user_id()
zp_get_user_taste_profile($user_id)
zp_get_user_subscription_tier($user_id)

// Capabilities
zp_user_can($capability)
zp_user_owns_business($business_id)
zp_has_premium_subscription()

// Auth operations
zp_generate_auth_token($user_id)
zp_invalidate_user_tokens($user_id)
zp_get_auth_headers($user_id)

// Requirements
zp_require_auth($message)
zp_require_capability($capability, $message)
```

## Security Best Practices

1. **Always validate tokens** server-side
2. **Use HTTPS** for all authenticated requests
3. **Implement rate limiting** for API endpoints
4. **Rotate secrets** regularly
5. **Log authentication events** for auditing

## Configuration

Add to `wp-config.php`:

```php
// JWT Secret (required for JWT auth)
define('ZIPPICKS_JWT_SECRET', 'your-secret-key-here');

// HMAC Secret (required for HMAC auth)
define('ZIPPICKS_HMAC_SECRET', 'your-hmac-secret-here');
```

## Troubleshooting

### Common Issues

1. **"Authentication required" error**
   - Check if user is logged in
   - Verify auth headers are sent
   - Check token expiration

2. **"Insufficient permissions" error**
   - Verify user has required role/capability
   - Check role hierarchy

3. **Token refresh not working**
   - Ensure headers aren't already sent
   - Check token expiry window (10 minutes)

### Debug Mode

Enable auth debugging:

```php
add_filter('zippicks_auth_debug', '__return_true');
```

This will log all authentication attempts to the error log.

## Examples

See `class-example-auth-controller.php` for complete implementation examples.