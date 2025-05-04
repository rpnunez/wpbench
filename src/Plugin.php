<?php
namespace WPBench;

// Required classes (ensure autoloader works for these)
use WPBench\AdminBenchmark;
use WPBench\BenchmarkProfileAdmin;
use WPBench\CustomPostType; // Keep for reference if needed, but registration moved
use WPBench\ProfileCPT;    // Keep for reference if needed, but registration moved
use WPBench\PluginState;
use WPBench\PluginStateView;
use WPBench\PluginManager;
use WPBench\TestRegistry;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main Plugin Class for WPBench.
 * Handles initialization, hooks, activation, deactivation. Orchestrates other classes.
 */
final class Plugin {

    private static $instance = null;
    private $plugin_state_handler;
    private $assets_handler;
    private $admin_benchmark_handler;
    private $admin_profile_handler;
    private $testRegistry;


    /**
     * Private constructor. Sets up handlers and registers hooks.
     */
    private function __construct() {
        // Instantiate core utilities first
        $this->testRegistry = new TestRegistry();
        $this->plugin_state_handler = new PluginState();

        // Instantiate view/manager that might depend on core utilities
        $pluginStateView = new PluginStateView($this->plugin_state_handler, $this->testRegistry);
        $pluginManager = new PluginManager(); // Contains risky operations

        // Instantiate asset handler
        $this->assets_handler = new Assets();

        // Instantiate main admin controllers, passing dependencies
        $this->admin_benchmark_handler = new AdminBenchmark($this->plugin_state_handler, $pluginStateView, $this->testRegistry, $pluginManager);
        $this->admin_profile_handler = new BenchmarkProfileAdmin($this->plugin_state_handler, $pluginStateView, $this->testRegistry);


        $this->loadPlugin();
    }

    /**
     * Singleton instance getter.
     * @return Plugin
     */
    public static function instance() : Plugin {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Loads the plugin by registering hooks and filters, delegating to handler classes.
     */
    private function loadPlugin() {
        // --- Register Custom Post Types ---
        // These methods now reside in the Admin classes
        add_action( 'init', [ $this->admin_benchmark_handler, 'register_cpt' ] );
        add_action( 'init', [ $this->admin_profile_handler, 'register_cpt' ] );

        // --- Register Meta Fields ---
        // Hooking late on init ensures CPTs and potentially TestRegistry are ready
        add_action( 'init', [ $this->admin_benchmark_handler, 'register_meta_fields' ], 20 );
        add_action( 'init', [ $this->admin_profile_handler, 'register_meta_fields' ], 20 );

        // --- Admin Menu ---
        add_action( 'admin_menu', [ $this->admin_benchmark_handler, 'add_admin_menu' ] );

        // --- Profile Admin Hooks ---
        // Use class constants for post type hooks
        add_action( 'add_meta_boxes_' . BenchmarkProfileAdmin::POST_TYPE, [ $this->admin_profile_handler, 'add_profile_meta_boxes' ] );
        add_action( 'save_post_' . BenchmarkProfileAdmin::POST_TYPE, [ $this->admin_profile_handler, 'save_profile_meta' ], 10, 2 );

        // --- Assets ---
        add_action( 'admin_enqueue_scripts', [ $this->assets_handler, 'enqueue_admin_scripts' ] );

        // --- AJAX Handlers ---
        add_action( 'wp_ajax_wpbench_run_benchmark', [ $this->admin_benchmark_handler, 'handle_ajax_run_benchmark' ] );
        add_action( 'wp_ajax_wpbench_load_profile', [ $this->admin_profile_handler, 'handle_ajax_load_profile' ] );

        // --- Result Admin Hooks ---
        // Use class constants for post type hooks
        add_action( 'add_meta_boxes_' . AdminBenchmark::POST_TYPE, [ $this->admin_benchmark_handler, 'add_results_meta_box' ] );
        add_filter( 'manage_' . AdminBenchmark::POST_TYPE . '_posts_columns', [ $this->admin_benchmark_handler, 'set_custom_edit_benchmark_result_columns' ] );
        add_action( 'manage_' . AdminBenchmark::POST_TYPE . '_posts_custom_column', [ $this->admin_benchmark_handler, 'custom_benchmark_result_column' ], 10, 2 );
        // Hook points to method in PluginState, but uses the Result CPT constant from AdminBenchmark
        add_action( 'save_post_' . AdminBenchmark::POST_TYPE, [ $this->plugin_state_handler, 'saveActualPluginsListHook' ], 10, 2 );
    }

    /**
     * Plugin Activation Tasks.
     * Runs once when the plugin is activated.
     * Static method called by register_activation_hook.
     */
    public static function activatePlugin() {
        // Instantiate admin classes *only* to call their CPT registration methods.
        // This ensures the CPTs are known to WordPress before flushing rules.
        // We avoid calling register_meta_fields here as it's not needed for flushing
        // and might have unmet dependencies in this static context.
        $admin_benchmark_temp = new AdminBenchmark();
        $admin_benchmark_temp->register_cpt();

        $admin_profile_temp = new BenchmarkProfileAdmin();
        $admin_profile_temp->register_cpt();

        // Flush rewrite rules to recognize the CPTs immediately
        flush_rewrite_rules();

        // --- Create dummy JS files on activation if they don't exist ---
        // Moved from wpbench.php
        $js_dir = WPBENCH_PATH . 'js/';
        $js_admin_path = $js_dir . 'admin-benchmark.js';
        $js_results_path = $js_dir . 'admin-results.js';
        if (!is_dir($js_dir)) { @wp_mkdir_p($js_dir); } // Use @ to suppress errors if exists/perms issue
        if (!file_exists($js_admin_path)) { @file_put_contents($js_admin_path, '// WPBench Admin Benchmark JS - Autocreated on Activation'); }
        if (!file_exists($js_results_path)) { @file_put_contents($js_results_path, '// WPBench Admin Results JS - Autocreated on Activation'); }
        // --- End Dummy JS File Creation ---
        // Other potential activation tasks: set default options, schedule cron jobs, etc.
    }

    /**
     * Plugin Deactivation Tasks.
     * Runs once when the plugin is deactivated.
     * Static method called by register_deactivation_hook.
     */
    public static function deactivatePlugin() {
        // Flush rewrite rules to remove CPT rules if plugin is deactivated
         flush_rewrite_rules();

        // Other deactivation tasks: unschedule cron jobs, remove temporary data (but not settings/posts usually)
    }

    /**
     * Placeholder for Plugin Uninstall Tasks. (SHOULD BE IN uninstall.php)
     * Static method - not typically called directly. Use uninstall.php instead.
     */
    public static function uninstallPlugin() {
        // Check if the user intended to uninstall
        if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
        // Delete options, CPT posts (with caution!), custom tables etc.
    }

    // --- Singleton pattern guards ---
    private function __clone() {}
    public function __wakeup() {
         trigger_error("Unserializing is not allowed.", E_USER_ERROR);
    }
    // --- End Singleton pattern guards ---

} // End Class Plugin