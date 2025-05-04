<?php
/**
 * Plugin Name:       WPBench
 * Plugin URI:        https://example.com/wpbench-plugin-uri/
 * Description:       Plugin that benchmarks and stress-tests your current WordPress site, allowing you to see what's slowing down your website.
 * Version:           1.0.2  <-- Incremented version
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Author:            Your Name Here
 * Author URI:        https://example.com/author-uri/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpbench
 * Domain Path:       /languages
 * Namespace:         WPBench
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --- Define Constants ---
// Ensure constants are defined only once
if ( ! defined( 'WPBENCH_VERSION' ) ) {
    define( 'WPBENCH_VERSION', '1.0.2' );
}

if ( ! defined( 'WPBENCH_PATH' ) ) {
    define( 'WPBENCH_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WPBENCH_URL' ) ) {
    define( 'WPBENCH_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WPBENCH_BASE_NAMESPACE' ) ) {
    define( 'WPBENCH_BASE_NAMESPACE', 'WPBench\\' );
}

// --- Register Autoloader ---
// Ensures classes in src/ are loaded automatically
spl_autoload_register( function( $class ) {
    // Project-specific namespace prefix
    $prefix = WPBENCH_BASE_NAMESPACE;
    $base_dir = WPBENCH_PATH . 'src/';
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return; // Not our namespace
    }
    $relative_class = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
    if ( file_exists( $file ) ) {
        require $file;
    }
});

// --- Instantiate the Plugin ---
/**
 * Begins execution of the plugin.
 *
 * Instantiates the main plugin class only after all plugins are loaded
 * to prevent conflicts and ensure WordPress functions are available.
 */
function wpbench_run_plugin() {
    // Ensure the main class exists via autoloader before calling instance()
    if ( class_exists( WPBENCH_BASE_NAMESPACE . 'Plugin' ) ) {
        \WPBench\Plugin::instance(); // Get the singleton instance
    } else {
        // Log an error or add an admin notice if the class failed to load
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error is-dismissible"><p>';
            esc_html_e( 'WPBench plugin critical error: Main plugin class failed to load. Check file structure and autoloader.', 'wpbench' );
            echo '</p></div>';
        });
        
        error_log('WPBench Error: Failed to load main plugin class ' . WPBENCH_BASE_NAMESPACE . 'Plugin');
    }
}

// Use the 'plugins_loaded' hook to ensure everything is ready
add_action( 'plugins_loaded', 'wpbench_run_plugin' );

// --- Register Activation / Deactivation Hooks ---
// These hooks MUST point to static methods or global functions defined BEFORE the hook is registered. They
register_activation_hook( __FILE__, [ WPBENCH_BASE_NAMESPACE . 'Plugin', 'activatePlugin' ] );
register_deactivation_hook( __FILE__, [ WPBENCH_BASE_NAMESPACE . 'Plugin', 'deactivatePlugin' ] );