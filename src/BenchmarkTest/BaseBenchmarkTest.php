<?php
namespace WPBench\BenchmarkTest;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Interface for all benchmark test classes.
 */
interface BaseBenchmarkTest {

    /**
     * Run the benchmark test.
     *
     * @param mixed $value Configuration value (e.g., iterations, size).
     * @return array An array containing the results of the test (e.g., ['time' => 1.234, 'peak_usage_mb' => 10]).
     * Must include an 'error' key (null if no error).
     * @throws \Exception If a critical error prevents the test from running (should be caught by caller).
     */
    public function run( $value ) : array;

    /**
     * Get descriptive information about the test.
     *
     * @return array{
     * id: string,
     * name: string,
     * description: string,
     * config_label: string,
     * config_unit: string,
     * default_value: int,
     * min_value: int,
     * max_value: int
     * } Associative array with test details.
     */
    public function get_info() : array;
}