<?php
namespace WPBench;

use WPBench\BenchmarkTest; // Use the BenchmarkTest namespace

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles Admin Area functionalities for WPBench.
 * Includes menu creation, page rendering, AJAX handling, meta boxes, and CPT list columns.
 */
class AdminBenchmark {

    /**
     * Add admin menu pages.
     * Hooked into 'admin_menu'.
     */
    public function add_admin_menu() {
        // Add main menu page
        add_menu_page(
            __( 'WPBench', 'wpbench' ), // Page Title
            __( 'WPBench', 'wpbench' ), // Menu Title
            'manage_options',           // Capability
            'wpbench_main_menu',        // Menu Slug
            [ $this, 'render_run_benchmark_page' ], // Callback function for the page content
            'dashicons-dashboard',      // Icon URL
            75                          // Position
        );

        // Add submenu for running a new benchmark (points to the main page callback)
        add_submenu_page(
            'wpbench_main_menu',        // Parent Slug
            __( 'Run New Benchmark', 'wpbench' ), // Page Title
            __( 'Run New Benchmark', 'wpbench' ), // Menu Title
            'manage_options',           // Capability
            'wpbench_main_menu',        // Menu Slug (same as parent to make it the default)
            [ $this, 'render_run_benchmark_page' ] // Callback function
        );

        // Add submenu linking to the CPT list table
        add_submenu_page(
            'wpbench_main_menu',        // Parent Slug
            __( 'All Benchmarks', 'wpbench' ),  // Page Title
            __( 'All Benchmarks', 'wpbench' ),  // Menu Title
            'manage_options',           // Capability
            'edit.php?post_type=' . CustomPostType::POST_TYPE, // Menu Slug (links directly to CPT list)
            null                       // No callback function needed
        );
    }

