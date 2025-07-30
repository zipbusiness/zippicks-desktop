/**
 * ZipPicks Client-Side Error Reporter
 * Captures and reports JavaScript errors to the server
 */
(function() {
    'use strict';
    
    // Only run if ajax_url is defined
    if (typeof zippicks_ajax === 'undefined' || !zippicks_ajax.ajax_url) {
        return;
    }
    
    // Track reported errors to avoid duplicates
    const reportedErrors = new Set();
    
    /**
     * Generate error signature for deduplication
     */
    function getErrorSignature(message, source, lineno) {
        return `${message}::${source}::${lineno}`;
    }
    
    /**
     * Report error to server
     */
    function reportError(errorData) {
        const signature = getErrorSignature(errorData.message, errorData.file, errorData.line);
        
        // Skip if already reported
        if (reportedErrors.has(signature)) {
            return;
        }
        
        reportedErrors.add(signature);
        
        // Send to server
        const formData = new FormData();
        formData.append('action', 'zippicks_report_js_error');
        formData.append('nonce', zippicks_ajax.nonce);
        formData.append('message', errorData.message);
        formData.append('file', errorData.file);
        formData.append('line', errorData.line);
        formData.append('column', errorData.column);
        formData.append('stack', errorData.stack);
        formData.append('url', window.location.href);
        
        fetch(zippicks_ajax.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).catch(function() {
            // Silently fail if reporting fails
        });
    }
    
    /**
     * Global error handler
     */
    window.addEventListener('error', function(event) {
        // Skip if not a ZipPicks-related error
        if (!event.filename || event.filename.indexOf('zippicks') === -1) {
            return;
        }
        
        const errorData = {
            message: event.message || 'Unknown error',
            file: event.filename || 'unknown',
            line: event.lineno || 0,
            column: event.colno || 0,
            stack: event.error && event.error.stack ? event.error.stack : ''
        };
        
        reportError(errorData);
    });
    
    /**
     * Promise rejection handler
     */
    window.addEventListener('unhandledrejection', function(event) {
        const errorData = {
            message: 'Unhandled Promise Rejection: ' + (event.reason || 'Unknown'),
            file: 'promise',
            line: 0,
            column: 0,
            stack: event.reason && event.reason.stack ? event.reason.stack : ''
        };
        
        reportError(errorData);
    });
    
    /**
     * Manual error reporting function
     */
    window.ZipPicksErrorReporter = {
        report: function(error) {
            if (error instanceof Error) {
                reportError({
                    message: error.message,
                    file: error.fileName || 'manual',
                    line: error.lineNumber || 0,
                    column: error.columnNumber || 0,
                    stack: error.stack || ''
                });
            } else {
                reportError({
                    message: String(error),
                    file: 'manual',
                    line: 0,
                    column: 0,
                    stack: ''
                });
            }
        }
    };
})();