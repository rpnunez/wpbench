<?php
namespace WPBench;

// Required classes (ensure autoloader works for these)
use WPBench\AdminBenchmark;
use WPBench\BenchmarkProfileAdmin;
use WPBench\PluginState;
use WPBench\PluginStateView;
use WPBench\PluginManager;
use WPBench\TestRegistry;
use WPBench\WPBenchSettings;
use WPBench\Assets;
use WPBench\Logger;
use WP_Error;
use WP_Post;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Represents the core Plugin class to initialize and manage the plugin functionality.
 *
 * Implements the Singleton design pattern to ensure only one instance exists during execution.
 */
final class Plugin {

    protected static $instance;
    private \WPBench\PluginState $pluginStateHandler;
    private \WPBench\PluginStateView $pluginStateViewHandler;
	private \WPBench\PluginManager $pluginManager;
    private \WPBench\Assets $assetsHandler;
    private \WPBench\AdminBenchmark $adminBenchmarkHandler;
    private \WPBench\BenchmarkProfileAdmin $adminProfileHandler;
	private \WPBench\BenchmarkScore $benchmarkScore;
    private \WPBench\TestRegistry $testRegistry;
	private \WPBench\WPBenchSettings $settingsHandler;

    /**
     * Constructs the main plugin object.
     * Initializes core utilities, views, managers, and controllers required by the plugin.
     * Establishes dependencies between components and ensures necessary resources are loaded.
     *
     * This constructor is private to enforce singleton design patterns or controlled instantiation elsewhere in the plugin.
     *
     * @return void
     */
    private function __construct() {
        // Instantiate core utilities first
        $this->testRegistry = new TestRegistry();
        $this->pluginStateHandler = new PluginState();
	    $this->settingsHandler = new WPBenchSettings();

        $this->pluginStateViewHandler = new PluginStateView(
			$this->pluginStateHandler,
			$this->testRegistry
        );
        $this->pluginManager = new PluginManager(); // Contains risky operations

	    $this->benchmarkScore = new BenchmarkScore();

        // Instantiate asset handler
        $this->assetsHandler = new Assets();

        // Instantiate main admin controllers, passing dependencies
        $this->adminBenchmarkHandler = new AdminBenchmark(
			$this->pluginManager,
			$this->pluginStateHandler,
			$this->pluginStateViewHandler,
			$this->testRegistry,
			$this->benchmarkScore
        );

        $this->adminProfileHandler = new BenchmarkProfileAdmin(
			$this->pluginStateHandler,
			$this->pluginStateViewHandler,
			$this->testRegistry
        );

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
     * Loads and initializes the plugin's functionality.
     * This method sets up necessary WordPress hooks for registering custom post types, meta fields,
     * admin menus, scripts, AJAX handlers, and custom columns in the admin area. It also establishes
     * actions required for handling plugin state and result data.
     *
     * @return void
     */
    private function loadPlugin() {
        // --- Register Custom Post Types ---
        // These methods now reside in the Admin classes
        add_action( 'init', [ $this->adminBenchmarkHandler, 'register_cpt' ] );
        add_action( 'init', [ $this->adminProfileHandler, 'register_cpt' ] );

        // --- Register Meta Fields ---
        // Hooking late on init ensures CPTs and potentially TestRegistry are ready
        add_action( 'init', [ $this->adminBenchmarkHandler, 'register_meta_fields' ], 20 );
        add_action( 'init', [ $this->adminProfileHandler, 'register_meta_fields' ], 20 );

        // --- Admin Menu ---
        add_action( 'admin_menu', [ $this->adminBenchmarkHandler, 'add_admin_menu' ] );

	    // --- Initialize Settings Page ---
	    $this->settingsHandler->init();

        // --- Profile Admin Hooks ---
        // Use class constants for post type hooks
        add_action( 'add_meta_boxes_' . BenchmarkProfileAdmin::POST_TYPE, [ $this->adminProfileHandler, 'add_profile_meta_boxes' ] );
        add_action( 'save_post_' . BenchmarkProfileAdmin::POST_TYPE, [ $this->adminProfileHandler, 'save_profile_meta' ], 10, 2 );

        // --- Assets ---
        add_action( 'admin_enqueue_scripts', [ $this->assetsHandler, 'enqueue_admin_scripts' ] );

        // --- AJAX Handlers ---
        add_action( 'wp_ajax_wpbench_run_benchmark', [ $this->adminBenchmarkHandler, 'handle_ajax_request' ] );
    }

    /**
     * Activates the plugin by registering custom post types (CPTs), flushing rewrite rules,
     * and performing initial setup tasks such as creating dummy JavaScript files.
     *
     * This method ensures that the CPTs are registered before flushing rewrite rules,
     * allowing WordPress to recognize them immediately. Additionally, it handles
     * the creation of required JavaScript files if they do not already exist.
     *
     * @return void
     */
    public static function activatePlugin() {
        // Instantiate admin classes *only* to call their CPT registration methods.
        // This ensures the CPTs are known to WordPress before flushing rules.
        // We avoid calling register_meta_fields here as it's not needed for flushing
        // and might have unmet dependencies in this static context.
        $AdminBenchmarkTmp = new AdminBenchmark();
        $AdminBenchmarkTmp->register_cpt();

        $AdminProfileTemp = new BenchmarkProfileAdmin();
        $AdminProfileTemp->register_cpt();

        // Flush rewrite rules to recognize the CPTs immediately
        flush_rewrite_rules();

        // Other potential activation tasks: set default options, schedule cron jobs, etc.

	    // Set default options on activation
	    if ( false === get_option( WPBenchSettings::OPTION_NAME ) ) {
	        update_option( WPBenchSettings::OPTION_NAME, WPBenchSettings::get_defaults() );
	    }
    }

    /**
     * Plugin Deactivation Tasks.
     * Runs once when the plugin is deactivated.
     *
     * @used-by register_activation_hook
     */
    public static function deactivatePlugin() {
        // Flush rewrite rules to remove CPT rules if plugin is deactivated
        flush_rewrite_rules();

        // Other deactivation tasks: unschedule cron jobs, remove temporary data (but not settings/posts usually)
    }

    /**
     * Placeholder for Plugin Uninstall Tasks. (SHOULD BE IN uninstall.php)
     * Static method - not typically called directly. Use uninstall.php instead.
     *
     * @used-by register_deactivation_hook
     */
    public static function uninstallPlugin() {
        // Check if the user intended to uninstall
        if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
            exit;
        }

        // Delete options, CPT posts (with caution!), custom tables etc.
    }

    // --- Singleton pattern guards ---
    private function __clone() {}

    public function __wakeup() {
	    Logger::log("Unserializing is not allowed.", E_USER_ERROR);
    }
    // --- End Singleton pattern guards ---

} // End Class Plugin