    /**
     * Render the "Run New Benchmark" admin page content.
     * Now dynamically displays tests and configuration fields.
     */
    public function render_run_benchmark_page() {
        $available_tests = $this->get_available_tests();
        ?>
        <div class="wrap wpbench-wrap">
            <h1><?php esc_html_e( 'Run New Benchmark', 'wpbench' ); ?></h1>
            <p><?php esc_html_e('Configure and run benchmark tests for your WordPress site.', 'wpbench'); ?></p>
            <p><strong><?php esc_html_e('Important:', 'wpbench'); ?></strong> <?php esc_html_e('Running benchmarks, especially with high iterations, can consume significant server resources and may temporarily slow down your site. Run during off-peak hours if possible.', 'wpbench'); ?></p>

            <?php if (empty($available_tests)): ?>
                <div class="notice notice-error"><p><?php esc_html_e('Error: No benchmark test classes found or loaded correctly. Check plugin files and permissions.', 'wpbench'); ?></p></div>
            <?php else: ?>
                <form id="wpbench-run-form" method="post">
                    <?php wp_nonce_field( 'wpbench_run_action', 'wpbench_run_nonce' ); // Nonce for form submission (used by JS validator) ?>

                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="benchmark_name"><?php esc_html_e( 'Benchmark Name', 'wpbench' ); ?></label></th>
                                <td><input name="benchmark_name" type="text" id="benchmark_name" value="Benchmark <?php echo esc_attr( date('Y-m-d H:i:s') ); ?>" class="regular-text" required>
                                <p class="description"><?php esc_html_e('Give this benchmark run a descriptive name.', 'wpbench'); ?></p></td>
                            </tr>

                             <tr>
                                <th scope="row"><?php esc_html_e( 'Active Plugins During Test', 'wpbench' ); ?></th>
                                <td>
                                    <?php
                                    // --- Identical Active Plugins display code as before ---
                                     $all_plugins = get_plugins();
                                     $active_plugins = get_option( 'active_plugins', [] );
                                     if (is_multisite()) {
                                         $network_plugins = array_keys(get_site_option( 'active_sitewide_plugins', [] ));
                                         $active_plugins = array_unique(array_merge($active_plugins, $network_plugins));
                                     }
                                     if (!empty($active_plugins)) {
                                         echo '<ul>';
                                         foreach ($active_plugins as $plugin_file) {
                                             if (isset($all_plugins[$plugin_file])) {
                                                 echo '<li>' . esc_html($all_plugins[$plugin_file]['Name']) . ' (' . esc_html($all_plugins[$plugin_file]['Version']) . ')</li>';
                                             } else {
                                                 $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
                                                 if (!empty($plugin_data['Name'])) {
                                                     echo '<li>' . esc_html($plugin_data['Name']) . ' (' . esc_html($plugin_data['Version']) . ') [Network]</li>';
                                                 } else {
                                                    echo '<li>' . esc_html($plugin_file) . ' (Network Active or Info Missing)</li>';
                                                 }
                                             }
                                         }
                                         echo '</ul>';
                                         echo '<p class="description">' . esc_html__('The benchmark will run with the plugins listed above currently active.', 'wpbench') . '</p>';
                                     } else {
                                         echo '<p>' . esc_html__('No active plugins detected.', 'wpbench') . '</p>';
                                     }
                                    ?>
                                </td>
                            </tr>

                            <tr><td colspan="2"><hr><h2><?php esc_html_e('Select Tests to Run', 'wpbench'); ?></h2></td></tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Tests', 'wpbench'); ?></th>
                                <td>
                                    <fieldset>
                                        <legend class="screen-reader-text"><span><?php esc_html_e('Select Tests', 'wpbench'); ?></span></legend>
                                        <?php foreach ($available_tests as $id => $info): ?>
                                            <label for="test_<?php echo esc_attr($id); ?>" style="display: block; margin-bottom: 10px;">
                                                <input name="selected_tests[]" type="checkbox" id="test_<?php echo esc_attr($id); ?>" value="<?php echo esc_attr($id); ?>" checked="checked">
                                                <strong><?php echo esc_html($info['name']); ?></strong>
                                                <p style="margin-left: 25px; margin-top: 0; font-style: italic; color: #666;"><?php echo esc_html($info['description']); ?></p>
                                            </label>
                                        <?php endforeach; ?>
                                        <p class="description"><?php esc_html_e('Check the boxes for the tests you wish to include in this benchmark run.', 'wpbench'); ?></p>
                                    </fieldset>
                                </td>
                            </tr>

                             <tr><td colspan="2"><hr><h2><?php esc_html_e('Test Configuration', 'wpbench'); ?></h2></td></tr>
                             <?php foreach ($available_tests as $id => $info): ?>
                                <tr>
                                    <th scope="row">
                                        <label for="config_<?php echo esc_attr($id); ?>"><?php echo esc_html($info['config_label']); ?></label>
                                    </th>
                                    <td>
                                        <input name="config_<?php echo esc_attr($id); ?>"
                                               type="number"
                                               id="config_<?php echo esc_attr($id); ?>"
                                               value="<?php echo esc_attr($info['default_value']); ?>"
                                               class="regular-text"
                                               min="<?php echo esc_attr($info['min_value']); ?>"
                                               max="<?php echo esc_attr($info['max_value']); ?>"
                                               step="1">
                                        <?php if (!empty($info['config_unit'])): ?>
                                            <span class="description"><?php echo esc_html( $info['config_unit'] ); ?></span>
                                        <?php endif; ?>
                                         <p class="description"><?php printf( __('Default: %s, Min: %s, Max: %s', 'wpbench'), esc_html(number_format_i18n($info['default_value'])), esc_html(number_format_i18n($info['min_value'])), esc_html(number_format_i18n($info['max_value'])) ); ?></p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        </tbody>
                    </table>
                    <?php // Note: The AJAX nonce is created in Assets::enqueue_admin_scripts ?>
                    <?php submit_button( __( 'Start Benchmark', 'wpbench' ), 'primary', 'wpbench-start-button' ); ?>
                </form>

                <div id="wpbench-results-area" style="display: none; margin-top: 20px; padding: 15px; border: 1px solid #ccc; background-color: #f9f9f9;">
                    <h2><?php esc_html_e( 'Benchmark Running...', 'wpbench' ); ?></h2>
                    <p id="wpbench-status"><?php esc_html_e( 'Please wait, tests are in progress.', 'wpbench' ); ?></p>
                    <div id="wpbench-progress" style="height: 20px; background-color: #eee; border: 1px solid #ccc; margin-top: 10px; position: relative;">
                         <div id="wpbench-progress-bar" style="width: 0%; height: 100%; background-color: #4CAF50; position: absolute; text-align: center; color: white; line-height: 20px;">0%</div>
                    </div>
                    <div id="wpbench-final-results" style="margin-top: 15px;"></div>
                </div>
            <?php endif; // End check for available tests ?>
        </div>
        <style>
            .wpbench-wrap .form-table th { padding-top: 15px; padding-bottom: 15px; }
            .wpbench-wrap #wpbench-results-area ul { list-style: disc; margin-left: 20px; }
             .wpbench-wrap .widefat { margin-bottom: 15px; }
        </style>
        <?php
    }

