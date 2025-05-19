<?php
/**
 * View file for the Benchmark Results meta box content.
 *
 * Expects the following variables:
 * @var array $config            Benchmark configuration used.
 * @var array $results           Benchmark results array (includes 'errors' key if any).
 * @var array $selectedTests    Array of test IDs selected for the run.
 * @var array $activePluginsFinal Array of {name, version, file} for plugins active AFTER restoration attempt.
 * @var array $desiredPlugins   Array of plugin file paths user wanted active.
 * @var array|null $preBenchmarkState Array containing ['active_site', 'active_network'] before the run.
 * @var array $allPossibleTests Array of info for all available tests.
 * @var array $allPluginsInfo  Array of all plugin data from get_plugins().
 * @var int|null $profileIdUsed ID of the profile used for this run, or null.
 * @var \WP_Post $post             The current benchmark_result post object.
 */

 // Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Decode pre-benchmark state parts safely
$pre_site_active = $preBenchmarkState['active_site'] ?? [];
$pre_network_active = $preBenchmarkState['active_network'] ?? [];
$pre_all_active = array_unique(array_merge($pre_site_active, $pre_network_active));

// Ensure other variables are present and an excpected type
$config = is_array($config) ? $config : [];
$results = is_array($results) ? $results : [];
$selectedTests = is_array($selectedTests) ? $selectedTests : [];
$activePluginsFinal = is_array($activePluginsFinal) ? $activePluginsFinal : [];
$desiredPlugins = is_array($desiredPlugins) ? $desiredPlugins : [];
$profileStateDuringRun = is_array($profileStateDuringRun) ? $profileStateDuringRun : [];
$allPossibleTests = is_array($allPossibleTests) ? $allPossibleTests : [];
$allPluginsInfo = is_array($allPluginsInfo) ? $allPluginsInfo : [];
$benchmarkResultPost = is_object($benchmarkResultPost) ? $benchmarkResultPost : null;
$benchmarkProfilePost = is_object($benchmarkProfilePost) ? $benchmarkProfilePost : null;

if ( empty($config) && empty($results) && empty($preBenchmarkState)) {
     echo '<p>' . esc_html__( 'Benchmark data not found for this result.', 'wpbench' ) . '</p>';
     return;
}

?>
<?php if (isset($results['errors']) && !empty($results['errors'])): ?>
    <div class="notice notice-error inline" style="margin-bottom:15px;">
        <h4 style="margin-top:0;"><?php echo esc_html__('Errors Occurred During Benchmark Run:', 'wpbench'); ?></h4>
        <ul style="list-style:disc; margin-left:20px;">
        <?php foreach ($results['errors'] as $error_type => $error_details): ?>
            <li><strong><?php echo esc_html(ucfirst(str_replace('_', ' ', $error_type))); ?>:</strong>
            <?php if (is_array($error_details)): ?>
                <ul style="margin-top: 5px;">
                 <?php foreach($error_details as $plugin_or_key => $message): ?>
                     <li><em><?php echo esc_html($plugin_or_key); ?>:</em> <?php echo esc_html($message); ?></li>
                 <?php endforeach; ?>
                 </ul>
            <?php else: ?>
                 <?php echo esc_html($error_details); ?>
            <?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
        <p><em><?php echo esc_html__('Plugin state and results may be inaccurate.', 'wpbench'); ?></em></p>
    </div>
<?php endif; ?>

<?php if ($profileIdUsed): ?>
     <?php
     $profile_link = get_edit_post_link(absint($profileIdUsed));
     $profile_title = get_the_title(absint($profileIdUsed));
     ?>
     <p><em><?php echo esc_html__('Settings loaded from profile:', 'wpbench'); ?>
     <?php if ($profile_link && $profile_title): ?>
        <a href="<?php echo esc_url($profile_link); ?>"><?php echo esc_html($profile_title); ?></a>
     <?php elseif ($profile_title): ?>
         <?php echo esc_html($profile_title) . ' ' . esc_html__('(Profile Link Invalid?)','wpbench'); ?>
     <?php else: ?>
         <?php echo '#' . esc_html($profileIdUsed) . ' ' . esc_html__('(Profile Deleted?)','wpbench'); ?>
     <?php endif; ?>
     </em></p>
 <?php endif; ?>

