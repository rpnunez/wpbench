<?php
namespace WPBench;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Calculates benchmark scores based on results and configuration.
 */
class BenchmarkScore {

    // --- Score Calculation Constants ---
    public const float TARGET_CPU_S_PER_M_ITER = 0.5;
    public const float TARGET_FILE_IO_S_PER_1K_OPS = 0.1;
    public const float TARGET_DB_READ_S_PER_1K_QUERIES = 0.2;
    public const float TARGET_DB_WRITE_S_PER_1K_OPS = 0.3;
	public const float TARGET_MEMORY_MB = 100;

    // --- Weights (Should ideally add up to 1.0) ---
    public const float WEIGHT_CPU = 0.30;
    public const float WEIGHT_FILE_IO = 0.10;
    public const float WEIGHT_DB_READ = 0.20;
    public const float WEIGHT_DB_WRITE = 0.20;
	public const float WEIGHT_MEMORY = 0.20;

	/**
	 * Calculates an overall benchmark score.
	 *
	 * @param array $results        The results array containing timings, etc. for run tests.
	 * @param array $config         The configuration array containing iterations, etc.
	 * @param array $selected_tests Array of test IDs that were selected and intended to run.
	 * @return int|null The calculated score (0-100) or null if insufficient data or errors occurred in all included tests.
	 */
	public function calculate(array $results, array $config, array $selected_tests): ?int {
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

			// Handle each test type based on test IDs
			// @TODO: Refactor this, as right now, we are breaking the dynamic intention of BenchmarkTest's. Each BenchmarkTest should have a calculateScore() method, used here.
			switch ($test_id) {
				case 'cpu':
					$iterations = (int) ($config['config_cpu'] ?? 0);

					[$target_time, $weight] = BenchmarkAnalyzer\AnalyzeTest::calculateTargetTimeWeight(
						self::TARGET_CPU_S_PER_M_ITER,
						$iterations,
						self::WEIGHT_CPU,

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