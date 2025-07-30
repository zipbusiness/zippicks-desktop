<?php
/**
 * Core initialization class
 *
 * @package ZipPicks\Core
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ZipPicks Core initialization
 */
class ZipPicks_Core_Init {
    
    /**
     * Logger instance
     *
     * @var ZipPicks_Logger
     */
    private $logger;
    
    /**
     * Error handler instance
     *
     * @var ZipPicks_Error_Handler
     */
    private $error_handler;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new ZipPicks_Logger();
        
        // Initialize error handler for enterprise-grade error tracking
        if (class_exists('ZipPicks_Error_Handler')) {
            $this->error_handler = new ZipPicks_Error_Handler();
        }
    }
    
    /**
     * Initialize core functionality
     */
    public function init() {
        // Admin hooks
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Frontend hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        
        // Global hooks
        add_action('init', [$this, 'register_global_hooks']);
        add_action('wp_ajax_zippicks_log_client_error', [$this, 'handle_client_error_logging']);
        
        // Foundation integration hooks
        if (function_exists('zippicks')) {
            add_action('zippicks_init', [$this, 'foundation_integration']);
        }
        
        // Performance monitoring
        add_action('shutdown', [$this, 'log_page_performance']);
    }
    
    /**
     * Register admin menu
     */
    public function register_admin_menu() {
        // Main menu
        add_menu_page(
            __('ZipPicks System', 'zippicks-core'),
            __('ZipPicks System', 'zippicks-core'),
            'manage_options',
            'zippicks-system',
            [$this, 'render_system_dashboard'],
            'dashicons-location-alt',
            25
        );
        
        // System Dashboard (rename the first submenu)
        add_submenu_page(
            'zippicks-system',
            __('System Dashboard', 'zippicks-core'),
            __('System Dashboard', 'zippicks-core'),
            'manage_options',
            'zippicks-system',
            [$this, 'render_system_dashboard']
        );
        
        // Logs page
        add_submenu_page(
            'zippicks-system',
            __('System Logs', 'zippicks-core'),
            __('System Logs', 'zippicks-core'),
            'manage_options',
            'zippicks-logs',
            [$this, 'render_logs_page']
        );
        
        // Error Log page
        add_submenu_page(
            'zippicks-system',
            __('Error Log', 'zippicks-core'),
            __('Error Log', 'zippicks-core'),
            'manage_options',
            'zippicks-error-log',
            [$this, 'render_error_log_page']
        );
    }
    
    /**
     * Render system dashboard
     */
    public function render_system_dashboard() {
        ?>
        <div class="wrap">
            <h1><?php _e('ZipPicks System Dashboard', 'zippicks-core'); ?></h1>
            
            <div class="zippicks-dashboard">
                <div class="zippicks-status-card">
                    <h2><?php _e('System Status', 'zippicks-core'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <tbody>
                            <tr>
                                <td><strong><?php _e('Core Version', 'zippicks-core'); ?></strong></td>
                                <td><?php echo ZIPPICKS_CORE_VERSION; ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Foundation Status', 'zippicks-core'); ?></strong></td>
                                <td>
                                    <?php if (function_exists('zippicks')): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                        <?php _e('Connected', 'zippicks-core'); ?>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-warning" style="color: #ffb900;"></span>
                                        <?php _e('Not Available', 'zippicks-core'); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('PHP Version', 'zippicks-core'); ?></strong></td>
                                <td><?php echo phpversion(); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('WordPress Version', 'zippicks-core'); ?></strong></td>
                                <td><?php echo get_bloginfo('version'); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Active Plugins', 'zippicks-core'); ?></strong></td>
                                <td>
                                    <?php
                                    $active_plugins = get_option('active_plugins', []);
                                    $zippicks_plugins = array_filter($active_plugins, function($plugin) {
                                        return strpos($plugin, 'zippicks') !== false;
                                    });
                                    echo count($zippicks_plugins) . ' ' . __('ZipPicks plugins active', 'zippicks-core');
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="zippicks-info-card" style="margin-top: 20px;">
                    <h2><?php _e('Available Services', 'zippicks-core'); ?></h2>
                    <p><?php _e('The ZipPicks Core plugin provides infrastructure services for the entire ZipPicks ecosystem:', 'zippicks-core'); ?></p>
                    <ul>
                        <li><?php _e('Global logging system for errors and performance', 'zippicks-core'); ?></li>
                        <li><?php _e('Shared UI components and helpers', 'zippicks-core'); ?></li>
                        <li><?php _e('Foundation integration and service registration', 'zippicks-core'); ?></li>
                        <li><?php _e('Plugin coordination and shared utilities', 'zippicks-core'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <style>
            .zippicks-dashboard {
                max-width: 800px;
                margin-top: 20px;
            }
            .zippicks-status-card,
            .zippicks-info-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin-bottom: 20px;
            }
            .zippicks-status-card h2,
            .zippicks-info-card h2 {
                margin-top: 0;
            }
        </style>
        <?php
    }
    
    /**
     * Render logs page
     */
    public function render_logs_page() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/zippicks-logs';
        $log_file = $log_dir . '/zippicks-' . date('Y-m-d') . '.log';
        
        ?>
        <div class="wrap">
            <h1><?php _e('System Logs', 'zippicks-core'); ?></h1>
            
            <div class="zippicks-logs-viewer">
                <h2><?php _e('Today\'s Logs', 'zippicks-core'); ?></h2>
                <div style="background: #f1f1f1; padding: 20px; font-family: monospace; overflow-x: auto; max-height: 500px; overflow-y: scroll;">
                    <?php
                    if (file_exists($log_file)) {
                        $logs = file_get_contents($log_file);
                        echo nl2br(esc_html($logs));
                    } else {
                        echo '<p>' . __('No logs found for today.', 'zippicks-core') . '</p>';
                    }
                    ?>
                </div>
                
                <div style="margin-top: 20px;">
                    <a href="<?php echo admin_url('admin.php?page=zippicks-logs&action=download&date=' . date('Y-m-d')); ?>" 
                       class="button button-secondary">
                        <?php _e('Download Today\'s Logs', 'zippicks-core'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        
        // Handle log download
        if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['date'])) {
            $this->download_logs($_GET['date']);
        }
    }
    
    /**
     * Download logs for a specific date
     */
    private function download_logs($date) {
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return;
        }
        
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/zippicks-logs/zippicks-' . $date . '.log';
        
        if (file_exists($log_file)) {
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="zippicks-' . $date . '.log"');
            readfile($log_file);
            exit;
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only on ZipPicks pages
        if (strpos($hook, 'zippicks') === false) {
            return;
        }
        
        wp_enqueue_style(
            'zippicks-core-admin',
            ZIPPICKS_CORE_URL . 'assets/css/admin.css',
            [],
            ZIPPICKS_CORE_VERSION
        );
        
        // Enqueue error reporter first
        wp_enqueue_script(
            'zippicks-error-reporter',
            ZIPPICKS_CORE_URL . 'assets/js/error-reporter.js',
            [],
            ZIPPICKS_CORE_VERSION,
            false // Load in header to catch early errors
        );
        
        // Localize for error reporter
        wp_localize_script('zippicks-error-reporter', 'zippicks_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zippicks_core_nonce')
        ]);
        
        wp_enqueue_script(
            'zippicks-core-admin',
            ZIPPICKS_CORE_URL . 'assets/js/admin.js',
            ['jquery'],
            ZIPPICKS_CORE_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('zippicks-core-admin', 'zippicks_core', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zippicks_core_nonce')
        ]);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'zippicks-core-frontend',
            ZIPPICKS_CORE_URL . 'assets/css/frontend.css',
            [],
            ZIPPICKS_CORE_VERSION
        );
        
        // Enqueue error reporter first
        wp_enqueue_script(
            'zippicks-error-reporter',
            ZIPPICKS_CORE_URL . 'assets/js/error-reporter.js',
            [],
            ZIPPICKS_CORE_VERSION,
            false // Load in header to catch early errors
        );
        
        // Localize for error reporter
        wp_localize_script('zippicks-error-reporter', 'zippicks_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zippicks_core_nonce')
        ]);
        
        wp_enqueue_script(
            'zippicks-core-frontend',
            ZIPPICKS_CORE_URL . 'assets/js/frontend.js',
            ['jquery'],
            ZIPPICKS_CORE_VERSION,
            true
        );
        
        // Localize script for error logging
        wp_localize_script('zippicks-core-frontend', 'zippicks_core', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('zippicks_core_nonce')
        ]);
    }
    
    /**
     * Register global hooks
     */
    public function register_global_hooks() {
        // Add body classes
        add_filter('body_class', [$this, 'add_body_classes']);
        
        // Add admin body classes
        add_filter('admin_body_class', [$this, 'add_admin_body_classes']);
    }
    
    /**
     * Add body classes
     */
    public function add_body_classes($classes) {
        $classes[] = 'zippicks-active';
        
        if (function_exists('zippicks')) {
            $classes[] = 'zippicks-foundation-active';
        }
        
        return $classes;
    }
    
    /**
     * Add admin body classes
     */
    public function add_admin_body_classes($classes) {
        $classes .= ' zippicks-admin';
        
        if (function_exists('zippicks')) {
            $classes .= ' zippicks-foundation-active';
        }
        
        return $classes;
    }
    
    /**
     * Foundation integration
     */
    public function foundation_integration() {
        // Register UI component loader
        if (function_exists('zippicks') && zippicks()->has('ui')) {
            zippicks()->get('ui')->register_component('follow-button', function($args) {
                return zippicks_render_follow_button($args);
            });
        }
    }
    
    /**
     * Handle client error logging via AJAX
     */
    public function handle_client_error_logging() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'zippicks_core_nonce')) {
            wp_die('Invalid nonce');
        }
        
        $error = sanitize_text_field($_POST['error'] ?? '');
        $url = esc_url_raw($_POST['url'] ?? '');
        $line = absint($_POST['line'] ?? 0);
        
        if ($error) {
            $this->logger->log_error('JavaScript Error', [
                'error' => $error,
                'url' => $url,
                'line' => $line,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        }
        
        wp_send_json_success();
    }
    
    /**
     * Log page performance
     */
    public function log_page_performance() {
        if (!is_admin() && defined('WP_START_TIMESTAMP')) {
            $duration = microtime(true) - WP_START_TIMESTAMP;
            $this->logger->log_performance('page_load', $duration * 1000);
        }
    }
    
    /**
     * Render error log page
     */
    public function render_error_log_page() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Get critical errors
        $errors = get_transient('zippicks_critical_errors') ?: [];
        
        ?>
        <div class="wrap">
            <h1><?php _e('ZipPicks Error Log', 'zippicks-core'); ?></h1>
            
            <?php if (isset($_GET['cleared']) && $_GET['cleared'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('All errors have been cleared successfully.', 'zippicks-core'); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (empty($errors)): ?>
                <div class="notice notice-success">
                    <p><?php _e('No critical errors detected.', 'zippicks-core'); ?></p>
                </div>
            <?php else: ?>
                <div class="notice notice-info">
                    <p><?php printf(_n('%d critical error detected.', '%d critical errors detected.', count($errors), 'zippicks-core'), count($errors)); ?></p>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Time', 'zippicks-core'); ?></th>
                            <th><?php _e('Error Message', 'zippicks-core'); ?></th>
                            <th><?php _e('File', 'zippicks-core'); ?></th>
                            <th><?php _e('Line', 'zippicks-core'); ?></th>
                            <th><?php _e('Type', 'zippicks-core'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($errors) as $error): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', $error['timestamp']); ?></td>
                                <td><?php echo esc_html($error['message']); ?></td>
                                <td><?php echo esc_html(basename($error['context']['file'] ?? 'Unknown')); ?></td>
                                <td><?php echo esc_html($error['context']['line'] ?? 'Unknown'); ?></td>
                                <td>
                                    <span class="error-type error-type-<?php echo esc_attr($error['context']['type'] ?? 'unknown'); ?>">
                                        <?php echo esc_html(ucfirst($error['context']['type'] ?? 'unknown')); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 20px;">
                    <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=zippicks_clear_errors'), 'clear_errors'); ?>" 
                       class="button button-primary">
                        <?php _e('Clear All Errors', 'zippicks-core'); ?>
                    </a>
                </div>
            <?php endif; ?>
            
            <h2 style="margin-top: 40px;"><?php _e('Error Statistics', 'zippicks-core'); ?></h2>
            <?php
            if ($this->error_handler) {
                $stats = $this->error_handler->get_error_stats();
                ?>
                <table class="wp-list-table widefat fixed striped" style="max-width: 500px;">
                    <thead>
                        <tr>
                            <th><?php _e('Error Type', 'zippicks-core'); ?></th>
                            <th><?php _e('Count', 'zippicks-core'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($stats['counts'])): ?>
                            <?php foreach ($stats['counts'] as $type => $count): ?>
                                <tr>
                                    <td><?php echo esc_html(ucfirst($type)); ?></td>
                                    <td><?php echo esc_html($count); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2"><?php _e('No errors recorded in this session.', 'zippicks-core'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php
            }
            ?>
            
            <style>
                .error-type {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 3px;
                    font-size: 12px;
                    font-weight: bold;
                }
                .error-type-error,
                .error-type-fatal {
                    background: #dc3232;
                    color: white;
                }
                .error-type-warning {
                    background: #ffb900;
                    color: white;
                }
                .error-type-notice {
                    background: #00a0d2;
                    color: white;
                }
                .error-type-exception {
                    background: #826eb4;
                    color: white;
                }
            </style>
        </div>
        <?php
    }
}