<?php
namespace WPBench\BenchmarkTest;

// Exit if accessed directly
use WPBench\Helpers\RandomStringTypes;
use WPBench\Helpers\Safeguard;
use WPBench\Helpers\Utility;
use WPBench\Logger;
use WPBench\Guards\ResourceGuard;
use WPBench\Exceptions\MaxIterationsReached;
use WPBench\BenchmarkAnalyzer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CPU Benchmark Test.
 */
class CPU implements BaseBenchmarkTest {

	public const string BENCHMARK_NAME = 'CPU';

	private const int PERIODIC_INTERVAL = 1000; // Interval for periodic operations

	private const int WORKLOAD_MULTIPLIER = 50;

	private const int MAX_CPU_ITERATIONS = 100000;

	private const int MAX_EXECUTION_TIME_SECONDS = 30;

	private const int MAX_MEMORY_USAGE_MB = 128;

	/** Scoring Variables */

	public const float TARGET_CPU_S_PER_M_ITER = 0.5;
	public const float WEIGHT_CPU = 0.30;

	// --- Scoring Parameters for CPU Test ---
	public const float TARGET_S_PER_M_ITER = 0.45; // Target seconds per million iterations (Adjust as needed)
	public const float SCORE_WEIGHT = 0.30;        // Weight for this test in overall score

	// MAX_CPU_ITERATIONS, MAX_EXECUTION_TIME_SECONDS, MAX_MEMORY_USAGE_MB can be used by ResourceGuard checks if desired

