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

	public static function log( $message, $level = 'debug'): void {
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 );

		if ( isset( $backtrace[1] ) ) {
			$frame      = $backtrace[1];
			$className  = $frame['class'] ?? 'Unknown Class';
			$methodName = $frame['function'] ?? 'Unknown Method';
			$fileName =
				isset( $frame['file'] ) ? '/wp-content/plugins/' . explode( '/wp-content/plugins/', $frame['file'], 2 )[1] : 'Unknown File';
			$lineNumber = $frame['line'] ?? 'Unknown Line';
			
			

			$line = "[" . WPBENCH_NAME . " - " . time() . " - Class: " . $className .
			        ", Method: " . $methodName . ", File: " . $fileName .
			        ", Line: " . $lineNumber . " | " . $level . "] " . $message;
		} else {
			$line = "[" . WPBENCH_NAME . " - " . time() . " | " . $level . "] " . $message;
		}

		// Append new line at end of log line
		$line .= PHP_EOL . "\n\n";

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
