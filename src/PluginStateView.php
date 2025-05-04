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

    private $pluginState;
    private $testRegistry; // Keep if needed later, but not strictly used in renderPluginSelector now

    // Accept TestRegistry but mark as potentially unused if only needed by other methods
    public function __construct(PluginState $pluginState, /** @scrutinizer ignore-unused-parameter */ TestRegistry $testRegistry) {
        $this->pluginState = $pluginState;
        $this->testRegistry = $testRegistry;
    }

    /**
     * Renders the HTML fieldset for selecting desired plugins by including a partial view.
     *
     * @param string[]|null $selectedPlugins Optional. Array of plugin file paths that should be pre-checked.
     * If null, defaults to checking currently active plugins.
     * @param string $inputName             The 'name' attribute for the checkboxes (e.g., 'desired_plugins[]').
     */
    public function renderPluginSelector(?array $selectedPlugins = null, string $inputName = 'desired_plugins[]') {
        // Prepare variables needed by the partial view
        $all_plugins = get_plugins();
        $currentState = $this->pluginState->getCurrentState();
        $current_active_site = $currentState['active_site'];
        $current_active_network = $currentState['active_network'];
        $is_multisite = is_multisite();
        $can_manage_network = $is_multisite && current_user_can('manage_network_plugins');

        // Determine initial checked state: use provided selection, or fallback to current reality
        $plugins_to_check = $selectedPlugins ?? $this->pluginState->getCurrentCombinedActivePlugins();

        // Include the partial view file
        include WPBENCH_PATH . 'views/admin/partials/plugin-selector.php';
    }
}