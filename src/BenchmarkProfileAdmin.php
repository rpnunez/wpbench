<?php
namespace WPBench;

// Required classes
use WPBench\PluginState;
use WPBench\PluginStateView;
use WPBench\TestRegistry;
use WP_Error;
use WP_Post;
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the Admin UI (Meta Boxes, Saving) for the Benchmark Profile CPT.
 */
class BenchmarkProfileAdmin {

    const POST_TYPE = 'benchmark_profile';

    // Meta Keys specific to Benchmark Profiles
    const META_SELECTED_TESTS = '_wpbench_profile_selected_tests';
    const META_CONFIG_PREFIX = '_wpbench_profile_config_'; // Prefix for config values

    private $pluginState;
    private $pluginStateView;
    private $testRegistry;

    public function __construct(PluginState $pluginState = null, PluginStateView $pluginStateView = null, TestRegistry $testRegistry = null) {
        $this->pluginState = $pluginState ?? new PluginState();
        $this->testRegistry = $testRegistry ?? new TestRegistry();
        // Ensure PluginStateView gets the correct dependencies
        $this->pluginStateView = $pluginStateView ?? new PluginStateView($this->pluginState, $this->testRegistry);
    }

    /**
     * Register the Benchmark Profile Custom Post Type.
     * Hooked into 'init'.
     */
    public function register_cpt() {
        $labels = [
            'name'                  => _x( 'Benchmark Profiles', 'Post type general name', 'wpbench' ),
            'singular_name'         => _x( 'Benchmark Profile', 'Post type singular name', 'wpbench' ),
            'menu_name'             => _x( 'Profiles', 'Admin Menu text', 'wpbench' ),
            'name_admin_bar'        => _x( 'Benchmark Profile', 'Add New on Toolbar', 'wpbench' ),
            'add_new'               => __( 'Add New Profile', 'wpbench' ),
            'add_new_item'          => __( 'Add New Benchmark Profile', 'wpbench' ),
            'new_item'              => __( 'New Benchmark Profile', 'wpbench' ),
            'edit_item'             => __( 'Edit Benchmark Profile', 'wpbench' ),
            'view_item'             => __( 'View Benchmark Profile', 'wpbench' ),
            'all_items'             => __( 'Benchmark Profiles', 'wpbench' ),
            'search_items'          => __( 'Search Benchmark Profiles', 'wpbench' ),
            'not_found'             => __( 'No benchmark profiles found.', 'wpbench' ),
            'not_found_in_trash'    => __( 'No benchmark profiles found in Trash.', 'wpbench' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false, 'publicly_queryable' => false,
            'show_ui'            => true, 'show_in_menu' => 'wpbench_main_menu',
            'query_var'          => false, 'rewrite' => false,
            'capability_type'    => 'post', 'has_archive' => false,
            'hierarchical'       => false, 'menu_position' => null,
            'supports'           => [ 'title' ],
            'show_in_rest'       => true, // Allow REST for meta if needed
            'menu_icon'          => 'dashicons-businessman',
        ];

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Add meta boxes to the Profile CPT edit screen.
     * Hooked to 'add_meta_boxes_{post_type}'.
     */
    /**
     * Register meta fields for the Benchmark Profile CPT.
     * Hooked into 'init'.
     */
    public function register_meta_fields() {
        // Meta for selected tests in the profile
        register_post_meta( self::POST_TYPE, self::META_SELECTED_TESTS, [
            'type'              => 'array',
            'description'       => __('Array of test IDs selected for this profile.', 'wpbench'),
            'single'            => true,
            'show_in_rest'      => [ 'schema' => [ 'type'  => 'array', 'items' => ['type' => 'string'], ], ],
            'sanitize_callback' => [$this, 'sanitize_string_array_meta'],
        ]);

        // Meta for desired plugins in the profile
        register_post_meta( self::POST_TYPE, PluginState::DESIRED_PLUGINS_META_KEY, [
            'type'              => 'array',
            'description'       => __('Array of plugin file paths desired active for this profile.', 'wpbench'),
            'single'            => true,
            'show_in_rest'      => [ 'schema' => [ 'type'  => 'array', 'items' => ['type' => 'string'], ], ],
            'sanitize_callback' => [$this, 'sanitize_string_array_meta'],
        ]);

        // Register individual config meta fields (allows easier querying if needed)
        $available_tests = $this->testRegistry->get_available_tests();
        foreach ($available_tests as $id => $info) {
             $meta_key = self::META_CONFIG_PREFIX . $id;
             register_post_meta( self::POST_TYPE, $meta_key, [
                'type'              => 'integer',
                'description'       => sprintf(__('Configuration value for the %s test.', 'wpbench'), $info['name'] ?? $id),
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'absint',
             ]);
        }
    }
    
    public function add_profile_meta_boxes($post) {
         add_meta_box(
            'wpbench_profile_config_metabox',
            __( 'Profile Configuration', 'wpbench' ),
            [ $this, 'render_profile_config_meta_box' ],
            BenchmarkProfileAdmin::POST_TYPE,
            'normal',
            'high'
        );
          add_meta_box(
            'wpbench_profile_plugins_metabox',
            __( 'Desired Plugin State for Profile', 'wpbench' ),
            [ $this, 'render_profile_plugins_meta_box' ],
            BenchmarkProfileAdmin::POST_TYPE,
            'normal',
            'default'
        );
    }
    
    // Generic sanitizer needed if register_meta_fields stays here
    public function sanitize_string_array_meta($meta_value) {
        if (!is_array($meta_value)) return [];
        return array_map('sanitize_text_field', $meta_value);
    }

    /**
     * Render the main configuration meta box content (Tests & Config).
     * Prepares variables and includes the view file.
     */
    public function render_profile_config_meta_box($post) {
        // Prepare variables needed by the view
        $available_tests = $this->testRegistry->get_available_tests();
        $saved_selected_tests = get_post_meta($post->ID, self::META_SELECTED_TESTS, true);
        $nonce_action = 'wpbench_save_profile_meta';
        $nonce_name = 'wpbench_profile_nonce';
        $tests_input_name = 'profile_selected_tests[]'; // Input name for checkboxes
        $config_input_prefix = 'profile_config_'; // Input name prefix for config values
        $config_meta_prefix = self::META_CONFIG_PREFIX; // Meta key prefix for config values

        // Include the view file
        include WPBENCH_PATH . 'views/admin/profile-config-metabox.php';
    }

     /**
     * Render the desired plugin state meta box content.
     * Prepares variables and includes the view file.
     */
    public function render_profile_plugins_meta_box($post) {
         // Prepare variables needed by the view
         $saved_desired_plugins = $this->pluginState->getDesiredState($post->ID);
         $pluginStateView = $this->pluginStateView; // Pass the instance
         $input_name = 'profile_desired_plugins[]'; // Input name for saving

         // Include the view file
         include WPBENCH_PATH . 'views/admin/profile-plugins-metabox.php';
    }


    /**
     * Save meta data when the Profile CPT is saved.
     * Hooked to 'save_post_{post_type}'.
     */
    public function save_profile_meta($post_id, WP_Post $post) {
        // --- Standard checks ---
        $nonce_action = 'wpbench_save_profile_meta';
        $nonce_name = 'wpbench_profile_nonce';
        if (!isset($_POST[$nonce_name]) || !wp_verify_nonce($_POST[$nonce_name], $nonce_action)) { 
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { 
            return; 
        }

        // Check post type using constant and ensure user can edit this specific post
        if ($post->post_type !== self::POST_TYPE || !current_user_can('edit_post', $post_id)) { 
            return; 
        }

        // --- Save Selected Tests ---
        $selected_tests = isset($_POST['profile_selected_tests']) && is_array($_POST['profile_selected_tests'])
                         ? array_map('sanitize_key', $_POST['profile_selected_tests'])
                         : [];

        update_post_meta($post_id, self::META_SELECTED_TESTS, $selected_tests);

        // --- Save Config Values ---
        $available_tests = $this->testRegistry->get_available_tests();

        foreach ($available_tests as $id => $info) {
            $input_name = 'profile_config_' . $id;
            $meta_key = self::META_CONFIG_PREFIX . $id;

            if (isset($_POST[$input_name])) {
                $value = absint($_POST[$input_name]);
                $value = max($info['min_value'] ?? 0, $value);
                $value = min($info['max_value'] ?? 1000000, $value);
                update_post_meta($post_id, $meta_key, $value);
            } else {
                // Optionally delete meta if not submitted, or save default?
                // Saving default might be better for consistency when loading profile
                update_post_meta($post_id, $meta_key, $info['default_value'] ?? 0);
            }
        }

        // --- Save Desired Plugins ---
        $desired_plugins = isset($_POST['profile_desired_plugins']) && is_array($_POST['profile_desired_plugins'])
                           ? $_POST['profile_desired_plugins'] // Raw paths from form
                           : [];

        // Use PluginState method to handle sanitization and saving (using correct meta key constant)
        $this->pluginState->saveDesiredState($post_id, $desired_plugins);
    }

     /**
     * AJAX handler for loading profile data.
     * Moved from AdminBenchmark.
     */
    public function handle_ajax_load_profile() {
        check_ajax_referer('wpbench_load_profile_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.', 403);
        }

        $profile_id = isset($_POST['profile_id']) ? absint($_POST['profile_id']) : 0;
        if ($profile_id <= 0 || get_post_type($profile_id) !== BenchmarkProfileAdminh::POST_TYPE) {
            wp_send_json_error('Invalid profile ID.', 400);
        }

        $profile_post = get_post($profile_id);
        if (!$profile_post || $profile_post->post_status !== 'publish') {
             wp_send_json_error('Profile not found or not published.', 404);
        }

        // Fetch meta data using constants
        $selected_tests = get_post_meta($profile_id, self::META_SELECTED_TESTS, true);
        $desired_plugins = $this->pluginState->getDesiredState($profile_id);

        // Get config data using prefix
        $config = [];
        $available_tests = $this->testRegistry->get_available_tests(); // Needed for defaults

        foreach ($available_tests as $id => $info) {
            $meta_key = self::META_CONFIG_PREFIX . $id;
            $saved_value = get_post_meta($profile_id, $meta_key, true);
            // Return value using 'config_cpu' format, use saved value or default
            $config['config_' . $id] = ($saved_value !== '' && $saved_value !== null) ? absint($saved_value) : ($info['default_value'] ?? 0);
        }

        $data = [
            'name_suggestion' => 'Benchmark run from profile: ' . $profile_post->post_title,
            'selected_tests' => is_array($selected_tests) ? $selected_tests : [],
            'config' => $config,
            'desired_plugins' => $desired_plugins,
        ];

        wp_send_json_success($data);
    }

} // End Class BenchmarkProfileAdmin