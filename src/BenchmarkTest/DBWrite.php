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

    public function run_old( $value ) : array {
	    global $wpdb;

        $iterations = absint($value);

		if ( $iterations <= 0 ) {
            return [
				'time' => 0,
				'operations' => 0,
				'rows_affected' => 0,
				'error' => 'Invalid iteration count.'
            ];
        }

        $start = microtime(true);
        $table_name = $wpdb->prefix . 'wpbench_temp_test_' . time(); // Add timestamp for uniqueness
        $rows_affected = 0;
        $operations = 0;
        $error = null;
        $table_created = false;
        $original_show_errors = $wpdb->show_errors; // Store original state
        $wpdb->show_errors(true); // Show errors during test

        try {
            $charset_collate = $wpdb->get_charset_collate();
            $sql_create = "CREATE TABLE `{$table_name}` (
                `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                `time` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                `name` tinytext NOT NULL,
                `text` text NOT NULL,
                `value` bigint(20) NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=InnoDB {$charset_collate};"; // Specify InnoDB, more common now

			// Use direct query for creation, check for errors
			$wpdb->query( $sql_create );

			if ( $wpdb->last_error ) {
				throw new \Exception("Failed to create temporary table '$table_name'. DB Error: " . $wpdb->last_error);
			}

			$table_created = true; // Assume success if no error thrown
        } catch (\Exception $e) {
			$error = $e->getMessage();
			// Skip the rest if table creation fails
			$iterations = 0; // Prevent loop execution
        }

        // --- Run Write Operations ---
        try {
            if ($table_created && $iterations > 0) { // Only run if table created and iterations > 0
                $last_id = 0; // Keep track of the last inserted ID

                for ($i = 0; $i < $iterations; $i++) {
                    // INSERT
                    $insert_data = [
						'time' => current_time( 'mysql' ),
						'name' => 'wpbench_insert_' . $i,
						'text' => 'Benchmark test data iteration ' . $i . ' ' . bin2hex(random_bytes(10)),
						'value' => $i * 1000 + rand(0, 999)
                    ];

                    $inserted = $wpdb->insert(
                        $table_name,
                        $insert_data,
                         [ '%s', '%s', '%s', '%d' ] // Format specifiers
                    );

                    $operations++;

                    if ($inserted === false) {
						throw new \Exception("DB Insert Failed. Error: " . $wpdb->last_error);
                    }

                    if ($inserted) {
						$rows_affected++;
                    }

                    $current_id = $wpdb->insert_id; // Get the ID of the row just inserted

                     // UPDATE (update the row just inserted, if insert succeeded)
                    if ($current_id > 0) {
                        $update_data = [
							'text' => 'Updated test data iteration ' . $i . ' - ' . bin2hex(random_bytes(5))
                        ];

                        $updated = $wpdb->update(
                             $table_name,
                             $update_data, // Data
                             [ 'id' => $current_id ], // Where
                             [ '%s' ], // Data format
                             [ '%d' ] // Where format
                         );

                         $operations++;

                         // update returns number of rows updated or false on error
                         if ($updated === false) {
							 throw new \Exception("DB Update Failed. Error: " . $wpdb->last_error);
                         }

                         if ($updated > 0) {
							 $rows_affected++;
                         }
                     } else {
                         // If insert failed to return an ID, something is wrong
                         throw new \Exception("DB Insert returned success but no insert ID received.");
                     }

					// DELETE (delete the row from the *previous* iteration to keep table size somewhat stable)
					if ($last_id > 0) {
						$deleted = $wpdb->delete( $table_name, [ 'id' => $last_id ], [ '%d' ] );
						$operations++;

						// delete returns number of rows deleted or false on error
						if ($deleted === false) {
							throw new \Exception("DB Delete Failed. Error: " . $wpdb->last_error);
						}

						if ($deleted > 0) {
							$rows_affected++;
						}
					}

					$last_id = $current_id; // Store current ID for next iteration's delete
                }
            }
        } catch (\Exception $e) {
             $error = ($error ? $error . '; ' : '') . $e->getMessage(); // Append potential operation errors

	        Logger::log("Error running DB Write test: " . $error, E_USER_WARNING, __CLASS__, __METHOD__);
        } finally {
            // --- Drop Temp Table ---
            if ($table_created) {
                $wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );
				// Optional: check $wpdb->last_error after drop?
            }

            $wpdb->show_errors($original_show_errors); // Restore original error display state
        }

        return $this->buildResult(
            time: round(microtime(true) - $start, 4),
            operations: $operations, // More accurate count of attempted ops
            rowsAffected: $rows_affected, // Count of successful ops
            error: $error
        );
    }

	public function calculateScore( array $test_results, array $config ): array {
		// TODO: Implement calculateScore() method.

		return [];
	}
}