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

    // --- Score Calculation Constants (EXAMPLES - ADJUST THESE!) ---
    public const float TARGET_CPU_S_PER_M_ITER = 0.5;
    public const float TARGET_FILE_IO_S_PER_1K_OPS = 0.1;
    public const float TARGET_DB_READ_S_PER_1K_QUERIES = 0.2;
    public const float TARGET_DB_WRITE_S_PER_1K_OPS = 0.3;

    // @TODO: Memory is excluded from score for now.

    // --- Weights (Should ideally add up to 1.0) ---
    public const float WEIGHT_CPU = 0.30;
    public const float WEIGHT_FILE_IO = 0.10;
    public const float WEIGHT_DB_READ = 0.30;
    public const float WEIGHT_DB_WRITE = 0.30;

    /**
     * Calculates an overall benchmark score.
     *
     * @param array $results        The results array containing timings, etc. for run tests.
     * @param array $config         The configuration array containing iterations, etc.
     * @param array $selected_tests Array of test IDs that were selected and intended to run.
     * @return int|null The calculated score (0-100) or null if insufficient data or errors occurred in all included tests.
     */
    public function calculate(array $results, array $config, array $selected_tests) : ?int {
        $sub_scores = [];
        $total_weight = 0;
        $has_valid_subscore = false;

        // Calculate sub-score for each selected test if results are valid
        foreach ($selected_tests as $test_id) {
	        // Skip tests not run or with errors
            if (!isset($results[$test_id]) || !empty($results[$test_id]['error'])) {
                continue;
            }

			// Get time
            $time = isset($results[$test_id]['time']) ? (float) $results[$test_id]['time'] : -1;

	        // Skip if time is invalid
            if ($time < 0) {
				continue;
            }

            $target_time = null;
            $weight = 0;
            $sub_score = 0;

            switch ($test_id) {
                case 'cpu':
                    $iterations = (int) ($config['config_cpu'] ?? 0);

                    if ($iterations > 0 && self::TARGET_CPU_S_PER_M_ITER > 0) {
                        $target_time = self::TARGET_CPU_S_PER_M_ITER * ($iterations / 1000000);
                        $weight = self::WEIGHT_CPU;
                    }
                break;

                case 'file_io':
                    $ops = (int) ($config['config_file_io'] ?? 0) * 2;

                    if ($ops > 0 && self::TARGET_FILE_IO_S_PER_1K_OPS > 0) {
                        $target_time = self::TARGET_FILE_IO_S_PER_1K_OPS * ($ops / 1000);
                        $weight = self::WEIGHT_FILE_IO;
                    }
                break;

                case 'db_read':
                    $queries = (int) ($config['config_db_read'] ?? 0);
                    $queries_executed = $results['db_read']['queries_executed'] ?? $queries * 2;

                    if ($queries_executed > 0 && self::TARGET_DB_READ_S_PER_1K_QUERIES > 0) {
                        $target_time = self::TARGET_DB_READ_S_PER_1K_QUERIES * ($queries_executed / 1000);
                        $weight = self::WEIGHT_DB_READ;
                    }
                break;

                case 'db_write':
                    $cycles = (int) ($config['config_db_write'] ?? 0);
                    $ops_executed = $results['db_write']['operations'] ?? $cycles * 3;

                    if ($ops_executed > 0 && self::TARGET_DB_WRITE_S_PER_1K_OPS > 0) {
                        $target_time = self::TARGET_DB_WRITE_S_PER_1K_OPS * ($ops_executed / 1000);
                        $weight = self::WEIGHT_DB_WRITE;
                    }
                break;
            }

            if ($target_time !== null && $target_time > 0 && $weight > 0) {
				// Score = 100 * (1 - ActualTime / TargetTime)
				$sub_score = max(0, 100 * (1 - ($time / $target_time)));
				$sub_scores[$test_id] = $sub_score * $weight;
				$total_weight += $weight;
				$has_valid_subscore = true;
            }
        } // end foreach

		// Calculate final weighted score
		if ($has_valid_subscore && $total_weight > 0) {
			$total_score = array_sum($sub_scores);

			// Normalize score based on the weight of tests actually run and scored
			$final_score = round(($total_score / $total_weight));

			return max(0, min(100, $final_score)); // Clamp between 0 and 100
		}

        return null; // Not enough valid data to calculate score
    }
}