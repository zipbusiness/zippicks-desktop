<?php
/**
 * Test script for Master Critic Public class functionality
 * 
 * Usage: Load this file in WordPress admin or frontend to test shortcode functionality
 * 
 * IMPORTANT: This is a test file for development - remove before production
 */

// WordPress environment check
if (!defined('ABSPATH')) {
    echo "Error: This script must be run within WordPress environment.\n";
    echo "Access it via: yoursite.com/wp-content/plugins/zippicks-master-critic-v2/test-public-shortcode.php\n";
    exit;
}

// Check if plugin is active
if (!class_exists('ZipPicks_Master_Critic_Database')) {
    echo "<div style='background:#fcf2f2;border:1px solid #cc1818;color:#cc1818;padding:1em;margin:1em 0;'>";
    echo "Master Critic plugin is not active or not loaded properly.";
    echo "</div>";
    exit;
}

echo "<h1>Master Critic Public Class Test</h1>";

// Test 1: Check database tables exist
echo "<h2>Test 1: Database Tables</h2>";
if (ZipPicks_Master_Critic_Database::verify_tables()) {
    echo "<div style='background:#d1e7dd;border:1px solid #badbcc;color:#0f5132;padding:1em;margin:1em 0;'>";
    echo "✅ All database tables exist";
    echo "</div>";
} else {
    echo "<div style='background:#fcf2f2;border:1px solid #cc1818;color:#cc1818;padding:1em;margin:1em 0;'>";
    echo "❌ Database tables are missing";
    echo "</div>";
    exit;
}

// Test 2: Check for sample data
echo "<h2>Test 2: Sample Data</h2>";
global $wpdb;
$sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
$sets = $wpdb->get_results("SELECT id, set_name, set_slug, status, total_items FROM {$sets_table} ORDER BY created_at DESC LIMIT 5");

if (!empty($sets)) {
    echo "<div style='background:#d1e7dd;border:1px solid #badbcc;color:#0f5132;padding:1em;margin:1em 0;'>";
    echo "✅ Found " . count($sets) . " master sets:";
    echo "<ul>";
    foreach ($sets as $set) {
        echo "<li>ID: {$set->id} - {$set->set_name} ({$set->status}) - {$set->total_items} items</li>";
    }
    echo "</ul>";
    echo "</div>";
    
    // Test 3: Test shortcode with first available set
    $first_set = $sets[0];
    echo "<h2>Test 3: Shortcode Rendering</h2>";
    echo "<p>Testing shortcode: <code>[master_critic_list id=\"{$first_set->id}\"]</code></p>";
    
    if (function_exists('do_shortcode')) {
        $shortcode_output = do_shortcode("[master_critic_list id=\"{$first_set->id}\"]");
        
        if (strpos($shortcode_output, 'zpmc-master-list') !== false) {
            echo "<div style='background:#d1e7dd;border:1px solid #badbcc;color:#0f5132;padding:1em;margin:1em 0;'>";
            echo "✅ Shortcode rendered successfully";
            echo "</div>";
            
            echo "<h3>Shortcode Output:</h3>";
            echo "<div style='border:1px solid #ccc;padding:1em;background:#f9f9f9;'>";
            echo $shortcode_output;
            echo "</div>";
        } else {
            echo "<div style='background:#fcf2f2;border:1px solid #cc1818;color:#cc1818;padding:1em;margin:1em 0;'>";
            echo "❌ Shortcode failed to render properly";
            echo "<pre>" . esc_html($shortcode_output) . "</pre>";
            echo "</div>";
        }
    } else {
        echo "<div style='background:#fff3cd;border:1px solid #ffecb5;color:#664d03;padding:1em;margin:1em 0;'>";
        echo "⚠️ do_shortcode function not available in this context";
        echo "</div>";
    }
    
} else {
    echo "<div style='background:#fff3cd;border:1px solid #ffecb5;color:#664d03;padding:1em;margin:1em 0;'>";
    echo "⚠️ No master sets found in database. Create some test data first.";
    echo "</div>";
    
    echo "<h3>Sample SQL to create test data:</h3>";
    echo "<pre style='background:#f4f4f4;padding:1em;overflow-x:auto;'>";
    echo "INSERT INTO {$sets_table} (set_name, set_slug, zip_code, category, total_items, status, created_by) 
VALUES ('Best Restaurants in Pleasanton', 'best-pleasanton-restaurants', '94566', 'Restaurants', 3, 'published', 1);

SET @set_id = LAST_INSERT_ID();

INSERT INTO " . ZipPicks_Master_Critic_Database::get_items_table() . " 
(set_id, business_name, business_slug, score, tier, price_tier, summary, status) VALUES
(@set_id, 'Sample Restaurant 1', 'sample-restaurant-1', 8.5, 'Essential', '$$', 'Amazing Italian cuisine with authentic flavors.', 'active'),
(@set_id, 'Sample Restaurant 2', 'sample-restaurant-2', 7.8, 'Notable', '$$$', 'Upscale dining with innovative American dishes.', 'active'),
(@set_id, 'Sample Restaurant 3', 'sample-restaurant-3', 7.2, 'Worthy', '$', 'Great casual spot for families and quick meals.', 'active');";
    echo "</pre>";
}

// Test 4: Check shortcode registration
echo "<h2>Test 4: Shortcode Registration</h2>";
global $shortcode_tags;
if (isset($shortcode_tags['master_critic_list'])) {
    echo "<div style='background:#d1e7dd;border:1px solid #badbcc;color:#0f5132;padding:1em;margin:1em 0;'>";
    echo "✅ master_critic_list shortcode is registered";
    echo "</div>";
} else {
    echo "<div style='background:#fcf2f2;border:1px solid #cc1818;color:#cc1818;padding:1em;margin:1em 0;'>";
    echo "❌ master_critic_list shortcode is NOT registered";
    echo "</div>";
}

// Test 5: Class existence
echo "<h2>Test 5: Class Availability</h2>";
$required_classes = [
    'ZipPicks_Master_Critic_Public',
    'ZipPicks_Master_Critic_Database',
    'ZipPicks_Master_Critic_Schema_Integration'
];

foreach ($required_classes as $class_name) {
    if (class_exists($class_name)) {
        echo "<div style='background:#d1e7dd;border:1px solid #badbcc;color:#0f5132;padding:1em;margin:1em 0;'>";
        echo "✅ Class {$class_name} is available";
        echo "</div>";
    } else {
        echo "<div style='background:#fcf2f2;border:1px solid #cc1818;color:#cc1818;padding:1em;margin:1em 0;'>";
        echo "❌ Class {$class_name} is NOT available";
        echo "</div>";
    }
}

echo "<hr>";
echo "<p><strong>Test completed!</strong> If all tests pass, the Master Critic public functionality is working correctly.</p>";
echo "<p>To use the shortcode on a page or post, add: <code>[master_critic_list id=\"SET_ID\"]</code></p>";
echo "<p><small><em>Remember to remove this test file before going to production.</em></small></p>";
?>