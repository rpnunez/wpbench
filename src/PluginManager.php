<?php
namespace WPBench;

use WP_Error;
use WPBench\Guards\PluginGuard;
use WPBench\PluginState;
use WPBench\Helpers\Safeguard;

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
     * @param string[] $toActivate Array of plugin file paths to activate.
     * @param string[] $toDeactivate Array of plugin file paths to deactivate.
     *
     * @return array{success: bool, errors: array, final_state: array} Result array.
     */
    public function executeChange( array $toActivate, array $toDeactivate) : array {
        $errors = [];
        $success = true;

	    // --- WPBench Self-Protection: Ensure WPBench is not in the lists ---
//	    $wpbench_plugin_basename = plugin_basename(WPBENCH_PATH . 'wpbench.php');
//
//	    $to_activate_orig_count = count( $to_activate);
//	    $to_deactivate_orig_count = count($to_deactivate);
//
//	    $to_activate   = array_values(array_diff( $to_activate, [$wpbench_plugin_basename]));
//	    $to_deactivate = array_values(array_diff($to_deactivate, [$wpbench_plugin_basename]));
//
//	    if (
//			count( $to_activate) !== $to_activate_orig_count ||
//			count($to_deactivate) !== $to_deactivate_orig_count
//	    ) {
//		    $errors['plugin_manager_safeguard'] = __('WPBench plugin itself was filtered out from activation/deactivation targets.', 'wpbench');
//
//		    Logger::log('PluginManager filtered out WPBench from activation/deactivation targets. This indicates a potential logic flow issue upstream.', 'security');
//	    }
//	    // --- End WPBench Self-Protection ---

	    // --- WPBench Self-Protection ---
//	    $pluginBaseName = plugin_basename(WPBENCH_PATH . 'wpbench.php');
//
//	    [$to_activate, $to_deactivate] = Safeguard::ensurePluginSelfProtection(
//			$to_activate,
//			$to_deactivate,
//			$pluginBaseName
//	    );
		// --- End WPBench Self-Protection ---

	    // --- Defense-in-depth: Ensure WPBench is not in the lists using PluginGuard static methods ---
	    $original_to_activate_count = count($toActivate);
	    $original_to_deactivate_count = count($toDeactivate);

	    $toActivate = PluginGuard::filterWPBenchFromActivationList($toActivate);   // <-- UPDATED static call
	    $toDeactivate = PluginGuard::filterWPBenchFromDeactivationList($toDeactivate); // <-- UPDATED static call

	    if (count($toActivate) !== $original_to_activate_count || count($toDeactivate) !== $original_to_deactivate_count) {
		    $errors['plugin_manager_safeguard'] = __('WPBench plugin itself was filtered out by PluginManager from targets.', 'wpbench');
		    error_log('WPBench SECURITY: PluginManager safeguard triggered. WPBench was found in activation/deactivation targets. This indicates an upstream logic error in PluginState::calculateStateChanges.');
	    }
	    // --- End Safeguard ---

        // Include WordPress's plugin functions if not already loaded
        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // --- Deactivate first ---
        if (!empty($to_deactivate)) {
			try {
				// Deactivate silently (suppress hooks)
				$deactivate_result = deactivate_plugins( $to_deactivate, true, false ); // false = not network wide

				if ( is_wp_error( $deactivate_result ) || ($deactivate_result === false || !isSet($deactivate_result)) ) {
					$errors['deactivation'][] = "Error deactivating plugins: " . ( isset( $deactivate_result ) ? $deactivate_result->get_error_message() : 'Unknown error' );
					$success = false;

					Logger::log( $errors, E_USER_WARNING );

					// @TODO: Bail early on deactivation failure? Might be safer.
					// return ['success' => false, 'errors' => $errors, 'final_state' => $this->getCurrentStateForReturn()];
				} else {
					Logger::log( 'Plugin deactivated: '. print_r($deactivate_result, true), 'info' );
				}
			} catch (\Exception $e) {
				$errors['deactivation'][] = "Exception caught while deactivating plugins: " . $e->getMessage();;

				Logger::log( $errors, E_USER_WARNING );
			}
        }

        // --- Activate second (only if deactivation didn't fail hard) ---
        if ( $success && ! empty( $to_activate)) {
            foreach ( $to_activate as $plugin_file) {
	            // Skip if somehow already active
                if (is_plugin_active($plugin_file)) {
					continue;
                }

	            $success = true; // Mark as failed if any activation fails

				try {
					// Activate silently, not network wide
					$activateResult = activate_plugin( $plugin_file, '', false, true );

					if ( is_wp_error( $activateResult ) || ($activateResult === false || !isSet($activateResult)) ) {
						$errors['activation'][ $plugin_file ] = "Error activating plugin: " . $activateResult->get_error_message();;
						$success = false;

						Logger::log($errors['activation'][ $plugin_file ], E_USER_WARNING);

						// @TODO: Continue trying others? Or bail? Bailing might leave partially activated state. Continue for now.
					} else {
						Logger::log( 'Plugin '. $plugin_file .' activated: '. print_r($activateResult, true), 'info' );
					}
				} catch (\Exception $e) {
					$errors['activation'][ $plugin_file ] = "Exception caught while activating plugin: $plugin_file: " . $e->getMessage();;
					$success = false;

					Logger::log('Error activating plugin '. $plugin_file .': '. $errors['activation'][ $plugin_file ], E_USER_WARNING);
				}
            }
        } else {
			Logger::log('Skipped activation plugin section because an error occurred while deactivation plugin section.', 'error');
        }

        $result = [
            'success' => $success,
            'errors' => $errors,
            'final_state' => $this->getCurrentStateForReturn() // Return the state *after* changes attempted
        ];

		Logger::log('executeChange final return value: '. print_r($result, true), 'info');
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
			array_unique(
				array_merge(
					$targetState['active_site'],
					$targetState['active_network']
				)
			)
	    );

	    // Note: This simple restore assumes we only need to restore site plugins. Restoring network may need more logic.
        $restoreResult = $this->executeChange(
			$restoreChanges['to_activate'],
			$restoreChanges['to_deactivate']
        );

        // Prepend context to errors
        foreach ($restoreResult['errors'] as $type => $messages) {
            $errors['restoration_' . $type] = $messages;
        }

		$errors = array_merge($errors, $restoreResult['errors']);

        return [
            'success' => $restoreResult['success'],
            'errors' => $errors,
        ];
    }

    /** Helper to get current state for return values */
    private function getCurrentStateForReturn() : array {
         return new PluginState()->getCurrentState();
    }
}