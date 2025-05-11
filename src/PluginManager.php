<?php
namespace WPBench;

use WP_Error;
use WPBench\PluginState;    // Assuming this is the correct namespace for PluginState

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the actual activation and deactivation of plugins.
 * Contains DANGEROUS operations. Use with extreme caution.
 */
class PluginManager {

    /**
     * Attempts to change the plugin state based on calculated differences.
     * !! THIS IS A HIGH-RISK OPERATION !!
     *
     * @param string[] $to_activate Array of plugin file paths to activate.
     * @param string[] $to_deactivate Array of plugin file paths to deactivate.
     *
     * @return array{success: bool, errors: array, final_state: array} Result array.
     */
    public function executeChange( array $to_activate, array $to_deactivate) : array {
        $errors = [];
        $success = true;

	    // --- WPBench Self-Protection: Ensure WPBench is not in the lists ---
	    $wpbench_plugin_basename = plugin_basename(WPBENCH_PATH . 'wpbench.php');

	    $to_activate_orig_count = count( $to_activate);
	    $to_deactivate_orig_count = count($to_deactivate);

	    $to_activate   = array_values(array_diff( $to_activate, [$wpbench_plugin_basename]));
	    $to_deactivate = array_values(array_diff($to_deactivate, [$wpbench_plugin_basename]));

	    if ( count( $to_activate) !== $to_activate_orig_count || count($to_deactivate) !== $to_deactivate_orig_count) {
			$errorString = __('WPBench plugin itself was filtered out from activation/deactivation targets.', 'wpbench');
		    $errors['plugin_manager_safeguard'] = $errorString;

		    Logger::log('PluginManager filtered out WPBench from activation/deactivation targets. This indicates a potential logic flow issue upstream.', 'security');
	    }
	    // --- End WPBench Self-Protection ---

        // Include WordPress's plugin functions if not already loaded
        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // --- Deactivate first ---
        if (!empty($to_deactivate)) {
			try {
				// Deactivate silently (suppress hooks)
				$deactivate_result = deactivate_plugins( $to_deactivate, true, false ); // false = not network wide

				if ( is_wp_error( $deactivate_result ) ) {
					$errorString = "Error deactivating plugins: " . ( isset( $deactivate_result ) ? $deactivate_result->get_error_message() : 'Unknown error' );

					$errors['deactivation'] = $errorString;

					$success = false; // Mark as failed if deactivation fails

					Logger::log($errorString, E_USER_WARNING);

					// @TODO: Bail early on deactivation failure? Might be safer.
					// return ['success' => false, 'errors' => $errors, 'final_state' => $this->getCurrentStateForReturn()];
				} elseif ( $deactivate_result === false ) {
					$errorString = "Failed deactivating (unknown reason, check file path/permissions).";

					$errors['deactivation'] = $errorString;

					$success = false;

					Logger::log($errorString, E_USER_WARNING);
				}
			} catch (\Exception $e) {
				$success = false;

				$errorString = "Exception caught while deactivating plugins: " . $e->getMessage();

				$errors['deactivation'] = $errorString;

				Logger::log($errorString, E_USER_WARNING);
			}
        }

        // --- Activate second (only if deactivation didn't fail hard) ---
        if ( $success && ! empty( $to_activate)) {
            $activation_sub_errors = [];

            foreach ( $to_activate as $plugin_file) {
	            // Skip if somehow already active
                if (is_plugin_active($plugin_file)) {
					continue;
                }

				try {
					// Activate silently, not network wide
					$activate_result = activate_plugin( $plugin_file, '', false, true );

					if ( is_wp_error( $activate_result ) ) {
						$errorString = "Error activating plugin: " . $activate_result->get_error_message();

						$activation_sub_errors[ $plugin_file ] = $errorString;

						$success = false; // Mark as failed if any activation fails

						Logger::log($errorString, E_USER_WARNING);

						// @TODO: Continue trying others? Or bail? Bailing might leave partially activated state. Continue for now.
					} elseif ( $activate_result === false ) {
						$errorString = "Failed activating (unknown reason, check file path/permissions).";

						$activation_sub_errors[ $plugin_file ] = $errorString;

						$success = false;

						Logger::log($errorString, E_USER_WARNING);
					}
				} catch (\Exception $e) {
					$errorString = "Exception caught while activating plugin: $plugin_file: " . $e->getMessage();

					$activation_sub_errors[ $plugin_file ] = $errorString;

					$success = false;

					Logger::log($errorString, E_USER_WARNING);
				}
            }

            if (!empty($activation_sub_errors)) {
                $errors['activation'] = $activation_sub_errors;
            }
        }

        return [
            'success' => $success,
            'errors' => $errors,
            'final_state' => $this->getCurrentStateForReturn() // Return the state *after* changes attempted
        ];
    }

     /**
     * Attempts to restore a previous plugin state.
     * !! THIS IS A HIGH-RISK OPERATION !!
     *
     * @param array $targetState The desired state ['active_site' => [], 'active_network' => []].
     * @param array $currentState The current state ['active_site' => [], 'active_network' => []].
     * @return array{success: bool, errors: array} Result array.
     */
    public function restoreState(array $targetState, array $currentState) : array {
        $errors = [];
        $pluginStateUtil = new PluginState(); // Need instance for calculation

        // Ensure target state keys exist
        $targetState['active_site'] = $targetState['active_site'] ?? [];
        $targetState['active_network'] = $targetState['active_network'] ?? [];

        // Calculate changes needed to get from current state back to target state
        //$restoreChanges = $pluginStateUtil->calculateStateChanges($currentState, $targetState['active_site']);
	    $restoreChanges = $pluginStateUtil->calculateStateChanges(
			$currentState,
			array_unique(array_merge($targetState['active_site'], $targetState['active_network']))
	    );
	    // Note: This simple restore assumes we only need to restore site plugins. Restoring network may need more logic.

        $restoreResult = $this->executeChange($restoreChanges['to_activate'], $restoreChanges['to_deactivate']);

        // Prepend context to errors
        foreach ($restoreResult['errors'] as $type => $messages) {
            $errors['restoration_' . $type] = $messages;
        }

        return [
            'success' => $restoreResult['success'],
            'errors' => $errors
        ];
    }

    /** Helper to get current state for return values */
    private function getCurrentStateForReturn() : array {
         return new PluginState()->getCurrentState();
    }
}