<?php
namespace WPBench;

// Exit if accessed directly
use WPBench\BenchmarkTest\CPU;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Calculates benchmark scores based on results and configuration.
 */
class BenchmarkScore {

    // --- Score Calculation Constants ---

    public const float TARGET_FILE_IO_S_PER_1K_OPS = 0.1;
    public const float TARGET_DB_READ_S_PER_1K_QUERIES = 0.2;
    public const float TARGET_DB_WRITE_S_PER_1K_OPS = 0.3;
	public const float TARGET_MEMORY_MB = 100;

    // --- Weights (Should ideally add up to 1.0) ---

    public const float WEIGHT_FILE_IO = 0.10;
    public const float WEIGHT_DB_READ = 0.20;
    public const float WEIGHT_DB_WRITE = 0.20;
	public const float WEIGHT_MEMORY = 0.20;

	/**
	 * Calculates an overall benchmark score by aggregating sub-scores from individual tests.
	 *
	 * @param array $results        The results array from AdminBenchmark, keyed by test ID.
	 * @param array $config         The full configuration array (e.g., $config['config_cpu']).
	 * @param array $selected_tests Array of test IDs that were selected and intended to run.
	 * @return int|null The calculated score (0-100) or null if no valid sub-scores.
	 */
	public function calculate(array $results, array $config, array $selected_tests): ?int {
		$weighted_sub_scores_sum = 0;
		$total_weight_of_scored_tests = 0;
		$has_valid_subscore = false;

		foreach ($selected_tests as $test_id) {
			// Skip tests that didn't run (no results entry) or had a top-level error in their result structure
			if (!isset($results[$test_id]) || !empty($results[$test_id]['error'])) {
				if (class_exists(Logger::class)) {
					Logger::log("Skipping score calculation for test '$test_id' due to missing results or reported error.", 'info', __CLASS__, __METHOD__);
				}
				continue;
			}

			$test_instance = $this->testRegistry->get_test_instance($test_id);

			if (!$test_instance instanceof BaseBenchmarkTest) {
				if (class_exists(Logger::class)) {
					Logger::log("Could not get a valid test instance for test ID '$test_id'. Skipping score calculation.", 'warning', __CLASS__, __METHOD__);
				}
				continue;
			}

			if (!method_exists($test_instance, 'calculateScore')) {
				if (class_exists(Logger::class)) {
					Logger::log("Test class for '$test_id' does not implement calculateScore(). Skipping.", 'warning', __CLASS__, __METHOD__);
				}
				continue;
			}

			try {
				$score_details = $test_instance->calculateScore($results[$test_id], $config);

				if (is_array($score_details) && isset($score_details['sub_score'], $score_details['weight'])) {
					$sub_score = (float) $score_details['sub_score'];
					$weight = (float) $score_details['weight'];

					if ($weight > 0) { // Only consider tests with a defined positive weight
						$weighted_sub_scores_sum += $sub_score * $weight;
						$total_weight_of_scored_tests += $weight;
						$has_valid_subscore = true;
					}
				} else {
					if (class_exists(Logger::class)) {
						Logger::log("Invalid score details returned by test '$test_id'. Skipping.", 'info', __CLASS__, __METHOD__);
					}
				}
			} catch (\Throwable $e) { // Catch any error during individual score calculation
				if (class_exists(Logger::class)) {
					Logger::log("Error calculating score for test '$test_id': " . $e->getMessage(), 'error', __CLASS__, __METHOD__);
				}
			}
		} // End foreach

		// Calculate final weighted average score
		if ($has_valid_subscore && $total_weight_of_scored_tests > 0) {
			$final_score = round($weighted_sub_scores_sum / $total_weight_of_scored_tests);
			return max(0, min(100, $final_score)); // Clamp between 0 and 100
		}

		if (class_exists(Logger::class)) {
			Logger::log("Final score could not be calculated due to no valid sub-scores.", 'info', __CLASS__, __METHOD__);
		}
		return null; // Return null if no valid sub-scores or total weight is zero
	}