    /**
     * Scans the BenchmarkTest directory and returns info for available tests.
     *
     * @return array<string, array> Array of test info arrays, keyed by test ID.
     */
    private function get_available_tests() : array {
        $available_tests = [];
        $test_dir = WPBENCH_PATH . 'src/BenchmarkTest/';
        $files = glob( $test_dir . '*.php' );

        if ( empty($files) ) {
            return [];
        }

        foreach ( $files as $file ) {
            $basename = basename( $file, '.php' );

            // Skip the interface itself
            if ( $basename === 'BaseBenchmarkTest' ) {
                continue;
            }

            $class_name = WPBENCH_BASE_NAMESPACE . 'BenchmarkTest\\' . $basename;

            if ( class_exists( $class_name ) ) {
                // Use Reflection to check if it implements the interface
                try {
                    $reflection = new \ReflectionClass( $class_name );
                    if ( $reflection->implementsInterface( BenchmarkTest\BaseBenchmarkTest::class ) && !$reflection->isAbstract() ) {
                        // Instantiate and get info
                        $test_instance = $reflection->newInstance();
                        $info = $test_instance->get_info();
                        if ( isset( $info['id'] ) ) {
                            $available_tests[ $info['id'] ] = $info;
                        } else {
                             trigger_error("WPBench: Test class $class_name get_info() did not return an 'id'.", E_USER_WARNING);
                        }
                    }
                } catch (\ReflectionException $e) {
                     trigger_error("WPBench: Reflection error for class $class_name: " . $e->getMessage(), E_USER_WARNING);
                } catch (\Error $e) { // Catch potential errors during instantiation or get_info() call
                     trigger_error("WPBench: Error processing test class $class_name: " . $e->getMessage(), E_USER_WARNING);
                }
            }
        }

        // Ensure a consistent order (optional, based on ID)
        ksort($available_tests);

        return $available_tests;
    }

    /**
     * AJAX handler for running the benchmark tests.
     * Now runs only selected tests.
     */
    public function handle_ajax_run_benchmark() {
        // 1. Security Checks
        check_ajax_referer( 'wpbench_run_action_ajax', 'nonce' ); // Verify the AJAX nonce
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wpbench' ) ], 403 );
        }

        // 2. Get Selected Tests and Configuration from $_POST
        $selected_tests_raw = isset($_POST['selected_tests']) && is_array($_POST['selected_tests']) ? $_POST['selected_tests'] : [];
        if ( empty($selected_tests_raw) ) {
             wp_send_json_error( [ 'message' => __( 'No benchmark tests were selected to run.', 'wpbench' ) ], 400 );
        }

        // Sanitize selected tests (ensure they are valid IDs from known tests)
        $available_tests = $this->get_available_tests();
        $valid_test_ids = array_keys($available_tests);
        $selected_tests = array_intersect( $selected_tests_raw, $valid_test_ids ); // Keep only valid IDs

        if ( empty($selected_tests) ) {
             wp_send_json_error( [ 'message' => __( 'Invalid tests selected.', 'wpbench' ) ], 400 );
        }

        $config = [
            'benchmark_name' => isset($_POST['benchmark_name']) ? sanitize_text_field(wp_unslash($_POST['benchmark_name'])) : 'Unnamed Benchmark',
        ];

