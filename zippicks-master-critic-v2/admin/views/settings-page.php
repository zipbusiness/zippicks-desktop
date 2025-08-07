<?php
/**
 * Settings page template
 *
 * @package ZipPicks_Master_Critic
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings with defaults
$settings = [
    'zpmc_enable_schema' => get_option('zpmc_enable_schema', '1'),
    'zpmc_items_per_page' => get_option('zpmc_items_per_page', '10'),
    'zpmc_enable_frontend' => get_option('zpmc_enable_frontend', '1'),
    'zpmc_default_status' => get_option('zpmc_default_status', 'draft'),
    'zpmc_require_verification' => get_option('zpmc_require_verification', '0'),
    'zpmc_enable_debug' => get_option('zpmc_enable_debug', '0')
];

// Display settings errors/success messages
settings_errors('zpmc_settings');
?>

<div class="wrap">
    <h1>Master Critic Settings</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('zpmc_settings', 'zpmc_settings_nonce'); ?>
        
        <div class="zpmc-settings-container">
            
            <!-- Core Features -->
            <div class="card">
                <h2>Core Features</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="zpmc_enable_frontend">Frontend Display</label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="zpmc_enable_frontend" value="1" 
                                           <?php checked($settings['zpmc_enable_frontend'], '1'); ?> />
                                    Enable frontend shortcodes and URLs
                                </label><br>
                                <label>
                                    <input type="radio" name="zpmc_enable_frontend" value="0" 
                                           <?php checked($settings['zpmc_enable_frontend'], '0'); ?> />
                                    Admin-only (no public display)
                                </label>
                                <p class="description">
                                    When enabled, master sets can be displayed on the frontend using shortcodes or custom URLs.
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="zpmc_enable_schema">Schema.org Output</label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="zpmc_enable_schema" value="1" 
                                           <?php checked($settings['zpmc_enable_schema'], '1'); ?> />
                                    Enable structured data output for SEO
                                </label>
                                <p class="description">
                                    Adds Restaurant schema markup to improve search engine visibility.
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="zpmc_items_per_page">Items Per Page</label>
                        </th>
                        <td>
                            <input type="number" name="zpmc_items_per_page" 
                                   value="<?php echo intval($settings['zpmc_items_per_page']); ?>" 
                                   min="5" max="50" class="small-text" />
                            <p class="description">
                                Number of restaurants to show per page in frontend displays (5-50).
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Performance -->
            <div class="card">
                <h2>Performance & Caching</h2>
                <table class="form-table">
                    <!-- Cache settings removed - now managed by Core cache service -->
                    
                </table>
            </div>
            
            <!-- Import & Data -->
            <div class="card">
                <h2>Import & Data Management</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="zpmc_default_status">Default Import Status</label>
                        </th>
                        <td>
                            <select name="zpmc_default_status">
                                <option value="draft" <?php selected($settings['zpmc_default_status'], 'draft'); ?>>Draft</option>
                                <option value="published" <?php selected($settings['zpmc_default_status'], 'published'); ?>>Published</option>
                            </select>
                            <p class="description">
                                Default status for newly imported master sets.
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="zpmc_require_verification">Require Verification</label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="zpmc_require_verification" value="1" 
                                           <?php checked($settings['zpmc_require_verification'], '1'); ?> />
                                    Mark imported restaurants as unverified by default
                                </label>
                                <p class="description">
                                    When enabled, all imported restaurant data will need manual verification before being marked as verified.
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Development -->
            <div class="card">
                <h2>Development & Debugging</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="zpmc_enable_debug">Debug Mode</label>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="zpmc_enable_debug" value="1" 
                                           <?php checked($settings['zpmc_enable_debug'], '1'); ?> />
                                    Enable detailed logging and debug information
                                </label>
                                <p class="description">
                                    <strong>Warning:</strong> Only enable for troubleshooting. This can generate large log files.
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Database Status</th>
                        <td>
                            <?php
                            $tables_status = ZipPicks_Master_Critic_Database::verify_tables();
                            $core_status = function_exists('zippicks') && zippicks()->has('core.logger');
                            ?>
                            <ul style="margin: 0;">
                                <li style="margin-bottom: 5px;">
                                    <span style="color: <?php echo $tables_status ? '#00a32a' : '#d63638'; ?>;">●</span>
                                    Database Tables: 
                                    <strong><?php echo $tables_status ? 'Active' : 'Missing'; ?></strong>
                                    <?php if (!$tables_status): ?>
                                        <br><span style="color: #d63638; font-size: 12px;">
                                            Please deactivate and reactivate the plugin to create tables.
                                        </span>
                                    <?php endif; ?>
                                </li>
                                <li style="margin-bottom: 5px;">
                                    <span style="color: <?php echo $core_status ? '#00a32a' : '#dba617'; ?>;">●</span>
                                    ZipPicks Core Integration: 
                                    <strong><?php echo $core_status ? 'Connected' : 'Standalone Mode'; ?></strong>
                                </li>
                                <li>
                                    <span style="color: #00a32a;">●</span>
                                    Plugin Version: <strong><?php echo ZPMC_VERSION; ?></strong>
                                </li>
                            </ul>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Cache Management removed - now handled by Core cache service -->
        </div>
        
        <?php submit_button('Save Settings', 'primary', 'submit', true, ['id' => 'save-settings']); ?>
    </form>
</div>

<style>
.zpmc-settings-container .card {
    background: #fff;
    border: 1px solid #c3c4c7;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 20px;
    margin-bottom: 20px;
}

.zpmc-settings-container .card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #e1e1e1;
}

/* Cache tools CSS removed - now handled by Core cache service */

.form-table td fieldset label {
    margin-bottom: 5px;
    display: block;
}

.form-table td fieldset label input[type="radio"] {
    margin-right: 5px;
}
</style>

<script>
// Cache management function removed - now handled by Core cache service

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const saveButton = document.getElementById('save-settings');
    
    form.addEventListener('submit', function(e) {
        const itemsPerPage = document.querySelector('input[name="zpmc_items_per_page"]').value;
        
        // Validate items per page
        if (itemsPerPage < 5 || itemsPerPage > 50) {
            alert('Items per page must be between 5 and 50.');
            e.preventDefault();
            return false;
        }
        
        
        // Show saving state
        saveButton.value = 'Saving...';
        saveButton.disabled = true;
    });
    
    // Real-time validation feedback
    const itemsInput = document.querySelector('input[name="zpmc_items_per_page"]');
    
    itemsInput.addEventListener('input', function() {
        const value = parseInt(this.value);
        if (value < 5 || value > 50) {
            this.style.borderColor = '#d63638';
        } else {
            this.style.borderColor = '';
        }
    });
});
</script>