<?php
namespace WPBench\BenchmarkTest;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Memory Benchmark Test.
 */
class Memory implements BaseBenchmarkTest {

    /**
     * Run the Memory benchmark test.
     *
     * @param int $value Approximate size in KB to allocate.
     * @return array Results including 'time', 'peak_usage_mb', and 'error'.
     */
    public function run( $value ) : array {
        $size_kb = absint($value);
        if ($size_kb <= 0) {
            return ['time' => 0, 'peak_usage_mb' => 0, 'error' => 'Invalid memory size.'];
        }

        $start = microtime(true);
        // $initial_memory = memory_get_usage(); // Getting initial usage isn't very useful for peak measurement
        $peak_memory_start = memory_get_peak_usage(true); // Get real peak usage *before* allocation
        $error = null;
        $string = null; // Initialize
        $allocation_size = 0; // Initialize

        try {
            $allocation_size = $size_kb * 1024;
             // Prevent allocating insanely large amounts relative to limit
             $memory_limit_str = ini_get('memory_limit');
             $memory_limit_bytes = wp_convert_hr_to_bytes($memory_limit_str);

            if ($memory_limit_bytes > 0 && $allocation_size >= $memory_limit_bytes * 0.8) {
                // Don't try to allocate more than 80% of the limit to avoid fatal errors
                 $allocation_size = intval($memory_limit_bytes * 0.8);
                 $error = 'Requested size too large for PHP memory_limit, reduced to ' . round($allocation_size / 1024) . ' KB.';
             }

             if ($allocation_size > 0) {
                 $string = str_repeat('a', $allocation_size);
                 // Check if allocation succeeded (str_repeat can return false or empty string on failure)
                 if ($string === false || strlen($string) !== $allocation_size) {
                     throw new \Exception("Failed to allocate requested string memory (".$allocation_size." bytes). Possibly exceeded memory limit during allocation.");
                 }
                 // Manipulate it slightly to ensure it's used and not optimized away
                 $string[ $allocation_size - 1 ] = 'b';
                 $checksum = crc32($string); // Use the string

             } else {
                 $error = ($error ? $error . ' ' : '') . 'Memory allocation size calculated to zero or less (after potential reduction).';
             }

        } catch (\Throwable $t) {
            // Catch potential allocation errors or other issues
            $error = ($error ? $error . ' ' : '') . 'Error during memory test: ' . $t->getMessage();
        } finally {
             unset($string); // Explicitly free memory
             // It's better to measure peak usage *after* freeing if possible,
             // but get_peak_usage tracks the high water mark anyway.
        }

        $peak_memory_end = memory_get_peak_usage(true); // Get real peak usage *after* test

        return [
            'time' => round(microtime(true) - $start, 4),
            // Report the peak usage during the entire script execution up to this point.
            // Subtracting peak_memory_start might give an idea of test impact, but total peak is often more relevant.
            'peak_usage_mb' => round($peak_memory_end / 1024 / 1024, 2),
            'error' => $error
        ];
    }

    /**
     * Get descriptive information about the Memory test.
     * @return array Test details.
     */
    public function get_info() : array {
        return [
            'id'            => 'memory',
            'name'          => __('Memory Test', 'wpbench'),
            'description'   => __('Allocates and manipulates a block of memory.', 'wpbench'),
            'config_label'  => __('Memory Test Size', 'wpbench'),
            'config_unit'   => __('KB', 'wpbench'),
            'default_value' => 1024,
            'min_value'     => 128,
            'max_value'     => 65536, // Be careful with high values here
        ];
    }
}