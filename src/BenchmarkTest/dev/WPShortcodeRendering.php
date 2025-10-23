<?php
namespace src\BenchmarkTest\dev;

use WPBench\BenchmarkTest\BaseBenchmarkTest;
use WPBench\Guards\ResourceGuard;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPShortcodeRendering implements BaseBenchmarkTest {

	/**
	 * Retrieves the singleton instance of the WPShortcodeRendering class.
	 *
	 * @return WPShortcodeRendering|null The singleton instance of the WPShortcodeRendering class, or null if an instance is not created.
	 */
	public function getInstance(): ?WPShortcodeRendering {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	public function getInfo() : array {
		return [
			'id'            => 'wp_shortcode_rendering',
			'name'          => __('Shortcode Rendering Stress Test', 'wpbench'),
			'description'   => __('Registers temporary simple and complex shortcodes, then renders content containing them multiple times.', 'wpbench'),
			'config_label'  => __('Number of Content Renderings', 'wpbench'),
			'config_unit'   => __('renderings', 'wpbench'),
			'default_value' => 500,
			'min_value'     => 50,
			'max_value'     => 10000,
		];
	}

	public function run( $value ) : array {
		$renderings = absint($value);
		if ($renderings <= 0) {
			return ['time' => 0, 'operations' => 0, 'error' => 'Invalid rendering count.'];
		}

		$start_time = microtime(true);
		$error_message = null;
		$total_operations = 0; // Number of times do_shortcode is called

		// Unique shortcode tags for this run
		$run_id = uniqid();
		$simple_tag = 'wpbench_simple_sc_' . $run_id;
		$complex_tag = 'wpbench_complex_sc_' . $run_id;

		try {
			ResourceGuard::checkIfMaxIterationsReached($renderings, 20000);

			// 1. Register Shortcodes
			add_shortcode($simple_tag, function() {
				static $i=0; $i++; // Minimal work
				return "Simple![$i]";
			});

			add_shortcode($complex_tag, function($atts, $content = null) {
				static $j=0; $j++;
				$atts = shortcode_atts(['num1' => 5, 'num2' => 10], $atts, $complex_tag);
				$result = (int)$atts['num1'] * (int)$atts['num2'];
				$output = "Complex[$j]: $result";
				if ($content) {
					$output .= " - Content: " . esc_html($content);
				}
				return $output;
			});

			// 2. Prepare content string with multiple shortcodes
			$content_string = "Hello [{$simple_tag}], this is a test. Value: [{$complex_tag} num1='7' num2='3']Inner Content[/{$complex_tag}]. Repeat: [{$simple_tag}]. And another [{$complex_tag}].";

			// 3. Render content multiple times
			for ($i = 0; $i < $renderings; $i++) {
				$rendered_output = do_shortcode($content_string);
				$total_operations++;
				// Optionally, check $rendered_output for basic validity if needed
			}

		} catch (\Throwable $e) {
			$error_message = get_class($e) . ': ' . $e->getMessage();
		} finally {
			// 4. Cleanup: Remove shortcodes
			remove_shortcode($simple_tag);
			remove_shortcode($complex_tag);
		}

		$end_time = microtime(true);

		return [
			'time'         => round($end_time - $start_time, 4),
			'renderings'   => $renderings, // Number of times do_shortcode was called
			'operations'   => $total_operations,
			'error'        => $error_message,
		];
	}

	public function calculateScore( array $test_results, array $config ): array {
		// TODO: Implement calculateScore() method.

		return [];
	}
}