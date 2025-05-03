<?php
/**
 * Plugin Name:       WPBench
 * Plugin URI:        https://example.com/wpbench-plugin-uri/
 * Description:       Plugin that benchmarks and stress-tests your current WordPress site, allowing you to see what's slowing down your website.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Your Name Here
 * Author URI:        https://example.com/author-uri/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpbench
 * Domain Path:       /languages
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPBENCH_VERSION', '1.0.0' );
define( 'WPBENCH_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPBENCH_URL', plugin_dir_url( __FILE__ ) );

class WPBench_Plugin {

    public function __construct() {
        add_action( 'init', [ $this, 'register_benchmark_post_type' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'wp_ajax_wpbench_run_benchmark', [ $this, 'handle_ajax_run_benchmark' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

        // Add meta box to display results on the CPT edit screen
        add_action( 'add_meta_boxes_benchmark_result', [ $this, 'add_results_meta_box' ] );

        // Customize columns in the CPT list table
        add_filter( 'manage_benchmark_result_posts_columns', [ $this, 'set_custom_edit_benchmark_result_columns' ] );
        add_action( 'manage_benchmark_result_posts_custom_column', [ $this, 'custom_benchmark_result_column' ], 10, 2 );

        // Store list of active plugins when saving benchmark result
        add_action('save_post_benchmark_result', [ $this, 'save_active_plugins_list' ], 10, 2);
    }

    /**
     * Register the Custom Post Type for Benchmark Results.
     */
    public function register_benchmark_post_type() {
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
            'public'             => false, // Not publicly queryable on front-end
            'publicly_queryable' => false,
            'show_ui'            => true, // Show in admin UI
            'show_in_menu'       => 'wpbench_main_menu', // Show under our main menu
            'query_var'          => false,
            'rewrite'            => false,
            'capability_type'    => 'post',
            'has_archive'        => false, // No front-end archive
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => [ 'title' ], // Only need title for the benchmark name
            'show_in_rest'       => true, // Enable Gutenberg editor / REST API access if needed
            'menu_icon'          => 'dashicons-performance',
        ];

        register_post_type( 'benchmark_result', $args );
    }

    /**
     * Add admin menu pages.
     */
    public function add_admin_menu() {
        // Add main menu page
        add_menu_page(
            __( 'WPBench', 'wpbench' ), // Page Title
            __( 'WPBench', 'wpbench' ), // Menu Title
            'manage_options',           // Capability
            'wpbench_main_menu',        // Menu Slug
            [ $this, 'render_run_benchmark_page' ], // Callback function for the page content
            'dashicons-dashboard',      // Icon URL
            75                          // Position
        );

        // Add submenu linking to the CPT list table
        add_submenu_page(
            'wpbench_main_menu',        // Parent Slug
            __( 'All Benchmarks', 'wpbench' ),  // Page Title
            __( 'All Benchmarks', 'wpbench' ),  // Menu Title
            'manage_options',           // Capability
            'edit.php?post_type=benchmark_result', // Menu Slug (links directly to CPT list)
            null                       // No callback function needed
        );

        // Add submenu for running a new benchmark (points to the main page callback)
        add_submenu_page(
            'wpbench_main_menu',        // Parent Slug
            __( 'Run New Benchmark', 'wpbench' ), // Page Title
            __( 'Run New Benchmark', 'wpbench' ), // Menu Title
            'manage_options',           // Capability
            'wpbench_main_menu',        // Menu Slug (same as parent to make it the default)
            [ $this, 'render_run_benchmark_page' ] // Callback function
        );
    }

    /**
     * Render the "Run New Benchmark" admin page content.
     */
    public function render_run_benchmark_page() {
        ?>
        <div class="wrap wpbench-wrap">
            <h1><?php esc_html_e( 'Run New Benchmark', 'wpbench' ); ?></h1>
            <p><?php esc_html_e('Configure and run benchmark tests for your WordPress site.', 'wpbench'); ?></p>
            <p><strong><?php esc_html_e('Important:', 'wpbench'); ?></strong> <?php esc_html_e('Running benchmarks, especially with high iterations, can consume significant server resources and may temporarily slow down your site. Run during off-peak hours if possible.', 'wpbench'); ?></p>

            <form id="wpbench-run-form" method="post">
                <?php wp_nonce_field( 'wpbench_run_action', 'wpbench_run_nonce' ); ?>

                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="benchmark_name"><?php esc_html_e( 'Benchmark Name', 'wpbench' ); ?></label></th>
                            <td><input name="benchmark_name" type="text" id="benchmark_name" value="Benchmark <?php echo esc_attr( date('Y-m-d H:i:s') ); ?>" class="regular-text" required>
                            <p class="description"><?php esc_html_e('Give this benchmark run a descriptive name.', 'wpbench'); ?></p></td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e( 'Active Plugins During Test', 'wpbench' ); ?></th>
                            <td>
                                <?php
                                $all_plugins = get_plugins();
                                $active_plugins = get_option( 'active_plugins', [] );
                                if (is_multisite()) {
                                     $network_plugins = array_keys(get_site_option( 'active_sitewide_plugins', [] ));
                                     $active_plugins = array_merge($active_plugins, $network_plugins);
                                }
                                if (!empty($active_plugins)) {
                                    echo '<ul>';
                                    foreach ($active_plugins as $plugin_file) {
                                        if (isset($all_plugins[$plugin_file])) {
                                            echo '<li>' . esc_html($all_plugins[$plugin_file]['Name']) . ' (' . esc_html($all_plugins[$plugin_file]['Version']) . ')</li>';
                                        } else {
                                             echo '<li>' . esc_html($plugin_file) . ' (Network Active or Info Missing)</li>';
                                        }
                                    }
                                    echo '</ul>';
                                    echo '<p class="description">' . esc_html__('The benchmark will run with the plugins listed above currently active. To test different plugin combinations, activate/deactivate them before running the benchmark.', 'wpbench') . '</p>';
                                } else {
                                    echo '<p>' . esc_html__('No active plugins detected.', 'wpbench') . '</p>';
                                }
                                ?>
                            </td>
                        </tr>

                        <tr><td colspan="2"><h2><?php esc_html_e('Test Configuration', 'wpbench'); ?></h2></td></tr>

                        <tr>
                            <th scope="row"><label for="cpu_iterations"><?php esc_html_e( 'CPU Test Iterations', 'wpbench' ); ?></label></th>
                            <td><input name="cpu_iterations" type="number" id="cpu_iterations" value="100000" class="regular-text" min="1000" max="10000000">
                            <p class="description"><?php esc_html_e('Number of loops for CPU-intensive calculations (e.g., math functions). Higher is more stressful.', 'wpbench'); ?></p></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="memory_iterations"><?php esc_html_e( 'Memory Test Size (KB)', 'wpbench' ); ?></label></th>
                            <td><input name="memory_iterations" type="number" id="memory_iterations" value="1024" class="regular-text" min="128" max="65536">
                             <p class="description"><?php esc_html_e('Approximate size (in KB) of data to manipulate in memory. Be mindful of PHP memory limits.', 'wpbench'); ?></p></td>
                       </tr>
                        <tr>
                            <th scope="row"><label for="file_io_iterations"><?php esc_html_e( 'File I/O Operations', 'wpbench' ); ?></label></th>
                            <td><input name="file_io_iterations" type="number" id="file_io_iterations" value="100" class="regular-text" min="10" max="5000">
                             <p class="description"><?php esc_html_e('Number of file write/read cycles.', 'wpbench'); ?></p></td>
                        </tr>
                         <tr>
                            <th scope="row"><label for="db_read_iterations"><?php esc_html_e( 'DB Read Queries', 'wpbench' ); ?></label></th>
                            <td><input name="db_read_iterations" type="number" id="db_read_iterations" value="250" class="regular-text" min="10" max="5000">
                            <p class="description"><?php esc_html_e('Number of database SELECT queries to execute.', 'wpbench'); ?></p></td>
                        </tr>
                         <tr>
                            <th scope="row"><label for="db_write_iterations"><?php esc_html_e( 'DB Write Operations', 'wpbench' ); ?></label></th>
                            <td><input name="db_write_iterations" type="number" id="db_write_iterations" value="100" class="regular-text" min="10" max="2500">
                            <p class="description"><?php esc_html_e('Number of database INSERT/UPDATE/DELETE operations on a temporary table.', 'wpbench'); ?></p></td>
                        </tr>

                    </tbody>
                </table>

                <?php submit_button( __( 'Start Benchmark', 'wpbench' ), 'primary', 'wpbench-start-button' ); ?>
            </form>

            <div id="wpbench-results-area" style="display: none; margin-top: 20px; padding: 15px; border: 1px solid #ccc; background-color: #f9f9f9;">
                <h2><?php esc_html_e( 'Benchmark Running...', 'wpbench' ); ?></h2>
                <p id="wpbench-status"><?php esc_html_e( 'Please wait, tests are in progress.', 'wpbench' ); ?></p>
                <div id="wpbench-progress" style="height: 20px; background-color: #eee; border: 1px solid #ccc; margin-top: 10px; position: relative;">
                     <div id="wpbench-progress-bar" style="width: 0%; height: 100%; background-color: #4CAF50; position: absolute; text-align: center; color: white; line-height: 20px;">0%</div>
                </div>
                <div id="wpbench-final-results" style="margin-top: 15px;"></div>
            </div>
        </div>
        <style>
            .wpbench-wrap .form-table th { padding-top: 15px; padding-bottom: 15px; }
            .wpbench-wrap #wpbench-results-area ul { list-style: disc; margin-left: 20px; }
        </style>
        <?php
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_admin_scripts( $hook ) {
        // Only load on our plugin pages and the CPT edit screen
        $screen = get_current_screen();
        $is_wpbench_page = ($hook === 'toplevel_page_wpbench_main_menu' || $hook === 'wpbench_page_wpbench_run_new'); // Older WP might use different hook names
        $is_wpbench_cpt_edit = ($hook === 'post.php' && isset($screen->post_type) && $screen->post_type === 'benchmark_result');
        $is_wpbench_cpt_new = ($hook === 'post-new.php' && isset($screen->post_type) && $screen->post_type === 'benchmark_result');


        if ( $is_wpbench_page ) {
            wp_enqueue_script( 'wpbench-admin-js', WPBENCH_URL . 'js/admin-benchmark.js', [ 'jquery' ], WPBENCH_VERSION, true );
            wp_localize_script( 'wpbench-admin-js', 'wpbench_ajax', [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'run_nonce' => wp_create_nonce( 'wpbench_run_action_ajax' ), // Separate nonce for AJAX
                'running_text' => __('Running...', 'wpbench'),
                'complete_text' => __('Benchmark Complete!', 'wpbench'),
                'error_text' => __('An error occurred.', 'wpbench'),
                'view_results_text' => __('View Results', 'wpbench'),
            ]);
        }

        if ( $is_wpbench_cpt_edit ) {
             // Enqueue Chart.js from CDN
             wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js', [], '3.7.0', true );
             // Enqueue our custom script for rendering charts
             wp_enqueue_script( 'wpbench-results-js', WPBENCH_URL . 'js/admin-results.js', [ 'jquery', 'chart-js' ], WPBENCH_VERSION, true );

             // Pass result data to the script
             global $post;
             if ($post && $post->ID) {
                 $results_data = get_post_meta( $post->ID, '_wpbench_results', true );
                 $config_data = get_post_meta( $post->ID, '_wpbench_config', true );
                 wp_localize_script( 'wpbench-results-js', 'wpbench_results_data', [
                     'results' => $results_data ?: [], // Ensure it's an array
                     'config'  => $config_data ?: [],
                     'text' => [
                         'cpu_time' => __('CPU Time (seconds)', 'wpbench'),
                         'memory_peak' => __('Peak Memory (MB)', 'wpbench'),
                         'file_io_time' => __('File I/O Time (seconds)', 'wpbench'),
                         'db_read_time' => __('DB Read Time (seconds)', 'wpbench'),
                         'db_write_time' => __('DB Write Time (seconds)', 'wpbench'),
                         'total_time' => __('Total Time (seconds)', 'wpbench'),
                         'benchmark_results' => __('Benchmark Results', 'wpbench'),
                     ]
                 ]);
             }
        }

         // Create dummy JS files if they don't exist to avoid PHP errors
        $js_admin_path = WPBENCH_PATH . 'js/admin-benchmark.js';
        $js_results_path = WPBENCH_PATH . 'js/admin-results.js';
        if (!file_exists(dirname($js_admin_path))) { wp_mkdir_p(dirname($js_admin_path)); }
        if (!file_exists($js_admin_path)) { file_put_contents($js_admin_path, '// WPBench Admin Benchmark JS'); }
        if (!file_exists($js_results_path)) { file_put_contents($js_results_path, '// WPBench Admin Results JS'); }
    }


    /**
     * AJAX handler for running the benchmark tests.
     */
    public function handle_ajax_run_benchmark() {
        // 1. Security Checks
        check_ajax_referer( 'wpbench_run_action_ajax', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wpbench' ) ], 403 );
        }

        // 2. Sanitize Input Configuration
        $config = [
            'name'              => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : 'Unnamed Benchmark',
            'cpu_iterations'    => isset($_POST['cpu_iterations']) ? absint($_POST['cpu_iterations']) : 100000,
            'memory_iterations' => isset($_POST['memory_iterations']) ? absint($_POST['memory_iterations']) : 1024,
            'file_io_iterations'=> isset($_POST['file_io_iterations']) ? absint($_POST['file_io_iterations']) : 100,
            'db_read_iterations'=> isset($_POST['db_read_iterations']) ? absint($_POST['db_read_iterations']) : 250,
            'db_write_iterations'=> isset($_POST['db_write_iterations']) ? absint($_POST['db_write_iterations']) : 100,
        ];
        // Add basic validation for minimums if desired
        $config['cpu_iterations'] = max(1000, $config['cpu_iterations']);
        // ... add other validations ...


        // 3. Run Benchmarks
        $results = [];
        $start_time = microtime( true );

        // --- CPU Test ---
        $results['cpu'] = $this->run_cpu_test( $config['cpu_iterations'] );

        // --- Memory Test ---
        $results['memory'] = $this->run_memory_test( $config['memory_iterations'] );

        // --- File I/O Test ---
        $results['file_io'] = $this->run_file_io_test( $config['file_io_iterations'] );

        // --- Database Tests ---
        $results['db_read'] = $this->run_db_read_test( $config['db_read_iterations'] );
        $results['db_write'] = $this->run_db_write_test( $config['db_write_iterations'] );


        $end_time = microtime( true );
        $results['total_time'] = round( $end_time - $start_time, 4 );

        // 4. Save Results to CPT
        $post_id = wp_insert_post([
            'post_title'  => $config['name'],
            'post_type'   => 'benchmark_result',
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ]);

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Error saving benchmark result post:', 'wpbench' ) . ' ' . $post_id->get_error_message() ], 500 );
        } elseif ( $post_id === 0) {
             wp_send_json_error( [ 'message' => __( 'Failed to save benchmark result post (unknown error).', 'wpbench' ) ], 500 );
        } else {
            // Store config and results as meta data
            update_post_meta( $post_id, '_wpbench_config', $config );
            update_post_meta( $post_id, '_wpbench_results', $results );
            // Active plugins list is saved via 'save_post_benchmark_result' hook

            // 5. Send Success Response
            wp_send_json_success( [
                'message' => __( 'Benchmark completed successfully!', 'wpbench' ),
                'post_id' => $post_id,
                'results' => $results, // Send results back to display immediately
                'view_url' => get_edit_post_link( $post_id, 'raw' ) // Get URL for the edit screen
            ] );
        }
    }

    /**
     * Save the list of active plugins when the benchmark result post is saved.
     */
    public function save_active_plugins_list($post_id, $post) {
        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        // Check the user's permissions.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        // Check if the post type is correct
        if ( 'benchmark_result' !== $post->post_type ) {
            return;
        }
         // Make sure we're saving, not just loading the edit screen
         // Check if this is actually a programmatic insertion (e.g. from our AJAX handler)
         // A bit hacky, maybe check if meta already exists? Or rely on AJAX handler being the only creator.
         // Let's only save if the meta *doesn't* exist yet, assuming the AJAX handler is the creator.
         if (get_post_meta($post_id, '_wpbench_active_plugins', true)) {
             return;
         }


        $active_plugins_list = [];
        $all_plugins = get_plugins();
        $active_plugin_files = get_option( 'active_plugins', [] );
         if (is_multisite()) {
             $network_plugins = array_keys(get_site_option( 'active_sitewide_plugins', [] ));
             $active_plugin_files = array_unique(array_merge($active_plugin_files, $network_plugins));
         }

        foreach ($active_plugin_files as $plugin_file) {
            if (isset($all_plugins[$plugin_file])) {
                 $active_plugins_list[] = [
                    'name' => $all_plugins[$plugin_file]['Name'],
                    'version' => $all_plugins[$plugin_file]['Version'],
                    'file' => $plugin_file
                 ];
            } else {
                 $active_plugins_list[] = [
                     'name' => $plugin_file . ' (Network Active or Info Missing)',
                     'version' => 'N/A',
                     'file' => $plugin_file
                 ];
            }
        }

        update_post_meta($post_id, '_wpbench_active_plugins', $active_plugins_list);
    }


    // --- Benchmark Test Functions ---

    private function run_cpu_test( $iterations ) {
        $start = microtime( true );
        for ( $i = 0; $i < $iterations; $i++ ) {
            // Perform some math operations
            $a = sqrt( ( $i + 1 ) * pi() );
            $b = log( $a + 1 );
            $c = sin( $b ) * cos( $a );
             // Perform some string operations
             $str = md5((string)$c . uniqid());
             $str = sha1($str . $i);
             $str = strrev($str);
        }
        return ['time' => round( microtime( true ) - $start, 4 )];
    }

    private function run_memory_test( $size_kb ) {
        $start = microtime(true);
        $initial_memory = memory_get_usage();
        $peak_memory_start = memory_get_peak_usage();

        $string = str_repeat('a', $size_kb * 1024); // Create a string of approx size
        // Manipulate it slightly to ensure it's used
        $string[$size_kb * 1024 -1] = 'b';
        unset($string); // Free memory

        $final_memory = memory_get_usage();
        $peak_memory_end = memory_get_peak_usage(true); // Get real peak usage

        return [
            'time' => round(microtime(true) - $start, 4),
            'peak_usage_mb' => round($peak_memory_end / 1024 / 1024, 2) // Report peak in MB
            // 'memory_used_mb' => round( ($final_memory - $initial_memory) / 1024 / 1024, 2) // Less reliable metric
        ];
    }


    private function run_file_io_test( $iterations ) {
         $start = microtime(true);
         $upload_dir = wp_upload_dir();
         $temp_file = trailingslashit($upload_dir['basedir']) . 'wpbench_temp_file.txt';
         $error = null;
         $bytes_written = 0;
         $bytes_read = 0;

         $dummy_data = str_repeat("0123456789abcdef", 64); // 1KB data chunk

        try {
            for ($i = 0; $i < $iterations; $i++) {
                // Write
                $fh = fopen($temp_file, 'w');
                if ($fh) {
                    $written = fwrite($fh, $dummy_data);
                    if ($written) $bytes_written += $written;
                    fclose($fh);
                } else { throw new Exception("Could not open file for writing."); }

                // Read
                $fh = fopen($temp_file, 'r');
                 if ($fh) {
                    $read_data = fread($fh, strlen($dummy_data) + 10); // Read slightly more to test EOF
                     if ($read_data !== false) $bytes_read += strlen($read_data);
                    fclose($fh);
                 } else { throw new Exception("Could not open file for reading."); }
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        } finally {
             if (file_exists($temp_file)) {
                unlink($temp_file);
             }
        }

        return [
            'time' => round(microtime(true) - $start, 4),
            'operations' => $iterations * 2, // Write + Read
            'bytes_written' => $bytes_written,
            'bytes_read' => $bytes_read,
            'error' => $error
        ];
    }

     private function run_db_read_test( $iterations ) {
        global $wpdb;
        $start = microtime(true);
        $rows_fetched = 0;

        for ($i = 0; $i < $iterations; $i++) {
            // Simple query that should always return something but not too large
             $option_name = 'blogname'; // Query a common option
             $result = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option_name ) );
             if ($result !== null) $rows_fetched++;

             // Query a post - vary the query slightly
             $post_id = ($i % 50) + 1; // Query first 50 posts cyclically
             $post_title = $wpdb->get_var( $wpdb->prepare("SELECT post_title FROM $wpdb->posts WHERE ID = %d AND post_status = 'publish' LIMIT 1", $post_id));
              if ($post_title !== null) $rows_fetched++; // Count this as another "fetch" even if var
        }

        return [
            'time' => round(microtime(true) - $start, 4),
            'queries_executed' => $iterations * 2,
            'rows_fetched' => $rows_fetched // Approximate indication of work done
        ];
    }

    private function run_db_write_test( $iterations ) {
        global $wpdb;
        $start = microtime(true);
        $table_name = $wpdb->prefix . 'wpbench_temp_test';
        $rows_affected = 0;
        $error = null;

        // Create Temp Table
        $charset_collate = $wpdb->get_charset_collate();
        $sql_create = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            name tinytext NOT NULL,
            text text NOT NULL,
            value bigint(20) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_create ); // Use dbDelta for compatibility

         if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
             return [ // If table creation failed, bail out
                 'time' => round(microtime(true) - $start, 4),
                 'operations' => 0,
                 'rows_affected' => 0,
                 'error' => 'Failed to create temporary table: ' . $table_name
             ];
         }


        try {
            for ($i = 0; $i < $iterations; $i++) {
                // INSERT
                $inserted = $wpdb->insert(
                    $table_name,
                    [
                        'time' => current_time( 'mysql' ),
                        'name' => 'wpbench_insert_' . $i,
                        'text' => 'Benchmark test data iteration ' . $i . ' ' . bin2hex(random_bytes(10)),
                        'value' => $i * 1000 + rand(0, 999)
                    ],
                     [ '%s', '%s', '%s', '%d' ]
                );
                if ($inserted) $rows_affected++;

                 // UPDATE (update the row just inserted)
                if ($inserted) {
                    $last_id = $wpdb->insert_id;
                    $updated = $wpdb->update(
                         $table_name,
                         [ 'text' => 'Updated test data iteration ' . $i ], // Data
                         [ 'id' => $last_id ], // Where
                         [ '%s' ], // Data format
                         [ '%d' ] // Where format
                     );
                     if ($updated !== false) $rows_affected++; // update returns rows matched/updated or false on error
                 }

                 // DELETE (delete the row from the previous iteration to keep table size manageable)
                 if ($i > 0 && $last_id > 1) {
                    $deleted = $wpdb->delete( $table_name, [ 'id' => $last_id - 1 ], [ '%d' ] );
                     if ($deleted !== false) $rows_affected++;
                 }
            }
        } catch (Exception $e) {
             $error = $e->getMessage(); // Catch potential DB errors (though $wpdb often suppresses them)
        } finally {
            // Drop Temp Table
            $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
        }

        return [
            'time' => round(microtime(true) - $start, 4),
            'operations' => $iterations * 3, // Approx Insert/Update/Delete
            'rows_affected' => $rows_affected, // Total successful operations
            'error' => $error ?: $wpdb->last_error // Capture last WPDB error if any occurred
        ];
    }


    /**
     * Add the meta box to the CPT edit screen.
     */
    public function add_results_meta_box($post) {
         add_meta_box(
            'wpbench_results_metabox', // $id
            __( 'Benchmark Results & Configuration', 'wpbench' ), // $title
            [ $this, 'render_results_meta_box_content' ], // $callback
            'benchmark_result', // $screen (post type)
            'normal', // $context (normal, side, advanced)
            'high' // $priority (high, core, default, low)
        );
    }

    /**
     * Render the content of the results meta box.
     */
    public function render_results_meta_box_content( $post ) {
        $config = get_post_meta( $post->ID, '_wpbench_config', true );
        $results = get_post_meta( $post->ID, '_wpbench_results', true );
        $active_plugins = get_post_meta( $post->ID, '_wpbench_active_plugins', true );

        if ( empty($config) || empty($results) ) {
            echo '<p>' . esc_html__( 'Benchmark data not found for this result.', 'wpbench' ) . '</p>';
            return;
        }

        echo '<h3>' . esc_html__( 'Benchmark Configuration', 'wpbench' ) . '</h3>';
        echo '<table class="form-table">';
        echo '<tr><th>' . esc_html__('CPU Iterations:', 'wpbench') . '</th><td>' . esc_html( number_format_i18n( $config['cpu_iterations'] ?? 0 ) ) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Memory Test Size (KB):', 'wpbench') . '</th><td>' . esc_html( number_format_i18n( $config['memory_iterations'] ?? 0 ) ) . '</td></tr>';
        echo '<tr><th>' . esc_html__('File I/O Operations:', 'wpbench') . '</th><td>' . esc_html( number_format_i18n( $config['file_io_iterations'] ?? 0 ) ) . '</td></tr>';
        echo '<tr><th>' . esc_html__('DB Read Queries:', 'wpbench') . '</th><td>' . esc_html( number_format_i18n( $config['db_read_iterations'] ?? 0 ) ) . '</td></tr>';
        echo '<tr><th>' . esc_html__('DB Write Operations:', 'wpbench') . '</th><td>' . esc_html( number_format_i18n( $config['db_write_iterations'] ?? 0 ) ) . '</td></tr>';
        echo '</table>';

        echo '<h3>' . esc_html__( 'Benchmark Results', 'wpbench' ) . '</h3>';
        echo '<table class="form-table">';
        echo '<tr><th>' . esc_html__('Total Benchmark Time:', 'wpbench') . '</th><td><strong>' . esc_html( $results['total_time'] ?? 'N/A' ) . ' ' . esc_html__( 'seconds', 'wpbench' ) . '</strong></td></tr>';
        echo '<tr><th>' . esc_html__('CPU Test Time:', 'wpbench') . '</th><td>' . esc_html( $results['cpu']['time'] ?? 'N/A' ) . ' ' . esc_html__( 'seconds', 'wpbench' ) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Memory Test Peak Usage:', 'wpbench') . '</th><td>' . esc_html( $results['memory']['peak_usage_mb'] ?? 'N/A' ) . ' MB (' . esc_html( $results['memory']['time'] ?? 'N/A' ) . ' s)</td></tr>';
        echo '<tr><th>' . esc_html__('File I/O Test Time:', 'wpbench') . '</th><td>' . esc_html( $results['file_io']['time'] ?? 'N/A' ) . ' ' . esc_html__( 'seconds', 'wpbench' ) . ' (' . esc_html( number_format_i18n($results['file_io']['operations'] ?? 0) ) . ' ops) ' . ($results['file_io']['error'] ? '<span style="color:red;">Error: ' . esc_html($results['file_io']['error']) . '</span>' : '') . '</td></tr>';
        echo '<tr><th>' . esc_html__('DB Read Test Time:', 'wpbench') . '</th><td>' . esc_html( $results['db_read']['time'] ?? 'N/A' ) . ' ' . esc_html__( 'seconds', 'wpbench' ) . ' (' . esc_html( number_format_i18n($results['db_read']['queries_executed'] ?? 0) ) . ' queries)</td></tr>';
        echo '<tr><th>' . esc_html__('DB Write Test Time:', 'wpbench') . '</th><td>' . esc_html( $results['db_write']['time'] ?? 'N/A' ) . ' ' . esc_html__( 'seconds', 'wpbench' ) . ' (' . esc_html( number_format_i18n($results['db_write']['operations'] ?? 0) ) . ' ops) ' . ($results['db_write']['error'] ? '<span style="color:red;">Error: ' . esc_html($results['db_write']['error']) . '</span>' : '') . '</td></tr>';
        echo '</table>';

        echo '<h3>' . esc_html__( 'Active Plugins During Test', 'wpbench' ) . '</h3>';
        if (!empty($active_plugins) && is_array($active_plugins)) {
            echo '<ul>';
            foreach ($active_plugins as $plugin) {
                echo '<li>' . esc_html($plugin['name'] ?? 'N/A') . ' (' . esc_html($plugin['version'] ?? 'N/A') . ')</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__( 'No active plugin data recorded or none were active.', 'wpbench' ) . '</p>';
        }


        // Add placeholders for Charts
        echo '<h3>' . esc_html__( 'Result Graphs', 'wpbench' ) . '</h3>';
        echo '<div style="max-width: 600px; margin-bottom: 30px;"><canvas id="wpbenchTimingChart"></canvas></div>';
        echo '<div style="max-width: 600px;"><canvas id="wpbenchMemoryChart"></canvas></div>';

        // Add nonce field if you were planning to add saving capabilities here (not needed for display only)
        // wp_nonce_field( 'wpbench_save_meta', 'wpbench_meta_nonce' );
    }

     /**
      * Add custom columns to the benchmark_result list table.
      */
    public function set_custom_edit_benchmark_result_columns($columns) {
        // Remove or rearrange default columns if needed
        // unset($columns['date']);
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') { // Insert after title
                $new_columns['total_time'] = __( 'Total Time (s)', 'wpbench' );
                $new_columns['cpu_time'] = __( 'CPU Time (s)', 'wpbench' );
                $new_columns['memory_peak'] = __( 'Memory Peak (MB)', 'wpbench' );
            }
        }
        // Add date back at the end if it was unset
         if (!isset($new_columns['date']) && isset($columns['date'])) {
             $new_columns['date'] = $columns['date'];
         }

        return $new_columns;
    }

    /**
     * Display data in custom columns.
     */
    public function custom_benchmark_result_column( $column, $post_id ) {
        $results = get_post_meta( $post_id, '_wpbench_results', true );
        if (empty($results)) return;

        switch ( $column ) {
            case 'total_time':
                echo esc_html( $results['total_time'] ?? 'N/A' );
                break;
            case 'cpu_time':
                 echo esc_html( $results['cpu']['time'] ?? 'N/A' );
                break;
            case 'memory_peak':
                 echo esc_html( $results['memory']['peak_usage_mb'] ?? 'N/A' );
                break;
        }
    }

} // End Class WPBench_Plugin

// Instantiate the plugin class
new WPBench_Plugin();