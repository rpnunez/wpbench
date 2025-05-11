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
	    $is_wpbench_run_page = ($hook === 'toplevel_page_wpbench_main_menu');
	    $is_wpbench_result_list = ($hook === 'edit.php' && isset($screen->post_type) && $screen->post_type === AdminBenchmark::POST_TYPE);
	    $is_wpbench_result_edit = ($hook === 'post.php' && isset($screen->post_type) && $screen->post_type === AdminBenchmark::POST_TYPE);
	    $is_wpbench_profile_edit = ( ($hook === 'post.php' || $hook === 'post-new.php') && isset($screen->post_type) && $screen->post_type === BenchmarkProfileAdmin::POST_TYPE);
	    $is_wpbench_compare_page = ($hook === 'wpbench_page_' . BenchmarkCompare::PAGE_SLUG || (isset($_GET['page']) && $_GET['page'] === BenchmarkCompare::PAGE_SLUG));


	    // --- Script for Run Page & Profile Edit Page ---
        // This script now handles the profile loader on the run page.
        if ( $is_wpbench_run_page || $is_wpbench_profile_edit ) {
            wp_enqueue_script(
                'wpbench-admin-js', WPBENCH_URL . 'js/admin-benchmark.js',
                [ 'jquery' ], WPBENCH_VERSION, true
            );

	        // Localize data needed by admin-benchmark.js
	        wp_localize_script( 'wpbench-admin-js', 'wpbench_ajax', [
		        'ajax_url' => admin_url( 'admin-ajax.php' ),
		        // Nonces
		        'run_nonce' => wp_create_nonce( 'wpbench_run_action_ajax' ),
		        'load_profile_nonce' => wp_create_nonce( 'wpbench_load_profile_nonce'), // <<< Added Nonce
		        // Text strings for JS
		        'running_text' => __('Running...', 'wpbench'),
		        'complete_text' => __('Benchmark Complete!', 'wpbench'),
		        'error_text' => __('An error occurred.', 'wpbench'),
		        'view_results_text' => __('View Results', 'wpbench'),
		        'select_profile_alert' => __('Please select a profile to load.', 'wpbench'), // <<< Added Text
		        'load_profile_error_alert' => __('Error loading profile:', 'wpbench'), // <<< Added Text
		        'ajax_error_alert' => __('AJAX error loading profile. Check browser console.', 'wpbench'), // <<< Added Text
		        'validation_select_test' => __('You must select at least one Benchmark Test to run.', 'wpbench'), // Added validation text
	        ]);
        }

	    // --- Script for Results List Page (Compare Button) ---
	    if ($is_wpbench_result_list) {
		    /* ... Enqueue admin-compare-button.js & admin-compare.css ... */
		    // Note: add_button_js_vars can still output localized data if preferred over full wp_localize_script
	    }

        // Scripts for the Benchmark Result CPT edit screen (charts)
        if ( $is_wpbench_result_edit ) {
            wp_enqueue_script(
                'wpbench-results-js',
                WPBENCH_URL . 'js/admin-results.js',
                [ 'jquery', 'chart-js' ],
                WPBENCH_VERSION,
                true
            );

            // Enqueue JS for compare button enable/disable logic
            wp_enqueue_script('wpbench-compare-button-js', WPBENCH_URL . 'js/admin-compare-button.js', ['jquery'], WPBENCH_VERSION, true);

            // Enqueue CSS for compare button tooltip and maybe general list table tweaks
            wp_enqueue_style('wpbench-compare-css', WPBENCH_URL . 'css/admin-compare.css', [], WPBENCH_VERSION);
            // Localize data needed by admin-compare-button.js (done via wp_add_inline_script or localize if preferred)
            // Note: The current implementation uses inline script via admin_footer hook.

            // Pass result data from PHP to our results JS script
            global $post;

            if ( $post && $post->ID && $post->post_type === AdminBenchmark::POST_TYPE ) {
                $results_data = get_post_meta( $post->ID, AdminBenchmark::META_RESULTS, true );
                $config_data = get_post_meta( $post->ID, '_wpbench_config', true );
                $selected_tests_data = get_post_meta( $post->ID, AdminBenchmark::META_SELECTED_TESTS, true); // Get selected tests
	            $score_data = get_post_meta($post->ID, AdminBenchmark::META_SCORE, true);

	            wp_localize_script( 'wpbench-results-js', 'wpbench_results_data', [
                    'results' => is_array($results_data) ? $results_data : [],
                    'config'  => is_array($config_data) ? $config_data : [],
                    'selected_tests' => is_array($selected_tests_data) ? $selected_tests_data : [], // Pass selected tests
                    'score' => ($score_data !== '' && $score_data !== null) ? (int) $score_data : null, // Pass the score as int or null
                    'text' => [
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
					'results' => [],
					'config' => [],
					'selected_tests' => [],
					'text' => []
				]);
            }
        }

        // --- Assets for Compare Page ---
        if ($is_wpbench_compare_page) {
			// Enqueue compare page specific CSS
			wp_enqueue_style('wpbench-compare-css', WPBENCH_URL . 'css/admin-compare.css', [], WPBENCH_VERSION);
			// WordPress Diff styles should be loaded automatically when needed by wp_text_diff,
			// but enqueue explicitly if needed: wp_enqueue_style('wp-diff');
        }
    }
}