<h3><?php echo esc_html__( 'Benchmark Configuration Used', 'wpbench' ); ?></h3>
<?php if (!empty($config) && !empty($allPossibleTests)): ?>
    <table class="form-table widefat striped">
        <tbody>
        <?php foreach ($allPossibleTests as $id => $info): ?>
            <?php
            $config_key = 'config_' . $id;
            $value_display = $config[$config_key] ?? __('N/A', 'wpbench');
            $value_raw = $config[$config_key] ?? null;
            ?>
            <tr>
                <th scope="row"><?php echo esc_html($info['config_label'] ?? $id); ?>:</th>
                <td><?php echo esc_html( is_numeric($value_raw) ? number_format_i18n( $value_raw ) : $value_display ); ?> <?php echo esc_html($info['config_unit'] ?? ''); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
     <p><?php echo esc_html__( 'Configuration data or test definitions missing.', 'wpbench' ); ?></p>
<?php endif; ?>

<h3 style="margin-top: 20px;"><?php echo esc_html__( 'Benchmark Results', 'wpbench' ); ?></h3>
<?php if (!empty($results)): ?>
    <table class="form-table widefat striped">
        <tbody>
            <tr>
                <th scope="row"><?php echo esc_html__('Total Benchmark Time:', 'wpbench'); ?></th>
                <td><strong><?php echo esc_html( $results['total_time'] ?? 'N/A' ); ?> <?php esc_html_e( 'seconds', 'wpbench' ); ?></strong></td>
            </tr>
            <tr>
                <th scope="row" style="width: 30%;"><?php echo esc_html__('Overall Score', 'wpbench'); ?></th>
                <td>
