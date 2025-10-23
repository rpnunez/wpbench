<?php
namespace src\BenchmarkTest\dev;

use WP_Query;
use WPBench\BenchmarkTest\BaseBenchmarkTest;
use WPBench\Guards\ResourceGuard;

// If you want to use iteration checks inside the loop
// WordPress Query class

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPQuery implements BaseBenchmarkTest {

	/**
	 * Retrieves the singleton instance of the WPQuery class.
	 *
	 * @return WPQuery|null The singleton instance of the WPQuery class, or null if an instance is not created.
	 */
	public function getInstance(): ?WPQuery {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	public function getInfo() : array {
		return [
			'id'            => 'wp_query',
			'name'          => __('WP_Query Stress Test', 'wpbench'),
			'description'   => __('Executes a configurable number of complex WP_Query calls with varied arguments to test database query performance through WordPress\'s query layer.', 'wpbench'),
			'config_label'  => __('Number of WP_Query Executions', 'wpbench'),
			'config_unit'   => __('queries', 'wpbench'),
			'default_value' => 50,
			'min_value'     => 5,
			'max_value'     => 500, // Higher values can be very slow
		];
	}

	public function run( $value ) : array {
		$iterations = absint($value);
		if ($iterations <= 0) {
			return ['time' => 0, 'queries_executed' => 0, 'posts_found' => 0, 'error' => 'Invalid iteration count.'];
		}

		$start_time = microtime(true);
		$error_message = null;
		$total_posts_found = 0;
		$queries_executed_count = 0; // To count how many WP_Query objects were created

		// Pre-fetch some term IDs and post types to vary queries
		$post_types = get_post_types(['public' => true]);
		if (empty($post_types)) $post_types = ['post', 'page']; // Fallback
		$categories = get_terms(['taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'ids', 'number' => 10]);
		$tags = get_terms(['taxonomy' => 'post_tag', 'hide_empty' => false, 'fields' => 'ids', 'number' => 10]);

		// Store original query flags
		global $wpdb;
		$original_savequeries = $wpdb->save_queries;
		$wpdb->save_queries = false; // Ensure SAVEQUERIES is off to not skew results unless specifically testing it

		try {
			ResourceGuard::checkIfMaxIterationsReached($iterations, 2000); // Override default max if needed

			for ($i = 0; $i < $iterations; $i++) {
				$args = [
					'post_type'      => $post_types[array_rand($post_types)],
					'posts_per_page' => rand(5, 20),
					'orderby'        => 'rand', // Using rand can be slow, good for stress
					'paged'          => rand(1, 3), // Vary pagination
					'suppress_filters' => true, // Slightly faster, less interference
					'ignore_sticky_posts' => true,
					'no_found_rows' => true, // Optimization if not needing pagination totals
				];

				// Randomly add more complex queries
				if ($i % 3 === 0 && !empty($categories)) {
					$args['tax_query'] = [
						[
							'taxonomy' => 'category',
							'field'    => 'term_id',
							'terms'    => $categories[array_rand($categories)],
						],
					];
				}
				if ($i % 4 === 0 && !empty($tags)) {
					if (!isset($args['tax_query'])) $args['tax_query'] = ['relation' => 'OR'];
					$args['tax_query'][] = [
						'taxonomy' => 'post_tag',
						'field'    => 'term_id',
						'terms'    => $tags[array_rand($tags)],
					];
				}
				if ($i % 5 === 0) {
					$args['meta_query'] = [
						[
							'key'     => '_wp_page_template', // A common meta key
							'compare' => 'EXISTS',
						],
					];
				}
				if ($i % 7 === 0) {
					$args['date_query'] = [
						['year' => date('Y') - rand(0,2), 'month' => rand(1,12) ],
					];
				}


				$query = new WP_Query($args);
				$total_posts_found += $query->post_count;
				$queries_executed_count++;
				wp_reset_postdata(); // Important after custom WP_Query loops
			}

		} catch (\Throwable $e) { // Catch Exception or Error
			$error_message = get_class($e) . ': ' . $e->getMessage();
		} finally {
			// Restore original query flags
			$wpdb->save_queries = $original_savequeries;
		}

		$end_time = microtime(true);

		return [
			'time'             => round($end_time - $start_time, 4),
			'queries_executed' => $queries_executed_count,
			'posts_found'      => $total_posts_found,
			'error'            => $error_message,
		];
	}

	public function calculateScore( array $test_results, array $config ): array {
		// TODO: Implement calculateScore() method.

		return [];
	}
}