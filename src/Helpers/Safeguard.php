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

	public static function ensurePluginSelfProtection(array $toActivate, array $toDeactivate, string $pluginBaseName): array {
		$toActivateOriginalCount = count($toActivate);
		$toDeactivateOriginalCount = count($toDeactivate);

		// Remove WPBench from activation and deactivation lists
		$toActivate = array_values(array_diff($toActivate, [$pluginBaseName]));
		$toDeactivate = array_values(array_diff($toDeactivate, [$pluginBaseName]));

		if (count($toActivate) !== $toActivateOriginalCount || count($toDeactivate) !== $toDeactivateOriginalCount) {
			Logger::log(
				'WPBench plugin itself was removed from activation/deactivation operations. Potential logic issue detected.',
				'security'
			);
		}

		return [$toActivate, $toDeactivate];
	}

	// --- New Static Methods (Plugin Self-Protection) ---

	/**
	 * Get the main WPBench plugin file path (e.g., wpbench/wpbench.php).
	 *
	 * @return string|null The plugin's basename or null if WPBENCH_PATH not defined.
	 */
	public static function getWpBenchPluginFile() : ?string {
		if (defined('WPBENCH_PATH')) {
			return plugin_basename(WPBENCH_PATH . 'wpbench.php');
		}
		error_log('WPBench Safeguard: WPBENCH_PATH constant not defined. Self-protection might not work.');
		return null;
	}

	/**
	 * Checks if a given plugin file is the WPBench main plugin file.
	 *
	 * @param string $pluginFile The plugin file path to check.
	 * @return bool True if it's the WPBench plugin, false otherwise.
	 */
	public static function isWpBenchPlugin(string $pluginFile) : bool {
		$selfFile = self::getWpBenchPluginFile();
		if (empty($selfFile)) {
			return false; // Cannot determine if path not set
		}
		return $pluginFile === $selfFile;
	}

	/**
	 * Removes the WPBench plugin from an array of plugin files intended for deactivation.
	 *
	 * @param array $pluginsToDeactivate Array of plugin file paths.
	 * @return array Filtered array.
	 */
	public static function filterWpBenchFromDeactivationList(array $pluginsToDeactivate) : array {
		$selfFile = self::getWpBenchPluginFile();
		if (empty($selfFile) || empty($pluginsToDeactivate)) {
			return $pluginsToDeactivate;
		}
		return array_values(array_diff($pluginsToDeactivate, [$selfFile]));
	}

	/**
	 * Removes the WPBench plugin from an array of plugin files intended for activation
	 * (as it should already be active).
	 *
	 * @param array $pluginsToActivate Array of plugin file paths.
	 * @return array Filtered array.
	 */
	public static function filterWpBenchFromActivationList(array $pluginsToActivate) : array {
		$selfFile = self::getWpBenchPluginFile();
		if (empty($selfFile) || empty($pluginsToActivate)) {
			return $pluginsToActivate;
		}
		return array_values(array_diff($pluginsToActivate, [$selfFile]));
	}

	/**
	 * Ensures WPBench plugin file is present in a list of desired active plugins.
	 *
	 * @param array $desiredPlugins Array of plugin file paths.
	 * @return array Array with WPBench plugin file ensured to be present.
	 */
	public static function ensureWpBenchInDesiredList(array $desiredPlugins) : array {
		$selfFile = self::getWpBenchPluginFile();
		if (!empty($selfFile) && !in_array($selfFile, $desiredPlugins)) {
			$desiredPlugins[] = $selfFile;
		}
		return array_unique($desiredPlugins); // Ensure uniqueness if already present
	}

}