	/**
	 * Calculates an overall benchmark score.
	 *
	 * @param array $results        The results array containing timings, etc. for run tests.
	 * @param array $config         The configuration array containing iterations, etc.
	 * @param array $selected_tests Array of test IDs that were selected and intended to run.
	 * @return int|null The calculated score (0-100) or null if insufficient data or errors occurred in all included tests.
	 */
	public function calculate_old(array $results, array $config, array $selected_tests): ?int {
		$sub_scores = [];
		$total_weight = 0;
		$has_valid_subscore = false;

		// Iterate through selected tests and calculate sub-scores
		foreach ($selected_tests as $test_id) {
			// Skip tests that didn't run or encountered errors
			if (!isset($results[$test_id]) || !empty($results[$test_id]['error'])) {
				continue;
			}

			// Get execution time
			$time = isset($results[$test_id]['time']) ? (float) $results[$test_id]['time'] : -1;
			if ($time < 0) {
				continue; // Invalid time value
			}

			// Initialize test-specific variables
			$target_time = null;
			$weight = 0;
			$sub_score = 0;

			// Dynamically retrieve the test class instance from TestRegistry
			$test = TestRegistry::get_test_instance($test_id); // Assume `get_test_instance` returns the test class

			if (!$test) {
				Logger::log("BenchmarkTest with ID $test_id not found.", E_USER_WARNING, __CLASS__, __METHOD__);
				continue;
			}

			// Delegate score calculation to the test class
			//@TODO: This is the refactored version. Make it work.
			//try {
			//	$score_details = $test->calculateScore($results[$test_id], $config); // Test handles its own logic
			//	if (isset($score_details['sub_score'], $score_details['weight'])) {
			//		$sub_scores[] = $score_details['sub_score'] * $score_details['weight'];
			//		$total_weight += $score_details['weight'];
			//		$has_valid_subscore = true;
			//	}
			//} catch (\Exception $e) {
			//	Logger::log("Error calculating score for test $test_id: " . $e->getMessage(), E_USER_WARNING, __CLASS__, __METHOD__);
			//}


			// Handle each test type based on test IDs
			// @TODO: Refactor this, as right now, we are breaking the dynamic intention of BenchmarkTest's. Each BenchmarkTest should have a calculateScore() method, used here.
			switch ($test_id) {
				case 'cpu':
					$iterations = (int) ($config['config_cpu'] ?? 0);

					[$target_time, $weight] = BenchmarkAnalyzer\AnalyzeTest::calculateTargetTimeWeight(
						CPU::TARGET_CPU_S_PER_M_ITER, // Ugly hack while refactoring
						$iterations,
						CPU::WEIGHT_CPU, // Ugly hack while refactoring

					);
				break;

				case 'file_io':
					$ops = (int) ($config['config_file_io'] ?? 0) * 2;

					[$target_time, $weight] = BenchmarkAnalyzer\AnalyzeTest::calculateTargetTimeWeight(
					self::TARGET_FILE_IO_S_PER_1K_OPS,
						$ops,
					self::WEIGHT_FILE_IO
					);
				break;

				case 'db_read':
					$queries = (int) ($config['config_db_read'] ?? 0);
					$queries_executed = $results[$test_id]['queries_executed'] ?? $queries * 2;

					if ($queries_executed > 0 && self::TARGET_DB_READ_S_PER_1K_QUERIES > 0) {
						[$target_time, $weight] = BenchmarkAnalyzer\AnalyzeTest::calculateTargetTimeWeight(
							self::TARGET_DB_READ_S_PER_1K_QUERIES,
							$queries,
							self::WEIGHT_DB_READ
						);
					}
				break;

				case 'db_write':
					$cycles = (int) ($config['config_db_write'] ?? 0);
					$ops_executed = $results[$test_id]['operations'] ?? $cycles * 3;

					if ($ops_executed > 0 && self::TARGET_DB_WRITE_S_PER_1K_OPS > 0) {
						[$target_time, $weight] = BenchmarkAnalyzer\AnalyzeTest::calculateTargetTimeWeight(
							self::TARGET_DB_WRITE_S_PER_1K_OPS,
							$ops_executed,
							self::WEIGHT_DB_WRITE
						);
					}
				break;

				case 'memory':
					$config_memory = (int) ( $config['config_memory'] ?? 0 );
					$memory_used   = isset( $results[ $test_id ]['memory_used'] )
						? (float) $results[ $test_id ]['memory_used']
						: - 1;

					if ( $memory_used < 0 ) {
						Logger::log('Invalid memory used value: ' . $memory_used, E_USER_WARNING, __CLASS__, __METHOD__);
					}

					// Assuming we have a target and weight constants for memory usage
					if ( $config_memory > 0 ) {
						[ $target_time, $weight ] = BenchmarkAnalyzer\AnalyzeTest::calculateTargetTimeWeight(
							self::TARGET_MEMORY_MB,
							$config_memory,
							self::WEIGHT_MEMORY
						);
					}
				break;
			}

			// Perform sub-score calculation only if valid target and weight are provided
			if ($target_time !== null && $target_time > 0 && $weight > 0) {
				// Score = 100 * (1 - ActualTime / TargetTime)
				$sub_score = max(0, 100 * (1 - ($time / $target_time)));
				$sub_scores[] = $sub_score * $weight;
				$total_weight += $weight;
				$has_valid_subscore = true;
			}
		}

		// Calculate final weighted score
		if ($has_valid_subscore && $total_weight > 0) {
			$total_score = array_sum($sub_scores);

			// Normalize score based on the weight of tests actually run and scored
			$final_score = round(($total_score / $total_weight));

			// Clamp between 0 and 100
			return max(0, min(100, $final_score));
		}

		return null; // Return null if no valid sub-scores
	}
}