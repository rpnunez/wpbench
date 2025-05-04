<?php
/**
 * View file for the "Run New Benchmark" admin page.
 *
 * Expects the following variables:
 * @var array $available_tests      Array of available test info arrays, keyed by test ID.
 * @var WPBench\PluginStateView $pluginStateView Instance of the PluginStateView class.
 * @var array $profiles             Array of WP_Post objects for saved profiles.
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap wpbench-wrap">
    <h1><?php esc_html_e( 'Run New Benchmark', 'wpbench' ); ?></h1>
    <p><?php esc_html_e('Configure and run benchmark tests for your WordPress site.', 'wpbench'); ?></p>
    <div class="notice notice-error inline">
         <p><strong><?php esc_html_e('HIGH RISK FEATURE:', 'wpbench'); ?></strong> <?php esc_html_e('The automatic Plugin State Change feature is EXTREMELY DANGEROUS and may break your site or lead to data loss. Ensure you have full backups and understand the risks. If unsure, manually set the plugin state via the Plugins screen before running.', 'wpbench'); ?></p>
     </div>
    <p><strong><?php esc_html_e('Resource Warning:', 'wpbench'); ?></strong> <?php esc_html_e('Running benchmarks can consume significant server resources.', 'wpbench'); ?></p>


    <?php if (empty($available_tests)): ?>
        <div class="notice notice-error"><p><?php esc_html_e('Error: No benchmark test classes found or loaded correctly. Check plugin files and permissions.', 'wpbench'); ?></p></div>
    <?php else: ?>
        <form id="wpbench-run-form" method="post">
             <?php wp_nonce_field( 'wpbench_run_action', 'wpbench_run_nonce' ); ?>

             <div style="margin-bottom: 20px; padding: 15px; background-color: #f0f0f1; border: 1px solid #c3c4c7;">
                <h3 style="margin-top: 0;"><?php esc_html_e('Load Settings from Profile', 'wpbench'); ?></h3>
                <?php if (!empty($profiles)): ?>
                    <label for="wpbench_profile_loader"><?php esc_html_e('Select Profile:', 'wpbench'); ?> </label>
                    <select id="wpbench_profile_loader" name="wpbench_profile_loader">
                        <option value=""><?php esc_html_e('-- Load Profile --', 'wpbench'); ?></option>
                        <?php foreach ($profiles as $profile_post): ?>
                            <option value="<?php echo esc_attr($profile_post->ID); ?>">
                                <?php echo esc_html($profile_post->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="wpbench-load-profile-btn" class="button button-secondary" style="margin-left: 10px;"><?php esc_html_e('Load', 'wpbench'); ?></button>
                    <span id="wpbench-profile-loader-status" style="margin-left: 10px; display: none;" class="spinner is-active"></span>
                    <p class="description"><?php esc_html_e('Select a saved profile to populate the settings below.', 'wpbench'); ?></p>
                <?php else: ?>
                     <p><?php esc_html_e('No saved benchmark profiles found.', 'wpbench'); ?></p>
                     <p><a href="<?php echo esc_url(admin_url('post-new.php?post_type='.WPBench\ProfileCPT::POST_TYPE)); ?>" class="button button-secondary"><?php esc_html_e('Create New Profile', 'wpbench'); ?></a></p>
                <?php endif; ?>
             </div>
             <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="benchmark_name"><?php esc_html_e( 'Benchmark Name', 'wpbench' ); ?></label></th>
                        <td><input name="benchmark_name" type="text" id="benchmark_name" value="Benchmark <?php echo esc_attr( date('Y-m-d H:i:s') ); ?>" class="regular-text" required>
                        <p class="description"><?php esc_html_e('Give this specific benchmark run a descriptive name.', 'wpbench'); ?></p></td>
                    </tr>

                    <tr><td colspan="2"><hr><h2><?php esc_html_e('Desired Plugin State for Run', 'wpbench'); ?></h2></td></tr>
                     <tr>
                        <th scope="row"><?php esc_html_e('Plugins', 'wpbench'); ?></th>
                        <td id="wpbench-plugin-selector-container">
                            <?php
                                // Render the plugin selector using the view class instance
                                // It defaults to checking currently active plugins
                                $pluginStateView->renderPluginSelector(null, 'desired_plugins[]'); // Pass null for default, set input name
                            ?>
                        </td>
                    </tr>


                    <tr><td colspan="2"><hr><h2><?php esc_html_e('Select Tests to Run', 'wpbench'); ?></h2></td></tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Tests', 'wpbench'); ?></th>
                        <td>
                            <fieldset id="wpbench-test-selector-container">
                                <legend class="screen-reader-text"><span><?php esc_html_e('Select Tests', 'wpbench'); ?></span></legend>
                                <?php foreach ($available_tests as $id => $info): ?>
                                    <label for="test_<?php echo esc_attr($id); ?>" style="display: block; margin-bottom: 10px;">
                                        <input name="selected_tests[]" type="checkbox" id="test_<?php echo esc_attr($id); ?>" value="<?php echo esc_attr($id); ?>" checked="checked">
                                        <strong><?php echo esc_html($info['name'] ?? $id); ?></strong>
                                        <?php if (!empty($info['description'])): ?>
                                            <p style="margin-left: 25px; margin-top: 0; font-style: italic; color: #666;"><?php echo esc_html($info['description']); ?></p>
                                        <?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                                <p class="description"><?php esc_html_e('Check the boxes for the tests you wish to include in this benchmark run.', 'wpbench'); ?></p>
                            </fieldset>
                        </td>
                    </tr>

                     <tr><td colspan="2"><hr><h2><?php esc_html_e('Test Configuration', 'wpbench'); ?></h2></td></tr>
                     <?php foreach ($available_tests as $id => $info): ?>
                        <tr class="wpbench-config-row" data-testid="<?php echo esc_attr($id); ?>">
                            <th scope="row">
                                <label for="config_<?php echo esc_attr($id); ?>"><?php echo esc_html($info['config_label'] ?? $id); ?></label>
                            </th>
                            <td>
                                <input name="config_<?php echo esc_attr($id); ?>" type="number" id="config_<?php echo esc_attr($id); ?>"
                                       value="<?php echo esc_attr($info['default_value'] ?? 0); ?>" class="regular-text"
                                       min="<?php echo esc_attr($info['min_value'] ?? 0); ?>" max="<?php echo esc_attr($info['max_value'] ?? 1000000); ?>" step="1">
                                <?php if (!empty($info['config_unit'])): ?>
                                    <span class="description"><?php echo esc_html( $info['config_unit'] ); ?></span>
                                <?php endif; ?>
                                 <p class="description"><?php printf( __('Default: %s, Min: %s, Max: %s', 'wpbench'), esc_html(number_format_i18n($info['default_value'] ?? 0)), esc_html(number_format_i18n($info['min_value'] ?? 0)), esc_html(number_format_i18n($info['max_value'] ?? 1000000)) ); ?></p>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                </tbody>
            </table>
            <input type="hidden" name="profile_id_used" id="profile_id_used" value="" /> <?php // Store loaded profile ID ?>
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
    #wpbench-plugin-selector-container div[style*="margin-bottom"] { padding: 5px; border-bottom: 1px dotted #eee; }
</style>
<?php
// Note: Inline JS for profile loading should be moved to a separate file and enqueued.
// The add_profile_loader_js() method called previously would be removed from AdminBenchmark.
// The JS code itself (provided before) would go into admin-benchmark.js (or a new file).
?>