	/**
	 * Retrieves the singleton instance of the CPU class.
	 *
	 * @return CPU|null The singleton instance of the CPU class, or null if an instance is not created.
	 */
	public function getInstance(): ?CPU {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

    /**
     * Get descriptive information about the CPU test.
     * @return array Test details.
     */
    public function getInfo() : array {
        return [
	        'id'            => 'cpu',
	        'name'          => __('CPU Test', 'wpbench'),
	        'description'   => __('Performs CPU-intensive calculations (math, string manipulation).', 'wpbench'),
	        'config_label'  => __('CPU Test Iterations', 'wpbench'),
	        'config_unit'   => __('iterations', 'wpbench'),
	        'default_value' => 100000,
	        'min_value'     => 1000,
	        'max_value'     => 5000000, // Adjusted max slightly
            'instance'      => $this->getInstance()
        ];
    }

	private function buildResult(float $time, ?string $error = null): array {
		return [
			'time' => $time,
			'error' => $error,
		];
	}

	/**
	 * Calculates the sub-score and weight based on test results and configuration.
	 *
	 * @param array $test_results The results of the benchmarking test, including timing data.
	 * @param array $config The configuration settings, including CPU iteration count information.
	 *
	 * @return array An associative array with keys 'sub_score' and 'weight' representing
	 *               the calculated score and weight respectively.
	 */
	/**
	 * Calculates the sub-score and weight for the CPU test.
	 */
	public function calculateScore(array $test_run_results, array $full_config) : ?array {
		$iterations = (int) ($full_config['config_cpu'] ?? 0); // Config key is 'config_cpu'
		$time = isset($test_run_results['time']) ? (float) $test_run_results['time'] : -1;

		if ($iterations <= 0 || $time < 0 || self::TARGET_S_PER_M_ITER <= 0) {
			return ['sub_score' => 0, 'weight' => 0]; // Cannot calculate
		}

		// Calculate the target time for the given number of iterations
		$target_time_for_run = self::TARGET_S_PER_M_ITER * ($iterations / 1000000.0);

		if ($target_time_for_run <= 0) { // Avoid division by zero if target is too small or iterations too low
			return ['sub_score' => 0, 'weight' => self::SCORE_WEIGHT];
		}

		// Score formula: 100 means achieved target or better. Lower score for slower times.
		// (1 - ActualTime / TargetTime) gives a ratio. If ActualTime = TargetTime, ratio is 0, score is 100.
		// If ActualTime is 2 * TargetTime, ratio is -1, score is 0.
		// If ActualTime is 0.5 * TargetTime, ratio is 0.5, score is 50. This is inverse, let's fix.
		// We want: if ActualTime = TargetTime, score ~50-75. If ActualTime < TargetTime, score -> 100.
		// Simpler: score = 100 * (TargetTime / ActualTime) capped at 100 if ActualTime is very low. Or (1 - (ActualTime - TargetTime) / TargetTime)
		// Using: Score = max(0, 100 * (1 - (ActualTime / IdealTargetTime)))
		// Where IdealTargetTime is the time taken by an "ideal" system.
		// For WPBench, let's use: score = 100 * (1 - ActualTime / (ExpectedGoodTime))
		// Let's use the formula: if ActualTime is less than or equal to TargetTime, score is higher.
		// If ActualTime = TargetTime, score around 50-75. If ActualTime = 0.5 * TargetTime, score closer to 100.
		// If ActualTime = 1.5 * TargetTime, score closer to 0.

		$sub_score = max(0, 100 * (1 - ($time / (2 * $target_time_for_run)) ) );
		// This gives 50 if time = target_time_for_run, 100 if time = 0, 0 if time = 2*target_time_for_run

		return ['sub_score' => round($sub_score), 'weight' => self::SCORE_WEIGHT];
	}

	/**
     * Run the CPU benchmark test.
     *
     * @param mixed $value Number of iterations.
     *
     * @return array Results including 'time' and 'error'.
     */
	public function run(mixed $value): array {
		$totalIterations = absint($value);

		if ($totalIterations <= 0) {
			return $this->buildResult(0, 'Invalid iteration count.');
		}

		$startTime = microtime(true);
		$error = null;
		$checksum = 0;

		try {
			for ($iteration = 0; $iteration < $totalIterations; $iteration++) {
				// Run safeguards to ensure we don't break ourselves
				if ($iteration % 1000 === 0) {
					ResourceGuard::checkIfMaxIterationsReached($iteration);

					//Safeguard::checkIfCPULoadReached( sys_getloadavg()[0] );
					//Safeguard::checkIfMaxExecutionTimeReached( $startTime, self::MAX_EXECUTION_TIME_SECONDS );
					//Safeguard::checkIfMaxMemoryReached(memory_get_usage(), self::MAX_MEMORY_USAGE_MB);
					//Safeguard::checkIfMaxIterationsReached( $iteration, self::MAX_CPU_ITERATIONS );
				}

				$mediumLoadWork = $this->performMediumOperations($iteration);
				$intensiveLoadWork = $this->performIntensiveOperations($iteration);

				$generatedString = Utility::get_random_string(200);

				if ($this->isPeriodic($iteration)) {
					$checksum = $this->updateChecksum($checksum, $generatedString);
				}
			}
		} catch (\WPBench\Exceptions\MaxIterationsReached $e) { // Or your namespaced Exception
			$error = "Max iterations exceeded during ". self::BENCHMARK_NAME .': ' . $e->getMessage();
		} catch (\Exception $exception) {
			$error = "Exception ". $exception->getLine() ." caught during ". self::BENCHMARK_NAME .": ". $exception->getMessage();
		} finally {
			if ($error) {
				Logger::log($error, 'error');

				return $this->buildResult(0,$error);
			}
		}

		return $this->buildResult(
			round(microtime(true) - $startTime, 4),
			$error
		);
	}

	private function performMediumOperations(int $iteration): float {
		$valueA = sqrt(($iteration + 1) * M_PI);
		$valueB = log($valueA + 1);
		return sin($valueB) * cos($valueA);
	}

	private function performIntensiveOperations(int $iteration): void {
		$workload = $iteration * self::WORKLOAD_MULTIPLIER;
		$sum = 0;

		for ($i = 1; $i <= $workload; $i++) {
			$value = pow($i, 3) / sqrt($i + 1) * sin($i) * cos($i);
			$sum += log(abs($value) + 1);
		}

		for ($i = 1; $i <= $workload; $i++) {
			for ($j = 1; $j <= 50; $j++) {
				$temp = ($i * $j) / ($j + 1);
				$sum += tanh($temp);
			}
		}
	}

	private function isPeriodic(int $iteration): bool {
		return $iteration % self::PERIODIC_INTERVAL === 0;
	}

	private function updateChecksum(int $checksum, string $value): int {
		return $checksum + crc32($value);
	}

	public function run_old( mixed $value ) : array {
		$totalIterations = absint($value);

		if ( $totalIterations <= 0 ) {
			return $this->buildResult(0, 'Invalid iteration count.');

		}

		$startTime = microtime(true);
		$error = null;
		$checksum = 0;

		try {
			for ( $iteration = 0; $iteration < $totalIterations; $iteration++ ) {
				$result = 0;
				$result += $this->performMediumOperations($iteration);

				$this->performIntensiveOperations($iteration);

				$generatedString = Utility::get_random_string( 200, RandomStringTypes::Alphanumeric);

				if ( $this->isPeriodic($iteration) ) {
					$checksum = $this->updateChecksum($checksum, $generatedString);
				}
			}
		} catch (\Throwable $exception) {
			Logger::log("Error during CPU test: ". $exception->getMessage(), 'error');
		}

		return [
			'time' => round(microtime(true) - $startTime, 4),
			'error' => $error,
		];
	}

	public function checkSystemHealth() {
		// TODO: Implement checkSystemHealth() method.
	}

}