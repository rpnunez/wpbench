<?php
namespace src\BenchmarkTest\dev;

use WPBench\BenchmarkTest\BaseBenchmarkTest;
use WPBench\Guards\ResourceGuard;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPTransients implements BaseBenchmarkTest {

	const TRANSIENT_PREFIX = 'wpb_test_trans_';

	/**
	 * Retrieves the singleton instance of the WPTransients class.
	 *
	 * @return WPTransients|null The singleton instance of the WPTransients class, or null if an instance is not created.
	 */
	public function getInstance(): ?WPTransients {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	public function getInfo() : array {
		return [
			'id'            => 'wp_transients',
			'name'          => __('Transients API Stress Test', 'wpbench'),
			'description'   => __('Performs repeated set, get, and delete operations using the Transients API with a short expiration.', 'wpbench'),
			'config_label'  => __('Number of Transient CRUD Cycles', 'wpbench'),
			'config_unit'   => __('cycles (set/get/update/get/delete)', 'wpbench'),
			'default_value' => 100,
			'min_value'     => 10,
			'max_value'     => 2000, // High values can be slow depending on object cache
		];
	}

	public function run( $value ) : array {
		$cycles = absint($value);
		if ($cycles <= 0) {
			return ['time' => 0, 'operations' => 0, 'error' => 'Invalid cycle count.'];
		}

		$start_time = microtime(true);
		$error_message = null;
		$total_operations = 0;
		$transient_names_used = []; // For cleanup

		// Generate a unique run ID to ensure transient names are unique per test execution
		$run_id = uniqid();

		try {
			ResourceGuard::checkIfMaxIterationsReached($cycles, 5000); // Max cycles

			for ($i = 0; $i < $cycles; $i++) {
				$transient_name = self::TRANSIENT_PREFIX . $run_id . '_' . $i;
				$transient_names_used[] = $transient_name; // Store for cleanup
				$sample_data = ['value' => "Test data $i", 'rand' => rand(1, 100000), 'time' => microtime(true)];

				// 1. SET
				set_transient($transient_name, $sample_data, MINUTE_IN_SECONDS); // Short expiration
				$total_operations++;

				// 2. GET
				$retrieved = get_transient($transient_name);
				$total_operations++;
				// Optional: if (!$retrieved || $retrieved['rand'] !== $sample_data['rand']) throw new \Exception("Data mismatch on get for $transient_name");


				// 3. UPDATE (effectively another SET)
				$updated_data = array_merge($sample_data, ['updated' => true, 'update_time' => microtime(true)]);
				set_transient($transient_name, $updated_data, MINUTE_IN_SECONDS);
				$total_operations++;

				// 4. GET (updated)
				// $retrieved_updated = get_transient($transient_name);
				// $total_operations++;
				// Optional: if (!$retrieved_updated || !isset($retrieved_updated['updated'])) throw new \Exception("Data mismatch on get_updated for $transient_name");

				// 5. DELETE
				delete_transient($transient_name);
				$total_operations++;
			}

		} catch (\Throwable $e) {
			$error_message = get_class($e) . ': ' . $e->getMessage();
		} finally {
			// Cleanup any transients that might have been left over due to error/timeout
			foreach ($transient_names_used as $name) {
				delete_transient($name);
			}
		}

		$end_time = microtime(true);

		return [
			'time'         => round($end_time - $start_time, 4),
			'cycles'       => $cycles,
			'operations'   => $total_operations, // Each cycle has multiple operations
			'error'        => $error_message,
		];
	}

	public function calculateScore( array $test_results, array $config ): array {
		// TODO: Implement calculateScore() method.

		return [];
	}
}