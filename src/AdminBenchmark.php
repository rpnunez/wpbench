<?php
namespace WPBench;

use WPBench\BenchmarkScore;
use WPBench\BenchmarkTest\BaseBenchmarkTest;
use WPBench\Logger;
use WP_Error;
use WP_Post;

// Type hint for post objects

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles Admin Area for running benchmarks and viewing results CPT (benchmark_result).
 */
class AdminBenchmark {

    const string POST_TYPE = 'benchmark_result';

    // Meta Keys specific to Benchmark Results
    const string META_CONFIG = '_wpbench_config';
    const string META_RESULTS = '_wpbench_results';
    const string META_SELECTED_TESTS = '_wpbench_selected_tests';
    const string META_PROFILE_ID_USED = '_wpbench_profile_id_used';
    const string META_PROFILE_STATE_DURING_RUN = '_wpbench_profile_state_during_benchmark'; // Stores profile data array
	const string META_SCORE = '_wpbench_score';

	private PluginState $pluginState;
    private PluginStateView $pluginStateView;
    private PluginManager $pluginManager;
    private TestRegistry $testRegistry;
	private BenchmarkScore $benchmarkScore;

    public function __construct(
	    PluginManager $pluginManager,
		PluginState $pluginState,
		PluginStateView $pluginStateView,
		TestRegistry $testRegistry,
	    BenchmarkScore $benchmarkScore
	    )
    {
        $this->pluginState = $pluginState;
        $this->testRegistry = $testRegistry;
        $this->pluginManager = $pluginManager;
        $this->pluginStateView = $pluginStateView;// ?? new PluginStateView($this->pluginState, $this->testRegistry);
	    $this->benchmarkScore = $benchmarkScore;
    }

