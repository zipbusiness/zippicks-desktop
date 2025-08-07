/**
 * Master Critic Admin JavaScript
 * Enterprise-grade functionality for the admin interface
 */

(function($) {
    'use strict';

    // Global admin object
    window.ZipPicksMasterCriticAdmin = {
        deleteSetId: 0,
        
        init: function() {
            this.initializeEventHandlers();
            this.initializeFormValidation();
            this.initializeTableActions();
        },
        
        initializeEventHandlers: function() {
            $(document).ready(function() {
                ZipPicksMasterCriticAdmin.bindEvents();
            });
        },
        
        bindEvents: function() {
            // Import form submission
            $('#zpmc-import-form').on('submit', this.handleImportSubmission);
            
            // Settings form validation
            $('form[method="post"][action=""]').on('submit', this.handleSettingsSubmission);
            
            // Cache clearing
            $(document).on('click', '[onclick*="clearMasterCriticsCache"]', this.handleCacheClear);
            
            // Modal close handlers
            $(document).on('click', '.zpmc-modal-backdrop', this.closeDeleteModal);
            $(document).on('keyup', this.handleModalKeypress);
        },
        
        initializeTableActions: function() {
            // Select all functionality
            $('#cb-select-all').on('change', function() {
                var isChecked = $(this).prop('checked');
                $('input[name="set_ids[]"]').prop('checked', isChecked);
            });
            
            // Individual checkbox change handler
            $('input[name="set_ids[]"]').on('change', function() {
                var totalBoxes = $('input[name="set_ids[]"]').length;
                var checkedBoxes = $('input[name="set_ids[]"]:checked').length;
                $('#cb-select-all').prop('checked', totalBoxes === checkedBoxes);
            });
        },
        
        initializeFormValidation: function() {
            // Real-time validation for settings fields
            var self = this;
            
            // Items per page validation
            $('input[name="zpmc_items_per_page"]').on('input', function() {
                self.validateNumericInput($(this), 5, 50);
            });
        },
        
        validateNumericInput: function($input, min, max) {
            var value = parseInt($input.val());
            var isValid = !isNaN(value) && value >= min && value <= max;
            
            $input.removeClass('invalid valid');
            $input.addClass(isValid ? 'valid' : 'invalid');
            
            return isValid;
        },
        
        handleImportSubmission: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $button = $('#import-submit');
            var $spinner = $('.spinner');
            var $result = $('#import-result');
            
            // Validate file selection
            var fileInput = document.getElementById('import_file');
            if (!fileInput.files.length) {
                alert('Please select a JSON file to import.');
                return false;
            }
            
            // Validate file type
            var fileName = fileInput.files[0].name;
            if (!fileName.toLowerCase().endsWith('.json')) {
                alert('Please select a valid JSON file.');
                return false;
            }
            
            // Validate file size (10MB limit)
            var fileSize = fileInput.files[0].size;
            var maxSize = 10 * 1024 * 1024; // 10MB in bytes
            if (fileSize > maxSize) {
                alert('File size must be less than 10MB.');
                return false;
            }
            
            var formData = new FormData($form[0]);
            formData.append('action', 'zpmc_import_json');
            
            // Ensure we have the nonce
            if (typeof zpmc_ajax !== 'undefined' && zpmc_ajax.import_nonce) {
                formData.append('nonce', zpmc_ajax.import_nonce);
            } else {
                console.error('Import nonce not available');
                alert('Security verification failed. Please refresh the page and try again.');
                return false;
            }
            
            // UI state changes
            $button.prop('disabled', true).text('Importing...');
            $spinner.addClass('is-active');
            $result.hide().removeClass('notice-success notice-error');
            
            $.ajax({
                url: zpmc_ajax ? zpmc_ajax.ajax_url : ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                timeout: 60000, // 60 second timeout
                success: function(response) {
                    if (response && response.success) {
                        $result.addClass('notice-success')
                               .html('<p>' + (response.data.message || 'Import completed successfully!') + '</p>')
                               .show();
                        
                        // Clear the form
                        $form[0].reset();
                        
                        // Redirect after success
                        setTimeout(function() {
                            window.location.href = 'admin.php?page=zpmc-sets';
                        }, 2000);
                    } else {
                        var errorMessage = 'Import failed.';
                        if (response && response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                        $result.addClass('notice-error')
                               .html('<p>' + errorMessage + '</p>')
                               .show();
                    }
                },
                error: function(xhr, status, error) {
                    var errorMessage = 'An error occurred during import.';
                    
                    if (status === 'timeout') {
                        errorMessage = 'Import timed out. Please try with a smaller file.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    }
                    
                    $result.addClass('notice-error')
                           .html('<p>' + errorMessage + '</p>')
                           .show();
                           
                    console.error('Import error:', xhr, status, error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Import Master Set');
                    $spinner.removeClass('is-active');
                }
            });
            
            return false;
        },
        
        handleSettingsSubmission: function(e) {
            var $form = $(this);
            var $saveButton = $('#save-settings');
            
            // Skip if this isn't the settings form
            if (!$saveButton.length) {
                return true;
            }
            
            // Validate items per page
            var itemsPerPage = parseInt($('input[name="zpmc_items_per_page"]').val());
            if (isNaN(itemsPerPage) || itemsPerPage < 5 || itemsPerPage > 50) {
                alert('Items per page must be between 5 and 50.');
                e.preventDefault();
                return false;
            }
            
            // Show saving state
            $saveButton.val('Saving Settings...').prop('disabled', true);
            
            return true;
        },
        
        handleCacheClear: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $spinner = $('#cache-spinner');
            var $result = $('#cache-result');
            
            $button.prop('disabled', true).text('Clearing Cache...');
            $spinner.show();
            $result.text('').removeAttr('style');
            
            // Make AJAX call to clear cache
            $.ajax({
                url: zpmc_ajax ? zpmc_ajax.ajax_url : ajaxurl,
                type: 'POST',
                data: {
                    action: 'zpmc_clear_cache',
                    nonce: zpmc_ajax ? zpmc_ajax.cache_nonce : ''
                },
                timeout: 30000,
                success: function(response) {
                    if (response && response.success) {
                        $result.text('Cache cleared successfully!')
                               .css('color', '#00a32a');
                    } else {
                        $result.text('Cache clear failed: ' + (response.data ? response.data.message : 'Unknown error'))
                               .css('color', '#d63638');
                    }
                },
                error: function(xhr, status, error) {
                    var errorMessage = 'Failed to clear cache.';
                    if (status === 'timeout') {
                        errorMessage = 'Cache clear timed out.';
                    }
                    $result.text(errorMessage).css('color', '#d63638');
                    console.error('Cache clear error:', xhr, status, error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Clear All Caches');
                    $spinner.hide();
                    
                    // Clear the message after 3 seconds
                    setTimeout(function() {
                        $result.text('').removeAttr('style');
                    }, 3000);
                }
            });
            
            return false;
        },
        
        // Delete confirmation functions
        confirmDelete: function(setId, setName) {
            if (!setId || !setName) {
                console.error('Invalid delete parameters:', setId, setName);
                return;
            }
            
            this.deleteSetId = setId;
            $('#delete-set-name').text(setName);
            $('#zpmc-delete-modal').show();
        },
        
        closeDeleteModal: function() {
            $('#zpmc-delete-modal').hide();
            ZipPicksMasterCriticAdmin.deleteSetId = 0;
        },
        
        executeDelete: function() {
            var self = this;
            
            if (!self.deleteSetId) {
                console.error('No delete set ID available');
                return;
            }
            
            var $btn = $('#confirm-delete-btn');
            var originalText = $btn.text();
            
            $btn.text('Deleting...').prop('disabled', true);
            
            $.ajax({
                url: zpmc_ajax ? zpmc_ajax.ajax_url : ajaxurl,
                type: 'POST',
                data: {
                    action: 'zpmc_delete_set',
                    set_id: self.deleteSetId,
                    nonce: zpmc_ajax ? zpmc_ajax.delete_nonce : ''
                },
                timeout: 30000,
                success: function(response) {
                    if (response && response.success) {
                        // Remove the row from the table with animation
                        $('tr[data-set-id="' + self.deleteSetId + '"]').fadeOut(300, function() {
                            $(this).remove();
                            
                            // Check if table is now empty
                            if ($('tbody tr').length === 0) {
                                location.reload();
                            }
                        });
                        
                        self.closeDeleteModal();
                    } else {
                        var errorMessage = 'Failed to delete set';
                        if (response && response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                        alert('Error: ' + errorMessage);
                        $btn.text(originalText).prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    var errorMessage = 'An error occurred while deleting the set.';
                    if (status === 'timeout') {
                        errorMessage = 'Delete operation timed out.';
                    }
                    alert(errorMessage);
                    $btn.text(originalText).prop('disabled', false);
                    console.error('Delete error:', xhr, status, error);
                }
            });
        },
        
        handleModalKeypress: function(e) {
            if (e.keyCode === 27) { // Escape key
                ZipPicksMasterCriticAdmin.closeDeleteModal();
            }
        },
        
        // Bulk actions handler
        handleBulkAction: function() {
            var action = $('#bulk-action-selector-top').val();
            var checkedBoxes = $('input[name="set_ids[]"]:checked');
            
            if (action === '-1') {
                alert('Please select an action.');
                return false;
            }
            
            if (checkedBoxes.length === 0) {
                alert('Please select at least one set.');
                return false;
            }
            
            if (action === 'delete') {
                var confirmMessage = 'Are you sure you want to delete the selected sets? This action cannot be undone.';
                if (!confirm(confirmMessage)) {
                    return false;
                }
            }
            
            // For now, show what would happen (implement actual bulk actions later)
            var actionText = action.charAt(0).toUpperCase() + action.slice(1);
            alert('Bulk ' + actionText + ' would be performed on ' + checkedBoxes.length + ' sets. (Feature implementation pending)');
            
            return false;
        }
    };
    
    // Global functions for backwards compatibility with inline onclick handlers
    window.confirmDelete = function(setId, setName) {
        ZipPicksMasterCriticAdmin.confirmDelete(setId, setName);
    };
    
    window.closeDeleteModal = function() {
        ZipPicksMasterCriticAdmin.closeDeleteModal();
    };
    
    window.executeDelete = function() {
        ZipPicksMasterCriticAdmin.executeDelete();
    };
    
    window.handleBulkAction = function() {
        return ZipPicksMasterCriticAdmin.handleBulkAction();
    };
    
    window.clearMasterCriticsCache = function() {
        return ZipPicksMasterCriticAdmin.handleCacheClear.call($(this));
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        ZipPicksMasterCriticAdmin.init();
    });
    
})(jQuery);

// Utility functions
(function() {
    'use strict';
    
    // Generic AJAX error handler
    function handleAjaxError(xhr, status, error, context) {
        console.group('AJAX Error - ' + (context || 'Unknown Context'));
        console.error('Status:', status);
        console.error('Error:', error);
        console.error('Response:', xhr.responseText);
        console.groupEnd();
        
        // Log to server if possible
        if (typeof zpmc_ajax !== 'undefined' && zpmc_ajax.ajax_url) {
            $.post(zpmc_ajax.ajax_url, {
                action: 'zpmc_log_js_error',
                error: {
                    status: status,
                    error: error,
                    context: context,
                    url: window.location.href,
                    userAgent: navigator.userAgent
                },
                nonce: zpmc_ajax.log_nonce || ''
            }).fail(function() {
                // Fail silently - don't create error loops
            });
        }
    }
    
    // Expose globally for error handling
    window.ZipPicksMasterCriticAdmin.handleAjaxError = handleAjaxError;
    
})();