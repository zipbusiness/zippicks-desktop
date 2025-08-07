<?php
/**
 * Plugin Name:       ZipPicks Master Critic V2
 * Plugin URI:        https://zippicks.com/
 * Description:       Enterprise-grade restaurant ranking system with JSON import and Schema.org integration
 * Version:           2.0.0
 * Author:            ZipPicks
 * Author URI:        https://zippicks.com/
 * License:           Proprietary
 * Text Domain:       zippicks-master-critic
 * Requires at least: 6.0
 * Requires PHP:      8.0
 */

// If this file is called directly, abort
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('ZPMC_VERSION', '2.0.0');
define('ZPMC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZPMC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZPMC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Plugin activation
 */
function activate_zippicks_master_critic_v2() {
    require_once ZPMC_PLUGIN_DIR . 'includes/class-activator.php';
    ZipPicks_Master_Critic_Activator::activate();
}

/**
 * Plugin deactivation
 */
function deactivate_zippicks_master_critic_v2() {
    require_once ZPMC_PLUGIN_DIR . 'includes/class-deactivator.php';
    ZipPicks_Master_Critic_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_zippicks_master_critic_v2');
register_deactivation_hook(__FILE__, 'deactivate_zippicks_master_critic_v2');

/**
 * Core plugin class
 */
require ZPMC_PLUGIN_DIR . 'includes/class-master-critic.php';

/**
 * Initialize the plugin
 */
function run_zippicks_master_critic_v2() {
    $plugin = new ZipPicks_Master_Critic();
    $plugin->run();
}

// Check dependencies before running
add_action('plugins_loaded', function() {
    $errors = [];
    
    // Check PHP version
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        $errors[] = sprintf('ZipPicks Master Critic requires PHP 8.0 or higher. You are running PHP %s.', PHP_VERSION);
    }
    
    // Check for Core plugin
    if (!function_exists('zippicks')) {
        $errors[] = 'ZipPicks Master Critic requires the ZipPicks Core plugin to be active.';
    }
    
    // Display errors or run plugin
    if (!empty($errors)) {
        add_action('admin_notices', function() use ($errors) {
            foreach ($errors as $error) {
                echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
            }
        });
    } else {
        run_zippicks_master_critic_v2();
    }
});