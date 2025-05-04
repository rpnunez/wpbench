<?php
/**
 * View file for the Benchmark Comparison page.
 *
 * Expects the following variables:
 * @var array $benchmarks_data      Array keyed by post ID, containing all data for each benchmark.
 * @var array $all_possible_tests Array of info for all available tests from TestRegistry.
 * @var array $all_plugins_info   Array of all plugin data from get_plugins().
 * @var array $error_messages     Array of any errors encountered fetching data.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Helper function to get nested data safely
if (!function_exists('wpbench_get_nested_value')) {
    function wpbench_get_nested_value($array, $keys, $default = null) {
        $current = $array;
        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current)) { // Check if array before accessing key
                // Handle non-array access or missing key
                if(is_array($current) && array_key_exists($key, $current) && $current[$key] !== null){
                    // Special case: key exists but might not be array, return its value
                    // Or handle error/logging if expecting array
                    return $current[$key]; // Return value if key exists but not array (like time/score)
                }
                return $default;
            }
            $current = $current[$key];
        }
        return $current;
    }
}


$benchmark_ids = array_keys($benchmarks_data);
$compare_count = count($benchmark_ids);
// Determine column width (simple example for 2 or 3 columns)
$column_class = 'compare-col-' . min(3, $compare_count); // Max 3 columns for now
$first_id = $benchmark_ids[0] ?? null;
$second_id = $benchmark_ids[1] ?? null;
// For > 2, layout needs more thought (e.g., table or horizontal scroll)

// Include WP Diff Renderer if not already loaded
if ( ! class_exists( 'WP_Text_Diff_Renderer_Table', false ) ) {
    require( ABSPATH . WPINC . '/wp-diff.php' );
}

?>
<div class="wrap wpbench-compare-page">
    <h1><?php esc_html_e('Compare Benchmark Results', 'wpbench'); ?></h1>

    <?php if (!empty($error_messages)): ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Errors occurred while loading benchmark data:', 'wpbench'); ?></p>
            <ul><?php foreach($error_messages as $err) echo '<li>'.esc_html($err).'</li>'; ?></ul>
        </div>
    <?php endif; ?>

    <div class="compare-container <?php echo esc_attr($column_class); ?>">
        <?php // --- Header Row --- ?>
        <div class="compare-row compare-header">
            <div class="compare-cell compare-label"><?php esc_html_e('Metric', 'wpbench'); ?></div>
            <?php foreach($benchmark_ids as $id): ?>
                <div class="compare-cell benchmark-header">
                    <a href="<?php echo esc_url(get_edit_post_link($id)); ?>" title="<?php esc_attr_e('Edit this benchmark result', 'wpbench'); ?>">
                        <?php echo esc_html($benchmarks_data[$id]['post']->post_title); ?>
                    </a>
                    <br>
                    <small><?php echo esc_html(get_the_date(get_option('date_format') . ' ' . get_option('time_format'), $id)); ?></small>
                </div>
            <?php endforeach; ?>
        </div>

        <?php // --- Score Row --- ?>
        <?php
        $scores = [];
        $best_score = -1;
        $best_score_id = null;
        foreach($benchmark_ids as $id) {
            $score = $benchmarks_data[$id]['score'] ?? null;
            $scores[$id] = is_numeric($score) ? intval($score) : null;
            if ($scores[$id] !== null && $scores[$id] > $best_score) {
                $best_score = $scores[$id];
                $best_score_id = $id;
            }
        }
        ?>
        <div class="compare-row score-row">
            <div class="compare-cell compare-label"><strong><?php esc_html_e('Overall Score', 'wpbench'); ?></strong> <small>(Higher is Better)</small></div>
            <?php foreach($benchmark_ids as $id): ?>
                <div class="compare-cell score-value <?php echo ($id === $best_score_id && $best_score >= 0) ? 'winner' : ''; ?>">
                    <?php if($scores[$id] !== null): ?>
                        <strong><?php echo esc_html($scores[$id]); ?></strong> / 100
                        <?php if ($id === $best_score_id): ?>
                            <span class="dashicons dashicons-awards" title="<?php esc_attr_e('Best score in this comparison', 'wpbench'); ?>"></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <em><?php esc_html_e('N/A', 'wpbench'); ?></em>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php // --- Total Time Row --- ?>
        <?php
        $times = []; $fastest_time = PHP_FLOAT_MAX; $fastest_time_id = null;
        foreach($benchmark_ids as $id) {
            $time = wpbench_get_nested_value($benchmarks_data[$id], ['results', 'total_time']);
            $times[$id] = is_numeric($time) ? (float)$time : null;
            if ($times[$id] !== null && $times[$id] < $fastest_time) {
                $fastest_time = $times[$id];
                $fastest_time_id = $id;
            }
        }
        ?>
        <div class="compare-row total-time-row">
            <div class="compare-cell compare-label"><strong><?php esc_html_e('Total Time', 'wpbench'); ?></strong> <small>(Lower is Better)</small></div>
            <?php foreach($benchmark_ids as $id): ?>
                <div class="compare-cell time-value <?php echo ($id === $fastest_time_id && $fastest_time !== PHP_FLOAT_MAX) ? 'winner' : ''; ?>">
                    <?php if($times[$id] !== null): ?>
                        <strong><?php echo esc_html(number_format($times[$id], 4)); ?></strong> <?php esc_html_e('s', 'wpbench'); ?>
                        <?php if ($id === $fastest_time_id): ?>
                            <span class="dashicons dashicons-clock" title="<?php esc_attr_e('Fastest time in this comparison', 'wpbench'); ?>"></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <em><?php esc_html_e('N/A', 'wpbench'); ?></em>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>


        <?php // --- Individual Test Rows --- ?>
        <div class="compare-row section-header">
            <div class="compare-cell compare-label"><h3><?php esc_html_e('Test Breakdown', 'wpbench'); ?></h3></div>
            <?php foreach($benchmark_ids as $id): ?> <div class="compare-cell"></div> <?php endforeach; // Empty cells for alignment ?>
        </div>

        <?php foreach ($all_possible_tests as $test_id => $test_info): ?>
            <?php
            // Determine best value based on test type (time -> lower, memory -> lower?)
            $test_values = [];
            $best_test_value = null;
            $best_test_id = null;
            $lower_is_better = true; // Assume lower time is better
            $value_key = 'time'; // Default key to compare

            if ($test_id === 'memory') {
                $lower_is_better = true;
                $value_key = 'peak_usage_mb';
                $best_test_value = PHP_FLOAT_MAX;
            } else { // Time based tests
                $best_test_value = PHP_FLOAT_MAX;
            }

            foreach($benchmark_ids as $id) {
                $value = null;
                $error = null;
                if (in_array($test_id, $benchmarks_data[$id]['selected_tests'] ?? []) && isset($benchmarks_data[$id]['results'][$test_id])) {
                    $result_data = $benchmarks_data[$id]['results'][$test_id];
                    $value = $result_data[$value_key] ?? null;
                    $error = $result_data['error'] ?? null;
                }
                $test_values[$id] = ['value' => is_numeric($value) ? (float)$value : null, 'error' => $error];

                // Find best value (handling nulls and errors)
                if ($test_values[$id]['value'] !== null && $test_values[$id]['error'] === null) {
                    if ($lower_is_better && $test_values[$id]['value'] < $best_test_value) {
                        $best_test_value = $test_values[$id]['value'];
                        $best_test_id = $id;
                    } elseif (!$lower_is_better && $test_values[$id]['value'] > $best_test_value) {
                        // Add logic here if higher is better for some future metric
                    }
                }
            }
            ?>
            <div class="compare-row test-row test-<?php echo esc_attr($test_id); ?>">
                <div class="compare-cell compare-label"><?php echo esc_html($test_info['name'] ?? $test_id); ?></div>
                <?php foreach($benchmark_ids as $id): ?>
                    <?php $current_test = $test_values[$id]; ?>
                    <div class="compare-cell test-value <?php echo ($id === $best_test_id && $best_test_value !== null && $current_test['error'] === null) ? 'winner' : ''; ?>">
                        <?php if ($current_test['value'] !== null): ?>
                            <?php
                            // Format display value
                            $display_val = '';
                            $unit = '';
                            switch($test_id) {
                                case 'cpu':
                                case 'file_io':
                                case 'db_read':
                                case 'db_write':
                                    $display_val = number_format($current_test['value'], 4);
                                    $unit = 's';
                                    break;
                                case 'memory':
                                    $display_val = number_format($current_test['value'], 2);
                                    $unit = 'MB';
                                    break;
                                default:
                                    $display_val = esc_html($current_test['value']);
                            }
                            ?>
                            <strong><?php echo esc_html($display_val); ?></strong> <?php echo esc_html($unit); ?>
                            <?php if ($current_test['error']): ?>
                                <span class="dashicons dashicons-warning error-indicator" title="<?php echo esc_attr(__('Error:', 'wpbench') . ' ' . $current_test['error']); ?>"></span>
                            <?php endif; ?>
                            <?php if ($id === $best_test_id && $current_test['error'] === null): ?>
                                <span class="dashicons <?php echo ($lower_is_better ? 'dashicons-arrow-down-alt2' : 'dashicons-arrow-up-alt2'); ?>" title="<?php esc_attr_e('Best result in this comparison', 'wpbench'); ?>"></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <em><?php echo in_array($test_id, $benchmarks_data[$id]['selected_tests'] ?? []) ? esc_html__('Error/No Data', 'wpbench') : esc_html__('Not Run', 'wpbench'); ?></em>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>


        <?php // --- Plugin State Comparison --- ?>
        <div class="compare-row section-header">
            <div class="compare-cell compare-label"><h3><?php esc_html_e('Plugin States', 'wpbench'); ?></h3></div>
            <?php foreach($benchmark_ids as $id): ?> <div class="compare-cell"></div> <?php endforeach; ?>
        </div>
        <?php
        // --- Function to prepare plugin list string for diff ---
        if (!function_exists('wpbench_prepare_plugin_list_for_diff')) {
            function wpbench_prepare_plugin_list_for_diff($plugin_files, $all_plugins_info) {
                $list = [];
                if (!is_array($plugin_files)) return '';
                foreach ($plugin_files as $file) {
                    $name = $all_plugins_info[$file]['Name'] ?? $file;
                    $version = $all_plugins_info[$file]['Version'] ?? 'N/A';
                    $list[$file] = $name . ' (v' . $version . ')'; // Use file as key for sorting
                }
                ksort($list); // Sort by file path for consistent diff
                return implode("\n", $list);
            }
        }

        // --- Pre-Benchmark State Diff ---
        $pre_state_strings = [];
        foreach($benchmark_ids as $id) {
            $pre_state = $benchmarks_data[$id]['pre_benchmark_state'] ?? [];
            $pre_all_active = array_unique(array_merge($pre_state['active_site'] ?? [], $pre_state['active_network'] ?? []));
            $pre_state_strings[$id] = wpbench_prepare_plugin_list_for_diff($pre_all_active, $all_plugins_info);
        }
        $pre_state_diff = wp_text_diff($pre_state_strings[$first_id] ?? '', $pre_state_strings[$second_id] ?? '', [
            'title' => sprintf(__('Plugin Differences (Pre-Benchmark): %1$s vs %2$s', 'wpbench'), '#' . $first_id, '#' . $second_id),
            'title_left' => '#' . $first_id, 'title_right' => '#' . $second_id
        ]);
        ?>
        <div class="compare-row plugin-diff-row">
            <div class="compare-cell compare-label"><?php esc_html_e('Pre-Benchmark State Diff', 'wpbench'); ?></div>
            <div class="compare-cell plugin-diff-content" colspan="<?php echo esc_attr($compare_count); ?>">
                <?php echo $pre_state_diff; // Output generated by wp_text_diff ?>
            </div>
        </div>

        <?php // --- Desired State Diff ---
        $desired_state_strings = [];
        foreach($benchmark_ids as $id) {
            $desired_state_strings[$id] = wpbench_prepare_plugin_list_for_diff($benchmarks_data[$id]['desired_plugins'] ?? [], $all_plugins_info);
        }
        $desired_state_diff = wp_text_diff($desired_state_strings[$first_id] ?? '', $desired_state_strings[$second_id] ?? '', [
            'title' => sprintf(__('Plugin Differences (Desired State): %1$s vs %2$s', 'wpbench'), '#' . $first_id, '#' . $second_id),
            'title_left' => '#' . $first_id, 'title_right' => '#' . $second_id
        ]);
        ?>
        <div class="compare-row plugin-diff-row">
            <div class="compare-cell compare-label"><?php esc_html_e('Desired State Diff', 'wpbench'); ?></div>
            <div class="compare-cell plugin-diff-content" colspan="<?php echo esc_attr($compare_count); ?>">
                <?php echo $desired_state_diff; ?>
            </div>
        </div>

        <?php // --- Final State Diff ---
        $final_state_strings = [];
        foreach($benchmark_ids as $id) {
            // active_plugins_final is array of {name, version, file} - need to extract file path
            $final_files = [];
            if(is_array($benchmarks_data[$id]['active_plugins_final'])) {
                foreach($benchmarks_data[$id]['active_plugins_final'] as $plugin_info) {
                    if(isset($plugin_info['file'])) $final_files[] = $plugin_info['file'];
                }
            }
            $final_state_strings[$id] = wpbench_prepare_plugin_list_for_diff($final_files, $all_plugins_info);
        }
        $final_state_diff = wp_text_diff($final_state_strings[$first_id] ?? '', $final_state_strings[$second_id] ?? '', [
            'title' => sprintf(__('Plugin Differences (Final State): %1$s vs %2$s', 'wpbench'), '#' . $first_id, '#' . $second_id),
            'title_left' => '#' . $first_id, 'title_right' => '#' . $second_id
        ]);
        ?>
        <div class="compare-row plugin-diff-row">
            <div class="compare-cell compare-label"><?php esc_html_e('Final State Diff (After Run)', 'wpbench'); ?></div>
            <div class="compare-cell plugin-diff-content" colspan="<?php echo esc_attr($compare_count); ?>">
                <?php echo $final_state_diff; ?>
            </div>
        </div>


        <?php // --- Configuration Comparison --- ?>
        <div class="compare-row section-header">
            <div class="compare-cell compare-label"><h3><?php esc_html_e('Configuration Used', 'wpbench'); ?></h3></div>
            <?php foreach($benchmark_ids as $id): ?> <div class="compare-cell"></div> <?php endforeach; ?>
        </div>
        <?php foreach ($all_possible_tests as $test_id => $test_info): ?>
            <div class="compare-row config-row">
                <div class="compare-cell compare-label"><?php echo esc_html($test_info['config_label'] ?? $test_id); ?></div>
                <?php foreach($benchmark_ids as $id): ?>
                    <?php
                    $config_key = 'config_' . $test_id;
                    $config_value = $benchmarks_data[$id]['config'][$config_key] ?? null;
                    ?>
                    <div class="compare-cell config-value">
                        <?php echo ($config_value !== null) ? esc_html(number_format_i18n($config_value)) : '<em>' . esc_html__('N/A','wpbench') . '</em>'; ?>
                        <?php if (!empty($test_info['config_unit'])): ?>
                            <small><?php echo esc_html( $test_info['config_unit'] ); ?></small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <?php // --- Profile Info Comparison --- ?>
        <div class="compare-row section-header">
            <div class="compare-cell compare-label"><h3><?php esc_html_e('Profile Used', 'wpbench'); ?></h3></div>
            <?php foreach($benchmark_ids as $id): ?> <div class="compare-cell"></div> <?php endforeach; ?>
        </div>
        <div class="compare-row profile-row">
            <div class="compare-cell compare-label"><?php esc_html_e('Profile Name', 'wpbench'); ?></div>
            <?php foreach($benchmark_ids as $id): ?>
                <div class="compare-cell profile-value">
                    <?php
                    $profile_id = $benchmarks_data[$id]['profile_id_used'] ?? null;
                    if ($profile_id) {
                        $profile_title = $benchmarks_data[$id]['profile_state_during_run']['profile_title'] ?? get_the_title($profile_id);
                        $profile_link = get_edit_post_link(absint($profile_id));
                        if ($profile_link && $profile_title) {
                            echo '<a href="'.esc_url($profile_link).'">'.esc_html($profile_title).'</a>';
                        } elseif ($profile_title) {
                            echo esc_html($profile_title);
                        } else {
                            echo '#' . esc_html($profile_id);
                        }
                    } else {
                        echo '<em>' . esc_html__('None', 'wpbench') . '</em>';
                    }
                    ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php // TODO: Optionally add diff for profile state data (_wpbench_profile_state_during_benchmark) ?>


    </div> <?php // end compare-container ?>

</div> <?php // end wrap ?>