<!--                    --><?php //if ($score !== '' && $score !== null): ?>
<!--                        <strong style="font-size: 1.2em;">--><?php //echo esc_html($score); ?><!--</strong> / 100-->
<!--                        <p class="description">--><?php //esc_html_e('Higher is better. Based on weighted performance against internal targets.', 'wpbench'); ?><!--</p>-->
<!--                    --><?php //else: ?>
<!--                        <em>--><?php //echo esc_html__('N/A (Could not be calculated due to errors or missing data)', 'wpbench'); ?><!--</em>-->
<!--                    --><?php //endif; ?>

                    <div class="wpbench-score-chart-container" style="width: 150px; height: 150px; margin: 15px auto 25px; position: relative;">
                        <?php if ($score !== null && $score !== ''): ?>
                            <canvas id="wpbenchScoreChart"></canvas>
                            <div class="wpbench-score-text" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; pointer-events: none;">
                                <?php // This text is overlaid, JS will update it more accurately if needed ?>
                                <span style="font-size: 2em; font-weight: bold; display: block; line-height: 1;">
                                    <?php echo esc_html($score); ?>
                                    <span style="font-size: 0.5em; font-weight: normal;">%</span>
                                </span>
                                <span style="font-size: 0.8em; color: #666;">
                                    <?php esc_html_e('Score', 'wpbench'); ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding-top: 40px; border: 1px dashed #ccc; height: 100%; box-sizing: border-box;">
                                <em><?php echo esc_html__('Score N/A', 'wpbench'); ?></em>
                                <p class="description" style="font-size: 11px;"><?php esc_html_e('(Calculation failed or data missing)', 'wpbench'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <tr></tr>
            <?php foreach ($allPossibleTests as $id => $info): ?>
                <tr>
                    <th scope="row"><?php echo esc_html($info['name'] ?? $id); ?>:</th>
                    <?php if (in_array($id, $selectedTests) && isset($results[$id])): ?>
                    <?php
                        // Test was selected and has results data
                        $result_data = $results[$id];
                        $display_value = '';
                        $error_msg = $result_data['error'] ?? null;
                        $time_val = $result_data['time'] ?? 'N/A';

                        // Format display based on test type
                        switch ($id) {
                            case 'cpu':
                                $display_value = esc_html($time_val) . ' ' . esc_html__( 's', 'wpbench' );
                            break;

                            case 'memory':
                                $peak_mb = $result_data['peak_usage_mb'] ?? 'N/A';
                                $display_value = esc_html($peak_mb) . ' MB (' . esc_html($time_val) . ' s)';
                            break;

                            case 'file_io':
                                $ops = number_format_i18n($result_data['operations'] ?? 0);
                                $display_value = esc_html($time_val) . ' s (' . esc_html($ops) . ' ops)';
                            break;

                            case 'db_read':
                                $queries = number_format_i18n($result_data['queries_executed'] ?? 0);
                                $display_value = esc_html($time_val) . ' s (' . esc_html($queries) . ' queries)';
                            break;

                            case 'db_write':
                                /** @noinspection PhpDuplicateSwitchCaseBodyInspection */
                                $ops            = number_format_i18n( $result_data['operations'] ?? 0);
                                $display_value = esc_html($time_val) . ' s (' . esc_html($ops) . ' ops)';
                            break;

                            default:
                                $display_value = esc_html($time_val) . ' s';
                        }
                        ?>
                        <td><?php echo $display_value; ?><?php if($error_msg) echo ' <span style="color:red;" title="'.esc_attr($error_msg).'">(!)</span>'; ?></td>
                    <?php elseif (in_array($id, $selectedTests)): ?>
                        <td><span style="color:orange;"><?php echo esc_html__('Selected but no result data found.', 'wpbench'); ?></span></td>
                    <?php else: ?>
                        <td><em><?php echo esc_html__('Not run', 'wpbench'); ?></em></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
     <p><?php echo esc_html__( 'Results data missing.', 'wpbench' ); ?></p>
<?php endif; ?>

<?php
// --- Plugin State Display ---
?>

<h3 style="margin-top: 20px;"><?php echo esc_html__( 'Plugin States', 'wpbench' ); ?></h3>
<table class="form-table widefat striped">
    <thead>
        <tr>
            <th><?php echo esc_html__('State Type', 'wpbench'); ?></th>
            <th><?php echo esc_html__('Active Plugins', 'wpbench'); ?></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <th scope="row"><?php echo esc_html__('Pre-Benchmark State', 'wpbench'); ?><p class="description"><?php echo esc_html__('Plugins active before the run.', 'wpbench'); ?></p></th>
            <td>
            <?php if (!empty($pre_all_active)): ?>
                <ul style="list-style: disc; margin-left: 20px;">
                <?php foreach ($pre_all_active as $plugin_file): ?>
                    <?php
                    $name = $allPluginsInfo[$plugin_file]['Name'] ?? $plugin_file;
                    $version = $allPluginsInfo[$plugin_file]['Version'] ?? 'N/A';
                    ?>
                    <li><?php echo esc_html($name) . ' (' . esc_html($version) . ')'; ?></li>
                <?php endforeach; ?>
                </ul>
            <?php else: echo '<p>' . esc_html__( 'None recorded or none active.', 'wpbench' ) . '</p>'; endif; ?>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php echo esc_html__('Desired State (for test)', 'wpbench'); ?><p class="description"><?php echo esc_html__('Plugins selected by user for run.', 'wpbench'); ?></p></th>
            <td>
            <?php if (!empty($desiredPlugins)): ?>
                <ul style="list-style: disc; margin-left: 20px;">
                <?php foreach ($desiredPlugins as $plugin_file): ?>
                    <?php
                     $name = $allPluginsInfo[$plugin_file]['Name'] ?? $plugin_file;
                     $version = $allPluginsInfo[$plugin_file]['Version'] ?? 'N/A';
                     ?>
                    <li><?php echo esc_html($name) . ' (' . esc_html($version) . ')'; ?></li>
                <?php endforeach; ?>
                </ul>
            <?php else: echo '<p>' . esc_html__( 'None selected or data missing.', 'wpbench' ) . '</p>'; endif; ?>
            </td>
        </tr>
         <tr>
            <th scope="row"><?php echo esc_html__('Final State (After Run)', 'wpbench'); ?><p class="description"><?php echo esc_html__('Plugins active AFTER restoration was attempted.', 'wpbench'); ?></p></th>
            <td>
            <?php if (!empty($activePluginsFinal)): ?>
                <ul style="list-style: disc; margin-left: 20px;">
                <?php foreach ($activePluginsFinal as $plugin): // This is array of {name, version, file} ?>
                    <li><?php echo esc_html($plugin['name'] ?? 'N/A') . ' (' . esc_html($plugin['version'] ?? 'N/A') . ')'; ?></li>
                <?php endforeach; ?>
                </ul>
             <?php else: echo '<p>' . esc_html__( 'None recorded or none were active.', 'wpbench' ) . '</p>'; endif; ?>
            </td>
        </tr>
    </tbody>
</table>

<?php 
// --- Display Profile State During Run --- 
?>

<?php if ( $profileIdUsed && is_array($profileStateDuringRun)): ?>
    <h3 style="margin-top: 20px;"><?php echo esc_html__( 'Profile State Used During Benchmark', 'wpbench' ); ?></h3>
    <p class="description"><?php echo esc_html__('This was the configuration loaded from the profile when the benchmark started.', 'wpbench'); ?></p>

    <h4><?php echo esc_html__('Selected Tests (Profile):', 'wpbench'); ?></h4>
    <?php if ( !empty($profileStateDuringRun['selected_tests']) && is_array($profileStateDuringRun['selected_tests'])): ?>
        <ul style="list-style: disc; margin-left: 20px;">
            <?php foreach ($profileStateDuringRun['selected_tests'] as $test_id): ?>
                <li><?php echo esc_html($test_id); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p><em><?php echo esc_html__('None specified in profile data.', 'wpbench'); ?></em></p>
    <?php endif; ?>

    <h4><?php echo esc_html__('Configuration (Profile):', 'wpbench'); ?></h4>
    <?php if ( !empty($profileStateDuringRun['config']) && is_array($profileStateDuringRun['config'])): ?>
        <table class="widefat striped" style="width: auto; max-width: 400px; margin-top: 5px;">
            <tbody>
            <?php foreach($profileStateDuringRun['config'] as $key => $val): ?>
                <?php
                // Prepare display values within PHP tags
                $display_key = ucwords(str_replace(['config_', '_'], ['', ' '], $key));
                $display_val = is_numeric($val) ? number_format_i18n($val) : esc_html($val ?? 0);
                ?>
                <tr>
                    <th scope="row" style="padding: 4px 8px;"><?php echo esc_html($display_key); ?>:</th>
                    <td style="padding: 4px 8px;"><?php echo esc_html($display_val); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p><em><?php echo esc_html__('None specified in profile data.', 'wpbench'); ?></em></p>
    <?php endif; ?>

    <h4><?php echo esc_html__('Desired Plugins (Profile):', 'wpbench'); ?></h4>
    <?php if ( !empty($profileStateDuringRun['desired_plugins']) && is_array($profileStateDuringRun['desired_plugins'])): ?>
        <ul style="list-style: disc; margin-left: 20px;">
        <?php foreach($profileStateDuringRun['desired_plugins'] as $plugin_file): ?>
            <?php
            // Get plugin name safely
            $name = $allPluginsInfo[$plugin_file]['Name'] ?? $plugin_file;
            ?>
            <li><?php echo esc_html($name); ?></li>
        <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p><em><?php echo esc_html__('None specified in profile data.', 'wpbench'); ?></em></p>
    <?php endif; ?>

<?php // Handle case where profile ID was used, but data wasn't saved correctly ?>
<?php elseif ($profileIdUsed): ?>
    <p><em><?php echo esc_html__('Profile state data during run is missing.', 'wpbench'); ?></em></p>
<?php endif; ?>

<h3 style="margin-top: 20px;"><?php echo esc_html__( 'Result Graphs', 'wpbench' ); ?></h3>
 <?php if (!empty($results) && !isset($results['errors']['benchmark_runtime']) && !isset($results['errors']['state_change'])): // Don't show chart if benchmark didn't run or state change failed ?>
    <div style="max-width: 700px; margin-bottom: 30px; background: #fff; padding: 15px; border: 1px solid #ddd;"><canvas id="wpbenchTimingChart"></canvas></div>
    <div style="max-width: 700px; background: #fff; padding: 15px; border: 1px solid #ddd;"><canvas id="wpbenchMemoryChart"></canvas></div>
 <?php elseif (isset($results['errors'])): ?>
     <p><?php echo esc_html__( 'Graphs cannot be displayed due to errors during the benchmark run.', 'wpbench' ); ?></p>
 <?php else: ?>
     <p><?php echo esc_html__( 'Graphs cannot be displayed as results data is missing.', 'wpbench' ); ?></p>
 <?php endif; ?>