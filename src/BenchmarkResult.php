<?php

namespace WPBench;

class BenchmarkResult {
	private int $postId;

	/**
	 * BenchmarkResult constructor.
	 *
	 * @param int $postId The post ID to store the benchmark result in WordPress metadata.
	 */
	public function __construct(int $postId) {
		$this->postId = $postId;
	}

	/**
	 * Save general configuration.
	 *
	 * @param array $config Associative array of configuration settings.
	 */
	public function saveConfig(array $config): void {
		update_post_meta($this->postId, AdminBenchmark::META_CONFIG, $config);
	}

	/**
	 * Get the saved general configuration.
	 *
	 * @return array|null The configuration if it exists, or null if it doesn't.
	 */
	public function getConfig(): ?array {
		return get_post_meta($this->postId, AdminBenchmark::META_CONFIG, true) ?: null;
	}

	/**
	 * Save selected tests.
	 *
	 * @param array $selectedTests List of selected test names or IDs.
	 */
	public function saveSelectedTests(array $selectedTests): void {
		update_post_meta($this->postId, AdminBenchmark::META_SELECTED_TESTS, $selectedTests);
	}

	/**
	 * Get the saved selected tests.
	 *
	 * @return array|null The selected tests if they exist, or null if they don't.
	 */
	public function getSelectedTests(): ?array {
		return get_post_meta($this->postId, AdminBenchmark::META_SELECTED_TESTS, true) ?: null;
	}

	/**
	 * Save benchmark score.
	 *
	 * @param int $score The benchmark score.
	 */
	public function saveScore(int $score): void {
		update_post_meta($this->postId, AdminBenchmark::META_SCORE, $score);
	}

	/**
	 * Get the saved benchmark score.
	 *
	 * @return int|null The score if it exists, or null if it doesn't.
	 */
	public function getScore(): ?int {
		$score = get_post_meta($this->postId, AdminBenchmark::META_SCORE, true);
		return is_numeric($score) ? (int) $score : null;
	}

	/**
	 * Save benchmark results.
	 *
	 * @param array $results Associative array of the test results.
	 */
	public function saveResults(array $results): void {
		update_post_meta($this->postId, AdminBenchmark::META_RESULTS, $results);
	}

	/**
	 * Get the saved benchmark results.
	 *
	 * @return array|null The results if they exist, or null if they don't.
	 */
	public function getResults(): ?array {
		return get_post_meta($this->postId, AdminBenchmark::META_RESULTS, true) ?: null;
	}

	/**
	 * Save profile ID used during the benchmark.
	 *
	 * @param int $profileId The profile ID.
	 */
	public function saveProfileIdUsed(int $profileId): void {
		update_post_meta($this->postId, AdminBenchmark::META_PROFILE_ID_USED, $profileId);
	}

	/**
	 * Get the saved profile ID used during the benchmark.
	 *
	 * @return int|null The profile ID if it exists, or null if it doesn't.
	 */
	public function getProfileIdUsed(): ?int {
		$profileId = get_post_meta($this->postId, AdminBenchmark::META_PROFILE_ID_USED, true);
		return is_numeric($profileId) ? (int) $profileId : null;
	}

	/**
	 * Save the profile state during the benchmark run.
	 *
	 * @param array|null $profileStateData The profile state data.
	 */
	public function saveProfileStateDuringRun(?array $profileStateData): void {
		update_post_meta($this->postId, AdminBenchmark::META_PROFILE_STATE_DURING_RUN, $profileStateData);
	}

	/**
	 * Get the saved profile state data during the benchmark run.
	 *
	 * @return array|null The profile state data if it exists, or null if it doesn't.
	 */
	public function getProfileStateDuringRun(): ?array {
		return get_post_meta($this->postId, AdminBenchmark::META_PROFILE_STATE_DURING_RUN, true) ?: null;
	}

	/**
	 * Compare this benchmark result's score with another benchmark's score.
	 *
	 * @param BenchmarkResult $other The other benchmark result to compare against.
	 * @return string A text summary of the comparison.
	 */
	public function compareTo(BenchmarkResult $other): string {
		$thisScore = $this->getScore();
		$otherScore = $other->getScore();

		if ($thisScore === null || $otherScore === null) {
			return "One or both benchmark scores are missing. Unable to compare.";
		}

		$difference = $thisScore - $otherScore;
		$percentDifference = ($difference / $otherScore) * 100;

		return sprintf(
			"Comparison:\n" .
			"- Score Difference: %d\n" .
			"- Percentage Difference: %.2f%%\n",
			$difference,
			$percentDifference
		);
	}

	/**
	 * Export the benchmark result to an array.
	 *
	 * @return array Associative array representation of the benchmark result.
	 */
	public function toArray(): array {
		return [
			'postId' => $this->postId,
			'config' => $this->getConfig(),
			'selectedTests' => $this->getSelectedTests(),
			'score' => $this->getScore(),
			'results' => $this->getResults(),
			'profileIdUsed' => $this->getProfileIdUsed(),
			'profileStateDuringRun' => $this->getProfileStateDuringRun(),
		];
	}

	/**
	 * Export the benchmark result to a JSON string.
	 *
	 * @return string JSON encoded representation of the benchmark result.
	 */
	public function toJson(): string {
		return json_encode($this->toArray(), JSON_PRETTY_PRINT);
	}
}