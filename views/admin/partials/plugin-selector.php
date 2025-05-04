<?php
/**
 * Partial view for rendering the plugin selector checkbox list.
 *
 * Expects the following variables to be set:
 * @var array $all_plugins          Array of all plugin data from get_plugins().
 * @var array $plugins_to_check     Array of plugin file paths that should be checked initially.
 * @var array $current_active_site  Array of currently site-active plugin files.
 * @var array $current_active_network Array of currently network-active plugin files.
 * @var bool  $is_multisite         Boolean indicating if the site is multisite.
 * @var bool  $can_manage_network   Boolean indicating if user can manage network plugins.
 * @var string $input_name          The 'name' attribute for the checkboxes (e.g., 'desired_plugins[]').
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if (empty($all_plugins)) {
     echo '<p>' . esc_html__('Could not retrieve plugin list.', 'wpbench') . '</p>';
     return;
}

?>
<fieldset>
    <legend class="screen-reader-text"><span><?php esc_html_e('Select Plugins', 'wpbench'); ?></span></legend>
    <div style="max-height: 300px; overflow-y: scroll; border: 1px solid #ccd0d4; padding: 10px; background: #fff;">
    <?php
    // Sort plugins by name for easier scanning
    uasort($all_plugins, function($a, $b) {
        return strcasecmp($a['Name'] ?? '', $b['Name'] ?? '');
    });

    foreach ($all_plugins as $plugin_file => $plugin_data) {
        $is_currently_site_active = in_array($plugin_file, $current_active_site);
        $is_currently_network_active = in_array($plugin_file, $current_active_network);
        $is_checked = in_array($plugin_file, $plugins_to_check); // Check based on passed variable
        $is_disabled = $is_currently_network_active && !$can_manage_network; // Site admin cannot change network-active plugins

        // Ensure disabled network plugins are always checked visually (their state cannot be changed by non-network admin)
        if ($is_disabled) {
            $is_checked = true;
        }

        $checkbox_id = 'plugin_' . esc_attr(sanitize_key($plugin_file));
        ?>
        <div style="margin-bottom: 8px; padding: 5px; border-bottom: 1px dotted #eee;">
            <label for="<?php echo $checkbox_id; ?>">
                <input name="<?php echo esc_attr($input_name); ?>"
                       type="checkbox"
                       id="<?php echo $checkbox_id; ?>"
                       value="<?php echo esc_attr($plugin_file); ?>"
                       <?php checked($is_checked); ?>
                       <?php disabled($is_disabled); ?>
                       >
                <strong><?php echo esc_html($plugin_data['Name'] ?? $plugin_file); ?></strong>
                <span style="color: #777; font-size: smaller;">(v<?php echo esc_html($plugin_data['Version'] ?? 'N/A'); ?>)</span>
            </label>
            <?php if ($is_currently_network_active): ?>
                <span style="margin-left: 10px; color: #0073aa; font-weight: bold; font-size: smaller;"><?php esc_html_e('[Network Active]', 'wpbench'); ?></span>
                 <?php if ($is_disabled): ?>
                       <em style="font-size: smaller; color: #999;"> <?php esc_html_e('(Cannot be changed)', 'wpbench'); ?></em>
                 <?php endif; ?>
            <?php elseif ($is_currently_site_active): ?>
                  <span style="margin-left: 10px; color: #228b22; font-weight: bold; font-size: smaller;"><?php esc_html_e('[Active]', 'wpbench'); ?></span>
            <?php else: ?>
                  <span style="margin-left: 10px; color: #999; font-size: smaller;"><?php esc_html_e('[Inactive]', 'wpbench'); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }
    ?>
    </div>
</fieldset>