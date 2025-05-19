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

	public static function log( $message, $level = 'debug', ...$args ): void {
		$line = "[". WPBENCH_NAME ." - ". time() ." - Class: ". $args[0] .", Method: ". $args[1]. " | ". $level ."] ". $message;

		// Attempt to write to Query Monitor log
		if ($level && isSet(self::$levels[$level])) {
			do_action( 'qm/'. $level, $line );
		}

		// Write to WordPress debug log
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG == true ) {
			error_log( $line );
		}
	}
}
