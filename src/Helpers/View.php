<?php

namespace WPBench\Helpers;

class View {

	/**
	 * Render a template with customizable logic.
	 *
	 * This method renders a template file with support for overriding template paths
	 * by themes or plugins. It allows parent/child themes to override the markup by
	 * placing a file with the same name (from `basename( $default_template_path )`) in their root folder.
	 * Alternatively, plugins or themes can override the full template path via the `wpbench_template_path` filter.
	 * Both methods allow full customization of the rendered output while maintaining access to the
	 * provided variables scoped to the template. Themes and plugins can further manipulate
	 * the resulting template content through the `wpbench_template_content` filter.
	 *
	 * Hooks:
	 * - `wpbench_render_template_pre`: Fires before the template is rendered.
	 * - `wpbench_template_path`: Filters the final template file path.
	 * - `wpbench_template_content`: Filters the rendered template content.
	 * - `wpbench_render_template_post`: Fires after the template is rendered.
	 *
	 * Usage: echo render_template( $view, $variables );
	 *
	 * Origin: https://github.com/iandunn/WordPress-Plugin-Skeleton/blob/master/classes/wpps-module.php
	 *
	 * @mvc @model
	 *
	 * @param string $default_template_path The relative path to the default template file in the plugin's `views` folder.
	 *                                      Example: `'default-template.php'`
	 * @param array  $variables             (Optional) Associative array of variables to pass to the template scope.
	 *                                      The variables will be extracted in the template. Default is an empty array `[]`.
	 * @param string $require               (Optional) Whether to use `require_once()` or `require()` to include the template.
	 *                                      Accepts `'once'` (default) or `'always'`.
	 *
	 * @return string The rendered content of the template. Returns an empty string if the file cannot be located.
	 */
	public static function render_template( $default_template_path = false, $variables = array(), $require = 'once' ) {
		do_action( 'wpbench_render_template_pre', $default_template_path, $variables );

		$template_path = locate_template( basename( $default_template_path ) );
		
		if ( ! $template_path ) {
			$template_path = dirname( __DIR__ ) . '/views/' . $default_template_path;
		}

		$template_path = apply_filters( 'wpbench_template_path', $template_path );

		if ( is_file( $template_path ) ) {
			extract( $variables );
			ob_start();

			if ( 'always' == $require ) {
				require( $template_path );
			} else {
				require_once( $template_path );
			}

			$template_content = apply_filters( 'wpbench_template_content', ob_get_clean(), $default_template_path, $template_path, $variables );
		} else {
			$template_content = '';
		}

		do_action( 'wpbench_render_template_post', $default_template_path, $variables, $template_path, $template_content );

		return $template_content;
	}
}