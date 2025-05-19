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
	const string META_ERRORS_DURING_TESTS = '_wpbench_errors_during_tests';

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
			'sanitize_callback' => [$this, 'sanitize_meta_object' ], // Use a generic sanitizer
			// 'auth_callback' => function() { return current_user_can('edit_posts'); } // Default auth is fine
		]);

		// Meta field for storing the results array (including errors)
		register_post_meta( self::POST_TYPE, self::META_RESULTS, [
			'type'              => 'object',
			'description'       => __('Results data including timings, usage, and errors.', 'wpbench'),
			'single'            => true,
			'show_in_rest'      => true, // Expose complex object schema if needed
			'sanitize_callback' => [$this, 'sanitize_meta_object' ],
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
			'sanitize_callback' => [$this, 'sanitize_meta_object' ],
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

	    // Meta field for the calculated benchmark score
	    register_post_meta( self::POST_TYPE, self::META_ERRORS_DURING_TESTS, [
		    'type'              => 'object',
		    'description'       => __('Overall calculated benchmark score (0-100, higher is better).', 'wpbench'),
		    'single'            => true,
		    'show_in_rest'      => true,
		    'sanitize_callback' => 'sanitize_meta_object',
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
    public function sanitize_meta_object($meta_value) {
        // Implement more specific sanitization based on expected array structure if needed
        return is_array($meta_value) || is_object($meta_value) ? $meta_value : null;
    }

    /** Generic sanitizer for array of strings meta */
    public function sanitize_string_array_meta($meta_value) {
        if (!is_array($meta_value)) {
			return [];
        }

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

		$inputSelectedTests = isset( $_POST['selected_tests'] ) && is_array( $_POST['selected_tests'] ) ? $_POST['selected_tests'] : [];
		$desired_plugins_raw = isset( $_POST['desired_plugins'] ) && is_array( $_POST['desired_plugins'] ) ? $_POST['desired_plugins'] : [];
		$benchmarkProfileId  = isset( $_POST['profile_id_used'] ) ? absint( $_POST['profile_id_used'] ) : null;

		if ( empty( $inputSelectedTests ) ) {
		    wp_send_json_error( [ 'message' => __( 'No benchmark tests were selected to run.', 'wpbench' ) ],400 );
		}

	    // --- 2. Create Post Early ---
	    $benchmark_name = isset( $_POST['benchmark_name'] ) ? sanitize_text_field( wp_unslash( $_POST['benchmark_name'] ) ) : 'Unnamed Benchmark';

	    $post_data = [
			'post_title' => $benchmark_name,
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
	    ];

	    // Initialize BenchmarkResultPost Object
	    $benchmarkResult = new \WPBench\BenchmarkResultPost($post_data);
		$post_id = $benchmarkResult->postId;

	    // --- 3. Save Initial States, Config, and Profile Data ---

	    $preBenchmarkState = $this->pluginState->savePreBenchmarkState( $post_id ); // Save pre-run state

	    $desiredPlugins = $this->getDesiredPlugins($preBenchmarkState);

	    $this->pluginState->saveDesiredState( $post_id, $desiredPlugins ); // Save desired state

	    $availableTests = $this->testRegistry->get_available_tests();
	    $validTestIDs = array_keys( $availableTests );
	    $selectedTests = array_intersect( $inputSelectedTests, $validTestIDs );

	    $config = [
			'benchmark_name' => $benchmark_name
	    ];

		foreach ( $availableTests as $id => $info ) {
			$config_key            = 'config_' . $id;

			$config[ $config_key ] = isset( $_POST[ $config_key ] ) ? absint( $_POST[ $config_key ] ) : ( $info['default_value'] ?? 0 );
			$config[ $config_key ] = max( $info['min_value'] ?? 0, $config[ $config_key ] );
			$config[ $config_key ] = min( $info['max_value'] ?? 1000000, $config[ $config_key ] );
		}

	    // Save Config and Selected Tests
	    $benchmarkResult->saveConfig($config);
	    $benchmarkResult->saveSelectedTests($selectedTests);

	    // --- Save Profile Data Used --
	    $benchmarkResult->saveProfileIdUsed($benchmarkProfileId);

		$benchmarkProfileResult = new BenchmarkProfilePost($benchmarkProfileId);
		$benchmarkProfileState = $benchmarkProfileResult->getProfileState();

	    $benchmarkResult->saveProfileStateDuringRun($benchmarkProfileState);

        // --- 4. Prepare for Benchmark ---
        $results = [];
		$start_time = null;
        $changes = $this->pluginState->calculateStateChanges($preBenchmarkState, $benchmarkProfileState['desired_plugins']);
        $needsStateChange = !empty($changes['to_activate']) || !empty($changes['to_deactivate']);
	    $score = null; // Initialize score
	    $start_benchmark_time = microtime(true);
	    $start_time_by_test_id = [];
	    $end_time_by_test_id = [];

        // --- 5. Execute Risky Operations & Benchmark ---
        try {
	        if ($needsStateChange) {
		        // Apply Plugin State Change (unchanged)
		        $stateChangeResult = $this->pluginManager->executeChange($changes['to_activate'], $changes['to_deactivate']);

				Logger::log("Plugin State Change result for $post_id: " . print_r($stateChangeResult, true), 'info', __CLASS__, __METHOD__);
	        }

	        // Run Benchmarks
	        foreach ($selectedTests as $test_id => $test_info) {
//		        $test_instance = $this->testRegistry->get_test_instance($test_id);
		        $test_instance = $test_info['instance'];

		        if ($test_instance instanceof BaseBenchmarkTest) {
			        // Use the test instance to run the benchmark
			        $config_value = $config['config_' . $test_id] ?? $availableTests[$test_id]['default_value'];

			        $start_time_by_test_id[$test_id] = microtime(true);

					$testResult = $test_instance->run($config_value);

			        $end_time_by_test_id[$test_id] = microtime(true);

			        $results[$test_id] = $testResult;

					Logger::log("Test ran successfully for {$test_id}: ". print_r($test_info, true), 'info');
		        } else {
			        // Handle cases where the test instance could not be created
			        $results[$test_id] = [
				        'error' => "Test instance for '$test_id' could not be created."
			        ];
		        }
	        }

	        $end_benchmark_time = microtime(true);

			$results['total_time'] = round( $end_benchmark_time - $start_benchmark_time, 4 );

			foreach ($start_time_by_test_id as $test_id => $start_time) {
				$results['start_time_' . $test_id] = round( $start_time, 4 );
				$results['end_time' . $test_id] = round( $end_time_by_test_id[$test_id],4 );
				$results['total_time' . $test_id] = round( $end_time_by_test_id[$test_id] - $start_time_by_test_id[$test_id], 4 );
			}

	        // Calculate score only if state change was successful (or not needed) and benchmark loop completed
	        $score = $this->benchmarkScore->calculate($results, $config, $selectedTests);

	        Logger::log("Calculated BenchmarkScore of $score for BenchmarkResultPost id $post_id...");
        } catch (\Throwable $e) { // Catch Throwable for PHP 7+
	        Logger::log("WPBench Exception/Error during benchmark $post_id: " . $e->getMessage(), 'error');
        } finally {
            // --- 5c. Restore Original Plugin State (using PluginManager) ---
            if ($needsStateChange) {
                $currentStateAfterTest = $this->pluginState->getCurrentState();
                $restoreResult = $this->pluginManager->restoreState($preBenchmarkState, $currentStateAfterTest);

                if (!empty($restoreResult['errors'])) {
                    $results['errors'][] = $restoreResult['errors'];

                    Logger::log("Restoration errors for $post_id: " . print_r($restoreResult['errors'], true));
                }
            }

	        // If exception happened during benchmark run, calculate partial time if possible
	        if ($start_time && !isset($results['total_time'])) {
		        $results['total_time'] = round( microtime(true) - $start_benchmark_time, 4 );
	        }
        }

		// --- 6. Finalize and Save Results ---

	    // --- Save Score
		$benchmarkScore = $score ?? 0;

	    $benchmarkResult->saveScore($benchmarkScore);

	    // Include Benchmark Score in AJAX response
	    $results['score'] = $benchmarkScore;

	    // Save Results into the BenchmarkResultPost
	    $benchmarkResult->saveResults($results);

		// --- 7. Send Response ---
		wp_send_json_success( [
			'message' => __('Benchmark completed!', 'wpbench'),
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
	public function render_results_meta_box_content($post): void {
		// Instantiate the BenchmarkResultPost object
		$benchmarkResultPost = new BenchmarkResultPost($post->ID);

		// Instantiate the BenchmarkProfilePost class
		$benchmarkProfilePost = new BenchmarkProfilePost($post->ID);

		// Fetch data using the BenchmarkResultPost class methods
		$config = $benchmarkResultPost->getConfig();
		$results = $benchmarkResultPost->getResults();
		$score = $benchmarkResultPost->getScore();
		$selectedTests = $benchmarkResultPost->getSelectedTests();
		$activePluginsFinal = $benchmarkResultPost->getRuntimePluginsActive();
		$desiredPlugins = $this->pluginState->getDesiredState($post->ID);
		$preBenchmarkState = $this->pluginState->getPreBenchmarkState($post->ID);

		$profileIdUsed = $benchmarkProfilePost->getProfileIdUsed();
		$profileStateDuringRun = $benchmarkProfilePost->getProfileStateDuringRun();

		$allPossibleTests = $this->testRegistry->get_available_tests();
		$allPluginsInfo = get_plugins();

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

	/**
	 * Get the desired plugins based on the pre-benchmark state and current plugins.
	 *
	 * @param array $preBenchmarkState The pre-benchmark state data, including active network plugins if multisite.
	 *
	 * @return array The filtered list of desired plugins.
	 */
	public function getDesiredPlugins(array $preBenchmarkState): array {
		// Retrieve all available plugins
		$allPluginFiles = array_keys(get_plugins());

		// Filter desired plugins against available plugins
		$desiredPluginsRaw = $preBenchmarkState['desired_plugins'] ?? [];
		$desiredPlugins = array_intersect($desiredPluginsRaw, $allPluginFiles);

		// Handle multisite and network plugin permissions
		if (is_multisite() && !current_user_can('manage_network_plugins')) {
			$currentNetworkPlugins = $preBenchmarkState['active_network'] ?? [];
			$desiredPlugins = array_unique(array_merge($desiredPlugins, $currentNetworkPlugins));
		}

		return $desiredPlugins;
	}
}