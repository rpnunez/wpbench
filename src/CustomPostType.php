<?php
namespace WPBench;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the registration of the Benchmark Result Custom Post Type.
 */
class CustomPostType {

    const POST_TYPE = 'benchmark_result';

    /**
     * Register the Custom Post Type.
     * Hooked into 'init'.
     */
    public function register() {
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
            'show_in_menu'       => 'wpbench_main_menu', // Show under our main menu (defined in AdminBenchmark)
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

        register_post_type( self::POST_TYPE, $args );
    }
}