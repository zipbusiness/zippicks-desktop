<?php
/**
 * Sets listing page template
 *
 * @package ZipPicks_Master_Critic
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Data is prepared by the admin class in display_sets_page()
// Available variables: $sets (array of set objects with item_count)

// Handle pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Get total count for pagination
global $wpdb;
$sets_table = ZipPicks_Master_Critic_Database::get_sets_table();
$total_sets = $wpdb->get_var("SELECT COUNT(*) FROM $sets_table");
$total_pages = ceil($total_sets / $per_page);

// Get sets for current page
$sets = $wpdb->get_results($wpdb->prepare(
    "SELECT s.*, 
            (SELECT COUNT(*) FROM " . ZipPicks_Master_Critic_Database::get_items_table() . " WHERE set_id = s.id) as item_count
     FROM $sets_table s 
     ORDER BY s.created_at DESC
     LIMIT %d OFFSET %d",
    $per_page,
    $offset
));

// Handle success/error messages
$message = '';
$message_type = '';

if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'published':
            $message = 'Set published successfully.';
            $message_type = 'success';
            break;
        case 'draft':
            $message = 'Set moved to draft successfully.';
            $message_type = 'success';
            break;
        case 'deleted':
            $message = 'Set deleted successfully.';
            $message_type = 'success';
            break;
        case 'error':
            $message = 'An error occurred. Please try again.';
            $message_type = 'error';
            break;
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">Master Sets</h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=zpmc-import')); ?>" class="page-title-action">Add New</a>
    <hr class="wp-header-end">
    
    <?php if ($message): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="zpmc-sets-container">
        <?php if (!empty($sets) && is_array($sets)): ?>
            
            <!-- Bulk Actions -->
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select id="bulk-action-selector-top">
                        <option value="-1">Bulk Actions</option>
                        <option value="publish">Publish</option>
                        <option value="draft">Move to Draft</option>
                        <option value="delete" style="color: #d63638;">Delete</option>
                    </select>
                    <button class="button action" onclick="handleBulkAction()">Apply</button>
                </div>
                
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo number_format($total_sets); ?> items</span>
                    <?php if ($total_pages > 1): ?>
                        <?php
                        $pagination = paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '‹',
                            'next_text' => '›',
                            'total' => $total_pages,
                            'current' => $current_page,
                            'type' => 'array'
                        ]);
                        
                        if ($pagination) {
                            echo '<span class="pagination-links">' . implode('', $pagination) . '</span>';
                        }
                        ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Sets Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all" />
                        </td>
                        <th class="manage-column">Set Name</th>
                        <th class="manage-column">Category</th>
                        <th class="manage-column">ZIP Code</th>
                        <th class="manage-column">Items</th>
                        <th class="manage-column">Status</th>
                        <th class="manage-column">Created</th>
                        <th class="manage-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sets as $set): ?>
                        <tr data-set-id="<?php echo intval($set->id); ?>">
                            <th class="check-column">
                                <input type="checkbox" name="set_ids[]" value="<?php echo intval($set->id); ?>" />
                            </th>
                            <td>
                                <strong>
                                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=page')); ?>">
                                        <?php echo esc_html($set->set_name); ?>
                                    </a>
                                </strong>
                                <div class="row-actions">
                                    <span class="view">
                                        <a href="<?php echo esc_url(get_home_url() . '/master-critic/' . $set->set_slug); ?>" 
                                           target="_blank">View</a> |
                                    </span>
                                    <?php if ($set->status === 'draft'): ?>
                                        <span class="publish">
                                            <a href="<?php echo esc_url(wp_nonce_url(
                                                admin_url('admin.php?page=zpmc-sets&action=publish&set_id=' . $set->id),
                                                'zpmc_action_' . $set->id
                                            )); ?>">Publish</a> |
                                        </span>
                                    <?php else: ?>
                                        <span class="draft">
                                            <a href="<?php echo esc_url(wp_nonce_url(
                                                admin_url('admin.php?page=zpmc-sets&action=draft&set_id=' . $set->id),
                                                'zpmc_action_' . $set->id
                                            )); ?>">Move to Draft</a> |
                                        </span>
                                    <?php endif; ?>
                                    <span class="delete">
                                        <a href="javascript:void(0);" 
                                           onclick="confirmDelete(<?php echo intval($set->id); ?>, '<?php echo esc_js($set->set_name); ?>')"
                                           style="color: #d63638;">Delete</a>
                                    </span>
                                </div>
                            </td>
                            <td><?php echo esc_html($set->category); ?></td>
                            <td><?php echo esc_html($set->zip_code); ?></td>
                            <td>
                                <span class="zpmc-item-count">
                                    <?php echo intval($set->item_count); ?> restaurants
                                </span>
                            </td>
                            <td>
                                <span class="zpmc-status zpmc-status-<?php echo esc_attr($set->status); ?>">
                                    <?php echo ucfirst(esc_html($set->status)); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $created = strtotime($set->created_at);
                                if ($created) {
                                    echo '<abbr title="' . esc_attr(date('Y/m/d g:i:s A', $created)) . '">';
                                    echo esc_html(date('Y/m/d', $created));
                                    echo '</abbr>';
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td>
                                <div class="zpmc-actions">
                                    <?php if ($set->status === 'draft'): ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(
                                            admin_url('admin.php?page=zpmc-sets&action=publish&set_id=' . $set->id),
                                            'zpmc_action_' . $set->id
                                        )); ?>" class="button button-small button-primary">Publish</a>
                                    <?php else: ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(
                                            admin_url('admin.php?page=zpmc-sets&action=draft&set_id=' . $set->id),
                                            'zpmc_action_' . $set->id
                                        )); ?>" class="button button-small">Draft</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Bottom pagination -->
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php if ($total_pages > 1): ?>
                        <?php
                        if ($pagination) {
                            echo '<span class="pagination-links">' . implode('', $pagination) . '</span>';
                        }
                        ?>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Empty State -->
            <div class="zpmc-empty-state" style="text-align: center; padding: 60px 20px; background: #fff; border: 1px solid #c3c4c7;">
                <div class="dashicons dashicons-star-filled" style="font-size: 48px; color: #c3c4c7; margin-bottom: 20px;"></div>
                <h2 style="color: #23282d; margin: 0 0 10px 0;">No Master Sets Found</h2>
                <p style="color: #646970; margin: 0 0 30px 0;">Get started by importing your first master set from the desktop tool.</p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=zpmc-import')); ?>" 
                   class="button button-primary button-large">Import Your First Set</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="zpmc-delete-modal" style="display: none;">
    <div class="zpmc-modal-backdrop" onclick="closeDeleteModal()"></div>
    <div class="zpmc-modal-content">
        <h2>Confirm Deletion</h2>
        <p>Are you sure you want to delete <strong id="delete-set-name"></strong>?</p>
        <p style="color: #d63638; font-weight: 600;">This action cannot be undone. All restaurants in this set will be permanently deleted.</p>
        <div class="zpmc-modal-actions">
            <button class="button button-secondary" onclick="closeDeleteModal()">Cancel</button>
            <button class="button button-primary" style="background: #d63638; border-color: #d63638;" 
                    onclick="executeDelete()" id="confirm-delete-btn">Delete Set</button>
        </div>
    </div>
</div>

<style>
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

.zpmc-item-count {
    color: #646970;
}

.zpmc-actions {
    white-space: nowrap;
}

/* Modal Styles */
#zpmc-delete-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100000;
}

