<?php
/**
 * View file for the Benchmark Profile Configuration meta box.
 *
 * Expects the following variables:
 * @var array      $available_tests      Array of available test info arrays, keyed by test ID.
 * @var array      $saved_selected_tests Array of test IDs saved for this profile.
 * @var \WP_Post   $post                 The current benchmark_profile post object.
 * @var string     $nonce_action         Nonce action name.
 * @var string     $nonce_name           Nonce input name.
 * @var string     $tests_input_name     Name attribute for test selection checkboxes.
 * @var string     $config_input_prefix  Prefix for config input names (e.g., 'profile_config_').
 * @var string     $config_meta_prefix   Prefix for config meta keys (e.g., '_wpbench_profile_config_').
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

wp_nonce_field($nonce_action, $nonce_name);

if (empty($available_tests)) {
     echo '<p>' . esc_html__('No benchmark tests found.', 'wpbench') . '</p>';
     return;
}
?>
<div class="wpbench-profile-section">
    <h4><?php esc_html_e('Tests to Include in Profile', 'wpbench'); ?></h4>
    <fieldset id="wpbench-profile-test-selector">
         <?php foreach ($available_tests as $id => $info): ?>
            <label for="profile_test_<?php echo esc_attr($id); ?>" style="display: block; margin-bottom: 5px;">
                <input name="<?php echo esc_attr($tests_input_name); ?>" type="checkbox" id="profile_test_<?php echo esc_attr($id); ?>" value="<?php echo esc_attr($id); ?>"
                       <?php checked(in_array($id, $saved_selected_tests)); ?>>
                <strong><?php echo esc_html($info['name'] ?? $id); ?></strong>
                <?php if (!empty($info['description'])): ?>
                    <em style="color: #666;"> - <?php echo esc_html($info['description']); ?></em>
                <?php endif; ?>
            </label>
        <?php endforeach; ?>
    </fieldset>
</div>

<hr style="margin-top: 20px; margin-bottom: 20px;">

<div class="wpbench-profile-section">
    <h4><?php esc_html_e('Test Configuration Values', 'wpbench'); ?></h4>
    <p class="description"><?php esc_html_e('Configure the parameters for each test when this profile is run.', 'wpbench'); ?></p>
    <table class="form-table">
        <tbody>
         <?php foreach ($available_tests as $id => $info):
              $config_meta_key = $config_meta_prefix . $id;
              $saved_value = get_post_meta($post->ID, $config_meta_key, true);
              // Use saved value or default if not set yet
              $current_value = ($saved_value !== '' && $saved_value !== null) ? $saved_value : $info['default_value'];
              $input_name = $config_input_prefix . $id;
         ?>
            <tr class="wpbench-profile-config-row" data-testid="<?php echo esc_attr($id); ?>">
                <th scope="row">
                    <label for="<?php echo esc_attr($input_name); ?>"><?php echo esc_html($info['config_label'] ?? $id); ?></label>
                </th>
                <td>
                    <input name="<?php echo esc_attr($input_name); ?>"
                           type="number"
                           id="<?php echo esc_attr($input_name); ?>"
                           value="<?php echo esc_attr($current_value); ?>"
                           class="small-text" <?php // Use small-text for profile page ?>
                           min="<?php echo esc_attr($info['min_value'] ?? 0); ?>"
                           max="<?php echo esc_attr($info['max_value'] ?? 1000000); ?>"
                           step="1">
                    <?php if (!empty($info['config_unit'])): ?>
                        <span class="description"><?php echo esc_html( $info['config_unit'] ); ?></span>
                    <?php endif; ?>
                     <p class="description"><?php printf( __('Default: %s, Min: %s, Max: %s', 'wpbench'), esc_html(number_format_i18n($info['default_value'] ?? 0)), esc_html(number_format_i18n($info['min_value'] ?? 0)), esc_html(number_format_i18n($info['max_value'] ?? 1000000)) ); ?></p>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>