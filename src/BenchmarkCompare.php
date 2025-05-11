<?php
namespace WPBench;

// Required classes
use WPBench\AdminBenchmark; // For CPT constant & meta keys
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
        if (!current_user_can('manage_options')) { wp_die(/*...*/); }
        $ids_raw = isset($_GET['ids']) ? sanitize_text_field($_GET['ids']) : '';
        $post_ids = !empty($ids_raw) ? array_map('absint', explode(',', $ids_raw)) : [];
        $post_ids = array_filter(array_unique($post_ids));
        if (count($post_ids) < 2) { wp_die(__('Please select at least two benchmarks to compare.', 'wpbench')); }

        // --- Fetch Data for Each Benchmark ---
        $benchmarks_data = []; $error_messages = [];
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);

            // Ensure post exists and is the correct type
            if (!$post || $post->post_type !== AdminBenchmark::POST_TYPE) {
                $error_messages[] = sprintf(__('Invalid benchmark ID or type for post %d.', 'wpbench'), $post_id);
                continue;
            }

            // Fetch all relevant data using AdminBenchmark constants and PluginState methods
            $benchmarks_data[$post_id] = [
                'post' => $post,
                'config' => get_post_meta($post_id, AdminBenchmark::META_CONFIG, true),
                'results' => get_post_meta($post_id, AdminBenchmark::META_RESULTS, true),
                'selected_tests' => get_post_meta($post_id, AdminBenchmark::META_SELECTED_TESTS, true),
                'pre_benchmark_state' => $this->pluginState->getPreBenchmarkState($post_id),
                'desired_plugins' => $this->pluginState->getDesiredState($post_id),
                'active_plugins_final' => get_post_meta($post_id, PluginState::ACTUAL_PLUGINS_META_KEY, true),
                'profile_id_used' => get_post_meta($post_id, AdminBenchmark::META_PROFILE_ID_USED, true),
                'profile_state_during_run' => get_post_meta($post_id, AdminBenchmark::META_PROFILE_STATE_DURING_RUN, true),
                'score' => get_post_meta($post_id, AdminBenchmark::META_SCORE, true),
            ];

            //?// $benchmarks_data[$post_id]['profile_title'] = get_the_title($benchmarks_data[$post_id]['profile_id_used']);
            //?// $benchmarks_data[$post_id]['profile_link'] = get_edit_post_link($benchmarks_data[$post_id]['profile_id_used']);

            // Basic check/ensure array type for data expected as arrays
            $benchmarks_data[$post_id]['selected_tests'] = is_array($benchmarks_data[$post_id]['selected_tests']) ? $benchmarks_data[$post_id]['selected_tests'] : [];
            $benchmarks_data[$post_id]['desired_plugins'] = is_array($benchmarks_data[$post_id]['desired_plugins']) ? $benchmarks_data[$post_id]['desired_plugins'] : [];
            $benchmarks_data[$post_id]['active_plugins_final'] = is_array($benchmarks_data[$post_id]['active_plugins_final']) ? $benchmarks_data[$post_id]['active_plugins_final'] : [];
            $benchmarks_data[$post_id]['results'] = is_array($benchmarks_data[$post_id]['results']) ? $benchmarks_data[$post_id]['results'] : [];
            $benchmarks_data[$post_id]['config'] = is_array($benchmarks_data[$post_id]['config']) ? $benchmarks_data[$post_id]['config'] : [];
            $benchmarks_data[$post_id]['profile_state_during_run'] = is_array($benchmarks_data[$post_id]['profile_state_during_run']) ? $benchmarks_data[$post_id]['profile_state_during_run'] : [];
        }

        if (count($benchmarks_data) < 2) {
            wp_die(/*...*/);
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