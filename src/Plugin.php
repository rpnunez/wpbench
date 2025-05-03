<?php
namespace WPBench;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main Plugin Class for WPBench.
 * Handles initialization, hooks, activation, deactivation.
 */
final class Plugin {

    /**
     * Plugin instance.
     * @var Plugin|null
     */
    private static $instance = null;

    /** @var CustomPostType */
    private $cpt_handler;

    /** @var AdminBenchmark */
    private $admin_handler;

    /** @var Assets */
    private $assets_handler;

    /**
     * Private constructor to prevent direct instantiation.
     * Initializes handlers and registers hooks via loadPlugin().
     */
    private function __construct() {
        $this->cpt_handler = new CustomPostType();
        $this->admin_handler = new AdminBenchmark();
        $this->assets_handler = new Assets();
        $this->loadPlugin();
    }

    /**
     * Ensures only one instance of the plugin class is loaded.
     *
     * @return Plugin
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Loads the plugin by registering hooks and filters.
     */
    private function loadPlugin() {
        // Register Custom Post Type
        add_action( 'init', [ $this->cpt_handler, 'register' ] );

        // Add Admin Menus
        add_action( 'admin_menu', [ $this->admin_handler, 'add_admin_menu' ] );

        // Enqueue Scripts & Styles
        add_action( 'admin_enqueue_scripts', [ $this->assets_handler, 'enqueue_admin_scripts' ] );

        // AJAX Handler for running benchmarks
        add_action( 'wp_ajax_wpbench_run_benchmark', [ $this->admin_handler, 'handle_ajax_run_benchmark' ] );

        // Add Meta Box for displaying results
        add_action( 'add_meta_boxes_benchmark_result', [ $this->admin_handler, 'add_results_meta_box' ] );

        // Customize CPT list table columns
        add_filter( 'manage_benchmark_result_posts_columns', [ $this->admin_handler, 'set_custom_edit_benchmark_result_columns' ] );
        add_action( 'manage_benchmark_result_posts_custom_column', [ $this->admin_handler, 'custom_benchmark_result_column' ], 10, 2 );

        // Save additional data (like active plugins) when CPT is saved
        // Priority 10, accepts 2 arguments ($post_id, $post)
        add_action( 'save_post_benchmark_result', [ $this->admin_handler, 'save_active_plugins_list' ], 10, 2 );
    }

    /**
     * Plugin Activation Tasks.
     * e.g., Flush rewrite rules if CPT is public.
     */
    public static function activatePlugin() {
        // Ensure CPT is registered before flushing
        $cpt_handler = new CustomPostType();
        $cpt_handler->register();

        // Flush rewrite rules to ensure CPT permalinks work (if they were public)
        flush_rewrite_rules();

         // Create dummy JS files on activation if they don't exist
         $js_dir = WPBENCH_PATH . 'js/';
         $js_admin_path = $js_dir . 'admin-benchmark.js';
         $js_results_path = $js_dir . 'admin-results.js';
         if (!is_dir($js_dir)) { wp_mkdir_p($js_dir); }
         if (!file_exists($js_admin_path)) { @file_put_contents($js_admin_path, '// WPBench Admin Benchmark JS - Created on Activation'); }
         if (!file_exists($js_results_path)) { @file_put_contents($js_results_path, '// WPBench Admin Results JS - Created on Activation'); }

        // Other activation tasks (e.g., setting default options) can go here
    }

    /**
     * Plugin Deactivation Tasks.
     * e.g., Cleanup scheduled tasks.
     */
    public static function deactivatePlugin() {
        // Flush rewrite rules on deactivation if CPT was public
         flush_rewrite_rules();

        // Other deactivation tasks can go here
    }

    /**
     * Plugin Uninstall Tasks. (SHOULD BE IN uninstall.php)
     * Warning: This runs when the user deletes the plugin. Be careful!
     * It's generally better practice to put uninstall logic in `uninstall.php`.
     */
    public static function uninstallPlugin() {
        // Check if the user intended to uninstall (security check)
        if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
            exit;
        }

        // Example: Delete custom options associated with the plugin
        // delete_option('wpbench_settings');

        // Example: Delete benchmark result posts (use with extreme caution!)
        /*
        $args = array(
            'post_type' => 'benchmark_result',
            'posts_per_page' => -1, // Get all posts
            'post_status' => 'any', // Include trash
            'fields' => 'ids' // Only get IDs for efficiency
        );
        $benchmark_posts = get_posts($args);
        if (!empty($benchmark_posts)) {
            foreach ($benchmark_posts as $post_id) {
                wp_delete_post($post_id, true); // true = force delete, bypass trash
            }
        }
        */

        // Example: Delete temporary tables (if any were left undeleted - should not happen)
        // global $wpdb;
        // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wpbench_temp_test");
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {}
}