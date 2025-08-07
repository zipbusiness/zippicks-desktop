<?php
/**
 * REST API Test Examples for Master Critic V2
 * 
 * This file demonstrates how to test the REST API endpoints
 * DO NOT access this file directly in production
 */

// If this file is called directly, abort
if (!defined('WPINC')) {
    die('This is a development test file');
}

/**
 * Test REST API Endpoints
 */
function test_master_critic_rest_api() {
    ?>
    <div class="wrap">
        <h1>Master Critic V2 REST API Test</h1>
        
        <h2>Available Endpoints:</h2>
        
        <h3>1. List All Master Sets (Public)</h3>
        <p><strong>GET</strong> <code>/wp-json/zippicks/v1/master-sets</code></p>
        <p>Parameters:</p>
        <ul>
            <li><code>page</code> - Page number (default: 1)</li>
            <li><code>per_page</code> - Items per page (default: 20, max: 100)</li>
        </ul>
        
        <h4>Example cURL:</h4>
        <pre><code>curl -X GET "<?php echo home_url('/wp-json/zippicks/v1/master-sets?page=1&per_page=10'); ?>"</code></pre>
        
        <h3>2. Get Single Master Set (Public)</h3>
        <p><strong>GET</strong> <code>/wp-json/zippicks/v1/master-sets/{id}</code></p>
        <p>Parameters:</p>
        <ul>
            <li><code>id</code> - Master set ID (required)</li>
            <li><code>include_items</code> - Include items in response (default: true)</li>
        </ul>
        
        <h4>Example cURL:</h4>
        <pre><code>curl -X GET "<?php echo home_url('/wp-json/zippicks/v1/master-sets/1?include_items=true'); ?>"</code></pre>
        
        <h3>3. Import Master Set Data (Admin Only)</h3>
        <p><strong>POST</strong> <code>/wp-json/zippicks/v1/master-sets/import</code></p>
        <p>Requires: <code>manage_options</code> capability</p>
        
        <h4>Example cURL (with authentication):</h4>
        <pre><code>curl -X POST "<?php echo home_url('/wp-json/zippicks/v1/master-sets/import'); ?>" \
     -H "Content-Type: application/json" \
     -H "X-WP-Nonce: <?php echo wp_create_nonce('wp_rest'); ?>" \
     --cookie "wordpress_logged_in_cookie=YOUR_COOKIE" \
     -d '{
  "data": {
    "set_name": "Best Pizza in 10001",
    "zip_code": "10001",
    "category": "Pizza",
    "vertical": "Restaurants",
    "radius": 5,
    "categories": {
      "essential": [
        {
          "name": "Joe'\''s Pizza",
          "score": 9.2,
          "summary": "Classic NY slice",
          "price_tier": "$$",
          "neighborhood": "Greenwich Village"
        }
      ]
    }
  }
}'</code></pre>
        
        <h2>Response Examples:</h2>
        
        <h3>List Response:</h3>
        <pre><code>{
  "sets": [
    {
      "id": 1,
      "set_name": "Best Pizza in 10001",
      "set_slug": "best-pizza-in-10001",
      "zip_code": "10001",
      "category": "Pizza",
      "vertical": "Restaurants",
      "radius": 5,
      "total_items": 1,
      "status": "draft",
      "created_at": "2024-01-01 12:00:00",
      "updated_at": "2024-01-01 12:00:00"
    }
  ],
  "pagination": {
    "total": 1,
    "pages": 1,
    "page": 1,
    "per_page": 20
  }
}</code></pre>
        
        <h3>Single Item Response (with items):</h3>
        <pre><code>{
  "id": 1,
  "set_name": "Best Pizza in 10001",
  "set_slug": "best-pizza-in-10001",
  "zip_code": "10001",
  "category": "Pizza",
  "vertical": "Restaurants",
  "radius": 5,
  "total_items": 1,
  "status": "draft",
  "created_at": "2024-01-01 12:00:00",
  "updated_at": "2024-01-01 12:00:00",
  "items": [
    {
      "id": 1,
      "business_name": "Joe's Pizza",
      "business_slug": "joes-pizza",
      "score": 9.2,
      "tier": "essential",
      "price_tier": "$$",
      "neighborhood": "Greenwich Village",
      "address": null,
      "phone": null,
      "summary": "Classic NY slice",
      "position": 1,
      "verified": false,
      "business_id": null,
      "top_dishes": [],
      "pillar_scores": [],
      "vibes": [],
      "schema_payload": []
    }
  ]
}</code></pre>
        
        <h2>Testing with JavaScript:</h2>
        <pre><code>// Test GET request
fetch('/wp-json/zippicks/v1/master-sets')
  .then(response => response.json())
  .then(data => console.log(data));

// Test POST request (must be authenticated)
fetch('/wp-json/zippicks/v1/master-sets/import', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce
  },
  body: JSON.stringify({
    data: {
      set_name: "Test Import",
      categories: {
        essential: [
          {
            name: "Test Restaurant",
            score: 8.5,
            summary: "Test description"
          }
        ]
      }
    }
  })
})
.then(response => response.json())
.then(data => console.log(data));</code></pre>
        
        <h2>Error Responses:</h2>
        <ul>
            <li><strong>404</strong> - Master set not found</li>
            <li><strong>400</strong> - Bad request (invalid data)</li>
            <li><strong>401</strong> - Unauthorized (for import endpoint)</li>
            <li><strong>500</strong> - Database error</li>
        </ul>
        
    </div>
    <?php
}

// Only show if admin and in development
if (current_user_can('manage_options') && defined('WP_DEBUG') && WP_DEBUG) {
    add_action('admin_menu', function() {
        add_submenu_page(
            'tools.php',
            'Master Critic API Test',
            'MC API Test',
            'manage_options',
            'mc-api-test',
            'test_master_critic_rest_api'
        );
    });
}
?>