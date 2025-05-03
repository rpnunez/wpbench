<?php
namespace WPBench;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles enqueueing of CSS and JavaScript files.
 */
class Assets {

    /**
     * Enqueue admin scripts and styles.
     * Hooked into 'admin_enqueue_scripts'.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        $screen = get_current_screen();

        // Check if it's one of our plugin's admin pages
        $is_wpbench_page = ($hook === 'toplevel_page_wpbench_main_menu' || strpos($hook, 'wpbench_page_') === 0);

        // Check if it's the edit screen for our CPT
        $is_wpbench_cpt_edit = ($hook === 'post.php' && isset($screen->post_type) && $screen->post_type === CustomPostType::POST_TYPE);
        // $is_wpbench_cpt_new = ($hook === 'post-new.php' && isset($screen->post_type) && $screen->post_type === CustomPostType::POST_TYPE); // Not strictly needed for viewing results

        // Scripts for the "Run New Benchmark" page
        if ( $is_wpbench_page ) {
            wp_enqueue_script(
                'wpbench-admin-js',
                WPBENCH_URL . 'js/admin-benchmark.js',
                [ 'jquery' ],
                WPBENCH_VERSION,
                true // Load in footer
            );

            // Localize script with data for AJAX calls
            wp_localize_script( 'wpbench-admin-js', 'wpbench_ajax', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'run_nonce' => wp_create_nonce( 'wpbench_run_action_ajax' ), // Nonce for AJAX verification
                'running_text' => __('Running...', 'wpbench'),
                'complete_text' => __('Benchmark Complete!', 'wpbench'),
                'error_text' => __('An error occurred.', 'wpbench'),
                'view_results_text' => __('View Results', 'wpbench'),
            ]);
        }

        // Scripts and styles for the Benchmark Result CPT edit screen (to display charts)
        if ( $is_wpbench_cpt_edit ) {
            // ... (keep chart-js enqueue) ...
            wp_enqueue_script(
                'wpbench-results-js',
                WPBENCH_URL . 'js/admin-results.js',
                [ 'jquery', 'chart-js' ],
                WPBENCH_VERSION,
                true
               );

            // Pass result data from PHP to our results JS script
            global $post;
            if ( $post && $post->ID && $post->post_type === CustomPostType::POST_TYPE ) {
                $results_data = get_post_meta( $post->ID, '_wpbench_results', true );
                $config_data = get_post_meta( $post->ID, '_wpbench_config', true );
                $selected_tests_data = get_post_meta( $post->ID, '_wpbench_selected_tests', true); // Get selected tests

                wp_localize_script( 'wpbench-results-js', 'wpbench_results_data', [
                    'results' => is_array($results_data) ? $results_data : [],
                    'config'  => is_array($config_data) ? $config_data : [],
                    'selected_tests' => is_array($selected_tests_data) ? $selected_tests_data : [], // Pass selected tests
                    'text' => [
                        // ... (keep existing text labels) ...
                        'cpu_time' => __('CPU Time (seconds)', 'wpbench'),
                        'memory_peak' => __('Peak Memory (MB)', 'wpbench'),
                        'file_io_time' => __('File I/O Time (seconds)', 'wpbench'),
                        'db_read_time' => __('DB Read Time (seconds)', 'wpbench'),
                        'db_write_time' => __('DB Write Time (seconds)', 'wpbench'),
                        'total_time' => __('Total Time (seconds)', 'wpbench'),
                        'benchmark_results' => __('Benchmark Results', 'wpbench'),
                        'error_text' => __('Error', 'wpbench')
                    ]
                ]);
            } else {
                 // Send empty data
                 wp_localize_script( 'wpbench-results-js', 'wpbench_results_data', [
                    'results' => [], 'config' => [], 'selected_tests' => [], 'text' => []
                ]);
            }
       }
    }
}