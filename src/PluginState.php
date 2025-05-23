<?php
namespace WPBench;
use WP_Post;
use WPBench\AdminBenchmark;
use WPBench\Guards\PluginGuard;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles retrieving, calculating, and saving plugin states.
 */
class PluginState {

    public const string PRE_BENCHMARK_STATE_META_KEY = '_wpbench_pre_benchmark_plugin_state';
    public const string DESIRED_PLUGINS_META_KEY = '_wpbench_desired_plugins';
    public const string ACTUAL_PLUGINS_META_KEY = '_wpbench_active_plugins'; // From the old hook

    /**
     * Get the current active plugin state.
     *
     * @return array{active_site: string[], active_network: string[]}
     */
    public function getCurrentState() : array {
        $state = [
            'active_site'    => (array) get_option( 'active_plugins', [] ),
            'active_network' => [],
        ];

        if (is_multisite()) {
            $state['active_network'] = array_keys(get_site_option( 'active_sitewide_plugins', [] ));
        }

        return $state;
    }

    /**
     * Get all currently active plugin files (merged site and network).
     *
     * @return string[] Array of active plugin file paths.
     */
    public function getCurrentCombinedActivePlugins() : array {
         $state = $this->getCurrentState();

         return array_unique(array_merge($state['active_site'], $state['active_network']));
    }


    /**
     * Save the current plugin state as the "pre-benchmark" state for a post.
     *
     * @param int $postId The ID of the benchmark_result post.
     * @return array The saved state array.
     */
    public function savePreBenchmarkState(int $postId) : array {
        $currentState = $this->getCurrentState();
        $jsonState = wp_json_encode($currentState);

        update_post_meta($postId, self::PRE_BENCHMARK_STATE_META_KEY, $jsonState);

        return $currentState; // Return the array form
    }

    /**
     * Get the saved pre-benchmark plugin state for a post.
     *
     * @param int $postId The ID of the benchmark_result post.
     * @return array|null The decoded state array or null if not found/invalid.
     */
    public function getPreBenchmarkState(int $postId) : ?array {
        $jsonState = get_post_meta($postId, self::PRE_BENCHMARK_STATE_META_KEY, true);

        if (empty($jsonState)) {
            return null;
        }

        $state = json_decode($jsonState, true);

        return is_array($state) ? $state : null;
    }

    /**
     * Save the desired plugin state (list of plugin files) for a post.
     *
     * @param int   $postId         The ID of the benchmark_result or benchmark_profile post.
     * @param array $desiredPlugins Array of plugin file paths user wants active.
     */
    public function saveDesiredState(int $postId, array $desiredPlugins) {
        // Basic validation/sanitization - ensure they look like plugin paths
        $sanitizedPlugins = array_filter($desiredPlugins, function($plugin_file) {
            return is_string($plugin_file) && strpos($plugin_file, '.php') !== false && strlen($plugin_file) < 255;
        });

        update_post_meta($postId, self::DESIRED_PLUGINS_META_KEY, $sanitizedPlugins);
    }

     /**
     * Get the desired plugin state for a post.
     *
     * @param int $postId The ID of the benchmark_result or benchmark_profile post.
     * @return string[] Array of desired plugin file paths.
     */
    public function getDesiredState(int $postId) : array {
        return get_post_meta($postId, self::DESIRED_PLUGINS_META_KEY, true) ?: [];
    }


    /**
     * Calculates which plugins need to be activated or deactivated.
     * Incorporates logic for multisite and non-network admins.
     *
     * @param array $currentState   ['active_site' => [], 'active_network' => []]
     * @param array $desiredPlugins List of plugin files that should be active.
     * @return array{to_activate: string[], to_deactivate: string[]}
     */
    public function calculateStateChanges(array $currentState, array $desiredPlugins) : array {
	    $current_site = $currentState['active_site'] ?? [];
	    $current_network = $currentState['active_network'] ?? [];
	    $current_all = array_unique(array_merge($current_site, $current_network));

	    // Ensure desired state includes WPBench, handled by PluginGuard
	    $desiredIncludingSelf = PluginGuard::ensureWPBenchInDesiredList(array_unique($desiredPlugins)); // <-- UPDATED static call (FQCN if no use statement)

	    $can_manage_network = is_multisite() && current_user_can('manage_network_plugins');
	    $deactivateCandidates = $can_manage_network ? $current_all : $current_site;

	    if (!$can_manage_network) {
		    $deactivateCandidates = array_diff($deactivateCandidates, $current_network);
	    }

	    $toDeactivate = array_diff($deactivateCandidates, $desiredIncludingSelf);
	    $toActivate = array_diff($desiredIncludingSelf, $current_all);

	    // --- Use PluginGuard static methods for filtering ---
	    $toDeactivate = PluginGuard::filterWPBenchFromDeactivationList($toDeactivate); // <-- UPDATED static call
	    $toActivate = PluginGuard::filterWPBenchFromActivationList($toActivate);       // <-- UPDATED static call
	    // --- END SAFEGUARD ---

        return [
            'to_activate'   => $toActivate,
            'to_deactivate' => $toDeactivate,
        ];
    }


    /**
     * Save the list of currently active plugins.
     * Intended for use with the 'save_post' hook for benchmark_result CPT.
     * Saves the state *after* any benchmark run and potential restoration attempt.
     *
     * @param int     $post_id The ID of the post being saved.
     * @param \WP_Post $post    The post object.
     */
    public function saveActualPluginsListHook( $post_id, WP_Post $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

        // Use AdminBenchmark constant for post type check
        if ( AdminBenchmark::POST_TYPE !== $post->post_type ) {
			return;
		}

	    $currentState = $this->getCurrentState();
	    $active_plugins_list_for_meta = [];
	    $all_plugins_info = get_plugins();
	    $all_current_active = array_unique(array_merge($currentState['active_site'], $currentState['active_network']));

	    foreach ($all_current_active as $plugin_file) {
		    $plugin_data = $all_plugins_info[$plugin_file] ?? null;

		    if (!$plugin_data && is_multisite()) {
			    $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

			    if (file_exists($plugin_path)) {
					$plugin_data = get_plugin_data($plugin_path);
			    }
		    }

		    if ($plugin_data && !empty($plugin_data['Name'])) {
			    $active_plugins_list_for_meta[] = [
					'name' => $plugin_data['Name'],
					'version' => $plugin_data['Version'],
					'file' => $plugin_file
			    ];
		    } else {
			    $active_plugins_list_for_meta[] = [
					'name' => $plugin_file . ' (Info Missing)',
					'version' => 'N/A',
					'file' => $plugin_file
			    ];
		    }
	    }

	    update_post_meta($post_id, self::ACTUAL_PLUGINS_META_KEY, $active_plugins_list_for_meta);
    }

}