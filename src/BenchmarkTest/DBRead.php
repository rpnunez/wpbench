<?php
namespace WPBench\BenchmarkTest;

// Exit if accessed directly
use WPBench\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Database Read Benchmark Test.
 */
class DBRead implements BaseBenchmarkTest {

	// --- Scoring Parameters for DB Read Test ---
	public const float TARGET_S_PER_1K_QUERIES = 0.2; // Target seconds per 1000 queries
	public const float SCORE_WEIGHT = 0.30;          // Weight for this test in overall score


	/**
	 * Retrieves the singleton instance of the DBRead class.
	 *
	 * @return DBRead|null The singleton instance of the DBRead class, or null if an instance is not created.
	 */
	public function getInstance(): ?DBRead {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Get descriptive information about the DB Read test.
	 * @return array Test details.
	 */
	public function getInfo() : array {
		return [
			'id'            => 'db_read',
			'name'          => __('DB Read Test', 'wpbench'),
			'description'   => __('Executes multiple SELECT queries against WP tables.', 'wpbench'),
			'config_label'  => __('DB Read Iterations (each runs 2 queries)', 'wpbench'), // Clarified label
			'config_unit'   => __('iterations', 'wpbench'),
			'default_value' => 250,
			'min_value'     => 10,
			'max_value'     => 5000,
			// 'instance' key removed
		];
	}

	private function buildResult( float $time, int $queriesExecuted, int $rowsFetched, ?string $error ): array {
		return [
			'time' => $time,
			'queries_executed' => $queriesExecuted,
			'rows_fetched' => $rowsFetched,
			'error' => $error,
		];
	}

    /**
     * Run the Database Read benchmark test.
     *
     * @param mixed $value Number of SELECT queries to execute.
     *
     * @return array Results including 'time', 'queries_executed', 'rows_fetched', 'error'.
     */
	public function run( mixed $value ): array {
		global $wpdb;

		$iterations = absint($value);

		if ( $iterations <= 0 ) {
			return $this->buildResult(0, 0, 0, 'Invalid iteration count.');
		}

		$startTime = microtime(true);
		$errorMessages = [];
		$rowsFetched = 0;
		$queriesExecuted = 0;

		// Initialize database settings and retrieve max post ID
		$originalShowErrors = $this->initializeDatabaseSettings($wpdb);
		$maxPostId = $this->fetchMaxPostId($wpdb, $errorMessages);

		try {
			for ( $i = 0; $i < $iterations; $i++ ) {
				// Execute Option Query
				$result = $this->executeOptionQuery($wpdb, 'blogname', $errorMessages);
				$queriesExecuted++;

				if ( $result !== null ) {
					$rowsFetched++;
				}

				// Execute Post Query
				$postTitle = $this->executePostTitleQuery($wpdb, $maxPostId, $errorMessages);
				$queriesExecuted++;

				if ( $postTitle !== null ) {
					$rowsFetched++;
				}
			}
		} catch (\Throwable $exception) { // Catch any throwable (PHP 7+)
			$errorMessages[] = 'Critical error during DBRead run: ' . $exception->getMessage();

			Logger::log('Critical error in DBRead::run - ' . $exception->getMessage(), 'critical', __CLASS__, __METHOD__);
		} finally {
			$this->restoreDatabaseSettings($wpdb, $originalShowErrors);
		}

		return $this->buildResult(
			round(microtime(true) - $startTime, 4),
			$queriesExecuted,
			$rowsFetched,
			empty($errorMessages) ? null : implode('; ', $errorMessages)
		);
	}

	/**
	 * Calculates the sub-score and weight for the DB Read test.
	 *
	 * @param array $test_run_results The specific results array from this test's run() method.
	 * @param array $full_config      The full benchmark configuration array.
	 *
	 * @return array|null An associative array with keys 'sub_score' (0-100) and 'weight' (0.0-1.0),
	 * or null if score cannot be calculated.
	 */
	public function calculateScore(array $test_run_results, array $full_config) : ?array {
		// $config_iterations = (int) ($full_config['config_db_read'] ?? 0); // Iterations from config
		$queries_executed = isset($test_run_results['queries_executed']) ? (int) $test_run_results['queries_executed'] : 0;
		$time = isset($test_run_results['time']) ? (float) $test_run_results['time'] : -1;

		if ($queries_executed <= 0 || $time < 0 || self::TARGET_S_PER_1K_QUERIES <= 0) {
			return ['sub_score' => 0, 'weight' => 0]; // Cannot calculate score with invalid inputs
		}

		// Calculate the target time for the actual number of queries executed
		$target_time_for_run = self::TARGET_S_PER_1K_QUERIES * ($queries_executed / 1000.0);

		if ($target_time_for_run <= 0) { // Avoid division by zero or issues with very few queries
			return ['sub_score' => 0, 'weight' => self::SCORE_WEIGHT];
		}

		// Score formula:
		// Gives 50 if time = target_time_for_run
		// Gives 100 if time approaches 0
		// Gives 0 if time = 2 * target_time_for_run or more
		$sub_score = max(0, 100 * (1 - ($time / (2 * $target_time_for_run)) ) );

		return ['sub_score' => round($sub_score), 'weight' => self::SCORE_WEIGHT];
	}

	private function initializeDatabaseSettings( \wpdb $wpdb ): bool { // Type hint $wpdb
		$originalShowErrors = $wpdb->show_errors;
		$wpdb->show_errors(true);
		return $originalShowErrors;
	}

	private function restoreDatabaseSettings( \wpdb $wpdb, bool $originalShowErrors ): void { // Type hint $wpdb
		$wpdb->show_errors($originalShowErrors);
	}

	private function fetchMaxPostId( \wpdb $wpdb, array &$errorMessages ): int { // Type hint $wpdb
		$maxPostId = (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}"); // Use {$wpdb->posts}
		if ( $wpdb->last_error ) {
			$errorMessages[] = 'DB Error (Max Post ID Query): ' . $wpdb->last_error;
			return 1;
		}
		return max(1, $maxPostId);
	}

	private function executeOptionQuery( \wpdb $wpdb, string $optionName, array &$errorMessages ): ?string { // Type hint $wpdb
		$result = $wpdb->get_var(
			$wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $optionName) // Use {$wpdb->options}
		);

		if ( $wpdb->last_error ) {
			$errorMessages[] = 'DB Error (Option Query for ' . $optionName . '): ' . $wpdb->last_error;
		}

		return $result; // Can be null if option doesn't exist or on error
	}