.zpmc-modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
}

.zpmc-modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #fff;
    padding: 20px;
    border-radius: 4px;
    box-shadow: 0 3px 6px rgba(0,0,0,0.3);
    max-width: 400px;
    width: 90%;
}

.zpmc-modal-actions {
    text-align: right;
    margin-top: 20px;
}

.zpmc-modal-actions .button {
    margin-left: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Select all functionality
    $('#cb-select-all').on('change', function() {
        $('input[name="set_ids[]"]').prop('checked', this.checked);
    });
    
    $('input[name="set_ids[]"]').on('change', function() {
        var allChecked = $('input[name="set_ids[]"]:checked').length === $('input[name="set_ids[]"]').length;
        $('#cb-select-all').prop('checked', allChecked);
    });
});

// Global variables for delete confirmation
let deleteSetId = 0;

function confirmDelete(setId, setName) {
    deleteSetId = setId;
    document.getElementById('delete-set-name').textContent = setName;
    document.getElementById('zpmc-delete-modal').style.display = 'block';
}

function closeDeleteModal() {
    document.getElementById('zpmc-delete-modal').style.display = 'none';
    deleteSetId = 0;
}

function executeDelete() {
    if (!deleteSetId) return;
    
    const btn = document.getElementById('confirm-delete-btn');
    btn.textContent = 'Deleting...';
    btn.disabled = true;
    
    // Use AJAX for deletion
    jQuery.ajax({
        url: zpmc_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'zpmc_delete_set',
            set_id: deleteSetId,
            nonce: zpmc_ajax.delete_nonce
        },
        success: function(response) {
            if (response.success) {
                // Remove the row from the table
                jQuery('tr[data-set-id="' + deleteSetId + '"]').fadeOut(300, function() {
                    jQuery(this).remove();
                    
                    // Check if table is now empty
                    if (jQuery('tbody tr').length === 0) {
                        location.reload();
                    }
                });
                closeDeleteModal();
            } else {
                alert('Error: ' + (response.data.message || 'Failed to delete set'));
                btn.textContent = 'Delete Set';
                btn.disabled = false;
            }
        },
        error: function() {
            alert('An error occurred while deleting the set.');
            btn.textContent = 'Delete Set';
            btn.disabled = false;
        }
    });
}

function handleBulkAction() {
    const action = document.getElementById('bulk-action-selector-top').value;
    const checkedBoxes = document.querySelectorAll('input[name="set_ids[]"]:checked');
    
    if (action === '-1') {
        alert('Please select an action.');
        return;
    }
    
    if (checkedBoxes.length === 0) {
        alert('Please select at least one set.');
        return;
    }
    
    if (action === 'delete') {
        if (!confirm('Are you sure you want to delete the selected sets? This action cannot be undone.')) {
            return;
        }
    }
    
    // For now, just show what would happen
    alert('Bulk ' + action + ' would be performed on ' + checkedBoxes.length + ' sets. (Feature coming soon)');
}
</script>