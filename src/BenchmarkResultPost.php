<?php

namespace WPBench;
use WP_Post;

class BenchmarkResultPost {
	public readonly int $postId;

	public WP_Post $post;
	private Storage\ArrayCacheStorage $cache;

	/**
	 * BenchmarkResultPost constructor.
	 *
	 * @param int $postId The post ID to store the benchmark result in WordPress metadata.
	 */
	public function __construct(int|array|WP_Post $postOrPostId, $createPost = false) {
		if ( $createPost === false && ( is_a($postOrPostId, WP_Post::class ) || is_int( $postOrPostId ) ) ) {
			$postId = $postOrPostId->ID ?? $postOrPostId;

			if ( $postId <= 0 || get_post_type( $postOrPostId ) !== AdminBenchmark::POST_TYPE ) {
				wp_send_json_error( "Invalid parameter passed to BenchmarkResultPost. You can only pass a Post ID, an existing WP_Post object, or an array of WP_Post key/value pairs, along with create: true.", 400 );
			}
		} else {
			if ( is_array($postOrPostId) ) {
				$postData = $postOrPostId;
				$postId = wp_insert_post($postData);
			}

			if ( is_wp_error($postId) || $postId === 0 ) {
				wp_send_json_error([
					'message' => __('Error creating benchmark result post:', 'wpbench') . (is_wp_error($postId) ? ' ' . $postId->get_error_message() : '')
				], 500);
			}

			if ( is_wp_error( $postId ) || $postId === 0 ) {
				wp_send_json_error( [ 'message' => __( 'Error creating benchmark result post:', 'wpbench' ) . ( is_wp_error( $postId ) ? ' ' . $postId->get_error_message() : '' ) ],
					500 );
			}

			$this->postId = $postId;
		}

		if ( ! ( $this->post = get_post($postId) ) ) {
			Logger::log("Could not find BenchmarkResult Post with ID: {$postId}");
		}

		// Set up the cache
		// @TODO: make it configurable, so one can choose which driver to use
		$this->cache = new Storage\ArrayCacheStorage();
	}

	/**
	 * Save general configuration.
	 *
	 * @param array $config Associative array of configuration settings.
	 */
	public function saveConfig(array $config): void {
		update_post_meta($this->postId, AdminBenchmark::META_CONFIG, $config);
	}

	public function getConfig(): array {
		$self = $this;

		return $this->cache->remember('config', function() use ($self) {
			return get_post_meta($self->postId, AdminBenchmark::META_CONFIG, true) ?: [];
		}, WPBENCH_CACHE_DAY);
	}

	/**
	 * Get the list of plugins that were active during the benchmark run.
	 *
	 * @return array The list of active plugins, or an empty array if none are saved or the value is not stored as an array.
	 */
	public function getRuntimePluginsActive(): array {
		$self = $this;

		return $this->cache->remember('runtime_plugins_active', function () use ($self) {
			return get_post_meta($self->postId, PluginState::ACTUAL_PLUGINS_META_KEY, true) ?: [];
		}, WPBENCH_CACHE_DAY);
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
		$self = $this;

		return $this->cache->remember('selected_tests', function() use ($self) {
			return get_post_meta($self->postId, AdminBenchmark::META_SELECTED_TESTS, true) ?: null;
		}, WPBENCH_CACHE_DAY);
	}

	/**
	 * Save benchmark score.
	 *
	 * @param int $score The benchmark score.
	 */
	public function saveScore(int $score): bool|int {
		return update_post_meta($this->postId, AdminBenchmark::META_SCORE, $score);
	}

	/**
	 * Get the saved benchmark score.
	 *
	 * @return int|null The score if it exists, or null if it doesn't.
	 */
	public function getScore(): ?int {
		$self = $this;

		return $this->cache->remember('results', function () use ($self) {
			$score = get_post_meta($self->postId, AdminBenchmark::META_SCORE, true);

			return is_numeric($score) ? (int) $score : null;
		}, WPBENCH_CACHE_DAY);
	}

	/**
	 * Save benchmark results.
	 *
	 * @param array $results Associative array of the test results.
	 */
	public function saveResults(array $results): bool|int {
		return update_post_meta($this->postId, AdminBenchmark::META_RESULTS, $results);
	}

	/**
	 * Get the saved benchmark results.
	 *
	 * @return array|null The results if they exist, or null if they don't.
	 */
	public function getResults(): ?array {
		$self = $this;

		return $this->cache->remember('results', function () use ($self) {
			return get_post_meta( $self->postId, AdminBenchmark::META_RESULTS, true ) ?: null;
		}, WPBENCH_CACHE_DAY);
	}

	/**
	 * Get the
	 *
	 * @return array|null The results if they exist, or null if they don't.
	 */
	public function getErrors(): ?array {
		//return get_post_meta($this->postId, AdminBenchmark::META_RESULTS, true)['errors'] ?: null;

		$results = $this->getResults();
		$errors = [];

		if (isset($results['errors']) && is_array($results['errors']) && count($results['errors']) > 0) {
			$errors = $results['errors'];
		}

		return $errors;
	}

	/**
	 * Retrieve all relevant benchmark data for the current post.
	 *
	 * @param PluginState $pluginState An instance of the PluginState class.
	 *
	 * @return array An associative array containing all relevant benchmark data.
	 */
	public function getBenchmarkData(): array {
		$profileIdUsed = $this->getProfileIdUsed();
		$pluginState = new PluginState();

		// Assemble the benchmark data
		return [
			'post' => $this->post,
			'config' => $this->getConfig(),
			'results' => $this->getResults(),
			'errors' => $this->getErrors(),
			'selected_tests' => $this->getSelectedTests(),
			'profile_title' => $profileIdUsed ? get_the_title($profileIdUsed) : null,
			'profile_link' => $profileIdUsed ? get_edit_post_link($profileIdUsed) : null,
			'pre_benchmark_state' => $pluginState->getPreBenchmarkState($this->postId),
			'desired_plugins' => $pluginState->getDesiredState($this->postId),
			'active_plugins_final' => $this->getRuntimePluginsActive(),
			'profile_id_used' => $profileIdUsed,
			'profile_state_during_run' => $this->getProfileStateDuringRun(),
			'score' => $this->getScore(),
		];
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
		$self = $this;

		return $this->cache->remember('profile_id_used', function () use ($self) {
			$profileId = get_post_meta($self->postId, AdminBenchmark::META_PROFILE_ID_USED, true);

			return is_numeric($profileId) ? (int) $profileId : null;
		}, WPBENCH_CACHE_DAY);
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
		$self = $this;

		return $this->cache->remember('profile_id_used', function () use ($self) {
			return get_post_meta( $self->postId, AdminBenchmark::META_PROFILE_STATE_DURING_RUN, true ) ?: null;
		}, WPBENCH_CACHE_DAY);
	}

	/**
	 * Compare this benchmark result's score with another benchmark's score.
	 *
	 * @param BenchmarkResultPost $other The other benchmark result to compare against.
	 *
	 * @return string A text summary of the comparison.
	 */
	public function compareTo( BenchmarkResultPost $other): string {
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

	/**
	 * Retrieves the benchmark profile post object.
	 *
	 * @return BenchmarkProfilePost The instance of the benchmark profile post based on the profile ID.
	 */
	public function getBenchmarkProfilePost() {
		return new BenchmarkProfilePost($this->getProfileIdUsed());
	}
}