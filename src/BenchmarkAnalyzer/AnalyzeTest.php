<?php

namespace src\BenchmarkAnalyzer;

class AnalyzeTest {

	/**
	 * Calculate the target time and weight.
	 *
	 * @param float $target The target database write time per 1K operations.
	 * @param int $n The total number of operations executed.
	 * @param float $weight The scaled weight value for database writes.
	 *
	 * @return array Returns an array with 'target_time' and 'weight'.
	 */
	public static function calculateTargetTimeWeight(float $target, int $n, float $weight): array {
		if ($n > 0 && $target > 0) {
			$targetTime = $target * ($n / 1000);

			return [
				'target_time' => $targetTime,
				'weight' => $weight
			];
		}

		// Return fallback values in case conditions aren't met
		return [
			'target_time' => 0.0, // Default value for target time
			'weight' => $weight   // Return the unchanged weight
		];
	}

}