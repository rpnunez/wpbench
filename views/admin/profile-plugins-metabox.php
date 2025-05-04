<?php
/**
 * View file for the Benchmark Profile Desired Plugins meta box.
 *
 * Expects the following variables:
 * @var WPBench\PluginStateView $pluginStateView Instance of the PluginStateView class.
 * @var array $saved_desired_plugins Array of plugin file paths saved for this profile.
 * @var \WP_Post $post               The current benchmark_profile post object.
 */

 // Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Nonce is expected to be in the first meta box rendered on the page ('profile-config-metabox.php')

?>
<p class="description"><?php esc_html_e('Select the plugins that should be active when running a benchmark using this profile.', 'wpbench'); ?></p>
<?php
// Render the reusable plugin selector partial, passing the saved desired plugins for this profile
// and the correct input name for saving.
$pluginStateView->renderPluginSelector($saved_desired_plugins, 'profile_desired_plugins[]');

?>