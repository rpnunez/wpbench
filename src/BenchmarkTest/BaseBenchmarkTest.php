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

	/**
	 * Calculates the sub-score and weight for this specific test.
	 *
	 * @param array $test_run_results The specific results array from this test's run() method.
	 * @param array $full_config      The full benchmark configuration array (e.g., $config['config_cpu']).
	 *
	 * @return array An associative array with keys 'sub_score' (0-100) and 'weight' (0.0-1.0).
	 * Return ['sub_score' => 0, 'weight' => 0] or null if score cannot be calculated.
	 */
	public function calculateScore(array $test_run_results, array $full_config) : ?array;


	//public function checkSystemHealth();
}