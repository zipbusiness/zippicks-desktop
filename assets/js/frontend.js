// ZipPicks Core Frontend JavaScript
jQuery(document).ready(function($) {
    // Client error logging
    window.addEventListener('error', function(e) {
        if (typeof zippicks_core !== 'undefined') {
            $.ajax({
                url: zippicks_core.ajax_url,
                type: 'POST',
                data: {
                    action: 'zippicks_log_client_error',
                    nonce: zippicks_core.nonce,
                    error: e.message,
                    url: e.filename,
                    line: e.lineno
                }
            });
        }
    });
});