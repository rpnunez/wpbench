<?php
namespace WPBench\BenchmarkTest;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Database Write Benchmark Test.
 */
class DBWrite implements BaseBenchmarkTest {

    /**
     * Run the Database Write benchmark test.
     * Creates, uses, and drops a temporary table.
     *
     * @param int $value Number of INSERT/UPDATE/DELETE cycles.
     * @return array Results including 'time', 'operations', 'rows_affected', 'error'.
     */
    public function run( $value ) : array {
        $iterations = absint($value);
         if ($iterations <= 0) {
            return ['time' => 0, 'operations' => 0, 'rows_affected' => 0, 'error' => 'Invalid iteration count.'];
        }

        global $wpdb;
        $start = microtime(true);
        $table_name = $wpdb->prefix . 'wpbench_temp_test_' . time(); // Add timestamp for uniqueness
        $rows_affected = 0;
        $operations = 0;
        $error = null;
        $table_created = false;
        $original_show_errors = $wpdb->show_errors; // Store original state
        $wpdb->show_errors(true); // Show errors during test

        // --- Create Temp Table ---
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
            if ($wpdb->last_error) {
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
                    if ($inserted === false) throw new \Exception("DB Insert Failed. Error: " . $wpdb->last_error);
                    if ($inserted) $rows_affected++;
                    $current_id = $wpdb->insert_id; // Get the ID of the row just inserted

                     // UPDATE (update the row just inserted, if insert succeeded)
                    if ($current_id > 0) {
                        $update_data = [ 'text' => 'Updated test data iteration ' . $i . ' - ' . bin2hex(random_bytes(5)) ];
                        $updated = $wpdb->update(
                             $table_name,
                             $update_data, // Data
                             [ 'id' => $current_id ], // Where
                             [ '%s' ], // Data format
                             [ '%d' ] // Where format
                         );
                         $operations++;
                         // update returns number of rows updated or false on error
                         if ($updated === false) throw new \Exception("DB Update Failed. Error: " . $wpdb->last_error);
                         if ($updated > 0) $rows_affected++;
                     } else {
                         // If insert failed to return an ID, something is wrong
                         throw new \Exception("DB Insert returned success but no insert ID received.");
                     }

                     // DELETE (delete the row from the *previous* iteration to keep table size somewhat stable)
                     if ($last_id > 0) {
                        $deleted = $wpdb->delete( $table_name, [ 'id' => $last_id ], [ '%d' ] );
                        $operations++;
                         // delete returns number of rows deleted or false on error
                        if ($deleted === false) throw new \Exception("DB Delete Failed. Error: " . $wpdb->last_error);
                        if ($deleted > 0) $rows_affected++;
                     }
                     $last_id = $current_id; // Store current ID for next iteration's delete

                } // end for loop
            } // end if table_created
        } catch (\Exception $e) {
             $error = ($error ? $error . '; ' : '') . $e->getMessage(); // Append potential operation errors
        } finally {
            // --- Drop Temp Table ---
            if ($table_created) {
                $wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" );
                 // Optional: check $wpdb->last_error after drop?
            }
             $wpdb->show_errors($original_show_errors); // Restore original error display state
        }

        return [
            'time' => round(microtime(true) - $start, 4),
            'operations' => $operations, // More accurate count of attempted ops
            'rows_affected' => $rows_affected, // Count of successful ops
            'error' => $error
        ];
    }

    /**
     * Get descriptive information about the DB Write test.
     * @return array Test details.
     */
    public function get_info() : array {
        return [
            'id'            => 'db_write',
            'name'          => __('DB Write Test', 'wpbench'),
            'description'   => __('Performs INSERT, UPDATE, DELETE operations on a temporary table.', 'wpbench'),
            'config_label'  => __('DB Write Operations', 'wpbench'),
            'config_unit'   => __('cycles (insert/update/delete)', 'wpbench'),
            'default_value' => 100,
            'min_value'     => 10,
            'max_value'     => 2500,
            'target'        => 0.3,  // Target value specific to DB Write test
            'weight'        => 0.30, // Weight specific to DB Write test

        ];
    }
}