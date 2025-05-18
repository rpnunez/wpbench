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
     * Run the Database Read benchmark test.
     *
     * @param int $value Number of SELECT queries to execute.
     * @return array Results including 'time', 'queries_executed', 'rows_fetched', 'error'.
     */
    public function run( $value ) : array {
        $iterations = absint($value);
         if ($iterations <= 0) {
            return ['time' => 0, 'queries_executed' => 0, 'rows_fetched' => 0, 'error' => 'Invalid iteration count.'];
        }

        global $wpdb;
        $start = microtime(true);
        $rows_fetched = 0;
        $queries_executed = 0;
        $error = null;
        $original_show_errors = $wpdb->show_errors; // Store original state
        $wpdb->show_errors(true); // Turn on error display for debugging within test

        try {
             // Find max post ID once to avoid querying non-existent posts too often
             $max_post_id = (int) $wpdb->get_var("SELECT MAX(ID) FROM $wpdb->posts");
             if ($wpdb->last_error) throw new \Exception("DB Error (Max Post ID Query): " . $wpdb->last_error);
             $max_post_id = max(1, $max_post_id); // Ensure at least 1

            for ($i = 0; $i < $iterations; $i++) {
                // Query 1: Simple option query
                $option_name = 'blogname';
                $result = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option_name ) );
                $queries_executed++;
                if ($wpdb->last_error) throw new \Exception("DB Error (Option Query): " . $wpdb->last_error);
                if ($result !== null) $rows_fetched++; // Count as 1 "row" conceptually for get_var

                 // Query 2: Query a random existing post title
                 $post_id_to_query = rand(1, $max_post_id);
                 $post_title = $wpdb->get_var( $wpdb->prepare("SELECT post_title FROM $wpdb->posts WHERE ID = %d AND post_status = 'publish' LIMIT 1", $post_id_to_query));
                 $queries_executed++;
                 if ($wpdb->last_error) throw new \Exception("DB Error (Post Query): " . $wpdb->last_error);
                 if ($post_title !== null) $rows_fetched++; // Count as 1 "row"
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
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

    /**
     * Get descriptive information about the DB Read test.
     * @return array Test details.
     */
    public function get_info() : array {
        return [
            'id'            => 'db_read',
            'name'          => __('DB Read Test', 'wpbench'),
            'description'   => __('Executes multiple SELECT queries against WP tables.', 'wpbench'),
            'config_label'  => __('DB Read Queries', 'wpbench'),
            'config_unit'   => __('queries', 'wpbench'),
            'default_value' => 250,
            'min_value'     => 10,
            'max_value'     => 5000,
            'target'        => 0.2,  // Target value specific to DB Read test
            'weight'        => 0.30, // Weight specific to DB Read test
        ];
    }
}