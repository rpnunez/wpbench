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
	 * Retrieves the singleton instance of the BenchmarkTest class.
	 *
	 * @return BaseBenchmarkTest|null The singleton instance of the BenchmarkTest class that implements this, or null if an instance is not created.
	 */
	public function getInstance();

	/**
	 * Get descriptive information about the test.
	 *
	 * @return array{
	 *      id: string,
	 *      name: string,
	 *      description: string,
	 *      config_label: string,
	 *      config_unit: string,
	 *      default_value: int,
	 *      min_value: int,
	 *      max_value: int
	 * } Associative array with test details.
	 */
	public function getInfo() : array;

    /**
     * Run the benchmark test.
     *
     * @param int $value Configuration value (e.g., iterations, size).
     * @return array An array containing the results of the test (e.g., ['time' => 1.234, 'peak_usage_mb' => 10]).
     * Must include an 'error' key (null if no error).
     * @throws \Exception If a critical error prevents the test from running (should be caught by caller).
     */
    public function run( int $value ) : array;

	public function checkSystemHealth();
}