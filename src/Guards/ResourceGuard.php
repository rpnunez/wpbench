<?php
namespace WPBench\Guards;

// Assuming these are correctly located. Adjust 'use' statements if your Exception/Logger are namespaced differently.
use WPBench\Exceptions\MaxIterationsReached; // Or e.g., WPBench\Exceptions\MaxIterationsReached
use WPBench\Logger;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Safeguards related to resource limits for benchmark tests.
 */
class ResourceGuard {

	/**
	 * Maximum number of iterations when running individual BenchmarkTests.
	 */
	public const int BENCHMARK_TEST_MAX_ITERATIONS = 25000;

	public static function checkIfMaxIterationsReached( int $localIterations, int $overrideMax = null ): bool {
		$maxIterations = $overrideMax ?? self::BENCHMARK_TEST_MAX_ITERATIONS;

		if ($localIterations > $maxIterations) {
			$log = "Maximum number of iterations reached: $localIterations (Max: $maxIterations)";

			if (class_exists(Logger::class)) { // Ensure Logger class exists
				Logger::log($log, 'warning');
			} else {
				error_log("WPBench ResourceGuard: Logger class not found. " . $log);
			}

			throw new MaxIterationsReached($log);
		}

		return false;
	}

	public static function checkIfMaxExecutionTimeReached( int $startTime, int $maxExecutionTime ): bool {
		$executionTime = microtime(true) - $startTime;

		if ($executionTime > $maxExecutionTime) {
			$log = "Maximum execution time reached: $executionTime seconds (Max: $maxExecutionTime)";
			if (class_exists(Logger::class)) {
				Logger::log($log, 'warning');
			} else {
				error_log("WPBench ResourceGuard: Logger class not found. " . $log);
			}
			return true;
		}
		return false;
	}

	public static function checkIfMaxMemoryReached( int $memoryUsage, int $maxMemory ): bool {
		if ($memoryUsage > $maxMemory) {
			$log = "Maximum memory usage reached: " . round($memoryUsage / 1024 / 1024, 2) . "MB (Max: " . round($maxMemory / 1024 / 1024, 2) . "MB)";
			if (class_exists(Logger::class)) {
				Logger::log($log, 'warning');
			} else {
				error_log("WPBench ResourceGuard: Logger class not found. " . $log);
			}
			return true;
		}
		return false;
	}

	public static function checkIfCPULoadReached( int $cpuUsage, int $maxCPU = 75 ): bool {
		// Note: $cpuUsage source needs to be defined elsewhere.
		if ($cpuUsage > $maxCPU) {
			$log = "Maximum CPU usage threshold reached: $cpuUsage% (Threshold: $maxCPU%)";
			if (class_exists(Logger::class)) {
				Logger::log($log, 'warning');
			} else {
				error_log("WPBench ResourceGuard: Logger class not found. " . $log);
			}
			return true;
		}
		return false;
	}
}