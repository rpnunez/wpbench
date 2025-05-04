<?php
namespace WPBench;

use WPBench\BenchmarkTest\BaseBenchmarkTest;

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
            trigger_error("WPBench: No PHP files found in BenchmarkTest directory $test_dir", E_USER_WARNING);
            return $this->available_tests;
        }

        foreach ( $files as $file ) {
            $basename = basename( $file, '.php' );

            // Skip the interface itself and any non-class files
            if ( $basename === 'BaseBenchmarkTest' ) {
                continue;
            }

            $class_name = WPBENCH_BASE_NAMESPACE . 'BenchmarkTest\\' . $basename;

            if ( class_exists( $class_name ) ) {
                try {
                    $reflection = new \ReflectionClass( $class_name );
                    if ( $reflection->implementsInterface( BaseBenchmarkTest::class ) && !$reflection->isAbstract() ) {
                        // Instantiate ONLY to get info - use get_test_instance for actual use
                        $test_instance_for_info = $reflection->newInstanceWithoutConstructor(); // Avoid constructor side-effects if any
                        if(method_exists($test_instance_for_info, 'get_info')) {
                             $info = $test_instance_for_info->get_info();
                             if ( isset( $info['id'] ) && is_string($info['id']) ) {
                                $this->available_tests[ $info['id'] ] = $info;
                             } else {
                                 trigger_error("WPBench: Test class $class_name get_info() did not return a valid string 'id'.", E_USER_WARNING);
                             }
                        } else {
                             trigger_error("WPBench: Test class $class_name does not implement get_info() method.", E_USER_WARNING);
                        }
                    }
                } catch (\ReflectionException $e) {
                     trigger_error("WPBench: Reflection error for class $class_name: " . $e->getMessage(), E_USER_WARNING);
                } catch (\Error $e) { // Catch potential errors during instantiation or get_info() call
                     trigger_error("WPBench: Error processing test class $class_name for info: " . $e->getMessage(), E_USER_WARNING);
                }
            } else {
                 trigger_error("WPBench: Class $class_name not found after including file $file.", E_USER_WARNING);
            }
        }

        // Ensure a consistent order (optional, based on ID)
        ksort($this->available_tests);

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