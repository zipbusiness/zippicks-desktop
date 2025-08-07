<?php
/**
 * Dashboard page template
 *
 * @package ZipPicks_Master_Critic
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Data is prepared by the admin class in display_dashboard_page()
// Available variables: $total_sets, $total_items, $published_sets, $draft_sets, $recent_sets
?>

<div class="wrap">
    <h1>Master Critic Dashboard</h1>
    
    <div class="zpmc-dashboard-container">
        <!-- Statistics Cards -->
        <div class="zpmc-stats-row" style="display: flex; gap: 20px; margin-bottom: 30px;">
            <div class="card" style="flex: 1; padding: 20px;">
                <h2 style="margin: 0 0 10px 0; font-size: 24px; color: #2271b1;">
                    <?php echo intval($total_sets ?? 0); ?>
                </h2>
                <p style="margin: 0; font-size: 14px; color: #646970;">Total Master Sets</p>
            </div>
            
            <div class="card" style="flex: 1; padding: 20px;">
                <h2 style="margin: 0 0 10px 0; font-size: 24px; color: #00a32a;">
                    <?php echo intval($total_items ?? 0); ?>
                </h2>
                <p style="margin: 0; font-size: 14px; color: #646970;">Total Restaurants</p>
            </div>
            
            <div class="card" style="flex: 1; padding: 20px;">
                <h2 style="margin: 0 0 10px 0; font-size: 24px; color: #d63638;">
                    <?php echo intval($published_sets ?? 0); ?>
                </h2>
                <p style="margin: 0; font-size: 14px; color: #646970;">Published Sets</p>
            </div>
            
            <div class="card" style="flex: 1; padding: 20px;">
                <h2 style="margin: 0 0 10px 0; font-size: 24px; color: #dba617;">
                    <?php echo intval($draft_sets ?? 0); ?>
                </h2>
                <p style="margin: 0; font-size: 14px; color: #646970;">Draft Sets</p>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="zpmc-actions-row" style="display: flex; gap: 20px; margin-bottom: 30px;">
            <div class="card" style="flex: 1;">
                <h2>Quick Actions</h2>
                <p>Get started with Master Critic:</p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=zpmc-import')); ?>" class="button button-primary">
                        Import New Master Set
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=zpmc-sets')); ?>" class="button button-secondary">
                        Manage All Sets
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=zpmc-settings')); ?>" class="button button-secondary">
                        Plugin Settings
                    </a>
                </p>
            </div>
            
            <div class="card" style="flex: 1;">
                <h2>System Status</h2>
                <?php 
                $tables_exist = ZipPicks_Master_Critic_Database::verify_tables();
                $has_core = function_exists('zippicks') && zippicks()->has('core.logger');
                ?>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>
                        <span style="color: <?php echo $tables_exist ? '#00a32a' : '#d63638'; ?>;">●</span>
                        Database Tables: <?php echo $tables_exist ? 'Active' : 'Missing'; ?>
                    </li>
                    <li>
                        <span style="color: <?php echo $has_core ? '#00a32a' : '#dba617'; ?>;">●</span>
                        Core Integration: <?php echo $has_core ? 'Connected' : 'Standalone'; ?>
                    </li>
                    <li>
                        <span style="color: #00a32a;">●</span>
                        Plugin Status: Active
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Recent Master Sets -->
        <div class="card">
            <h2>Recent Master Sets</h2>
            <?php if (!empty($recent_sets) && is_array($recent_sets)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Set Name</th>
                            <th>Category</th>
                            <th>ZIP Code</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_sets as $set): 
                            $item_count = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM " . ZipPicks_Master_Critic_Database::get_items_table() . " WHERE set_id = %d",
                                $set->id
                            ));
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($set->set_name); ?></strong>
                                </td>
                                <td><?php echo esc_html($set->category); ?></td>
                                <td><?php echo esc_html($set->zip_code); ?></td>
                                <td><?php echo intval($item_count); ?></td>
                                <td>
                                    <span class="zpmc-status zpmc-status-<?php echo esc_attr($set->status); ?>">
                                        <?php echo ucfirst(esc_html($set->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html(date('M j, Y g:i A', strtotime($set->created_at))); ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=zpmc-sets&set_id=' . $set->id)); ?>" 
                                       class="button button-small">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p style="text-align: center; margin-top: 15px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=zpmc-sets')); ?>" 
                       class="button">View All Master Sets</a>
                </p>
            <?php else: ?>
                <div class="zpmc-empty-state" style="text-align: center; padding: 40px;">
                    <p style="color: #646970; font-size: 16px; margin: 0 0 20px 0;">
                        No master sets found. Get started by importing your first set.
                    </p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=zpmc-import')); ?>" 
                       class="button button-primary">Import Master Set</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.zpmc-dashboard-container .card {
    background: #fff;
    border: 1px solid #c3c4c7;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 20px;
}

.zpmc-status {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}

.zpmc-status-published {
    background: #d7eddd;
    color: #00a32a;
}

.zpmc-status-draft {
    background: #fff3cd;
    color: #856404;
}

@media (max-width: 768px) {
    .zpmc-stats-row,
    .zpmc-actions-row {
        flex-direction: column;
    }
}
</style>