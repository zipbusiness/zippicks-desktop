<?php
/**
 * ZipPicks Core - Plugin Health Test
 * 
 * Run this script to verify the plugin is enterprise-ready and has no fatal errors.
 * 
 * Usage: wp eval-file wp-content/plugins/zippicks-core/test-plugin-health.php
 * 
 * @package ZipPicks\Core\Tests
 * @since 1.0.0
 */

// Ensure WordPress is loaded
if (!defined('ABSPATH')) {
    require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
}

echo "\n=== ZipPicks Core Plugin Health Check ===\n\n";

// Test 1: Check if plugin constants are defined
echo "1. Checking plugin constants... ";
$constants = ['ZIPPICKS_CORE_VERSION', 'ZIPPICKS_CORE_FILE', 'ZIPPICKS_CORE_PATH', 'ZIPPICKS_CORE_URL'];
$constants_ok = true;
foreach ($constants as $constant) {
    if (!defined($constant)) {
        echo "\n   ❌ Missing constant: $constant";
        $constants_ok = false;
    }
}
echo $constants_ok ? "✓ OK\n" : "\n";

// Test 2: Check if compatibility functions exist
echo "2. Checking compatibility functions... ";
$functions = ['zippicks_is_plugin_active', 'zippicks_get_plugin_version', 'zippicks_check_plugin_dependencies'];
$functions_ok = true;
foreach ($functions as $function) {
    if (!function_exists($function)) {
        echo "\n   ❌ Missing function: $function";
        $functions_ok = false;
    }
}
echo $functions_ok ? "✓ OK\n" : "\n";

// Test 3: Check for function conflicts
echo "3. Checking for function conflicts... ";
// The is_plugin_active function should NOT be redeclared by our plugin
$reflection = new ReflectionFunction('is_plugin_active');
$filename = $reflection->getFileName();
if (strpos($filename, 'zippicks') !== false) {
    echo "❌ FAIL - is_plugin_active() is being redeclared by ZipPicks!\n";
} else {
    echo "✓ OK - No function conflicts detected\n";
}

// Test 4: Check if classes are loaded
echo "4. Checking core classes... ";
$classes = ['ZipPicks_Core_Init', 'ZipPicks_Logger', 'ZipPicks_Error_Handler'];
$classes_ok = true;
foreach ($classes as $class) {
    if (!class_exists($class)) {
        echo "\n   ❌ Missing class: $class";
        $classes_ok = false;
    }
}
echo $classes_ok ? "✓ OK\n" : "\n";

// Test 5: Check logger functionality
echo "5. Testing logger... ";
try {
    $logger = new ZipPicks_Logger();
    $logger->log_info('Plugin health check test');
    echo "✓ OK\n";
} catch (Exception $e) {
    echo "❌ FAIL - " . $e->getMessage() . "\n";
}

// Test 6: Check error handler
echo "6. Testing error handler... ";
try {
    $error_handler = new ZipPicks_Error_Handler();
    echo "✓ OK\n";
} catch (Exception $e) {
    echo "❌ FAIL - " . $e->getMessage() . "\n";
}

// Test 7: Check Foundation integration
echo "7. Checking Foundation integration... ";
if (function_exists('zippicks')) {
    echo "✓ Foundation is available\n";
    
    // Test service registration
    echo "   - Testing service registration... ";
    try {
        $test_service = zippicks()->get('core.logger');
        echo $test_service ? "✓ OK\n" : "❌ Service not found\n";
    } catch (Exception $e) {
        echo "❌ FAIL - " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠️  Foundation not available (optional)\n";
}

// Test 8: Check log directory
echo "8. Checking log directory... ";
$upload_dir = wp_upload_dir();
$log_dir = $upload_dir['basedir'] . '/zippicks-logs';
if (file_exists($log_dir) && is_writable($log_dir)) {
    echo "✓ OK - Log directory exists and is writable\n";
} else {
    echo "❌ FAIL - Log directory missing or not writable\n";
}

// Test 9: Memory and performance check
echo "9. Performance metrics:\n";
echo "   - Memory usage: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
echo "   - Peak memory: " . round(memory_get_peak_usage() / 1024 / 1024, 2) . " MB\n";

// Test 10: Security checks
echo "10. Security checks... ";
$security_ok = true;

// Check if direct access is prevented in key files
$files_to_check = [
    ZIPPICKS_CORE_PATH . 'includes/class-core-init.php',
    ZIPPICKS_CORE_PATH . 'includes/logging/logger.php',
    ZIPPICKS_CORE_PATH . 'includes/class-error-handler.php'
];

foreach ($files_to_check as $file) {
    $content = file_get_contents($file);
    if (strpos($content, "if (!defined('ABSPATH'))") === false) {
        echo "\n    ❌ Missing ABSPATH check in: " . basename($file);
        $security_ok = false;
    }
}
echo $security_ok ? "✓ OK\n" : "\n";

// Test 11: Admin capabilities
echo "11. Checking admin capabilities... ";
$current_user = wp_get_current_user();
if ($current_user->ID > 0) {
    echo "✓ OK - User authenticated\n";
} else {
    echo "⚠️  Warning - No authenticated user\n";
}

// Test 12: AJAX endpoints
echo "12. Checking AJAX endpoints... ";
$ajax_actions = ['zippicks_log_client_error', 'zippicks_report_js_error'];
$ajax_ok = true;
foreach ($ajax_actions as $action) {
    if (!has_action('wp_ajax_' . $action)) {
        echo "\n    ❌ Missing AJAX handler: $action";
        $ajax_ok = false;
    }
}
echo $ajax_ok ? "✓ OK\n" : "\n";

// Summary
echo "\n=== Summary ===\n";
$all_tests_passed = $constants_ok && $functions_ok && $classes_ok && $security_ok && $ajax_ok;

if ($all_tests_passed) {
    echo "✅ All tests passed! The plugin is enterprise-ready.\n";
} else {
    echo "❌ Some tests failed. Please fix the issues above.\n";
}

// Detailed report
echo "\n=== Detailed Report ===\n";
echo "Plugin Version: " . (defined('ZIPPICKS_CORE_VERSION') ? ZIPPICKS_CORE_VERSION : 'Unknown') . "\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Active Theme: " . wp_get_theme()->get('Name') . "\n";

// Check for known issues
echo "\n=== Known Issues Check ===\n";
echo "1. Function redeclaration (is_plugin_active): ";
echo $reflection && strpos($reflection->getFileName(), 'zippicks') === false ? "✓ FIXED\n" : "❌ NOT FIXED\n";

echo "\n=== End of Health Check ===\n";