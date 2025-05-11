<?php

namespace WPBench;
class Logger {
	private static array $levels = [
		'debug',
		'info',
		'notice',
		'warning',
		'error',
		'critical',
		'alert',
		'emergency'
	];

	public static function log( $message, $level = 'debug' ): void {
		// Attempt to write to Query Monitor log
		if ($level && isSet(self::$levels[$level])) {
			do_action( 'qm/'. $level, $message );
		}

		// Write to WordPress debug log
		if (
			defined( 'WP_DEBUG' ) && WP_DEBUG &&
			defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG
		) {
			$prefix = "[WPBench][$level]: ";
			error_log( $prefix . $message );
		}
	}
}
