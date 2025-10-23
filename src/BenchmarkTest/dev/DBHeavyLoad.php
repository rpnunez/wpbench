<?php
namespace src\BenchmarkTest\dev;

use WPBench\BenchmarkTest\BaseBenchmarkTest;
use WPBench\Guards\ResourceGuard;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DBHeavyLoad implements BaseBenchmarkTest {

	public function getInstance() {
		// TODO: Implement getInstance() method.
	}

	public function getInfo() : array {
		return [
			'id'            => 'db_heavy_load',
			'name'          => __('DB Mixed Heavy Load Test', 'wpbench'),
			'description'   => __('Performs a cycle of INSERT, SELECT, UPDATE, and DELETE operations on a temporary database table. Optionally uses transactions.', 'wpbench'),
			'config_label'  => __('Number of Operation Cycles', 'wpbench'),
			'config_unit'   => __('cycles', 'wpbench'),
			'default_value' => 100,
			'min_value'     => 10,
			'max_value'     => 2500, // Higher values can take significant time
		];
	}

	public function run( $value ) : array {
		$iterations = absint($value);
		if ($iterations <= 0) {
			return ['time' => 0, 'operations' => 0, 'error' => 'Invalid iteration count.'];
		}

		global $wpdb;
		$start_time = microtime(true);
		$error_message = null;
		$total_operations = 0;
		$inserted_ids = []; // Keep track of IDs to update/delete

		// Define temporary table name
		$table_name = $wpdb->prefix . 'wpbench_heavy_load_temp';

		// Store original error suppression
		$original_suppress_errors = $wpdb->suppress_errors;
		$wpdb->suppress_errors(false); // Ensure we catch DB errors

		try {
			ResourceGuard::checkIfMaxIterationsReached($iterations, 5000);

			// --- Create Temporary Table ---
			$charset_collate = $wpdb->get_charset_collate();
			$sql_create = "CREATE TABLE `{$table_name}` (
                `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `random_text` VARCHAR(255) NOT NULL,
                `random_number` INT(11) NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_random_number` (`random_number`)
            ) ENGINE=InnoDB {$charset_collate};";

			// Use dbDelta for table creation/update (safer)
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta($sql_create);

			if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
				throw new \RuntimeException("Failed to create temporary table: {$table_name}. DB Error: " . $wpdb->last_error);
			}

			// --- Perform Operations Loop ---
			for ($i = 0; $i < $iterations; $i++) {
				// Start transaction (optional, good for testing transactional load)
				// $wpdb->query('START TRANSACTION');

				// 1. INSERT
				$text = 'WPBench test string ' . bin2hex(random_bytes(10)) . ' iter ' . $i;
				$number = rand(1, 100000);
				$insert_result = $wpdb->insert(
					$table_name,
					['random_text' => $text, 'random_number' => $number],
					['%s', '%d']
				);
				if ($insert_result === false) throw new \RuntimeException("DB INSERT failed: " . $wpdb->last_error);
				$last_id = $wpdb->insert_id;
				$inserted_ids[] = $last_id;
				$total_operations++;

				// 2. SELECT (e.g., select the inserted row and some others)
				$selected_rows = $wpdb->get_results($wpdb->prepare(
					"SELECT * FROM `{$table_name}` WHERE id = %d OR random_number > %d ORDER BY id DESC LIMIT 5",
					$last_id, $number - 100
				));
				if ($wpdb->last_error) throw new \RuntimeException("DB SELECT failed: " . $wpdb->last_error);
				$total_operations++;

				// 3. UPDATE (e.g., update the row just inserted)
				if ($last_id) {
					$update_result = $wpdb->update(
						$table_name,
						['random_text' => 'Updated - ' . $text],
						['id' => $last_id],
						['%s'],
						['%d']
					);
					if ($update_result === false) throw new \RuntimeException("DB UPDATE failed: " . $wpdb->last_error);
					$total_operations++;
				}

				// 4. DELETE (e.g., delete older rows to keep table size somewhat controlled)
				if (count($inserted_ids) > 20) { // Keep a buffer of rows
					$id_to_delete = array_shift($inserted_ids); // Get the oldest ID
					if ($id_to_delete) {
						$delete_result = $wpdb->delete($table_name, ['id' => $id_to_delete], ['%d']);
						if ($delete_result === false) throw new \RuntimeException("DB DELETE failed: " . $wpdb->last_error);
						$total_operations++;
					}
				}
				// Commit transaction
				// if ($wpdb->query('COMMIT') === false) throw new \RuntimeException("DB COMMIT failed: " . $wpdb->last_error);
			}

		} catch (\Throwable $e) {
			$error_message = get_class($e) . ': ' . $e->getMessage();
			// If transaction was started and error occurred, try to rollback
			// $wpdb->query('ROLLBACK');
		} finally {
			// --- Drop Temporary Table ---
			$wpdb->query("DROP TABLE IF EXISTS `{$table_name}`");
			// Restore original error suppression
			$wpdb->suppress_errors($original_suppress_errors);
		}

		$end_time = microtime(true);

		return [
			'time'         => round($end_time - $start_time, 4),
			'operations'   => $total_operations, // Count of individual DB actions
			'cycles'       => $iterations,
			'error'        => $error_message,
		];
	}

	public function calculateScore( array $test_results, array $config ): array {
		// TODO: Implement calculateScore() method.

		return [];
	}
}