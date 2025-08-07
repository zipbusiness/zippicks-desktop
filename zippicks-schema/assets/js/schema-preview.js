/**
 * ZipPicks Schema Admin JavaScript
 * 
 * Handles schema testing, preview, and validation in admin
 */
(function($) {
    'use strict';
    
    const ZipPicksSchemaAdmin = {
        
        init: function() {
            this.bindEvents();
            this.loadSchemaHealth();
        },
        
        bindEvents: function() {
            // Schema test form
            $('#schema-test-form').on('submit', this.handleSchemaTest.bind(this));
            
            // Rich results form
            $('#rich-results-form').on('submit', this.handleRichResults.bind(this));
            
            // Clear cache button
            $('.clear-schema-cache').on('click', this.handleClearCache.bind(this));
            
            // Validate schema button
            $('.validate-schema').on('click', this.handleValidateSchema.bind(this));
            
            // Copy schema button
            $('.copy-schema').on('click', this.handleCopySchema.bind(this));
            
            // Refresh health button
            $('.refresh-health').on('click', this.loadSchemaHealth.bind(this));
        },
        
        handleSchemaTest: function(e) {
            e.preventDefault();
            
            const postId = $('#test-post-id').val();
            const $output = $('#schema-output');
            const $button = $(e.target).find('button[type="submit"]');
            
            if (!postId || isNaN(postId)) {
                this.showError($output, 'Please enter a valid post ID');
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true).text(zipPicksSchema.strings.loading);
            $output.html('<div class="loading-spinner"><div class="spinner"></div> Generating schema...</div>');
            
            // AJAX request
            $.ajax({
                url: zipPicksSchema.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'zippicks_schema_test',
                    nonce: zipPicksSchema.nonce,
                    post_id: postId
                },
                success: this.handleSchemaTestSuccess.bind(this, $output, $button),
                error: this.handleSchemaTestError.bind(this, $output, $button)
            });
        },
        
        handleSchemaTestSuccess: function($output, $button, response) {
            $button.prop('disabled', false).text('Generate Schema');
            
            if (!response.success) {
                this.showError($output, response.data || 'Schema generation failed');
                return;
            }
            
            const data = response.data;
            let html = '<div class="schema-results">';
            
            // Post information
            html += '<div class="schema-post-info">';
            html += '<h3>Post: ' + this.escapeHtml(data.post_title) + '</h3>';
            html += '</div>';
            
            // Validation status
            if (data.validation) {
                html += '<div class="schema-validation ' + (data.validation.valid ? 'valid' : 'invalid') + '">';
                html += '<h4>Validation: ' + (data.validation.valid ? zipPicksSchema.strings.validSchema : zipPicksSchema.strings.invalidSchema) + '</h4>';
                
                if (data.validation.errors && data.validation.errors.length > 0) {
                    html += '<div class="validation-errors"><strong>Errors:</strong><ul>';
                    data.validation.errors.forEach(function(error) {
                        html += '<li>' + this.escapeHtml(error) + '</li>';
                    }.bind(this));
                    html += '</ul></div>';
                }
                
                if (data.validation.warnings && data.validation.warnings.length > 0) {
                    html += '<div class="validation-warnings"><strong>Warnings:</strong><ul>';
                    data.validation.warnings.forEach(function(warning) {
                        html += '<li>' + this.escapeHtml(warning) + '</li>';
                    }.bind(this));
                    html += '</ul></div>';
                }
                html += '</div>';
            }
            
            // Schema JSON
            html += '<div class="schema-json-container">';
            html += '<div class="schema-actions">';
            html += '<button type="button" class="button copy-schema" data-schema="' + this.escapeHtml(data.schema_json) + '">Copy Schema</button>';
            html += '<button type="button" class="button validate-schema" data-schema="' + this.escapeHtml(JSON.stringify(data.schema)) + '">Validate with Google</button>';
            html += '</div>';
            html += '<h4>Generated Schema:</h4>';
            html += '<pre class="schema-json"><code>' + this.escapeHtml(data.schema_json) + '</code></pre>';
            html += '</div>';
            
            html += '</div>';
            
            $output.html(html);
            
            // Re-bind events for new elements
            this.bindEvents();
        },
        
        handleSchemaTestError: function($output, $button, xhr) {
            $button.prop('disabled', false).text('Generate Schema');
            
            let errorMessage = zipPicksSchema.strings.error;
            if (xhr.responseJSON && xhr.responseJSON.data) {
                errorMessage = xhr.responseJSON.data;
            } else if (xhr.responseText) {
                errorMessage = xhr.responseText;
            }
            
            this.showError($output, errorMessage);
        },
        
        handleRichResults: function(e) {
            e.preventDefault();
            
            const url = $('#test-url').val();
            const $button = $(e.target).find('button[type="submit"]');
            
            if (!url || !this.isValidUrl(url)) {
                alert('Please enter a valid URL');
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true).text(zipPicksSchema.strings.loading);
            
            $.ajax({
                url: zipPicksSchema.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'zippicks_schema_preview',
                    nonce: zipPicksSchema.nonce,
                    url: url
                },
                success: function(response) {
                    $button.prop('disabled', false).text('Test with Google');
                    
                    if (response.success && response.data.test_url) {
                        window.open(response.data.test_url, '_blank');
                    } else {
                        alert('Failed to generate test URL');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text('Test with Google');
                    alert('Request failed. Please try again.');
                }
            });
        },
        
        handleClearCache: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const postId = $button.data('post-id') || 'all';
            
            if (!confirm('Are you sure you want to clear the schema cache?')) {
                return;
            }
            
            $button.prop('disabled', true).text('Clearing...');
            
            $.ajax({
                url: wpApiSettings.root + 'zippicks/v1/schema/cache/clear',
                method: 'POST',
                data: {
                    post_id: postId
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                },
                success: function(response) {
                    $button.prop('disabled', false).text('Clear Cache');
                    
                    if (response.success) {
                        alert('Schema cache cleared successfully');
                    } else {
                        alert('Failed to clear cache');
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text('Clear Cache');
                    alert('Request failed. Please try again.');
                }
            });
        },
        
        handleValidateSchema: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const schemaJson = $button.data('schema');
            
            if (!schemaJson) {
                alert('No schema data available');
                return;
            }
            
            let schema;
            try {
                schema = typeof schemaJson === 'string' ? JSON.parse(schemaJson) : schemaJson;
            } catch (error) {
                alert('Invalid schema JSON');
                return;
            }
            
            $button.prop('disabled', true).text('Validating...');
            
            $.ajax({
                url: wpApiSettings.root + 'zippicks/v1/schema/validate',
                method: 'POST',
                data: {
                    schema: schema,
                    test_google: true
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                    xhr.setRequestHeader('Content-Type', 'application/json');
                },
                success: function(response) {
                    $button.prop('disabled', false).text('Validate with Google');
                    
                    if (response.success) {
                        this.showValidationResults(response);
                    } else {
                        alert('Validation failed');
                    }
                }.bind(this),
                error: function() {
                    $button.prop('disabled', false).text('Validate with Google');
                    alert('Validation request failed');
                }
            });
        },
        
        handleCopySchema: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const schemaJson = $button.data('schema');
            
            if (!schemaJson) {
                alert('No schema data to copy');
                return;
            }
            
            // Copy to clipboard
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(schemaJson).then(function() {
                    $button.text('Copied!');
                    setTimeout(function() {
                        $button.text('Copy Schema');
                    }, 2000);
                }).catch(function() {
                    this.fallbackCopyToClipboard(schemaJson, $button);
                }.bind(this));
            } else {
                this.fallbackCopyToClipboard(schemaJson, $button);
            }
        },
        
        fallbackCopyToClipboard: function(text, $button) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                $button.text('Copied!');
                setTimeout(function() {
                    $button.text('Copy Schema');
                }, 2000);
            } catch (err) {
                alert('Failed to copy to clipboard');
            } finally {
                document.body.removeChild(textArea);
            }
        },
        
        loadSchemaHealth: function() {
            const $healthStatus = $('#schema-health-status');
            
            $healthStatus.html('<div class="loading-spinner"><div class="spinner"></div> Loading health status...</div>');
            
            $.ajax({
                url: zipPicksSchema.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'zippicks_schema_health',
                    nonce: zipPicksSchema.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.displayHealthStatus($healthStatus, response.data);
                    } else {
                        this.showError($healthStatus, 'Failed to load health status');
                    }
                }.bind(this),
                error: function() {
                    this.showError($healthStatus, 'Health status request failed');
                }.bind(this)
            });
        },
        
        displayHealthStatus: function($container, health) {
            let html = '<div class="schema-health-status ' + health.status + '">';
            html += '<h4>Status: <span class="status-indicator">' + health.status.toUpperCase() + '</span></h4>';
            
            // Issues
            if (health.issues && health.issues.length > 0) {
                html += '<div class="health-issues"><strong>Issues:</strong><ul>';
                health.issues.forEach(function(issue) {
                    html += '<li>' + this.escapeHtml(issue) + '</li>';
                }.bind(this));
                html += '</ul></div>';
            }
            
            // Statistics
            if (health.stats) {
                html += '<div class="health-stats"><h5>Statistics:</h5><table>';
                Object.keys(health.stats).forEach(function(key) {
                    const label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    html += '<tr><td>' + label + ':</td><td>' + health.stats[key] + '</td></tr>';
                });
                html += '</table></div>';
            }
            
            html += '<div class="health-actions">';
            html += '<button type="button" class="button refresh-health">Refresh</button>';
            html += '<button type="button" class="button clear-schema-cache" data-post-id="all">Clear All Cache</button>';
            html += '</div>';
            
            html += '</div>';
            
            $container.html(html);
        },
        
        showValidationResults: function(response) {
            const validation = response.validation;
            const googleTest = response.google_test;
            
            let html = '<div class="validation-results-popup">';
            html += '<div class="validation-overlay">';
            html += '<div class="validation-modal">';
            html += '<div class="validation-header">';
            html += '<h3>Schema Validation Results</h3>';
            html += '<button type="button" class="close-validation">×</button>';
            html += '</div>';
            
            html += '<div class="validation-content">';
            
            // Basic validation
            html += '<div class="validation-basic ' + (validation.valid ? 'valid' : 'invalid') + '">';
            html += '<h4>' + (validation.valid ? 'Valid Schema' : 'Invalid Schema') + '</h4>';
            
            if (validation.errors && validation.errors.length > 0) {
                html += '<div class="validation-errors"><strong>Errors:</strong><ul>';
                validation.errors.forEach(function(error) {
                    html += '<li>' + this.escapeHtml(error) + '</li>';
                }.bind(this));
                html += '</ul></div>';
            }
            
            if (validation.warnings && validation.warnings.length > 0) {
                html += '<div class="validation-warnings"><strong>Warnings:</strong><ul>';
                validation.warnings.forEach(function(warning) {
                    html += '<li>' + this.escapeHtml(warning) + '</li>';
                }.bind(this));
                html += '</ul></div>';
            }
            html += '</div>';
            
            // Google test results
            if (googleTest) {
                html += '<div class="google-test-results">';
                html += '<h4>Google Rich Results Eligibility</h4>';
                html += '<p>Eligible: ' + (googleTest.eligible_for_rich_results ? 'Yes' : 'No') + '</p>';
                
                if (googleTest.google_issues && googleTest.google_issues.length > 0) {
                    html += '<div class="google-issues"><strong>Recommendations:</strong><ul>';
                    googleTest.google_issues.forEach(function(issue) {
                        html += '<li>' + this.escapeHtml(issue) + '</li>';
                    }.bind(this));
                    html += '</ul></div>';
                }
                html += '</div>';
            }
            
            html += '</div>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
            
            // Add to page
            $('body').append(html);
            
            // Bind close event
            $('.close-validation, .validation-overlay').on('click', function(e) {
                if (e.target === this) {
                    $('.validation-results-popup').remove();
                }
            });
        },
        
        showError: function($container, message) {
            $container.html('<div class="error-message"><strong>Error:</strong> ' + this.escapeHtml(message) + '</div>');
        },
        
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        isValidUrl: function(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        ZipPicksSchemaAdmin.init();
    });
    
})(jQuery);