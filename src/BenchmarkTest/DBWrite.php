<?php
namespace WPBench\BenchmarkTest;

// Exit if accessed directly
use WPBench\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Database Write Benchmark Test.
 */
class DBWrite implements BaseBenchmarkTest {
    public const float TARGET_DB_WRITE_S_PER_1K_OPS = 0.3;
    public const float WEIGHT_DB_WRITE = 0.20;
    private int $scoreMinimum = 20;

	/**
	 * Retrieves the singleton instance of the DBWrite class.
	 *
	 * @return DBWrite|null The singleton instance of the DBWrite class, or null if an instance is not created.
	 */
	public function getInstance(): ?DBWrite {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Get descriptive information about the DB Write test.
	 *
	 * @return array Test details.
	 */
	public function getInfo() : array {
		return [
			'id'            => 'db_write',
			'name'          => __('DB Write Test', 'wpbench'),
			'description'   => __('Performs INSERT, UPDATE, DELETE operations on a temporary table.', 'wpbench'),
			'config_label'  => __('DB Write Operations', 'wpbench'),
			'config_unit'   => __('cycles (insert/update/delete)', 'wpbench'),
			'default_value' => 100,
			'min_value'     => 10,
			'max_value'     => 2500,
			'instance'      => $this->getInstance()
		];
	}

	private function buildResult(float $time, int $operations, int $rowsAffected, ?string $error): array {
		return [
			'time' => $time,
			'operations' => $operations,
			'rows_affected' => $rowsAffected,
			'error' => $error,
		];
	}

    /**
     * Run the Database Write benchmark test.
     * Creates, uses, and drops a temporary table.
     *
     * @param int $value Number of INSERT/UPDATE/DELETE cycles.
     * @return array Results including 'time', 'operations', 'rows_affected', 'error'.
     */
	public function run( int $value ): array {
		global $wpdb;

		$iterations = absint($value);

		if ($iterations <= 0) {
			return $this->buildResult(0, 0, 0, 'Invalid iteration count.');
		}

		$startTime = microtime(true);
		$errorMessages = [];
		$operations = 0;
		$rowsAffected = 0;
		$tableCreated = false;
		$tableName = $this->generateTemporaryTableName($wpdb);

		$originalShowErrors = $this->initializeDatabaseSettings($wpdb);

		try {
			$this->createTemporaryTable($wpdb, $tableName, $errorMessages);
			$tableCreated = true;
		} catch (\Exception $exception) {
			$errorMessages[] = $exception->getMessage();
			$iterations = 0; // Prevent operations if table creation fails
		}

		$lastId = 0;
		if ($tableCreated && $iterations > 0) {
			try {
				for ($i = 0; $i < $iterations; $i++) {
					// INSERT
					$insertResult = $this->insertRow($wpdb, $tableName, $i, $errorMessages);
					$operations++;

					if ($insertResult['success']) {
						$rowsAffected++;
						$currentId = $insertResult['id'];

						// UPDATE
						$updateResult = $this->updateRow($wpdb, $tableName, $currentId, $i, $errorMessages);
						$operations++;

						if ($updateResult) {
							$rowsAffected++;
						}

						// DELETE (from the previous iteration)
						if ($lastId > 0) {
							$deleteResult = $this->deleteRow($wpdb, $tableName, $lastId, $errorMessages);
							$operations++;

							if ($deleteResult) {
								$rowsAffected++;
							}
						}

						$lastId = $currentId;
					}
				}
			} catch (\Exception $exception) {
				$errorMessages[] = $exception->getMessage();
			}
		}

		$this->dropTemporaryTable($wpdb, $tableName, $errorMessages);
		$this->restoreDatabaseSettings($wpdb, $originalShowErrors);

		return $this->buildResult(
			round(microtime(true) - $startTime, 4),
			$operations,
			$rowsAffected,
			empty($errorMessages) ? null : implode('; ', $errorMessages)
		);
	}

	private function generateTemporaryTableName($wpdb): string {
		return $wpdb->prefix . 'wpbench_temp_test_' . time();
	}

	private function createTemporaryTable($wpdb, string $tableName, array &$errorMessages): void {
		$charsetCollate = $wpdb->get_charset_collate();
		$sqlCreate = "CREATE TABLE `{$tableName}` (
        `id` mediumint(9) NOT NULL AUTO_INCREMENT,
        `time` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        `name` tinytext NOT NULL,
        `text` text NOT NULL,
        `value` bigint(20) NOT NULL,
        PRIMARY KEY  (`id`)
    ) ENGINE=InnoDB {$charsetCollate};";

		$wpdb->query($sqlCreate);

		if ($wpdb->last_error) {
			throw new \Exception("Failed to create temporary table `$tableName`. DB Error: " . $wpdb->last_error);
		}
	}

