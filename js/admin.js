/**
 * Broken Links Scanner Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var $scanButton = $('#bls-scan-button');
        var $clearButton = $('#bls-clear-button');
        var $status = $('#bls-status');

        /**
         * Run scan button click handler
         */
        $scanButton.on('click', function(e) {
            e.preventDefault();

            if ($scanButton.prop('disabled')) {
                return;
            }

            // Disable button and show status
            $scanButton.prop('disabled', true);
            $status.removeClass('success error').addClass('active');
            $status.html('<span class="bls-spinner"></span>' + brokenLinksScanner.scanning);

            $.ajax({
                url: brokenLinksScanner.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'bls_run_scan',
                    nonce: brokenLinksScanner.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.removeClass('error').addClass('success');
                        $status.html('✓ ' + response.data.message);

                        // Reload page after 1 second to show new results
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showError(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    showError('An error occurred during the scan: ' + error);
                },
                complete: function() {
                    $scanButton.prop('disabled', false);
                }
            });
        });

        /**
         * Clear results button click handler
         */
        $clearButton.on('click', function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to clear all scan results?')) {
                return;
            }

            $.ajax({
                url: brokenLinksScanner.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'bls_clear_results',
                    nonce: brokenLinksScanner.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.removeClass('error').addClass('success active');
                        $status.html('✓ ' + response.data);

                        // Reload page after 1 second
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showError(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    showError('An error occurred while clearing results: ' + error);
                }
            });
        });

        /**
         * Display error message
         */
        function showError(message) {
            $status.removeClass('success').addClass('error active');
            $status.html('✗ ' + message);
        }
    });

})(jQuery);
