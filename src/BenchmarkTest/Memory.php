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

	// --- Scoring Parameters for Memory Test ---
	public const float TARGET_PEAK_MB_IDEAL_FACTOR = 1.5; // Ideal peak should not be much more than allocated (e.g. 1.5x allocated size)
	public const float SCORE_WEIGHT = 0.15; // Memory test weight
    private int $scoreMinimum = 20;

	/**
	 * Retrieves the singleton instance of the Memory class.
	 *
	 * @return Memory|null The singleton instance of the Memory class, or null if an instance is not created.
	 */
	public function getInstance(): ?Memory {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Get descriptive information about the Memory test.
	 * @return array Test details.
	 */
	public function getInfo() : array {
		return [
			'id'            => 'memory',
			'name'          => __('Memory Test', 'wpbench'),
			'description'   => __('Allocates and manipulates a block of memory to measure peak usage.', 'wpbench'),
			'config_label'  => __('Memory Allocation Size', 'wpbench'),
			'config_unit'   => __('KB', 'wpbench'),
			'default_value' => 2048, // Increased default
			'min_value'     => 256,
			'max_value'     => 32768, // 32MB, be cautious
		];
	}

	public function buildResult($time, $peakUsageMB, $allocatedKB, $actuallyAllocatedKB, $error) {
		return [
			'time' => $time,
			'peak_usage_mb' => $peakUsageMB,
			'allocated_kb' => $allocatedKB,
			'actually_allocated_kb' => $actuallyAllocatedKB,
			'error' => $error
		];
	}

	public function run( $value ) : array {
		$size_kb = absint($value);
		if ( $size_kb <= 0 ) { return ['time' => 0, 'peak_usage_mb' => 0, 'error' => 'Invalid memory size.']; }

		$start = microtime(true);
		$peak_memory_before_allocation = memory_get_peak_usage(true);
		$error = null;
		$string = null;
		$allocation_size = 0;

		try {
			$allocation_size = $size_kb * 1024;
			$memory_limit_str = ini_get('memory_limit');
			$memory_limit_bytes = wp_convert_hr_to_bytes($memory_limit_str);

			if ($memory_limit_bytes > 0 && $allocation_size >= $memory_limit_bytes * 0.7) { // Reduced factor
				$allocation_size = intval($memory_limit_bytes * 0.7);
				$error_msg_part = 'Requested size too large for PHP memory_limit, reduced to ' . round($allocation_size / 1024) . ' KB.';
				$error = $error ? $error . ' ' . $error_msg_part : $error_msg_part;
			}

			if ($allocation_size > 0) {
				$string = str_repeat('a', $allocation_size);
				if ($string === false || strlen($string) !== $allocation_size) {
					throw new \Exception("Failed to allocate requested string memory (".$allocation_size." bytes).");
				}
				$string[ $allocation_size - 1 ] = 'b'; $checksum = crc32($string);
			} else { $error = ($error ? $error . ' ' : '') . 'Memory allocation size zero or less.'; }

		} catch (\Throwable $t) { $error = ($error ? $error . ' ' : '') . 'Error during memory test: ' . $t->getMessage(); }
		finally { unset($string); }

		$peak_memory_after_test = memory_get_peak_usage(true);

		return $this->buildResult(
			round(microtime(true) - $start, 4),
			round($peak_memory_after_test / 1024 / 1024, 2), // Report total peak
			$size_kb, // Store what was requested for scoring context
			round($allocation_size / 1024),
			$error
		);
	}

	/**
	 * Calculates the sub-score and weight for the Memory test.
	 * Score is higher if peak_usage_mb is closer to or less than (AllocationSize * Factor).
	 */
	public function calculateScore(array $test_run_results, array $full_config) : ?array {
		$allocated_kb = (int) ($full_config['config_memory'] ?? 0);
		$peak_usage_mb = isset($test_run_results['peak_usage_mb']) ? (float) $test_run_results['peak_usage_mb'] : -1;

		if ($allocated_kb <= 0 || $peak_usage_mb < 0) {
			return ['sub_score' => 0, 'weight' => 0];
		}

		$allocated_mb = $allocated_kb / 1024.0;
		$ideal_max_peak_mb = $allocated_mb * self::TARGET_PEAK_MB_IDEAL_FACTOR;

		if ($ideal_max_peak_mb <=0) {
			return [ 'sub_score' => 0, 'weight' => self::SCORE_WEIGHT ];
		}

		$overhead_ratio = ($peak_usage_mb - $allocated_mb) / $allocated_mb;
		$score = 100 * (1 - $overhead_ratio);

        if ($score < $this->scoreMinimum) {
            $score = $this->scoreMinimum;
        }


		return ['sub_score' => round(min(100, $score)), 'weight' => self::SCORE_WEIGHT];
	}
}
