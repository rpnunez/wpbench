<?php
namespace WPBench\Guards;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Safeguards related to protecting the WPBench plugin itself.
 */
class PluginGuard {

	private static null|string $WPBenchPluginPath = null;

	/**
	 * Get the main WPBench plugin file path (e.g., wpbench/wpbench.php).
	 * Caches the result for efficiency.
	 *
	 * @return string|null The plugin's basename or null if WPBENCH_PATH not defined.
	 */
	public static function getWPBenchPluginFile() : ?string {
		if ( self::$WPBenchPluginPath === null) {
			self::$WPBenchPluginPath = plugin_basename( WPBENCH_PATH . 'wpbench.php');
		}

		return self::$WPBenchPluginPath;
	}

	/**
	 * Checks if a given plugin file is the WPBench main plugin file.
	 *
	 * @param string $pluginFile The plugin file path to check.
	 * @return bool True if it's the WPBench plugin, false otherwise.
	 */
	public static function isWPBenchPlugin(string $pluginFile) : bool {
		$selfFile = self::getWPBenchPluginFile();

		if (empty($selfFile)) {
			return false;
		}

		return $pluginFile === $selfFile;
	}

	/**
	 * Removes the WPBench plugin from an array of plugin files intended for deactivation.
	 *
	 * @param array $pluginsToDeactivate Array of plugin file paths.
	 * @return array Filtered array.
	 */
	public static function filterWPBenchFromDeactivationList(array $pluginsToDeactivate) : array {
		$selfFile = self::getWPBenchPluginFile();

		if (empty($selfFile) || empty($pluginsToDeactivate)) {
			return $pluginsToDeactivate;
		}

		return array_values(array_diff($pluginsToDeactivate, [$selfFile]));
	}

	/**
	 * Removes the WPBench plugin from an array of plugin files intended for activation.
	 *
	 * @param array $pluginsToActivate Array of plugin file paths.
	 * @return array Filtered array.
	 */
	public static function filterWPBenchFromActivationList(array $pluginsToActivate) : array {
		$selfFile = self::getWPBenchPluginFile();

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
	public static function ensureWPBenchInDesiredList(array $desiredPlugins) : array {
		$selfFile = self::getWPBenchPluginFile();

		if (!empty($selfFile) && !in_array($selfFile, $desiredPlugins)) {
			$desiredPlugins[] = $selfFile;
		}

		return array_unique($desiredPlugins);
	}
}