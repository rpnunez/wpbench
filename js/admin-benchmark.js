jQuery(document).ready(function($) {
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
            alert('Please enter a benchmark name.');
             $('#benchmark_name').focus();
            return; // Stop processing
        }

        // Check if at least one test checkbox is selected
        if ($('input[name="selected_tests[]"]:checked').length === 0) {
             alert('You must select at least one Benchmark Test to run.');
             // Optionally focus the first checkbox or the fieldset
              $('input[name="selected_tests[]"]').first().focus();
             return; // Stop processing
        }
        // --- End Validation ---


        // Prepare data
        var formData = $form.serialize(); // serialize() handles checkboxes correctly

        // Add AJAX action and nonce (already localized)
        // Note: Using serialize() includes the nonce field from the form if present
        // We still need the action for admin-ajax.php routing
        var dataToSend = formData + '&action=wpbench_run_benchmark&nonce=' + wpbench_ajax.run_nonce;


        // --- UI updates - start ---
        $button.prop('disabled', true).val(wpbench_ajax.running_text);
        $resultsArea.slideDown();
        $statusBar.text('Benchmark starting... Please wait.');
        $progressBar.removeClass('error').css('background-color', '#4CAF50');
        $progressBar.css('width', '5%').text('5%');
        $finalResults.html('');

        if (progressInterval) { clearInterval(progressInterval); }
        progressInterval = setInterval(function() { /* ... (progress interval code identical to before) ... */
             var currentWidth = parseFloat($progressBar.css('width')) / $progressBar.parent().width() * 100;
             var increment = (currentWidth < 80) ? Math.random() * 8 : Math.random() * 2;
             var newWidth = Math.min(currentWidth + increment, 95);
              $progressBar.css('width', newWidth + '%').text(Math.round(newWidth) + '%');
         }, 600);


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
                    if(results.cpu) resultsHtml += '<li>CPU Time: ' + (results.cpu.time !== undefined ? results.cpu.time : 'N/A') + ' s' + (results.cpu.error ? ' <span style="color:red;" title="'+results.cpu.error+'">(!)</span>' : '') + '</li>';
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
                 // --- (Identical .fail() handler as before) ---
                 clearInterval(progressInterval);
                 console.error("WPBench AJAX Error:", textStatus, errorThrown, xhr.responseText);
                 var errorMsg = wpbench_ajax.error_text + '. Status: ' + xhr.status + ' (' + (errorThrown || textStatus) + '). Check browser console for details.';
                 if(xhr.status === 0) {
                    errorMsg = wpbench_ajax.error_text + '. Network error or request cancelled. Check connection and server availability.';
                 } else if (xhr.status === 500 && xhr.responseText && xhr.responseText.toLowerCase().includes("maximum execution time")) {
                    errorMsg = wpbench_ajax.error_text + '. Server timeout (max_execution_time exceeded). Try lower iteration counts.';
                 } else if (xhr.status === 504) {
                     errorMsg = wpbench_ajax.error_text + '. Gateway timeout. Server took too long to respond. Try lower iteration counts.';
                 }
                 $statusBar.text(errorMsg);
                 $progressBar.css('width', '100%').css('background-color', 'red').text('Error').addClass('error');
            })
            .always(function() {
                // --- (Identical .always() handler as before) ---
                $button.prop('disabled', false).val( $( '#submit' ).val() || 'Start Benchmark' );
            });
    });
});