<?php

namespace WPBench;

use WP_Post;

class BenchmarkProfilePost {
	public readonly int $postId;

	public WP_Post $post;

	/**
	 * Constructor.
	 *
	 * @param int $postId The post ID for the benchmark profile post.
	 */
	public function __construct(int $postId) {
		if ($postId <= 0 || get_post_type($postId) !== BenchmarkProfileAdmin::POST_TYPE) {
			wp_send_json_error("Invalid BenchmarkProfile Post ID: {$postId}", 400);
		}

		$this->postId = $postId;

		if (!$this->post = get_post($postId)) {
			Logger::log("Could not find BenchmarkProfile Post with ID: {$postId}");
		}
	}

	/**
	 * Get the saved general configuration.
	 *
	 * @return array|null The configuration if it exists, or null if it doesn't.
	 */
	public function getTestInfo($testId): ?array {
		return get_post_meta($this->postId, BenchmarkProfileAdmin::META_CONFIG_PREFIX . $testId, true) ?: [];
	}

	/**
	 * Get the saved selected tests for this benchmark profile.
	 *
	 * @return array|null The selected tests if they exist, or null if they don't.
	 */
	public function getSelectedTests(): ?array {
		return get_post_meta($this->postId, AdminBenchmark::META_SELECTED_TESTS, true) ?: null;
	}

	/**
	 * Get the state of the benchmark profile, including selected tests, desired plugins, and configuration data.
	 *
	 * @return array An associative array containing the profile's selected tests, desired plugins, and config data.
	 */
	public function getProfileState(): array {
		// Fetch selected tests
		$selectedTests = $this->getSelectedTests();

		// Fetch all meta data for the profile
		$profileMetaData = $this->getProfileMetaFields();

		// Fetch the desired state
		$desiredPlugins = new PluginState()->getDesiredState($this->postId);

		// Extract and process configuration data
		$configData = [];

		foreach ($profileMetaData as $metaKey => $metaValues) {
			if (str_starts_with($metaKey, BenchmarkProfileAdmin::META_CONFIG_PREFIX)) {
				$testId = substr($metaKey, strlen(BenchmarkProfileAdmin::META_CONFIG_PREFIX));
				$configData['config_' . $testId] = $metaValues[0] ?? null;
			}
		}

		return [
			'profile_id' => $this->post->ID,
			'profile_title' => $this->post->post_title,
			'selected_tests' => $selectedTests,
			'desired_plugins' => $desiredPlugins,
			'config' => $configData,
		];
	}

	/**
	 * Fetch all meta fields for the benchmark profile.
	 *
	 * @return array An associative array of all meta keys and their values for the profile.
	 */
	public function getProfileMetaFields(): array {
		return get_post_meta($this->postId);
	}

	/**
	 * Get the profile ID used during the run.
	 *
	 * @return int|null The profile ID used during the benchmark run, or null if not set.
	 */
	public function getProfileIdUsed(): ?int {
		return get_post_meta($this->postId, AdminBenchmark::META_PROFILE_ID_USED, true) ?: null;
	}

	/**
	 * Get the profile state during the run.
	 *
	 * @return array|null The saved profile state during the benchmark run, or null if not available.
	 */
	public function getProfileStateDuringRun(): ?array {
		return get_post_meta($this->postId, AdminBenchmark::META_PROFILE_STATE_DURING_RUN, true) ?: null;
	}
}