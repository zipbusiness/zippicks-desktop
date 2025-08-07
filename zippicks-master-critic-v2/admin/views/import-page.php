<?php
/**
 * Import page template
 *
 * @package ZipPicks_Master_Critic
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Import Master Set</h1>
    
    <div class="zpmc-import-container">
        <div class="card">
            <h2>Upload JSON File</h2>
            <p>Import a Master Set JSON file exported from the Master Critic desktop tool.</p>
            
            <form id="zpmc-import-form" method="post" enctype="multipart/form-data">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="import_file">JSON File</label>
                        </th>
                        <td>
                            <input type="file" name="import_file" id="import_file" accept=".json" required />
                            <p class="description">
                                Select a JSON file exported from the Master Critic tool (max 10MB)
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="import_status">Initial Status</label>
                        </th>
                        <td>
                            <select name="import_status" id="import_status">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                            </select>
                            <p class="description">
                                Status of the imported set
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary" id="import-submit">
                        Import Master Set
                    </button>
                    <span class="spinner" style="float: none;"></span>
                </p>
            </form>
            
            <div id="import-result" style="display: none;"></div>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>Expected JSON Structure</h2>
            <pre style="background: #f0f0f0; padding: 10px; overflow-x: auto;">
{
  "set_name": "Best Italian in Pleasanton",
  "zip_code": "94566",
  "category": "Italian",
  "vertical": "Restaurants",
  "radius": 10,
  "categories": {
    "essential": [
      {
        "name": "Restaurant Name",
        "score": 9.5,
        "price_tier": "$$",
        "summary": "Description...",
        "schema_payload": {
          "@type": "Restaurant",
          ...
        }
      }
    ],
    "notable": [...],
    "worthy": [...],
    "honorable": [...]
  }
}
            </pre>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#zpmc-import-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'zpmc_import_json');
        formData.append('nonce', zpmc_ajax.import_nonce);
        
        var $button = $('#import-submit');
        var $spinner = $('.spinner');
        var $result = $('#import-result');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.hide();
        
        $.ajax({
            url: zpmc_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    $('#import_file').val('');
                    
                    // Redirect to sets page after 2 seconds
                    setTimeout(function() {
                        window.location.href = 'admin.php?page=zpmc-sets';
                    }, 2000);
                } else {
                    $result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
                $result.show();
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>An error occurred during import.</p></div>');
                $result.show();
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
});
</script>