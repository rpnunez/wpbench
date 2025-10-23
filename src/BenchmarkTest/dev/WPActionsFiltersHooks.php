<?php
namespace src\BenchmarkTest\dev;

use WPBench\BenchmarkTest\BaseBenchmarkTest;
use WPBench\Guards\ResourceGuard;

// If detailed iteration checks are needed within loops

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPActionsFiltersHooks implements BaseBenchmarkTest {

	/**
	 * Retrieves the singleton instance of the WPActionsFiltersHooks class.
	 *
	 * @return WPActionsFiltersHooks|null The singleton instance of the WPActionsFiltersHooks class, or null if an instance is not created.
	 */
	public function getInstance(): ?WPActionsFiltersHooks {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	public function getInfo() : array {
		return [
			'id'            => 'wp_actions_filters_hooks',
			'name'          => __('Actions & Filters Hook Stress Test', 'wpbench'),
			'description'   => __('Adds a configurable number of dummy callback functions to custom action and filter hooks, then triggers them multiple times to measure hook system overhead.', 'wpbench'),
			'config_label'  => __('Callbacks per Hook & Execution Cycles', 'wpbench'),
			'config_unit'   => __('callbacks/executions', 'wpbench'),
			'default_value' => 500,  // e.g., 500 callbacks added, and hooks run 500 times
			'min_value'     => 50,
			'max_value'     => 5000, // Be cautious, N callbacks * N executions can be very heavy
		];
	}

	public function run( $value ) : array {
		$num_callbacks_and_executions = absint($value);
		if ($num_callbacks_and_executions <= 0) {
			return ['time' => 0, 'operations' => 0, 'error' => 'Invalid count for callbacks/executions.'];
		}

		$start_time = microtime(true);
		$error_message = null;
		$total_operations = 0; // Adding hooks + triggering hooks

		// Generate unique hook names for this run
		$test_action_name = 'wpbench_test_action_' . uniqid();
		$test_filter_name = 'wpbench_test_filter_' . uniqid();

		// Trivial callback functions
		$action_callback = function() { static $i = 0; $i++; };
		$filter_callback = function($data) { static $j = 0; $j++; return $data; };

		try {
			ResourceGuard::checkIfMaxIterationsReached($num_callbacks_and_executions, 10000); // Cap on callbacks

			// 1. Add Callbacks
			for ($i = 0; $i < $num_callbacks_and_executions; $i++) {
				add_action($test_action_name, $action_callback, 10, 0); // Priority 10, 0 accepted args
				add_filter($test_filter_name, $filter_callback, 10, 1); // Priority 10, 1 accepted arg
				$total_operations += 2; // 1 add_action, 1 add_filter
			}

			// 2. Execute Hooks
			$initial_filter_data = 'wpbench_data';
			for ($i = 0; $i < $num_callbacks_and_executions; $i++) {
				do_action($test_action_name);
				$filtered_data = apply_filters($test_filter_name, $initial_filter_data);
				$total_operations += 2; // 1 do_action, 1 apply_filters
			}

		} catch (\Throwable $e) {
			$error_message = get_class($e) . ': ' . $e->getMessage();
		} finally {
			// 3. Cleanup: Remove all callbacks for these specific hooks
			remove_all_actions($test_action_name);
			remove_all_filters($test_filter_name);
		}

		$end_time = microtime(true);

		return [
			'time'             => round($end_time - $start_time, 4),
			'callbacks_added_per_hook' => $num_callbacks_and_executions,
			'hook_executions'  => $num_callbacks_and_executions,
			'total_hook_operations' => $total_operations,
			'error'            => $error_message,
		];
	}

	public function calculateScore( array $test_results, array $config ): array {
		// TODO: Implement calculateScore() method.

		return [];
	}
}