        // Sanitize ALL possible config values based on available tests
        foreach ($available_tests as $id => $info) {
            $config_key = 'config_' . $id;
             $config[$config_key] = isset($_POST[$config_key]) ? absint($_POST[$config_key]) : $info['default_value'];
             // Apply min/max bounds
             $config[$config_key] = max($info['min_value'], $config[$config_key]);
             $config[$config_key] = min($info['max_value'], $config[$config_key]);
        }


        // 3. Run Selected Benchmarks
        $results = [];
        $start_time = microtime( true );

        try {
            // Loop through only the selected tests
            foreach ($selected_tests as $test_id) {
                if (isset($available_tests[$test_id])) {
                    $test_info = $available_tests[$test_id];
                    $class_name = WPBENCH_BASE_NAMESPACE . 'BenchmarkTest\\' . basename( $test_info['id'] ); // Construct class name safely? No, use ID to find class file basename usually. Let's assume ID maps to class name.
                    $class_basename = ucfirst($test_id); // e.g. cpu -> CPU, db_read -> Db_read (needs fixing maybe)
                    // Handle multi-word IDs like db_read -> DBRead
                     $class_parts = explode('_', $test_id);
                     $class_basename = implode('', array_map('ucfirst', $class_parts)); // db_read -> DBRead
                     $full_class_name = WPBENCH_BASE_NAMESPACE . 'BenchmarkTest\\' . $class_basename;


                    if (class_exists($full_class_name)) {
                        $test_instance = new $full_class_name();
                        $config_value = $config['config_' . $test_id] ?? $test_info['default_value']; // Get specific config for this test
                        $results[$test_id] = $test_instance->run($config_value); // Store results keyed by test ID
                    } else {
                        // Should not happen if get_available_tests worked, but good to check
                         $results[$test_id] = ['error' => "Test class $full_class_name not found."];
                    }
                }
            }

        } catch (\Exception $e) {
             // Catch any major exceptions during test execution
             wp_send_json_error( [ 'message' => __( 'Benchmark test execution failed:', 'wpbench' ) . ' ' . $e->getMessage() ], 500 );
        } catch (\Error $e) { // Catch fatal errors if possible
             wp_send_json_error( [ 'message' => __( 'Benchmark test execution failed (Fatal Error):', 'wpbench' ) . ' ' . $e->getMessage() ], 500 );
        }

        $end_time = microtime( true );
        $results['total_time'] = round( $end_time - $start_time, 4 );

