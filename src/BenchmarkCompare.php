<?php
namespace WPBench;

// Required classes
use WPBench\AdminBenchmark; // For CPT constant & meta keys
use WPBench\Helpers\Utility;
use WPBench\PluginState;
use WPBench\TestRegistry;
use WP_Post;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the Benchmark Comparison feature functionality.
 */
class BenchmarkCompare {

    const PAGE_SLUG = 'wpbench_compare';

    private $testRegistry;
    private $pluginState;

    public function __construct(TestRegistry $testRegistry = null, PluginState $pluginState = null) {
        $this->testRegistry = $testRegistry ?? new TestRegistry();
        $this->pluginState = $pluginState ?? new PluginState();
    }

    /**
     * Register hidden admin page for comparisons. Hooked via admin_menu.
     */
    public function register_page() {
        add_submenu_page(
            null, // Hidden page
            __('Compare Benchmark Results', 'wpbench'),
            __('Compare Benchmarks', 'wpbench'),
            'manage_options', // Capability
            self::PAGE_SLUG, // Page slug
            [$this, 'render_page'] // Callback function
        );
    }

    /**
     * Render the comparison page content. Includes the view file.
     * Callback for the hidden submenu page.
     */
    public function render_page() {
        // --- Security & Validation ---
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have access to this page.', 'wpbench'));
        }

        // Sanitize and filter incoming Benchmark IDs
//	    $ids_raw = isset($_GET['ids']) ? sanitize_text_field(wp_unslash($_GET['ids'])) : '';
//	    $post_ids = !empty($ids_raw) ? array_map('absint', explode(',', $ids_raw)) : [];
//	    $post_ids = array_filter($post_ids); // Remove zeros/invalid
//	    $post_ids = array_unique($post_ids);

        $post_ids = Utility::get_cleaned_post_ids($_GET['ids']);

        if (count($post_ids) < 2) {
	        // Handle case where fewer than 2 IDs were passed in the URL
	        wp_die(
        '<p>' . esc_html__('Please select at least two benchmarks to compare.', 'wpbench') . '</p>' .
                '<p><a href="' . esc_url(admin_url('edit.php?post_type=' . AdminBenchmark::POST_TYPE)) . '">' .
                esc_html__('Go back to All Benchmarks', 'wpbench') . '</a></p>',
                __('Comparison Error - Insufficient Selection', 'wpbench'),
		        [
                    'response' => 400,
                    'back_link' => true
                ]
	        );
        }

        // --- Fetch Data for Each Benchmark ---
        $benchmarks_data = [];
        $error_messages = [];

        foreach ($post_ids as $post_id) {
            $BenchmarkResult = new BenchmarkResultPost($post_id);

            // Ensure post exists and is the correct type
            if ($BenchmarkResult->post->post_type !== AdminBenchmark::POST_TYPE) {
                $error_messages[] = sprintf(__('Invalid benchmark ID or type for post %d.', 'wpbench'), $post_id);

                continue;
            }

            $benchmarks_data = $BenchmarkResult->getBenchmarkData();
        }

        if (count($benchmarks_data) < 2) {
            // --- COMPLETED wp_die() CALL ---
            $error_html = '<p>' . __('Could not load at least two valid benchmarks for comparison.', 'wpbench') . '</p>';

            if (!empty($error_messages)) {
                $error_html .= '<p>' . __('Specific issues found:', 'wpbench') . '</p><ul style="list-style:disc; margin-left: 20px;">';

                foreach ($error_messages as $err) {
                    $error_html .= '<li>' . esc_html($err) . '</li>';
                }

                $error_html .= '</ul>';
            }

            $error_html .= '<p><a href="' . esc_url(admin_url('edit.php?post_type=' . AdminBenchmark::POST_TYPE)) . '">' . __('Go back to All Benchmarks', 'wpbench') . '</a></p>';

            Logger::log($error_html, 'error');

            wp_die(
                $error_html, // Message content including specific errors
                __('Comparison Error - Invalid Data', 'wpbench'), // Title
                ['response' => 400, 'back_link' => true] // Arguments (Bad Request)
            );
            // --- END OF COMPLETED wp_die() CALL ---
        }

        // --- Prepare Variables for View ---
        $all_possible_tests = $this->testRegistry->get_available_tests();
        $all_plugins_info = get_plugins();

        // --- Include the View File ---
        include WPBENCH_PATH . 'views/admin/compare-benchmarks-page.php';
    }

    /**
     * Adds the "Compare Benchmarks" button HTML above/below the list table.
     * Hooked via `manage_posts_extra_tablenav`.
     *
     * @param string $which 'top' or 'bottom' indicating table nav position.
     */
    public function add_button_html($which) {
        global $typenow;
        // Only add to the top nav and only on the benchmark_result screen
        if ($typenow === AdminBenchmark::POST_TYPE && $which === 'top') {
            ?>
            <div class="alignleft actions wpbench-compare-action">
                <button type="button" id="wpbench-compare-btn" class="button" disabled>
                    <?php esc_html_e('Compare Benchmarks', 'wpbench'); ?>
                </button>
                <span class="wpbench-compare-tooltip" style="vertical-align: middle; margin-left: 5px;">
                    <span class="dashicons dashicons-editor-help"></span>
                    <span class="wpbench-tooltip-text">
                        <?php esc_attr_e('Select at least 2 benchmark results to compare them.', 'wpbench'); ?>
                    </span>
                </span>
            </div>
            <?php
        }
    }

    /**
     * Adds JavaScript variables needed for the compare button functionality.
     * The actual JS logic is in admin-compare-button.js.
     * Hooked via `admin_footer-edit.php`.
     */
    public function add_button_js_vars() {
        $screen = get_current_screen();
        // Only run on the benchmark_result list table screen
        if ($screen && $screen->id === 'edit-' . AdminBenchmark::POST_TYPE) {
            $compare_page_url = admin_url('admin.php?page=' . self::PAGE_SLUG);
            ?>
            <script type="text/javascript">
                // Pass data to the already enqueued admin-compare-button.js
                var wpbenchCompare = {
                    compareUrlBase: '<?php echo esc_url($compare_page_url); ?>',
                    selectText: '<?php echo esc_js(__('Please select at least 2 benchmarks to compare.', 'wpbench')); ?>'
                };
            </script>
            <?php
        }
    }

}