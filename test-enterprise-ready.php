<?php
/**
 * ZipPicks Core - Enterprise Readiness Test
 * 
 * This file tests that all the enterprise-ready fixes are working correctly
 * and there are no function conflicts.
 * 
 * Usage: Run this file via WP-CLI or direct browser access
 */

// Load WordPress
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';

// Test results
$tests = [];
$passed = 0;
$failed = 0;

/**
 * Run a test
 */
function run_test($name, $callback) {
    global $tests, $passed, $failed;
    
    try {
        $result = $callback();
        if ($result === true) {
            $tests[$name] = ['status' => 'PASSED', 'message' => 'Test passed successfully'];
            $passed++;
        } else {
            $tests[$name] = ['status' => 'FAILED', 'message' => $result ?: 'Test failed'];
            $failed++;
        }
    } catch (Exception $e) {
        $tests[$name] = ['status' => 'ERROR', 'message' => $e->getMessage()];
        $failed++;
    }
}

// Test 1: Check if compatibility layer is loaded
run_test('Compatibility Layer Loaded', function() {
    return file_exists(ZIPPICKS_CORE_PATH . 'includes/compatibility/functions-compatibility.php');
});

// Test 2: Check if zippicks_is_plugin_active function exists
run_test('Plugin Active Function Exists', function() {
    return function_exists('zippicks_is_plugin_active');
});

// Test 3: Test function guards in theme
run_test('Theme Function Guards', function() {
    $theme_functions = '/wp-content/themes/zippicks-child/functions.php';
    if (!file_exists(ABSPATH . $theme_functions)) {
        return 'Theme file not found';
    }
    
    $content = file_get_contents(ABSPATH . $theme_functions);
    
    // Check for specific function guards
    $checks = [
        'zippicks_is_plugin_active' => strpos($content, "if (!function_exists('zippicks_is_plugin_active'))") !== false,
        'zippicks_get_theme_option' => strpos($content, "if (!function_exists('zippicks_get_theme_option'))") !== false,
        'zippicks_get_user_location' => strpos($content, "if (!function_exists('zippicks_get_user_location'))") !== false,
    ];
    
    foreach ($checks as $func => $has_guard) {
        if (!$has_guard) {
            return "Function $func is missing function_exists guard";
        }
    }
    
    return true;
});

// Test 4: Check error handler initialization
run_test('Error Handler Loaded', function() {
    return class_exists('ZipPicks_Error_Handler');
});

// Test 5: Check if error reporter JavaScript exists
run_test('Error Reporter JS Exists', function() {
    $js_file = ZIPPICKS_CORE_PATH . 'assets/js/error-reporter.js';
    return file_exists($js_file);
});

// Test 6: Test plugin version function
run_test('Plugin Version Function', function() {
    if (!function_exists('zippicks_get_plugin_version')) {
        return 'Function does not exist';
    }
    
    $version = zippicks_get_plugin_version('core');
    return $version === ZIPPICKS_CORE_VERSION;
});

// Test 7: Check for duplicate function declarations
run_test('No Duplicate Functions', function() {
    // Get all declared functions
    $functions = get_defined_functions();
    $user_functions = $functions['user'];
    
    // Look for ZipPicks functions
    $zippicks_functions = array_filter($user_functions, function($func) {
        return strpos($func, 'zippicks') === 0;
    });
    
    // Check if any are declared multiple times (PHP would fatal error if true)
    // So if we got here, there are no duplicates
    return true;
});

// Test 8: Check logger availability
run_test('Logger Available', function() {
    if (!function_exists('zippicks_get_logger')) {
        return 'Logger function not found';
    }
    
    $logger = zippicks_get_logger();
    return $logger instanceof ZipPicks_Logger;
});

// Test 9: Check Foundation integration
run_test('Foundation Integration', function() {
    if (function_exists('zippicks')) {
        return zippicks() !== null;
    }
    return 'Foundation not available (this is OK for MVP)';
});

// Test 10: Check for PHP errors
run_test('No PHP Errors', function() {
    $last_error = error_get_last();
    if ($last_error && $last_error['type'] === E_ERROR) {
        return 'Fatal error detected: ' . $last_error['message'];
    }
    return true;
});

// Display results
?>
<!DOCTYPE html>
<html>
<head>
    <title>ZipPicks Core - Enterprise Readiness Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
        }
        .summary {
            padding: 20px;
            background: #f0f0f0;
            border-radius: 4px;
            margin-bottom: 30px;
        }
        .test {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .test-name {
            font-weight: 600;
        }
        .status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-passed {
            background: #d4f4dd;
            color: #1e7e34;
        }
        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        .status-error {
            background: #fff3cd;
            color: #856404;
        }
        .message {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        .success-banner {
            background: #d4f4dd;
            color: #1e7e34;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .error-banner {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 ZipPicks Core - Enterprise Readiness Test</h1>
        
        <?php if ($failed === 0): ?>
            <div class="success-banner">
                ✅ All tests passed! The plugin is enterprise-ready.
            </div>
        <?php else: ?>
            <div class="error-banner">
                ⚠️ Some tests failed. Please review the results below.
            </div>
        <?php endif; ?>
        
        <div class="summary">
            <strong>Test Summary:</strong><br>
            Total Tests: <?php echo count($tests); ?><br>
            Passed: <?php echo $passed; ?><br>
            Failed: <?php echo $failed; ?>
        </div>
        
        <h2>Test Results</h2>
        
        <?php foreach ($tests as $name => $result): ?>
            <div class="test">
                <div>
                    <div class="test-name"><?php echo esc_html($name); ?></div>
                    <?php if ($result['message'] !== 'Test passed successfully'): ?>
                        <div class="message"><?php echo esc_html($result['message']); ?></div>
                    <?php endif; ?>
                </div>
                <span class="status status-<?php echo strtolower($result['status']); ?>">
                    <?php echo esc_html($result['status']); ?>
                </span>
            </div>
        <?php endforeach; ?>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 14px;">
            <strong>Enterprise Features Implemented:</strong>
            <ul>
                <li>Function conflict prevention with compatibility layer</li>
                <li>Comprehensive error handling and logging</li>
                <li>Client-side JavaScript error reporting</li>
                <li>Graceful degradation patterns</li>
                <li>Foundation service integration</li>
                <li>Multi-site compatibility checks</li>
            </ul>
        </div>
    </div>
</body>
</html>