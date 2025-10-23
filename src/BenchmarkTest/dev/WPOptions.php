<?php
namespace src\BenchmarkTest\dev;

use WPBench\BenchmarkTest\BaseBenchmarkTest;
use WPBench\Guards\ResourceGuard;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPOptions implements BaseBenchmarkTest {

	const OPTION_PREFIX = 'wpbench_option_test_';
	const NUM_TEMP_OPTIONS = 5; // Number of unique option names to cycle through

	/**
	 * Retrieves the singleton instance of the WPOptions class.
	 *
	 * @return WPOptions|null The singleton instance of the WPOptions class, or null if an instance is not created.
	 */
	public function getInstance(): ?WPOptions {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	public function getInfo() : array {
		return [
			'id'            => 'wp_options',
			'name'          => __('WordPress Options API Stress Test', 'wpbench'),
			'description'   => __('Performs repeated add, get, update, and delete operations using the Options API on a small set of temporary options.', 'wpbench'),
			'config_label'  => __('Number of Option CRUD Cycles', 'wpbench'),
			'config_unit'   => sprintf(__('cycles (1 cycle = CRUD ops on %d options)', 'wpbench'), self::NUM_TEMP_OPTIONS),
			'default_value' => 100, // Each cycle involves NUM_TEMP_OPTIONS * 4 DB ops if no cache
			'min_value'     => 10,
			'max_value'     => 1000, // Can be slow, especially without object cache
		];
	}

	public function run( $value ) : array {
		$cycles = absint($value);
		if ($cycles <= 0) {
			return ['time' => 0, 'operations' => 0, 'error' => 'Invalid cycle count.'];
		}

		$start_time = microtime(true);
		$error_message = null;
		$total_operations = 0; // add, get, update, delete are individual ops
		$test_option_names = [];

		// Generate unique option names for this run to avoid collisions if test fails mid-way
		$run_id = uniqid();
		for ($i = 0; $i < self::NUM_TEMP_OPTIONS; $i++) {
			$test_option_names[] = self::OPTION_PREFIX . $run_id . '_' . $i;
		}

		try {
			ResourceGuard::checkIfMaxIterationsReached($cycles * self::NUM_TEMP_OPTIONS, 10000);

			for ($c = 0; $c < $cycles; $c++) {
				foreach ($test_option_names as $option_name) {
					$sample_data = ['value' => "Test data for $option_name cycle $c", 'rand' => rand(1, 100000)];
					$autoload = ( $c % 2 === 0 ) ? 'yes' : 'no'; // Vary autoload

					// 1. ADD
					$added = add_option($option_name, $sample_data, '', $autoload);
					$total_operations++;
					if (!$added && get_option($option_name) === false) { // Check if it truly failed or just existed
						// throw new \RuntimeException("Failed to add option: {$option_name}");
						// Option might already exist from a previous failed run if cleanup failed.
						// Let's try to update if add failed due to existence
						update_option($option_name, $sample_data);
					}


					// 2. GET
					$retrieved_data = get_option($option_name);
					$total_operations++;
					// Could add a check: if ($retrieved_data != $sample_data) { throw ... } but might slow test.

					// 3. UPDATE
					$updated_data = array_merge((array)$retrieved_data, ['updated_value' => "Updated at " . microtime(true)]);
					update_option($option_name, $updated_data);
					$total_operations++;

					// 4. GET (verify update)
					// $retrieved_again = get_option($option_name);
					// $total_operations++;

					// 5. DELETE
					delete_option($option_name);
					$total_operations++;
				}
			}

		} catch (\Throwable $e) {
			$error_message = get_class($e) . ': ' . $e->getMessage();
		} finally {
			// --- Cleanup all test options ---
			foreach ($test_option_names as $option_name_to_delete) {
				delete_option($option_name_to_delete);
			}
		}

		$end_time = microtime(true);

		return [
			'time'         => round($end_time - $start_time, 4),
			'operations'   => $total_operations,
			'cycles'       => $cycles,
			'options_per_cycle' => self::NUM_TEMP_OPTIONS,
			'error'        => $error_message,
		];
	}

	public function calculateScore( array $test_results, array $config ): array {
		// TODO: Implement calculateScore() method.

		return [];
	}
}