	private function executePostTitleQuery( \wpdb $wpdb, int $maxPostId, array &$errorMessages ): ?string { // Type hint $wpdb
		$postId = ($maxPostId > 0) ? rand(1, $maxPostId) : 0; // Handle maxPostId being 0 or less
		$result = null;

		if ($postId > 0) {
			$result = $wpdb->get_var(
				$wpdb->prepare("SELECT post_title FROM {$wpdb->posts} WHERE ID = %d AND post_status = 'publish' LIMIT 1", $postId) // Use {$wpdb->posts}
			);

			if ( $wpdb->last_error ) {
				$errorMessages[] = 'DB Error (Post Query for ID ' . $postId . '): ' . $wpdb->last_error;
			}
		} else {
			// Not necessarily an error, but no query was run if no posts exist.
			// $errorMessages[] = 'DB Info (Post Query): No posts exist to query.';
		}

		return $result; // Can be null if post doesn't exist, not publish, or on error
	}

    public function run_old( $value ) : array {
	    global $wpdb;

        $iterations = absint($value);

		if ( $iterations <= 0 ) {
            return [
				'time' => 0,
				'queries_executed' => 0,
				'rows_fetched' => 0,
				'error' => 'Invalid iteration count.'
            ];
        }

        $start = microtime(true);
        $rows_fetched = 0;
        $queries_executed = 0;
        $error = null;
        $original_show_errors = $wpdb->show_errors; // Store original state
        $wpdb->show_errors(true); // Turn on error display for debugging within test

        try {
			// Find max post ID once to avoid querying non-existent posts too often
			$max_post_id = (int) $wpdb->get_var("SELECT MAX(ID) FROM $wpdb->posts");

			if ($wpdb->last_error) {
				throw new \Exception("DB Error (Max Post ID Query): " . $wpdb->last_error);
			}

			$max_post_id = max(1, $max_post_id); // Ensure at least 1

            for ($i = 0; $i < $iterations; $i++) {
				// Query 1: Simple option query
				$option_name = 'blogname';
				$result = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option_name ) );
				$queries_executed++;

				if ($wpdb->last_error) {
					throw new \Exception("DB Error (Option Query): " . $wpdb->last_error);
				}

				if ($result !== null) {
					$rows_fetched++; // Count as 1 "row" conceptually for get_var
				}

				// Query 2: Query a random existing post title
				$post_id_to_query = rand(1, $max_post_id);
				$post_title = $wpdb->get_var( $wpdb->prepare("SELECT post_title FROM $wpdb->posts WHERE ID = %d AND post_status = 'publish' LIMIT 1", $post_id_to_query));
				$queries_executed++;

				if ($wpdb->last_error) {
					throw new \Exception("DB Error (Post Query): " . $wpdb->last_error);
				}

				if ($post_title !== null) {
					$rows_fetched++; // Count as 1 "row"
				}
            }
        } catch (\Exception $e) {
			$error = $e->getMessage();
			Logger::log('Error running DB Read test: ' . $error, E_USER_WARNING, __CLASS__, __METHOD__);
        } finally {
			$wpdb->show_errors($original_show_errors); // Restore original error display state
        }

        return [
            'time' => round(microtime(true) - $start, 4),
            'queries_executed' => $queries_executed,
            'rows_fetched' => $rows_fetched,
            'error' => $error
        ];
    }

}