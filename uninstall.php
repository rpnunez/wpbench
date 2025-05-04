<?php
/**
 * WPBench Uninstall Tasks
 *
 * This script runs only when the user deletes the WPBench plugin
 * via the WordPress Admin interface. It provides options to clean up data.
 *
 * @package WPBench
 */

// Exit if accessed directly or not during uninstall process
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// --- Configuration ---
// Define constants directly here as plugin files are not loaded
$wpbench_option_name = 'wpbench_options';
$wpbench_result_cpt_slug = 'benchmark_result';
$wpbench_profile_cpt_slug = 'benchmark_profile';

// --- Get Deletion Settings ---
// Retrieve the entire options array directly
$options = get_option($wpbench_option_name);

$delete_results = false;
$delete_profiles = false;

if (is_array($options)) {
	// Check if the specific setting exists and is set to 'yes'
	$delete_results = isset($options['delete_on_uninstall_benchmark_results']) && $options['delete_on_uninstall_benchmark_results'] === 'yes';
	$delete_profiles = isset($options['delete_on_uninstall_benchmark_profiles']) && $options['delete_on_uninstall_benchmark_profiles'] === 'yes';
}

// --- Perform Deletion Actions ---

// Load $wpdb global safely
global $wpdb;

// Delete Benchmark Results if requested
if ($delete_results) {
	// Get IDs of all posts of the custom post type
	$result_post_ids = $wpdb->get_col($wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
		$wpbench_result_cpt_slug
	));

	if (!empty($result_post_ids)) {
		foreach ($result_post_ids as $post_id) {
			// Use wp_delete_post to ensure associated meta, terms, etc., are also cleaned up
			// true = force delete, bypass trash
			wp_delete_post($post_id, true);
			// Add a small delay to prevent overwhelming server on large amounts? Optional.
			// usleep(10000); // Sleep for 10ms
		}
	}
}

// Delete Benchmark Profiles if requested
if ($delete_profiles) {
	// Get IDs of all profile posts
	$profile_post_ids = $wpdb->get_col($wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
		$wpbench_profile_cpt_slug
	));

	if (!empty($profile_post_ids)) {
		foreach ($profile_post_ids as $post_id) {
			wp_delete_post($post_id, true);
			// usleep(10000);
		}
	}
}

// --- Always delete the plugin's main options ---
// This happens regardless of the checkbox settings above.
delete_option($wpbench_option_name);

// --- Optional: Delete other data ---
// Example: delete transients, custom tables, etc.
// delete_transient('wpbench_some_transient');
// $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpbench_custom_table");