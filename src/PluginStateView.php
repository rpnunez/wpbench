<?php
namespace WPBench;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles rendering the HTML for plugin state selection.
 */
class PluginStateView {

    // Accept TestRegistry but mark as potentially unused if only needed by other methods
    public function __construct(
		private PluginState $pluginState,
		private TestRegistry $testRegistry
    ) {
    }

    /**
     * Renders the HTML fieldset for selecting desired plugins by including a partial view.
     *
     * @param string[]|null $selected_plugins Optional. Array of plugin file paths that should be pre-checked.
     * If null, defaults to checking currently active plugins.
     * @param string $input_name             The 'name' attribute for the checkboxes (e.g., 'desired_plugins[]').
     */
    public function renderPluginSelector(?array $selected_plugins = null, string $input_name = 'desired_plugins[]'): void {
        // Prepare variables needed by the partial view
        $all_plugins = get_plugins();
	    $is_multisite = is_multisite();
	    $can_manage_network = $is_multisite && current_user_can('manage_network_plugins');

        $current_state = $this->pluginState->getCurrentState();
        $current_active_site = $current_state['active_site'];
        $current_active_network = $current_state['active_network'];

        // Determine initial checked state: use provided selection, or fallback to current reality
        $plugins_to_check = $selected_plugins ?? $this->pluginState->getCurrentCombinedActivePlugins();

        // Include the partial view file
        include WPBENCH_PATH . 'views/admin/partials/plugin-selector.php';
    }
}