<?php

/**
 * Plugin Name: WPBench
 * Plugin URI:  https://example.com/wpbench
 * Description: A WordPress plugin for benchmarking custom functionality and performance.
 * Version:     1.0.0
 * Author:      Your Name
 * Author URI:  https://example.com
 * License:     GPL-2.0-or-later
 * Text Domain: wpbench
 *
 * --------------------------------------------------------------------
 *
 * File:        TestRegistry.php
 * Description: Handles discovery and instantiation of benchmark test classes.
 * Namespace:   WPBench
 * Classes:     WPBench\TestRegistry
 * Dependencies: WPBench\BenchmarkTest\BaseBenchmarkTest
 *
 * Functionality:
 * - Discovers available benchmark test classes.
 * - Caches and retrieves information about test classes for future use.
 * - Uses Reflection to ensure test classes implement the required interface.
 * - Handles errors and exceptions gracefully.
 *
 * Notes:
 * - This file is part of the WPBench plugin.
 * - Do not access this file directly; exit if accessed outside of WordPress.
 *
 * --------------------------------------------------------------------
 *
 * @package    WPBench
 * @author     Raymond Nunez <raypn93@gmail.com>
 */

namespace WPBench;

use WPBench\BenchmarkTest\BaseBenchmarkTest;
use WPBench\Logger;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles discovery and instantiation of benchmark test classes.
 */
class TestRegistry {

    /** @var array|null Cached list of available test info arrays. */
    private $available_tests = null;

    /** @var array Cached instances of test classes. */
    private $instances = [];

	/**
	 * Scans the BenchmarkTest directory and returns info for available tests.
	 * Caches the result for subsequent calls within the same request.
	 *
	 * @return array<string, array> Array of test info arrays, keyed by test ID. Returns empty array on failure.
	 */
	public function get_available_tests() : array {
		if ($this->available_tests !== null) {
			return $this->available_tests;
		}

		$this->available_tests = []; // Initialize as empty array
		$test_dir = WPBENCH_PATH . 'src/BenchmarkTest/';
		if (!is_dir($test_dir)) {
			trigger_error("WPBench: BenchmarkTest directory not found at $test_dir", E_USER_WARNING);
			return $this->available_tests;
		}

		$files = glob( $test_dir . '*.php' );
		if ( empty($files) ) {
			// This isn't necessarily an error, might just be no tests defined yet
			// trigger_error("WPBench: No PHP files found in BenchmarkTest directory $test_dir", E_USER_WARNING);
			return $this->available_tests;
		}

		foreach ( $files as $file ) {
			$basename = basename( $file, '.php' );

			// Skip the interface itself AND the standard index.php file
			if ( $basename === 'BaseBenchmarkTest' || $basename === 'index' ) {
				continue; // Skip to the next file
			}

			$class_name = WPBENCH_BASE_NAMESPACE . 'BenchmarkTest\\' . $basename;

			// Check if class exists *before* trying reflection etc. Relies on autoloader.
			if ( class_exists( $class_name ) ) {
				try {
					$reflection = new \ReflectionClass( $class_name );
					// Ensure it's a concrete class implementing our interface
					if ( $reflection->implementsInterface( BaseBenchmarkTest::class ) && !$reflection->isAbstract() ) {

						// Instantiate ONLY to get info - avoids running constructor logic needlessly here
						// Note: newInstanceWithoutConstructor is safer if constructors have side effects
						// But if get_info() relies on constructor setup, need newInstance()
						// Let's assume get_info is safe without full construction for now.
						$test_instance_for_info = $reflection->newInstanceWithoutConstructor();

						if(method_exists($test_instance_for_info, 'get_info')) {
							$info = $test_instance_for_info->get_info();
							// Validate the info structure basic requirements
							if ( isset( $info['id'] ) && is_string($info['id']) && !empty($info['id']) ) {
								$this->available_tests[ $info['id'] ] = $info; // Use the ID from get_info() as the key
							} else {
								Logger::log("WPBench: Test class $class_name get_info() did not return a valid string 'id'. Skipping.", E_USER_WARNING);
							}
						} else {
							Logger::log("WPBench: Test class $class_name does not implement get_info() method. Skipping.", E_USER_WARNING);
						}
					}
					// else: Class exists but doesn't implement interface or is abstract - ignore silently.
				} catch (\ReflectionException $e) {
					Logger::log("WPBench: Reflection error for class $class_name: " . $e->getMessage(), E_USER_WARNING);
				} catch (\Throwable $e) { // Catch potential errors during instantiation or get_info() call (Throwable for PHP 7+)
					Logger::log("WPBench: Error processing test class $class_name for info: " . $e->getMessage(), E_USER_WARNING);
				}
			} else {
				// This error now specifically means the autoloader found the file but couldn't load the expected class.
				// Could be namespace typo in the file, or the file IS index.php / BaseBenchmarkTest.php which autoloader included but class_exists failed.
				// Since we added specific checks for index/BaseBenchmarkTest, this warning is less likely now for those cases.
				trigger_error("WPBench: Expected class $class_name not found after including/autoloading file $file. Check namespace and class definition.", E_USER_WARNING);
			}
		} // End foreach loop

		// Ensure a consistent order (optional, based on ID)
		ksort($this->available_tests);

		Logger::log("Available tests: " . print_r($this->available_tests, true));

		return $this->available_tests;
	}


    /**
     * Get information array for a specific test ID.
     *
     * @param string $testId The ID of the test (e.g., 'cpu').
     * @return array|null Test info array or null if not found.
     */
    public function get_test_info(string $testId) : ?array {
        $tests = $this->get_available_tests(); // Ensure cache is populated
        return $tests[$testId] ?? null;
    }

    /**
     * Get an instance of a specific benchmark test class.
     * Caches instances for reuse within the same request.
     *
     * @param string $testId The ID of the test (e.g., 'cpu').
     * @return BaseBenchmarkTest|null Instance of the test class or null on failure.
     */
    public function get_test_instance(string $testId) : ?BaseBenchmarkTest {
        if (isset($this->instances[$testId])) {
            return $this->instances[$testId];
        }

        $info = $this->get_test_info($testId);
        if (!$info) {
			return null; // Test ID not found
        }

        // Construct class name from ID (assuming standard conversion)
        $class_parts = explode('_', $testId);
        $class_basename = implode('', array_map('ucfirst', $class_parts)); // db_read -> DBRead
        $full_class_name = WPBENCH_BASE_NAMESPACE . 'BenchmarkTest\\' . $class_basename;

		if ( class_exists( $full_class_name ) ) {
			try {
				$instance = new $full_class_name();

				if ($instance instanceof BaseBenchmarkTest) {
					$this->instances[$testId] = $instance;
					return $instance;
				} else {
					trigger_error("WPBench: Class $full_class_name does not implement BaseBenchmarkTest.", E_USER_WARNING);
				}
			} catch (\Throwable $e) {
				trigger_error("WPBench: Failed to instantiate test class $full_class_name: " . $e->getMessage(), E_USER_WARNING);
			}
		} else {
			trigger_error("WPBench: Test class $full_class_name not found for ID $testId.", E_USER_WARNING);
		}

        return null;
    }
}