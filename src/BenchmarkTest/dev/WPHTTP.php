<?php
namespace src\BenchmarkTest\dev;

use WPBench\BenchmarkTest\BaseBenchmarkTest;
use WPBench\BenchmarkTest\CPU;
use WPBench\Guards\ResourceGuard;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPHTTP implements BaseBenchmarkTest {

	/**
	 * Retrieves the singleton instance of the WPHTTP class.
	 *
	 * @return CPU|null The singleton instance of the WPHTTP class, or null if an instance is not created.
	 */
	public function getInstance(): ?WPHTTP {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	// Public test endpoint that accepts POST requests and returns JSON.
	// Alternatives: requestbin.com, or a local script if you don't want external dependency.
	const POST_TEST_URL = 'https://httpbin.org/post';
	// Another option for POST that is internal (loopback):
	// const POST_LOOPBACK_ACTION = 'wpbench_http_post_test_action';

	public function getInfo() : array {
		return [
			'id'            => 'wp_http_api',
			'name'          => __('WordPress HTTP API Test', 'wpbench'),
			'description'   => __('Performs a configurable number of wp_remote_get (to site_url) and wp_remote_post (to an external test endpoint or loopback) requests.', 'wpbench'),
			'config_label'  => __('Number of GET/POST Cycles', 'wpbench'),
			'config_unit'   => __('cycles', 'wpbench'),
			'default_value' => 10, // HTTP requests are slower
			'min_value'     => 1,
			'max_value'     => 50,  // Keep this relatively low due to network latency
		];
	}

	public function run( $value ) : array {
		$cycles = absint($value);
		if ($cycles <= 0) {
			return ['time' => 0, 'gets_ok' => 0, 'posts_ok' => 0, 'errors' => 0, 'error' => 'Invalid cycle count.'];
		}

		$start_time = microtime(true);
		$error_message = null;
		$get_requests_ok = 0;
		$post_requests_ok = 0;
		$total_request_errors = 0;
		$error_details = [];

		// For GET, use a loopback URL (less prone to external network issues)
		$get_url = add_query_arg(['wpbench_http_get_test' => uniqid()], site_url('/'));

		// For POST, we use an external service or could set up a loopback AJAX action
		$post_url = self::POST_TEST_URL;
		// // Example for loopback POST target setup (would require adding an AJAX action in PHP)
		// $post_url = admin_url('admin-ajax.php');
		// $post_body_base = ['action' => self::POST_LOOPBACK_ACTION, 'wpbench_test_data' => 'example'];

		try {
			ResourceGuard::checkIfMaxIterationsReached($cycles, 100); // Max 100 cycles

			for ($i = 0; $i < $cycles; $i++) {
				// --- Perform GET Request ---
				$get_args = ['timeout' => 10]; // 10 second timeout
				$response_get = wp_remote_get($get_url, $get_args);
				if (is_wp_error($response_get)) {
					$total_request_errors++;
					$error_details[] = "GET Error: " . $response_get->get_error_message();
				} else {
					$status_code_get = wp_remote_retrieve_response_code($response_get);
					if ($status_code_get == 200) {
						$get_requests_ok++;
					} else {
						$total_request_errors++;
						$error_details[] = "GET Status Code: " . $status_code_get;
					}
				}

				// --- Perform POST Request ---
				$post_args = [
					'timeout' => 15, // Slightly longer for POST
					'body'    => ['wpbench_test' => 'value' . $i, 'iteration' => $i, 'data' => bin2hex(random_bytes(10))],
				];
				$response_post = wp_remote_post($post_url, $post_args);
				if (is_wp_error($response_post)) {
					$total_request_errors++;
					$error_details[] = "POST Error: " . $response_post->get_error_message();
				} else {
					$status_code_post = wp_remote_retrieve_response_code($response_post);
					if ($status_code_post == 200) {
						$post_requests_ok++;
					} else {
						$total_request_errors++;
						$error_details[] = "POST Status Code: " . $status_code_post;
					}
				}
			}

		} catch (\Throwable $e) {
			$error_message = get_class($e) . ': ' . $e->getMessage();
		}

		$end_time = microtime(true);

		if (!empty($error_details) && $error_message === null) {
			$error_message = "Completed with " . $total_request_errors . " request error(s). First few: " . implode('; ', array_slice($error_details, 0, 2));
		}


		return [
			'time'         => round($end_time - $start_time, 4),
			'cycles'       => $cycles,
			'gets_ok'      => $get_requests_ok,
			'posts_ok'     => $post_requests_ok,
			'request_errors' => $total_request_errors,
			'error_details' => array_slice($error_details, 0, 5), // Return first 5 error details
			'error'        => $error_message, // Overall error message
		];
	}

	public function calculateScore( array $test_results, array $config ): array {
		// TODO: Implement calculateScore() method.

		return [];
	}
}