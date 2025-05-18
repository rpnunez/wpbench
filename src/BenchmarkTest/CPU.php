<?php
namespace WPBench\BenchmarkTest;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CPU Benchmark Test.
 */
class CPU implements BaseBenchmarkTest {

    /**
     * Get descriptive information about the CPU test.
     * @return array Test details.
     */
    public function get_info() : array {
        return [
            'id'            => 'cpu',
            'name'          => __('CPU Test', 'wpbench'),
            'description'   => __('Performs CPU-intensive calculations (math, string manipulation).', 'wpbench'),
            'config_label'  => __('CPU Test Iterations', 'wpbench'),
            'config_unit'   => __('iterations', 'wpbench'),
            'default_value' => 100000,
            'min_value'     => 1000,
            'max_value'     => 10000000,
            'target'        => 0.5,  // Target value specific to CPU test
            'weight'        => 0.30, // Weight specific to CPU test

        ];
    }

    /**
     * Run the CPU benchmark test.
     *
     * @param int $value Number of iterations.
     * @return array Results including 'time' and 'error'.
     */
    public function run( $value ) : array {
        $iterations = absint($value);
        if ($iterations <= 0) {
            return ['time' => 0, 'error' => 'Invalid iteration count.'];
        }

        $start = microtime( true );
        $error = null;
        $checksum = 0; // To potentially prevent optimizations

        try {
            for ( $i = 0; $i < $iterations; $i++ ) {
                // Perform some math operations
                $a = sqrt( ( $i + 1 ) * M_PI ); // Use M_PI constant
                $b = log( $a + 1 );
                $c = sin( $b ) * cos( $a );
                 // Perform some string operations
                 $str = md5((string)$c . uniqid('', true)); // Add more entropy
                 $str = sha1($str . $i);
                 $str = strrev($str);
                 // Prevent potential "Unused variable" optimization issues
                 if ($i % 1000 === 0) { // Do something trivial periodically
                     $checksum += crc32($str);
                 }
            }
        } catch (\Throwable $t) {
             // Catch potential errors during calculations
             $error = 'Error during CPU test: ' . $t->getMessage();
        }

        return [
            'time' => round( microtime( true ) - $start, 4 ),
            'error' => $error // Will be null if try block completed without exception
        ];
    }
}