	private function insertRow($wpdb, string $tableName, int $iteration, array &$errorMessages): array {
		$insertData = [
			'time' => current_time('mysql'),
			'name' => 'wpbench_insert_' . $iteration,
			'text' => 'Benchmark test data iteration ' . $iteration . ' ' . bin2hex(random_bytes(10)),
			'value' => $iteration * 1000 + rand(0, 999),
		];

		$inserted = $wpdb->insert($tableName, $insertData, ['%s', '%s', '%s', '%d']);
		if ($inserted === false) {
			$errorMessages[] = 'DB Insert Failed. Error: ' . $wpdb->last_error;
			return ['success' => false];
		}

		return ['success' => true, 'id' => $wpdb->insert_id];
	}

	private function updateRow($wpdb, string $tableName, int $rowId, int $iteration, array &$errorMessages): bool {
		$updateData = ['text' => 'Updated test data iteration ' . $iteration . ' - ' . bin2hex(random_bytes(5))];

		$updated = $wpdb->update($tableName, $updateData, ['id' => $rowId], ['%s'], ['%d']);
		if ($updated === false) {
			$errorMessages[] = 'DB Update Failed. Error: ' . $wpdb->last_error;
			return false;
		}

		return $updated > 0;
	}

	private function deleteRow($wpdb, string $tableName, int $rowId, array &$errorMessages): bool {
		$deleted = $wpdb->delete($tableName, ['id' => $rowId], ['%d']);
		if ($deleted === false) {
			$errorMessages[] = 'DB Delete Failed. Error: ' . $wpdb->last_error;
			return false;
		}

		return $deleted > 0;
	}

	private function dropTemporaryTable($wpdb, string $tableName, array &$errorMessages): void {
		$wpdb->query("DROP TABLE IF EXISTS `{$tableName}`");
		if ($wpdb->last_error) {
			$errorMessages[] = 'Failed to drop temporary table `' . $tableName . '`. DB Error: ' . $wpdb->last_error;
		}
	}

	private function initializeDatabaseSettings($wpdb): bool {
		$originalShowErrors = $wpdb->show_errors;
		$wpdb->show_errors(true); // Enable error display for debugging
		return $originalShowErrors;
	}

	private function restoreDatabaseSettings($wpdb, bool $originalShowErrors): void {
		$wpdb->show_errors($originalShowErrors);
	}

	public function calculateScore( array $test_results, array $config ): array {
		$cycles = (int) ($config['config_db_write'] ?? 0);
        $time = isset($test_results['time']) ? (float) $test_results['time'] : -1;

        if ($cycles <= 0 || $time < 0) {
            return ['sub_score' => 0, 'weight' => 0];
        }

        $ops_executed = $test_results['operations'] ?? $cycles * 3;
        $target_time_for_run = self::TARGET_DB_WRITE_S_PER_1K_OPS * ($ops_executed / 1000.0);

        if ($target_time_for_run <= 0) {
            return ['sub_score' => 0, 'weight' => self::WEIGHT_DB_WRITE];
        }

        $score = 100 * ($target_time_for_run / $time);

        if ($score < $this->scoreMinimum) {
            $score = $this->scoreMinimum;
        }

		return ['sub_score' => round(min(100, $score)), 'weight' => self::WEIGHT_DB_WRITE];
	}
}
