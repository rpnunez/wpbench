jQuery(document).ready(function($) {

    // --- Profile Loader Logic ---
    $('#wpbench-load-profile-btn').on('click', function() {
        var profileId = $('#wpbench_profile_loader').val();
        var $status = $('#wpbench-profile-loader-status');
        var $button = $(this);

        if (!profileId) {
            alert(wpbench_ajax.select_profile_alert || 'Please select a profile to load.');
            return;
        }

        $status.show();
        $button.prop('disabled', true);

        $.post(wpbench_ajax.ajax_url, {
            action: 'wpbench_load_profile', // Matches action hook in Plugin.php
            nonce: wpbench_ajax.load_profile_nonce, // Use localized nonce
            profile_id: profileId
        })
        .done(function(response) {
            if (response.success && response.data) {
                var data = response.data;
                var profileName = $('#wpbench_profile_loader option:selected').text();

                // Populate fields
                $('#benchmark_name').val(data.name_suggestion || 'Benchmark from ' + profileName);
                $('#profile_id_used').val(profileId);

                // Update selected tests checkboxes
                $('#wpbench-test-selector-container input[type="checkbox"]').prop('checked', false);
                if (data.selected_tests && Array.isArray(data.selected_tests)) {
                    data.selected_tests.forEach(function(testId) {
                        if($('#test_' + testId).length) { $('#test_' + testId).prop('checked', true); }
                        else { console.warn("Profile Loader: Test checkbox not found:", testId); }
                    });
                }

                // Update config inputs
                if (data.config && typeof data.config === 'object') {
                     $('.wpbench-config-row input[type="number"]').each(function() {
                           var inputId = $(this).attr('id'); // e.g., config_cpu
                           if (inputId && data.config[inputId] !== undefined && data.config[inputId] !== null) {
                               $(this).val(data.config[inputId]);
                           }
                           // Keep default HTML value if profile doesn't specify
                      });
                }

                // Update desired plugins checkboxes
                 $('#wpbench-plugin-selector-container input[type="checkbox"]').prop('checked', false);
                 if (data.desired_plugins && Array.isArray(data.desired_plugins)) {
                    data.desired_plugins.forEach(function(pluginFile) {
                        var inputIdSuffix = pluginFile.replace(/[^a-zA-Z0-9_\-\.:]/g, '_');
                        var $checkbox = $('#plugin_' + inputIdSuffix);
                        if($checkbox.length && !$checkbox.is(':disabled')) {
                           $checkbox.prop('checked', true);
                        } else if ($checkbox.length && $checkbox.is(':disabled')) {
                            $checkbox.prop('checked', true); // Ensure disabled stay checked if in profile
                        } else {
                            console.warn("Profile Loader: Plugin checkbox not found:", pluginFile);
                        }
                    });
                }
                // Ensure disabled (Network Active) plugins remain checked visually
                $('#wpbench-plugin-selector-container input[type="checkbox"]:disabled').prop('checked', true);

            } else {
                alert( (wpbench_ajax.load_profile_error_alert || 'Error loading profile:') + ' ' + (response.data?.message || response.data || 'Unknown error.') );
            }
        })
        .fail(function(xhr) {
            alert(wpbench_ajax.ajax_error_alert || 'AJAX error loading profile. Check console.');
            console.error('Profile Load Error:', xhr);
        })
        .always(function() {
            $status.hide();
            $button.prop('disabled', false);
        });
    }); // End Profile Loader Click

    // --- Benchmark Run Form Submission ---
    $('#wpbench-run-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $('#wpbench-start-button');
        var $resultsArea = $('#wpbench-results-area');
        var $statusBar = $('#wpbench-status');
        var $progressBar = $('#wpbench-progress-bar');
        var $finalResults = $('#wpbench-final-results');
        var progressInterval; // Define interval variable

       // --- Client-Side Validation ---
       if ($('#benchmark_name').val().trim() === '') {
            alert('Please enter a benchmark name.'); // TODO: Use localized string

            $('#benchmark_name').focus();

            return;
        }

        if ($('input[name="selected_tests[]"]:checked').length === 0) {
            alert(wpbench_ajax.validation_select_test || 'You must select at least one Benchmark Test.');

            $('input[name="selected_tests[]"]').first().focus();

            return;
        }
        // --- End Validation ---

        // Prepare data & UI updates
        var dataToSend = $form.serialize() + '&action=wpbench_run_benchmark&nonce=' + wpbench_ajax.run_nonce;
        $button.prop('disabled', true).val(wpbench_ajax.running_text);
        $resultsArea.slideDown();

        // Add AJAX action and nonce (already localized)
        // Note: Using serialize() includes the nonce field from the form if present
        // We still need the action for admin-ajax.php routing

        // --- UI updates - start ---
        $button.prop('disabled', true).val(wpbench_ajax.running_text);
        $resultsArea.slideDown();
        $statusBar.text('Benchmark starting... Please wait.');
        $progressBar.removeClass('error').css('background-color', '#4CAF50');
        $progressBar.css('width', '5%').text('5%');
        $finalResults.html('');

        // Simulate progress (since PHP runs sequentially in one request)
        if (progressInterval) { 
            clearInterval(progressInterval); 
        }

        progressInterval = setInterval(function() { 
            var currentWidth = parseFloat($progressBar.css('width')) / $progressBar.parent().width() * 100;
            var increment = (currentWidth < 80) ? Math.random() * 8 : Math.random() * 2;
            var newWidth = Math.min(currentWidth + increment, 95);
            $progressBar.css('width', newWidth + '%').text(Math.round(newWidth) + '%');
        }, 600); // Update progress roughly every 0.6 seconds

        // --- AJAX request ---
        // Use dataToSend which includes action and nonce properly formatted
        $.post(wpbench_ajax.ajax_url, dataToSend)
            .done(function(response) {
                // --- (Identical .done() handler as before) ---
                 clearInterval(progressInterval); // Stop simulating progress

                if (response.success) {
                    $statusBar.text(wpbench_ajax.complete_text);
                    $progressBar.css('width', '100%').text('100%');

                    // Build results display
                    var resultsHtml = '<h4>Results Summary:</h4><ul>';
                    var results = response.data.results || {}; // Ensure results object exists

                    resultsHtml += '<li>Total Time: ' + (results.total_time !== undefined ? results.total_time : 'N/A') + ' s</li>';

                    // Display results only for keys present in the response (which correspond to run tests)
                    // We rely on the PHP handler only returning results for run tests.
                    if(results.cpu) {
                        resultsHtml += '<li>CPU Time: ' + (results.cpu.time !== undefined ? results.cpu.time : 'N/A') + ' s' + (results.cpu.error ? ' <span style="color:red;" title="'+results.cpu.error+'">(!)</span>' : '') + '</li>';
                    }
                    
                    if(results.memory) resultsHtml += '<li>Memory Peak: ' + (results.memory.peak_usage_mb !== undefined ? results.memory.peak_usage_mb : 'N/A') + ' MB' + (results.memory.error ? ' <span style="color:red;" title="'+results.memory.error+'">(!)</span>' : '') + '</li>';

                    if(results.file_io) resultsHtml += '<li>File I/O Time: ' + (results.file_io.time !== undefined ? results.file_io.time : 'N/A') + ' s' + (results.file_io.error ? ' <span style="color:red;" title="'+results.file_io.error+'">(!)</span>' : '') + '</li>';

                    if(results.db_read) resultsHtml += '<li>DB Read Time: ' + (results.db_read.time !== undefined ? results.db_read.time : 'N/A') + ' s' + (results.db_read.error ? ' <span style="color:red;" title="'+results.db_read.error+'">(!)</span>' : '') + '</li>';

                    if(results.db_write) resultsHtml += '<li>DB Write Time: ' + (results.db_write.time !== undefined ? results.db_write.time : 'N/A') + ' s' + (results.db_write.error ? ' <span style="color:red;" title="'+results.db_write.error+'">(!)</span>' : '') + '</li>';

                    resultsHtml += '</ul>';

                    if (response.data.view_url) {
                        resultsHtml += '<p><a href="' + response.data.view_url + '" class="button button-secondary">' + wpbench_ajax.view_results_text + '</a></p>';
                    }

                    $finalResults.html(resultsHtml);
                } else {
                    $statusBar.text(wpbench_ajax.error_text + ': ' + (response.data && response.data.message ? response.data.message : 'Unknown server error.'));

                    $progressBar.css('width', '100%').css('background-color', 'red').text('Error').addClass('error');
                }
            })
            .fail(function(xhr, textStatus, errorThrown) {
                 clearInterval(progressInterval);

                 console.error("WPBench AJAX Error:", textStatus, errorThrown, xhr.responseText);

                 var errorMsg = wpbench_ajax.error_text + '. Status: ' + xhr.status + ' (' + (errorThrown || textStatus) + '). Check browser console for details.';

                 if(xhr.status === 0) {
                    errorMsg = wpbench_ajax.error_text + '. Network error or request cancelled. Check connection and server availability.';
                 } else if (xhr.status === 500 && xhr.responseText && xhr.responseText.     toLowerCase().includes("maximum execution time")) {
                    errorMsg = wpbench_ajax.error_text + '. Server timeout (max_execution_time exceeded). Try lower iteration counts.';
                 } else if (xhr.status === 504) {
                     errorMsg = wpbench_ajax.error_text + '. Gateway timeout. Server took too long to respond. Try lower iteration counts.';
                 }

                 $statusBar.text(errorMsg);
                 $progressBar.css('width', '100%').css('background-color', 'red').text('Error').addClass('error');
            })
            .always(function() {
                // Re-enable button
                $button.prop('disabled', false).val( $( '#submit' ).val() || 'Start Benchmark' );
            });
    });
});