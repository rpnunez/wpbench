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

			$test_instance = TestRegistry::get_test_instance($test_id);

			if (!$test_instance instanceof BenchmarkTest\BaseBenchmarkTest) {
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
}
