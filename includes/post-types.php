<?php
/**
 * Registers the custom post type for scholarships.
 *
 * @package ScholarshipSearch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Registers the 'scholarship' custom post type.
 *
 * This function defines the labels, arguments, and registers the
 * custom post type using register_post_type().
 *
 * @since 1.0.0
 */
function scholarship_search_register_post_type() {
    // Define labels for the custom post type.
    $labels = array(
        'name'                  => _x( 'Scholarships', 'Post type general name', 'scholarship-search' ),
        'singular_name'         => _x( 'Scholarship', 'Post type singular name', 'scholarship-search' ),
        'menu_name'             => _x( 'Scholarships', 'Admin Menu text', 'scholarship-search' ),
        'name_admin_bar'        => _x( 'Scholarship', 'Add New on Toolbar', 'scholarship-search' ),
        'add_new'               => __( 'Add New', 'scholarship-search' ),
        'add_new_item'          => __( 'Add New Scholarship', 'scholarship-search' ),
        'new_item'              => __( 'New Scholarship', 'scholarship-search' ),
        'edit_item'             => __( 'Edit Scholarship', 'scholarship-search' ),
        'view_item'             => __( 'View Scholarship', 'scholarship-search' ),
        'all_items'             => __( 'All Scholarships', 'scholarship-search' ),
        'search_items'          => __( 'Search Scholarships', 'scholarship-search' ),
        'parent_item_colon'     => __( 'Parent Scholarships:', 'scholarship-search' ),
        'not_found'             => __( 'No scholarships found.', 'scholarship-search' ),
        'not_found_in_trash'    => __( 'No scholarships found in Trash.', 'scholarship-search' ),
        'featured_image'        => _x( 'Scholarship Logo', 'Overrides the “Featured Image” phrase for this post type. Added in 4.3', 'scholarship-search' ),
        'set_featured_image'    => _x( 'Set scholarship logo', 'Overrides the “Set featured image” phrase for this post type. Added in 4.3', 'scholarship-search' ),
        'remove_featured_image' => _x( 'Remove scholarship logo', 'Overrides the “Remove featured image” phrase for this post type. Added in 4.3', 'scholarship-search' ),
        'use_featured_image'    => _x( 'Use as scholarship logo', 'Overrides the “Use as featured image” phrase for this post type. Added in 4.3', 'scholarship-search' ),
        'archives'              => _x( 'Scholarship archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'scholarship-search' ),
        'insert_into_item'      => _x( 'Insert into scholarship', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'scholarship-search' ),
        'uploaded_to_this_item' => _x( 'Uploaded to this scholarship', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'scholarship-search' ),
        'filter_items_list'     => _x( 'Filter scholarships list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'scholarship-search' ),
        'items_list_navigation' => _x( 'Scholarships list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'scholarship-search' ),
        'items_list'            => _x( 'Scholarships list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'scholarship-search' ),
    );

    // Define arguments for the custom post type.
    $args = array(
        'labels'             => $labels,
        'public'             => true, // Controls how the type is visible to authors (show_in_nav_menus, show_ui) and readers (exclude_from_search, publicly_queryable).
        'publicly_queryable' => true, // Whether queries can be performed on the front end as part of parse_request().
        'show_ui'            => true, // Whether to generate a default UI for managing this post type in the admin.
        'show_in_menu'       => true, // Where to show the post type in the admin menu. show_ui must be true.
        'query_var'          => true, // Sets the query_var key for this post type.
        'rewrite'            => array( 'slug' => 'scholarships' ), // Triggers the handling of rewrites for this post type.
        'capability_type'    => 'post', // The string to use to build the read, edit, and delete capabilities.
        'has_archive'        => true, // Enables post type archives.
        'hierarchical'       => false, // Whether the post type is hierarchical (e.g., like pages).
        'menu_position'      => 5, // The position in the menu order the post type should appear. 5 means below Posts.
        'supports'           => array( 'title', 'editor', 'custom-fields', 'thumbnail' ), // Core feature(s) the post type supports.
        'show_in_rest'       => true, // Whether to expose this post type in the REST API.
        'menu_icon'          => 'dashicons-awards', // The icon for this menu item.
    );

    // Register the custom post type.
    register_post_type( 'scholarship', $args );
}
// Hook the CPT registration function to the 'init' action.
add_action( 'init', 'scholarship_search_register_post_type' );
?>
