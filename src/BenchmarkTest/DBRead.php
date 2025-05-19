<?php
namespace WPBench\BenchmarkTest;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Database Read Benchmark Test.
 */
class DBRead implements BaseBenchmarkTest {

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
			'config_label'  => __('DB Read Queries', 'wpbench'),
			'config_unit'   => __('queries', 'wpbench'),
			'default_value' => 250,
			'min_value'     => 10,
			'max_value'     => 5000,
			'instance'      => $this->getInstance()
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
		} catch (\Exception $exception) {
			$errorMessages[] = 'Critical error: ' . $exception->getMessage();
		} finally {
			// Restore original database settings
			$this->restoreDatabaseSettings($wpdb, $originalShowErrors);
		}

		return $this->buildResult(
			round(microtime(true) - $startTime, 4),
			$queriesExecuted,
			$rowsFetched,
			empty($errorMessages) ? null : implode('; ', $errorMessages)
		);
	}

	private function initializeDatabaseSettings( $wpdb ): bool {
		$originalShowErrors = $wpdb->show_errors;
		$wpdb->show_errors(true); // Enable errors for debugging
		return $originalShowErrors;
	}

	private function restoreDatabaseSettings( $wpdb, bool $originalShowErrors ): void {
		$wpdb->show_errors($originalShowErrors);
	}

	private function fetchMaxPostId( $wpdb, array &$errorMessages ): int {
		$maxPostId = (int) $wpdb->get_var("SELECT MAX(ID) FROM $wpdb->posts");
		if ( $wpdb->last_error ) {
			$errorMessages[] = 'DB Error (Max Post ID Query): ' . $wpdb->last_error;
			return 1; // Fallback value
		}
		return max(1, $maxPostId); // Ensure at least 1
	}

	private function executeOptionQuery( $wpdb, string $optionName, array &$errorMessages ): ?string {
		$result = $wpdb->get_var(
			$wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $optionName)
		);

		if ( $wpdb->last_error ) {
			$errorMessages[] = 'DB Error (Option Query): ' . $wpdb->last_error;
		}

		return $result;
	}

	private function executePostTitleQuery( $wpdb, int $maxPostId, array &$errorMessages ): ?string {
		$postId = rand(1, $maxPostId);
		$result = $wpdb->get_var(
			$wpdb->prepare("SELECT post_title FROM $wpdb->posts WHERE ID = %d AND post_status = 'publish' LIMIT 1", $postId)
		);

		if ( $wpdb->last_error ) {
			$errorMessages[] = 'DB Error (Post Query): ' . $wpdb->last_error;
		}

		return $result;
	}

	private function buildResult( float $time, int $queriesExecuted, int $rowsFetched, ?string $error ): array {
		return [
			'time' => $time,
			'queries_executed' => $queriesExecuted,
			'rows_fetched' => $rowsFetched,
			'error' => $error,
		];
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