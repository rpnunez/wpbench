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
    private int $scoreMinimum = 20;


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
		$queries_executed = isset($test_run_results['queries_executed']) ? (int) $test_run_results['queries_executed'] : 0;
		$time = isset($test_run_results['time']) ? (float) $test_run_results['time'] : -1;

		if ($queries_executed <= 0 || $time < 0 || self::TARGET_S_PER_1K_QUERIES <= 0) {
			return ['sub_score' => 0, 'weight' => 0]; // Cannot calculate score with invalid inputs
		}

		// Calculate the target time for the actual number of queries executed
		$target_time_for_run = self::TARGET_S_PER_1K_QUERIES * ($queries_executed / 1000.0);

		if ($target_time_for_run <= 0) {
			return ['sub_score' => 0, 'weight' => self::SCORE_WEIGHT];
		}

        $score = 100 * ($target_time_for_run / $time);

        if ($score < $this->scoreMinimum) {
            $score = $this->scoreMinimum;
        }

		return ['sub_score' => round(min(100, $score)), 'weight' => self::SCORE_WEIGHT];
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
		}

		return $result;
	}
}