        // 4. Save Results to CPT
        $post_data = [
            'post_title'  => $config['benchmark_name'], // Use sanitized name
            'post_type'   => CustomPostType::POST_TYPE,
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ];
        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Error saving benchmark result post:', 'wpbench' ) . ' ' . $post_id->get_error_message() ], 500 );
        } elseif ( $post_id === 0) {
             wp_send_json_error( [ 'message' => __( 'Failed to save benchmark result post (unknown error).', 'wpbench' ) ], 500 );
        } else {
            // Store the full config (all possible values)
            update_post_meta( $post_id, '_wpbench_config', $config );
            // Store the results (only for tests that ran)
            update_post_meta( $post_id, '_wpbench_results', $results );
            // Store the list of tests that were selected to run
            update_post_meta( $post_id, '_wpbench_selected_tests', $selected_tests );

            // Active plugins list is saved automatically via the 'save_post_benchmark_result' hook

            // 5. Send Success Response
            wp_send_json_success( [
                'message' => __( 'Benchmark completed successfully!', 'wpbench' ),
                'post_id' => $post_id,
                'results' => $results, // Send results back to display immediately via JS
                'view_url' => get_edit_post_link( $post_id, 'raw' ) // Get URL for the edit screen
            ] );
        }
    }

    /**
     * Save the list of active plugins when the benchmark result post is saved.
     * Hooked into 'save_post_benchmark_result'.
     *
     * @param int     $post_id The ID of the post being saved.
     * @param \WP_Post $post    The post object.
     */
    public function save_active_plugins_list( $post_id, $post ) {
        // --- Content mostly identical to the previous save_active_plugins_list method ---
        // --- Key change: Only run if meta key doesn't exist (to avoid overriding on manual edit/save) ---

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        // Check the user's permissions (redundant if only triggered by our AJAX, but good practice).
        // if ( ! current_user_can( 'edit_post', $post_id ) ) {
        // This check might prevent our AJAX handler (running as admin) from saving if post author is different?
        // Let's rely on the AJAX capability check primarily. We might remove this specific check here.
        // return;
        //}
        // Check if the post type is correct
        if ( CustomPostType::POST_TYPE !== $post->post_type ) {
            return;
        }
        // Only save the list if it hasn't been saved before for this post ID.
        // This assumes the list is generated ONCE when the benchmark is run via AJAX.
        if ( get_post_meta( $post_id, '_wpbench_active_plugins', true ) ) {
            return;
        }

        $active_plugins_list = [];
        $all_plugins = get_plugins(); // Includes network plugins if in network admin
        $active_plugin_files = get_option( 'active_plugins', [] );

        if (is_multisite()) {
             $network_plugins = array_keys(get_site_option( 'active_sitewide_plugins', [] ));
             $active_plugin_files = array_unique(array_merge($active_plugin_files, $network_plugins));
             // Ensure all_plugins includes network activated ones if not in network admin
             // This might require get_mu_plugins() or iterating WP_PLUGIN_DIR if get_plugins() isn't enough.
             // For simplicity, rely on get_plugins() first.
             $network_plugin_data = [];
             if ( function_exists('get_mu_plugins')) { // MU plugins are different
                // $network_plugin_data = get_mu_plugins(); // Handle MU plugins separately if needed
             }

        }

        foreach ($active_plugin_files as $plugin_file) {
            $plugin_data = null;
            if (isset($all_plugins[$plugin_file])) {
                 $plugin_data = $all_plugins[$plugin_file];
            } elseif (is_multisite()) {
                 // Try fetching data directly for network activated plugins if missed
                 $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
                 if (file_exists($plugin_path)) {
                    $plugin_data = get_plugin_data($plugin_path);
                 }
            }

            if ($plugin_data && !empty($plugin_data['Name'])) {
                 $active_plugins_list[] = [
                    'name' => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                    'file' => $plugin_file
                 ];
            } else {
                 $active_plugins_list[] = [ // Fallback
                     'name' => $plugin_file . ' (Info Missing)',
                     'version' => 'N/A',
                     'file' => $plugin_file
                 ];
            }
        }
        // Store the collected list
        update_post_meta($post_id, '_wpbench_active_plugins', $active_plugins_list);
    }


    /**
     * Add the meta box to the CPT edit screen.
     * Hooked to 'add_meta_boxes_{post_type}'.
     *
     * @param \WP_Post $post The current post object.
     */
    public function add_results_meta_box( $post ) {
         add_meta_box(
            'wpbench_results_metabox', // $id
            __( 'Benchmark Results & Configuration', 'wpbench' ), // $title
            [ $this, 'render_results_meta_box_content' ], // $callback
            CustomPostType::POST_TYPE, // $screen (post type)
            'normal', // $context (normal, side, advanced)
            'high' // $priority (high, core, default, low)
        );
    }

    /**
     * Render the content of the results meta box.
     * Now indicates which tests were run.
     *
     * @param \WP_Post $post The current post object passed by WordPress.
     */
    public function render_results_meta_box_content( $post ) {
        $config = get_post_meta( $post->ID, '_wpbench_config', true );
        $results = get_post_meta( $post->ID, '_wpbench_results', true );
        $selected_tests = get_post_meta( $post->ID, '_wpbench_selected_tests', true );
        $active_plugins = get_post_meta( $post->ID, '_wpbench_active_plugins', true );

        // Ensure data is in expected format
        $config = is_array($config) ? $config : [];
        $results = is_array($results) ? $results : [];
        $selected_tests = is_array($selected_tests) ? $selected_tests : []; // Should be an array of IDs
        $active_plugins = is_array($active_plugins) ? $active_plugins : [];

        // Get info about all potentially available tests to display config/results correctly
        $all_possible_tests = $this->get_available_tests();


        if ( empty($config) && empty($results) ) {
            echo '<p>' . esc_html__( 'Benchmark data not found for this result.', 'wpbench' ) . '</p>';
            return;
        }

        echo '<h3>' . esc_html__( 'Benchmark Configuration', 'wpbench' ) . '</h3>';
        if (!empty($config) && !empty($all_possible_tests)) {
            echo '<table class="form-table widefat striped">';
            echo '<tbody>';
             // Display config value for ALL tests that *could* have run
            foreach ($all_possible_tests as $id => $info) {
                $config_key = 'config_' . $id;
                $value = $config[$config_key] ?? $info['default_value']; // Show saved value or default
                echo '<tr><th scope="row">' . esc_html($info['config_label']) . ':</th><td>' . esc_html( number_format_i18n( $value ) ) . ' ' . esc_html($info['config_unit']) . '</td></tr>';
            }
             echo '</tbody>';
            echo '</table>';
        } else {
             echo '<p>' . esc_html__( 'Configuration data or test definitions missing.', 'wpbench' ) . '</p>';
        }

        echo '<h3 style="margin-top: 20px;">' . esc_html__( 'Benchmark Results', 'wpbench' ) . '</h3>';
         if (!empty($results)) {
            echo '<table class="form-table widefat striped">';
             echo '<tbody>';
            echo '<tr><th scope="row">' . esc_html__('Total Benchmark Time:', 'wpbench') . '</th><td><strong>' . esc_html( $results['total_time'] ?? 'N/A' ) . ' ' . esc_html__( 'seconds', 'wpbench' ) . '</strong></td></tr>';

            // Display results ONLY for tests that were selected and ran
            foreach ($all_possible_tests as $id => $info) {
                 echo '<tr>';
                 echo '<th scope="row">' . esc_html($info['name']) . ':</th>';
                 if (in_array($id, $selected_tests) && isset($results[$id])) {
                     // Test was selected and has results data
                     $result_data = $results[$id];
                     $display_value = '';
                     $error_msg = $result_data['error'] ?? null;

                     // Format display based on test type (customize as needed)
                     if ($id === 'cpu') {
                         $display_value = esc_html( $result_data['time'] ?? 'N/A' ) . ' ' . esc_html__( 'seconds', 'wpbench' );
                     } elseif ($id === 'memory') {
                         $display_value = esc_html( $result_data['peak_usage_mb'] ?? 'N/A' ) . ' MB (' . esc_html( $result_data['time'] ?? 'N/A' ) . ' s)';
                     } elseif ($id === 'file_io') {
                         $display_value = esc_html( $result_data['time'] ?? 'N/A' ) . ' s (' . esc_html( number_format_i18n($result_data['operations'] ?? 0) ) . ' ops)';
                     } elseif ($id === 'db_read') {
                          $display_value = esc_html( $result_data['time'] ?? 'N/A' ) . ' s (' . esc_html( number_format_i18n($result_data['queries_executed'] ?? 0) ) . ' queries)';
                     } elseif ($id === 'db_write') {
                          $display_value = esc_html( $result_data['time'] ?? 'N/A' ) . ' s (' . esc_html( number_format_i18n($result_data['operations'] ?? 0) ) . ' ops)';
                     } else {
                         // Fallback for unknown test types
                         $display_value = esc_html( $result_data['time'] ?? 'N/A' ) . ' s';
                     }

                     echo '<td>' . $display_value . ($error_msg ? ' <span style="color:red;" title="'.esc_attr($error_msg).'">(!)</span>' : '') . '</td>';

                 } elseif (in_array($id, $selected_tests)) {
                     // Test was selected but no results exist (likely an error before it ran)
                      echo '<td><span style="color:orange;">' . esc_html__('Selected but no result data found.', 'wpbench') . '</span></td>';
                 } else {
                     // Test was not selected
                     echo '<td><em>' . esc_html__('Not run', 'wpbench') . '</em></td>';
                 }
                 echo '</tr>';
            }

             echo '</tbody>';
            echo '</table>';
        } else {
             echo '<p>' . esc_html__( 'Results data missing.', 'wpbench' ) . '</p>';
        }

        // --- Active Plugins Display (Identical to before) ---
        echo '<h3 style="margin-top: 20px;">' . esc_html__( 'Active Plugins During Test', 'wpbench' ) . '</h3>';
        if (!empty($active_plugins)) {
            echo '<ul style="list-style: disc; margin-left: 20px;">';
            foreach ($active_plugins as $plugin) {
                echo '<li>' . esc_html($plugin['name'] ?? 'N/A') . ' (' . esc_html($plugin['version'] ?? 'N/A') . ') - <code>' . esc_html($plugin['file'] ?? 'N/A') . '</code></li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__( 'No active plugin data recorded or none were active.', 'wpbench' ) . '</p>';
        }

        // --- Charts ---
        echo '<h3 style="margin-top: 20px;">' . esc_html__( 'Result Graphs', 'wpbench' ) . '</h3>';
         if (!empty($results)) {
             // The JS needs to be updated slightly to handle potentially missing results
            echo '<div style="max-width: 700px; margin-bottom: 30px; background: #fff; padding: 15px; border: 1px solid #ddd;"><canvas id="wpbenchTimingChart"></canvas></div>';
            echo '<div style="max-width: 700px; background: #fff; padding: 15px; border: 1px solid #ddd;"><canvas id="wpbenchMemoryChart"></canvas></div>';
         } else {
             echo '<p>' . esc_html__( 'Graphs cannot be displayed as results data is missing.', 'wpbench' ) . '</p>';
         }
    }


     /**
      * Add custom columns to the benchmark_result list table.
      * Hooked into 'manage_{post_type}_posts_columns'.
      *
      * @param array $columns Existing columns.
      * @return array Modified columns.
      */
    public function set_custom_edit_benchmark_result_columns( $columns ) {
        // --- Content identical to the previous set_custom_edit_benchmark_result_columns method ---
        $new_columns = [];
        // Define order preference
        $order_pref = ['cb', 'title', 'total_time', 'cpu_time', 'memory_peak', 'author', 'date'];

        // Add columns in preferred order if they exist in the original $columns
        foreach ($order_pref as $key) {
            if ($key === 'cb' && isset($columns['cb'])) {
                $new_columns['cb'] = $columns['cb'];
            } elseif ($key === 'title' && isset($columns['title'])) {
                 $new_columns['title'] = $columns['title'];
            } elseif ($key === 'total_time') {
                $new_columns['total_time'] = __( 'Total (s)', 'wpbench' );
            } elseif ($key === 'cpu_time') {
                 $new_columns['cpu_time'] = __( 'CPU (s)', 'wpbench' );
            } elseif ($key === 'memory_peak') {
                 $new_columns['memory_peak'] = __( 'Mem (MB)', 'wpbench' );
            } elseif ($key === 'author' && isset($columns['author'])) {
                 $new_columns['author'] = $columns['author'];
            } elseif ($key === 'date' && isset($columns['date'])) {
                 $new_columns['date'] = $columns['date'];
            }
        }

        // Add any remaining original columns that weren't in our preferred order (just in case)
        foreach ($columns as $key => $value) {
            if (!isset($new_columns[$key])) {
                $new_columns[$key] = $value;
            }
        }

        return $new_columns;
    }

    /**
     * Display data in custom columns for the CPT list table.
     * Hooked into 'manage_{post_type}_posts_custom_column'.
     *
     * @param string $column  The name of the column.
     * @param int    $post_id The ID of the current post.
     */
    public function custom_benchmark_result_column( $column, $post_id ) {
        // --- Content identical to the previous custom_benchmark_result_column method ---
        $results = get_post_meta( $post_id, '_wpbench_results', true );
        if (empty($results) || !is_array($results)) return; // Bail if no results or not an array

        switch ( $column ) {
            case 'total_time':
                echo esc_html( $results['total_time'] ?? 'N/A' );
                break;
            case 'cpu_time':
                 echo esc_html( $results['cpu']['time'] ?? 'N/A' );
                 if (!empty($results['cpu']['error'])) echo ' <span style="color:red;" title="'.esc_attr($results['cpu']['error']).'">(!)</span>';
                break;
            case 'memory_peak':
                 echo esc_html( $results['memory']['peak_usage_mb'] ?? 'N/A' );
                 if (!empty($results['memory']['error'])) echo ' <span style="color:red;" title="'.esc_attr($results['memory']['error']).'">(!)</span>';
                break;
            // Add cases for other custom columns if needed
        }
    }
}