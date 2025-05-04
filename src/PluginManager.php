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
     * @param string[] $toActivate   Array of plugin file paths to activate.
     * @param string[] $toDeactivate Array of plugin file paths to deactivate.
     * @return array{success: bool, errors: array, final_state: array} Result array.
     */
    public function executeChange(array $toActivate, array $toDeactivate) : array {
        $errors = [];
        $success = true;

        // Include WordPress's plugin functions if not already loaded
        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // --- Deactivate first ---
        if (!empty($toDeactivate)) {
            // Deactivate silently (suppress hooks)
            $deactivate_result = deactivate_plugins($toDeactivate, true, false); // false = not network wide
            
            if (is_wp_error($deactivate_result)) {
                $errors['deactivation'] = "Error deactivating plugins: " . (isset($deactivate_result) ? $deactivate_result->get_error_message() : 'Unknown error');
                $success = false; // Mark as failed if deactivation fails
                 // Bail early on deactivation failure? Might be safer.
                 // return ['success' => false, 'errors' => $errors, 'final_state' => $this->getCurrentStateForReturn()];
            }
        }

        // --- Activate second (only if deactivation didn't fail hard) ---
        if ($success && !empty($toActivate)) {
            $activation_sub_errors = [];

            foreach ($toActivate as $plugin_file) {
                if (is_plugin_active($plugin_file)) continue; // Skip if somehow already active

                // Activate silently, not network wide
                $activate_result = activate_plugin($plugin_file, '', false, true);

                if (is_wp_error($activate_result)) {
                    $activation_sub_errors[$plugin_file] = "Error activating: " . $activate_result->get_error_message();
                    $success = false; // Mark as failed if any activation fails
                    // Continue trying others? Or bail? Bailing might leave partially activated state. Continue for now.
                } elseif ($activate_result === false) {
                     $activation_sub_errors[$plugin_file] = "Failed activating (unknown reason, check file path/permissions).";
                     $success = false;
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
        $restoreChanges = $pluginStateUtil->calculateStateChanges($currentState, $targetState['active_site']);
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
         return (new PluginState())->getCurrentState();
    }
}