<?php

namespace WPBench\Helpers;

use src\Exceptions\MaxIterationsReached;
use WPBench\Logger;

class Safeguard {

	/**
	 * Maximum number of iterations when running individual BenchmarkTests.
	 */
	public const int BENCHMARK_TEST_MAX_ITERATIONS = 25000;

	public static function checkIfMaxIterationsReached( int $localIterations, int $overrideMax = null ): bool {
		$maxIterations = $overrideMax ?? self::BENCHMARK_TEST_MAX_ITERATIONS;

		if ($localIterations > $maxIterations) {
			$log = "Maximum number of iterations reached: $localIterations";
			Logger::log($log, 'info');

			throw new MaxIterationsReached($log);
		}

		return false;
	}

	public static function checkIfMaxExecutionTimeReached( int $startTime, int $maxExecutionTime ): bool {
		$executionTime = microtime(true) - $startTime;

		if ($executionTime > $maxExecutionTime) {
			$log = "Maximum execution time reached: $executionTime";
			Logger::log($log, 'info');

			return true;
		}

		return false;
	}

	public static function checkIfMaxMemoryReached( int $memoryUsage, int $maxMemory ): bool {
		if ($memoryUsage > $maxMemory) {
			$log = "Maximum memory usage reached: $memoryUsage";
			Logger::log($log, 'info');

			return true;
		}

		return false;
	}

	public static function checkIfCPULoadReached( int $cpuUsage, int $maxCPU = 75 ): bool {
		if ($cpuUsage > $maxCPU) {
			$log = "Maximum CPU usage reached: $cpuUsage";
			Logger::log($log, 'info');

			return true;
		}

		return false;
	}

}