    /**
     * Register the Benchmark Result Custom Post Type.
     * Hooked into 'init'.
     * @noinspection SqlNoDataSourceInspection
     */
    public function register_cpt() {
        $labels = [
            'name'                  => _x( 'Benchmark Results', 'Post type general name', 'wpbench' ),
            'singular_name'         => _x( 'Benchmark Result', 'Post type singular name', 'wpbench' ),
            'menu_name'             => _x( 'Benchmarks', 'Admin Menu text', 'wpbench' ),
            'name_admin_bar'        => _x( 'Benchmark Result', 'Add New on Toolbar', 'wpbench' ),
            'add_new'               => __( 'Add New', 'wpbench' ),
            'add_new_item'          => __( 'Add New Benchmark Result', 'wpbench' ),
            'new_item'              => __( 'New Benchmark Result', 'wpbench' ),
            'edit_item'             => __( 'View Benchmark Result', 'wpbench' ),
            'view_item'             => __( 'View Benchmark Result', 'wpbench' ),
            'all_items'             => __( 'All Benchmarks', 'wpbench' ),
            'search_items'          => __( 'Search Benchmark Results', 'wpbench' ),
            'parent_item_colon'     => __( 'Parent Benchmark Results:', 'wpbench' ),
            'not_found'             => __( 'No benchmark results found.', 'wpbench' ),
            'not_found_in_trash'    => __( 'No benchmark results found in Trash.', 'wpbench' ),
            'featured_image'        => _x( 'Benchmark Result Cover Image', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'wpbench' ),
            'set_featured_image'    => _x( 'Set cover image', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'wpbench' ),
            'remove_featured_image' => _x( 'Remove cover image', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'wpbench' ),
            'use_featured_image'    => _x( 'Use as cover image', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'wpbench' ),
            'archives'              => _x( 'Benchmark Result archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'wpbench' ),
            'insert_into_item'      => _x( 'Insert into benchmark result', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'wpbench' ),
            'uploaded_to_this_item' => _x( 'Uploaded to this benchmark result', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'wpbench' ),
            'filter_items_list'     => _x( 'Filter benchmark results list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'wpbench' ),
            'items_list_navigation' => _x( 'Benchmark Results list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'wpbench' ),
            'items_list'            => _x( 'Benchmark Results list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'wpbench' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => 'wpbench_main_menu', // Assumes parent menu exists
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => [ 'title' ], // Only title for the benchmark name
            'show_in_rest'       => true, // Allow REST API access for meta, etc.
            'menu_icon'          => 'dashicons-performance',
        ];

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Register meta fields for the Benchmark Result CPT.
     * Hooked into 'init'.
     */
    public function register_meta_fields() {
		// Meta field for storing the configuration used for this specific run
		register_post_meta( self::POST_TYPE, self::META_CONFIG, [
			'type'              => 'object', // Store as object/array
			'description'       => __('Configuration array used for this benchmark run.', 'wpbench'),
			'single'            => true, // Store a single value
			'show_in_rest'      => [ // Expose to REST API
				'schema' => [
				    'type'       => 'object',
				    'properties' => [ // Define expected properties (can be basic)
				        'benchmark_name' => ['type' => 'string'],
				        'config_cpu' => ['type' => 'integer'],
				        'config_memory' => ['type' => 'integer'],
				        'config_file_io' => ['type' => 'integer'],
				        'config_db_read' => ['type' => 'integer'],
				        'config_db_write' => ['type' => 'integer'],
				    ],
					// Allow additional properties if structure varies
				    'additionalProperties' => true,
				],
			],
			'sanitize_callback' => [$this, 'sanitize_array_meta'], // Use a generic sanitizer
			// 'auth_callback' => function() { return current_user_can('edit_posts'); } // Default auth is fine
		]);

		// Meta field for storing the results array (including errors)
		register_post_meta( self::POST_TYPE, self::META_RESULTS, [
			'type'              => 'object',
			'description'       => __('Results data including timings, usage, and errors.', 'wpbench'),
			'single'            => true,
			'show_in_rest'      => true, // Expose complex object schema if needed
			'sanitize_callback' => [$this, 'sanitize_array_meta'],
		]);

		// Meta field for storing which tests were selected for this run
		register_post_meta( self::POST_TYPE, self::META_SELECTED_TESTS, [
			'type'              => 'array',
			'description'       => __('Array of test IDs selected for this run.', 'wpbench'),
			'single'            => true,
			'show_in_rest'      => [
			    'schema' => [
			        'type'  => 'array',
			        'items' => ['type' => 'string'],
			    ],
			],
			'sanitize_callback' => [$this, 'sanitize_string_array_meta'],
		]);

		// Meta field for storing the ID of the profile used (if any)
		register_post_meta( self::POST_TYPE, self::META_PROFILE_ID_USED, [
			'type'              => 'integer',
			'description'       => __('ID of the Benchmark Profile used for this run, if any.', 'wpbench'),
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'absint', // Ensure it's a positive integer
		]);

		// Meta field for storing the profile's configuration DATA at the time of the run
		register_post_meta( self::POST_TYPE, self::META_PROFILE_STATE_DURING_RUN, [
			'type'              => 'object', // Saving the data array, not a serialized object instance
			'description'       => __('Configuration data array from the profile used during this run.', 'wpbench'),
			'single'            => true,
			'show_in_rest'      => true, // Adjust schema if needed
			'sanitize_callback' => [$this, 'sanitize_array_meta'],
		]);

	    // Meta field for the calculated benchmark score
	    register_post_meta( self::POST_TYPE, self::META_SCORE, [
		    'type'              => 'integer',
		    'description'       => __('Overall calculated benchmark score (0-100, higher is better).', 'wpbench'),
		    'single'            => true,
		    'show_in_rest'      => true,
		    'sanitize_callback' => 'absint',
		    // Add default value if needed via 'default' key, though calculated dynamically
	    ]);

	    // Register meta keys managed primarily by PluginState (for REST API visibility etc.)
		register_post_meta( self::POST_TYPE, PluginState::DESIRED_PLUGINS_META_KEY, [
			'type'              => 'array',
			'description'       => __('Array of plugin file paths desired to be active for the run.', 'wpbench'),
			'single'            => true,
			'show_in_rest'      => [ 'schema' => [ 'type'  => 'array', 'items' => ['type' => 'string'], ], ],
			'sanitize_callback' => [$this, 'sanitize_string_array_meta'],
		]);

		register_post_meta( self::POST_TYPE, PluginState::PRE_BENCHMARK_STATE_META_KEY, [
			'type'              => 'string', // Stored as JSON string
			'description'       => __('JSON string representing plugin state before the run.', 'wpbench'),
			'single'            => true,
			'show_in_rest'      => false, // Probably not needed in REST
			'sanitize_callback' => 'sanitize_text_field', // Basic sanitize for JSON string
		]);

		register_post_meta( self::POST_TYPE, PluginState::ACTUAL_PLUGINS_META_KEY, [
			'type'              => 'array',
			'description'       => __('Array of plugin info {name, version, file} active after restoration attempt.', 'wpbench'),
			'single'            => true,
			'show_in_rest'      => true, // Might be useful
			'sanitize_callback' => [$this, 'sanitize_plugin_list_meta'],
		]);
    }

    /** Generic sanitizer for array/object meta */
    public function sanitize_array_meta($meta_value) {
        // Implement more specific sanitization based on expected array structure if needed
        return is_array($meta_value) || is_object($meta_value) ? $meta_value : null;
    }

    /** Generic sanitizer for array of strings meta */
    public function sanitize_string_array_meta($meta_value) {
        if (!is_array($meta_value)) return [];
        return array_map('sanitize_text_field', $meta_value);
    }

     /** Sanitizer for the active plugin list structure */
    public function sanitize_plugin_list_meta($meta_value) {
        if (!is_array($meta_value)) {
			return [];
        }

        $sanitized = [];

        foreach ($meta_value as $item) {
            if (is_array($item)) {
                $sanitized[] = [
                    'name' => isset($item['name']) ? sanitize_text_field($item['name']) : '',
                    'version' => isset($item['version']) ? sanitize_text_field($item['version']) : '',
                    'file' => isset($item['file']) ? sanitize_text_field($item['file']) : '',
                ];
            }
        }

        return $sanitized;
    }

    /**
     * Add admin menu pages under the main WPBench menu.
     */
    public function add_admin_menu() {
        // Add main menu page (callback renders 'Run New Benchmark')
        add_menu_page(
            __( 'WPBench', 'wpbench' ),
            __( 'WPBench', 'wpbench' ),
            'manage_options',
            'wpbench_main_menu',
            [ $this, 'render_run_benchmark_page' ],
            'dashicons-dashboard',
            75
        );

        // Submenu for running a new benchmark (points to the main page callback)
        add_submenu_page(
            'wpbench_main_menu',
            __( 'Run New Benchmark', 'wpbench' ),
            __( 'Run New Benchmark', 'wpbench' ),
            'manage_options',
            'wpbench_main_menu',
            [ $this, 'render_run_benchmark_page' ]
        );

        // Submenu linking to the Benchmark Profiles CPT list table
         add_submenu_page(
            'wpbench_main_menu',
            __( 'Benchmark Profiles', 'wpbench' ),
            __( 'Profiles', 'wpbench' ),
            'manage_options',
            'edit.php?post_type=' . BenchmarkProfileAdmin::POST_TYPE,
            null
        );

        // Submenu linking to the Benchmark Results CPT list table
        add_submenu_page(
            'wpbench_main_menu',
            __( 'All Benchmarks', 'wpbench' ),
            __( 'All Benchmarks', 'wpbench' ),
            'manage_options',
            'edit.php?post_type=' . AdminBenchmark::POST_TYPE,
            null
        );
    }

    /**
     * Render the "Run New Benchmark" admin page content.
     * Prepares variables and includes the view file.
     */
    public function render_run_benchmark_page() {
        // Prepare variables needed by the view
        $available_tests = $this->testRegistry->get_available_tests();
        $pluginStateView = $this->pluginStateView; // Pass instance to view

        $profiles = get_posts([
	        'post_type'   => self::POST_TYPE, 'post_status' => 'publish',
	        'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC',
        ]);

        // Include the view file
        include WPBENCH_PATH . 'views/admin/run-benchmark-page.php';
    }

    /** AJAX handler for running the benchmark tests. */
    public function handle_ajax_run_benchmark() {
		// 1. Security Checks & Basic Input Validation
		// Ensure user can activate/deactivate plugins
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'activate_plugins' ) || ! current_user_can( 'deactivate_plugins' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied. You need capabilities to manage plugins.', 'wpbench' ) ], 403 );
		}

		// Verify AJAX nonce separately as check_ajax_referer exits on failure
		if ( ! check_ajax_referer( 'wpbench_run_action_ajax', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Nonce verification failed.', 'wpbench' ) ], 403 );
		}

		$selected_tests_raw  = isset( $_POST['selected_tests'] ) && is_array( $_POST['selected_tests'] ) ? $_POST['selected_tests'] : [];
		$desired_plugins_raw = isset( $_POST['desired_plugins'] ) && is_array( $_POST['desired_plugins'] ) ? $_POST['desired_plugins'] : [];
		$profile_id_used     = isset( $_POST['profile_id_used'] ) ? absint( $_POST['profile_id_used'] ) : null;

		if ( empty( $selected_tests_raw ) ) {
		    wp_send_json_error( [ 'message' => __( 'No benchmark tests were selected to run.', 'wpbench' ) ],400 );
		}

	    // --- 2. Create Post Early ---
	    $benchmark_name = isset( $_POST['benchmark_name'] ) ? sanitize_text_field( wp_unslash( $_POST['benchmark_name'] ) ) : 'Unnamed Benchmark';
	    $post_data      = [
			'post_title'  => $benchmark_name,
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
	    ];
	    $post_id        = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) || $post_id === 0 ) {
		    wp_send_json_error( [
				'message' => __( 'Error creating benchmark result post:', 'wpbench' ) . ( is_wp_error( $post_id ) ? ' ' . $post_id->get_error_message() : '' ) ],
		    500
		    );
		}

	    // --- 3. Save Initial States, Config, and Profile Data ---
	    $preBenchmarkState = $this->pluginState->savePreBenchmarkState( $post_id ); // Save pre-run state
	    $all_plugin_files  = array_keys( get_plugins() );
	    $desired_plugins   = array_intersect( $desired_plugins_raw, $all_plugin_files );

	    if ( is_multisite() && ! current_user_can( 'manage_network_plugins' ) ) {
		    $current_network = $preBenchmarkState['active_network'] ?? [];
		    $desired_plugins = array_unique( array_merge( $desired_plugins, $current_network ) );
	    }

	    $this->pluginState->saveDesiredState( $post_id, $desired_plugins ); // Save desired state

	    $available_tests = $this->testRegistry->get_available_tests();
	    $valid_test_ids  = array_keys( $available_tests );
	    $selected_tests  = array_intersect( $selected_tests_raw, $valid_test_ids );
	    $config          = [ 'benchmark_name' => $benchmark_name ];

		foreach ( $available_tests as $id => $info ) {
			$config_key            = 'config_' . $id;
			$config[ $config_key ] = isset( $_POST[ $config_key ] ) ? absint( $_POST[ $config_key ] ) : ( $info['default_value'] ?? 0 );
			$config[ $config_key ] = max( $info['min_value'] ?? 0, $config[ $config_key ] );
			$config[ $config_key ] = min( $info['max_value'] ?? 1000000, $config[ $config_key ] );
		}

	    update_post_meta($post_id, self::META_CONFIG, $config);
        update_post_meta($post_id, self::META_SELECTED_TESTS, $selected_tests);

        // --- Save Profile Data Used ---
        $profile_state_data = null;

        if ($profile_id_used && get_post_type($profile_id_used) === AdminBenchmark::POST_TYPE) {
             update_post_meta($post_id, self::META_PROFILE_ID_USED, $profile_id_used);

             // Fetch profile data to save its state at time of run
             $profile_selected_tests = get_post_meta($profile_id_used, BenchmarkProfileAdmin::META_SELECTED_TESTS, true);
             $profile_desired_plugins = $this->pluginState->getDesiredState($profile_id_used);
             $profile_config = [];
             $profile_all_meta = get_post_meta($profile_id_used);

             foreach ($profile_all_meta as $meta_key => $meta_values) {
                 if (strpos($meta_key, BenchmarkProfileAdmin::META_CONFIG_PREFIX) === 0) {
                      $test_id_key = substr($meta_key, strlen(BenchmarkProfileAdmin::META_CONFIG_PREFIX));

                      $profile_config[ 'config_' . $test_id_key ] = $meta_values[0] ?? null;
                 }
             }

             $profile_state_data = [
                 'profile_id' => $profile_id_used,
                 'profile_title' => get_the_title($profile_id_used),
                 'selected_tests' => is_array($profile_selected_tests) ? $profile_selected_tests : [],
                 'config' => $profile_config,
                 'desired_plugins' => $profile_desired_plugins
             ];

			update_post_meta($post_id, self::META_PROFILE_STATE_DURING_RUN, $profile_state_data); // Save data array
        }

        // --- 4. Prepare for Benchmark ---
        $results = [];
		$start_time = null;
		$all_errors = [];
        $state_change_result = null;
		$benchmark_exception = null;
		$restore_result = null;
        $changes = $this->pluginState->calculateStateChanges($preBenchmarkState, $desired_plugins);
        $needs_state_change = !empty($changes['to_activate']) || !empty($changes['to_deactivate']);
	    $score = null; // Initialize score

        // --- 5. Execute Risky Operations & Benchmark ---
        try {
             if ($needs_state_change) {
                 Logger::log("Attempting plugin state change for benchmark $post_id...");

                 $state_change_result = $this->pluginManager->executeChange($changes['to_activate'], $changes['to_deactivate']);

                 if (!empty($state_change_result['errors'])) {
	                 /* ... log ... */
	                 $all_errors['state_change'] = $state_change_result['errors'];
				 }

                 if (!$state_change_result['success']) {
					 throw new \Exception("Failed to set desired plugin state.");
				 }

                 Logger::log("Plugin state change complete for $post_id.");
             } else {
				 Logger::log("No state change needed for $post_id.");
			 }

            // Run Benchmarks
            $start_time = microtime(true);

            foreach ($selected_tests as $test_id) {
                $test_instance = $this->testRegistry->get_test_instance($test_id);

                if ($test_instance instanceof BaseBenchmarkTest) {
                     $config_value = $config['config_' . $test_id] ?? $available_tests[$test_id]['default_value'];
                     $results[$test_id] = $test_instance->run($config_value);
                } else {
					$results[$test_id] = [
						'error' => "Test instance for '$test_id' could not be created."
					];
				}
            }

			$end_time = microtime(true); $results['total_time'] = round( $end_time - $start_time, 4 );

	        // --- Calculate Score ---
	        // Calculate score only if state change was successful (or not needed) and benchmark loop completed
	        $score = $this->benchmarkScore->calculate($results, $config, $selected_tests);
	        // --- End Score Calculation ---

	        Logger::log("Calculated BenchmarkScore of $score for post id $post_id...");
        } catch (\Exception $e) { 
            $benchmark_exception = $e; Logger::log("WPBench Exception ($post_id): " . $e->getMessage());
        } catch (\Error $e) {
            $benchmark_exception = $e; Logger::log("WPBench Error ($post_id): " . $e->getMessage());
        } finally {
            // --- 5c. Restore Original Plugin State (using PluginManager) ---
            if ($needs_state_change) {
                Logger::log("Attempting state restoration for $post_id...");

                $currentStateAfterTest = $this->pluginState->getCurrentState();
                $restore_result = $this->pluginManager->restoreState($preBenchmarkState, $currentStateAfterTest);

                if (!empty($restore_result['errors'])) {
                    $all_errors['state_restore'] = $restore_result['errors'];

                    Logger::log("Restoration errors for $post_id: " . print_r($restore_result['errors'], true));
                }

                Logger::log("Restoration attempt complete for $post_id.");
            } else { 
                Logger::log("Skipping restoration (no changes needed) for $post_id.");
            }
        } // end try...finally

		// --- 6. Finalize and Save Results ---
		if ($benchmark_exception) {
	        $all_errors['benchmark_runtime'] = get_class($benchmark_exception) . ": " . $benchmark_exception->getMessage();
		}

		if (!empty($all_errors)) {
		    $results['errors'] = $all_errors;
		}

	    // --- Save Score
	    if ($score !== null) {
		    update_post_meta( $post_id, self::META_SCORE, $score ); // Save the calculated score

		    $results['score'] = $score; // Also add to results array for immediate AJAX response consistency
	    }

	    // --- Save Results Meta
		update_post_meta( $post_id, self::META_RESULTS, $results );

		// --- 7. Send Response ---
		$final_message = __( 'Benchmark completed!', 'wpbench' );

		if (!empty($all_errors)) {
		    $final_message .= ' ' . __( 'However, errors occurred. Check results for details.', 'wpbench');
		}

		wp_send_json_success( [
			'message' => $final_message,
			'post_id' => $post_id,
			'results' => $results,
			'view_url' => get_edit_post_link( $post_id, 'raw' )
		] );
    }

    /**
     * Add the meta box to the Benchmark Result CPT edit screen.
     */
    public function add_results_meta_box(WP_Post $post) {
		add_meta_box(
			'wpbench_results_metabox',
			__( 'Benchmark Results & States', 'wpbench' ), // Updated title
			[ $this, 'render_results_meta_box_content' ],
			self::POST_TYPE, 'normal', 'high'
		);
    }

    /**
     * Render the content of the results meta box.
     * Prepares variables and includes the view file.
     */
    public function render_results_meta_box_content( $post ) {
        // Prepare variables needed by the view
        $config = get_post_meta( $post->ID, self::META_CONFIG, true );
        $results = get_post_meta( $post->ID, self::META_RESULTS, true );
	    $score = get_post_meta($post->ID, self::META_SCORE, true);
        $selected_tests = get_post_meta( $post->ID, self::META_SELECTED_TESTS, true );
        $active_plugins_final = get_post_meta( $post->ID, PluginState::ACTUAL_PLUGINS_META_KEY, true );
        $desired_plugins = $this->pluginState->getDesiredState($post->ID);
        $pre_benchmark_state = $this->pluginState->getPreBenchmarkState($post->ID);
        $profile_id_used = get_post_meta($post->ID, self::META_PROFILE_ID_USED, true);
        $profile_state_during_run = get_post_meta($post->ID, self::META_PROFILE_STATE_DURING_RUN, true); // Get saved profile data

        $all_possible_tests = $this->testRegistry->get_available_tests();
        $all_plugins_info = get_plugins();

        // Include the view file
        include WPBENCH_PATH . 'views/admin/results-metabox.php';
    }

    /** Add custom columns to the benchmark_result list table. */
    public function set_custom_edit_benchmark_result_columns( $columns ) { 
        $new_columns = [];
		// Define order preference, including profile
		$order_pref = ['cb', 'title', 'profile', 'total_time', 'cpu_time', 'memory_peak', 'author', 'date'];

		foreach ($order_pref as $key) {
			// @TODO: Decide whether to use if/elseif statements, or switch case...

			/*if ($key === 'cb' && isset($columns['cb'])) { $new_columns['cb'] = $columns['cb']; }
			elseif ($key === 'title' && isset($columns['title'])) { $new_columns['title'] = $columns['title']; }
			elseif ($key === 'profile') { $new_columns['profile'] = __( 'Profile Used', 'wpbench' ); }
			elseif ($key === 'total_time') { $new_columns['total_time'] = __( 'Total (s)', 'wpbench' ); }
			elseif ($key === 'cpu_time') { $new_columns['cpu_time'] = __( 'CPU (s)', 'wpbench' ); }
			elseif ($key === 'memory_peak') { $new_columns['memory_peak'] = __( 'Mem (MB)', 'wpbench' ); }
			elseif ($key === 'author' && isset($columns['author'])) { $new_columns['author'] = $columns['author']; }
			elseif ($key === 'date' && isset($columns['date'])) { $new_columns['date'] = $columns['date']; }*/

			switch ($key) {
				case 'cb':
					if (isset($columns['cb'])) {
					 $new_columns['cb'] = $columns['cb'];
					}
				break;

				case 'title':
					if (isset($columns['title'])) {
					 $new_columns['title'] = $columns['title'];
					}
				break;

				case 'profile':
					$new_columns['profile'] = __( 'Profile Used', 'wpbench' );
				break;

				case 'total_time':
					$new_columns['total_time'] = __( 'Total (s)', 'wpbench' );
				break;

				case 'cpu_time':
					$new_columns['cpu_time'] = __( 'CPU (s)', 'wpbench' );
				break;

				case 'memory_peak':
					$new_columns['memory_peak'] = __( 'Mem (MB)', 'wpbench' );
				break;

				case 'author':
					if (isset($columns['author'])) {
						$new_columns['author'] = $columns['author'];
					}
				break;

				case 'date':
					if (isset($columns['date'])) {
						$new_columns['date'] = $columns['date'];
					}
				break;

				default:
					// No action for unhandled cases
				break;
			}
			}

         // Add any remaining original columns
         foreach ($columns as $key => $value) { 
            if (!isset($new_columns[$key])) { 
                $new_columns[$key] = $value;
            } 
        }

         return $new_columns;
    }
    
    /** Display data in custom columns. */
    public function custom_benchmark_result_column( $column, $post_id ) {
        switch ( $column ) {
            case 'profile':
                $profile_id = get_post_meta($post_id, self::META_PROFILE_ID_USED, true);

                if ($profile_id) {
                    $profile_link = get_edit_post_link(absint($profile_id));
                    $profile_title = get_the_title(absint($profile_id));

                    if ($profile_link && $profile_title) {
                        echo '<a href="'.esc_url($profile_link).'">'.esc_html($profile_title).'</a>';
                    } elseif ($profile_title) {
                        echo esc_html($profile_title) . ' ' . esc_html__('(Profile Link Invalid?)','wpbench');
                    } else {
                        echo '#' . esc_html($profile_id) . ' ' . esc_html__('(Profile Deleted?)','wpbench');
                    }
                } else {
					echo '<em>' . esc_html__('None', 'wpbench') . '</em>';
				}
            break;

            default:
                // Handle other columns (time, cpu, mem)
                $results = get_post_meta( $post_id, self::META_RESULTS, true );

                if (empty($results) || !is_array($results)) {
					return;
                }

                switch ( $column ) {
					case 'total_time':
						echo esc_html( $results['total_time'] ?? 'N/A' );
					break;

					case 'cpu_time':
						echo esc_html( $results['cpu']['time'] ?? 'N/A' );

						if (!empty($results['cpu']['error'])) {
							echo ' <span style="color:red;" title="'.esc_attr($results['cpu']['error']).'">(!)</span>';
						}
					break;

					case 'memory_peak':
						echo esc_html( $results['memory']['peak_usage_mb'] ?? 'N/A' );

						if (!empty($results['memory']['error'])) {
							echo ' <span style="color:red;" title="'.esc_attr($results['memory']['error']).'">(!)</span>';
						}
					break;
                }

            break;
        }
    }

}