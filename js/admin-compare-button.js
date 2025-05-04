jQuery(document).ready(function($){
    // Check if we are on the correct screen (benchmark_result list table)
    // and if the compare button and localized data exist.
    if (typeof wp.data === 'function' && typeof wp.data.select === 'function') {
        // More robust check using wp.data if available (Gutenberg context)
        const currentPostType = wp.data.select('core/editor')?.getCurrentPostType();
        if (currentPostType !== 'benchmark_result' && $('body').hasClass('edit-php') && $('body').hasClass('post-type-benchmark_result')) {
            // Likely the list table screen, proceed.
        } else if (!$('body').hasClass('post-type-benchmark_result') || !$('body').hasClass('edit-php')) {
            return; // Exit if not the benchmark result list table
        }
    } else if (!$('body').hasClass('post-type-benchmark_result') || !$('body').hasClass('edit-php')) {
        // Fallback check for older WP or non-block editor contexts
        return;
    }


    var $compareBtn = $('#wpbench-compare-btn');
    // Check if button exists before proceeding
    if ($compareBtn.length === 0) {
        return;
    }
    // Ensure localized data is available
    if (typeof wpbenchCompare === 'undefined' || !wpbenchCompare.compareUrlBase) {
        console.error('WPBench Error: Compare button JS missing required localized data (wpbenchCompare).');
        return;
    }

    var $checkboxes = $('tbody input[name="post[]"]'); // Target checkboxes within the table body

    function toggleCompareButton() {
        var checkedCount = $checkboxes.filter(':checked').length;
        // Enable button if 2 or more are checked
        $compareBtn.prop('disabled', checkedCount < 2);
    }

    // Initial check on page load
    toggleCompareButton();

    // Check when any checkbox in the table body changes
    // Use event delegation for potentially loaded via AJAX later? Safer.
    $('#the-list').on('change', 'input[name="post[]"]', toggleCompareButton);

    // Also check when the header/footer bulk checkboxes change
    $('input#cb-select-all-1, input#cb-select-all-2').on('change', function() {
        // Need a slight delay as the individual checkboxes might update after this event
        setTimeout(toggleCompareButton, 50);
    });

    // Handle button click
    $compareBtn.on('click', function(e){
        e.preventDefault();
        var selectedIds = [];
        $checkboxes.filter(':checked').each(function(){
            selectedIds.push($(this).val());
        });

        if (selectedIds.length >= 2) {
            // Redirect to compare page with IDs
            window.location.href = wpbenchCompare.compareUrlBase + '&ids=' + selectedIds.join(',');
        } else {
            alert(wpbenchCompare.selectText || 'Please select at least 2 benchmarks to compare.');
        }
    });
});