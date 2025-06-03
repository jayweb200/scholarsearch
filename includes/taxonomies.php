<?php
/**
 * Registers custom taxonomies for the Scholarship Search plugin.
 *
 * @package ScholarshipSearch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Registers the 'scholarship_category' custom taxonomy.
 *
 * This function defines the labels, arguments, and registers the
 * custom taxonomy associated with the 'scholarship' post type.
 *
 * @since 1.0.0
 */
function scholarship_search_register_taxonomy() {
    // Define labels for the custom taxonomy.
    $labels = array(
        'name'                       => _x( 'Scholarship Categories', 'taxonomy general name', 'scholarship-search' ),
        'singular_name'              => _x( 'Scholarship Category', 'taxonomy singular name', 'scholarship-search' ),
        'search_items'               => __( 'Search Scholarship Categories', 'scholarship-search' ),
        'popular_items'              => __( 'Popular Scholarship Categories', 'scholarship-search' ),
        'all_items'                  => __( 'All Scholarship Categories', 'scholarship-search' ),
        'parent_item'                => __( 'Parent Scholarship Category', 'scholarship-search' ),
        'parent_item_colon'          => __( 'Parent Scholarship Category:', 'scholarship-search' ),
        'edit_item'                  => __( 'Edit Scholarship Category', 'scholarship-search' ),
        'update_item'                => __( 'Update Scholarship Category', 'scholarship-search' ),
        'add_new_item'               => __( 'Add New Scholarship Category', 'scholarship-search' ),
        'new_item_name'              => __( 'New Scholarship Category Name', 'scholarship-search' ),
        'separate_items_with_commas' => __( 'Separate categories with commas', 'scholarship-search' ),
        'add_or_remove_items'        => __( 'Add or remove categories', 'scholarship-search' ),
        'choose_from_most_used'      => __( 'Choose from the most used categories', 'scholarship-search' ),
        'not_found'                  => __( 'No scholarship categories found.', 'scholarship-search' ),
        'menu_name'                  => __( 'Scholarship Categories', 'scholarship-search' ),
        'back_to_items'              => __( '← Back to Scholarship Categories', 'scholarship-search' ),
    );

    // Define arguments for the custom taxonomy.
    $args = array(
        'hierarchical'      => true, // Behaves like categories (true) or tags (false).
        'labels'            => $labels,
        'show_ui'           => true, // Whether to generate a default UI for managing this taxonomy in the admin.
        'show_admin_column' => true, // Whether to display a column for the taxonomy on its associated post type listing screen.
        'query_var'         => true, // Sets the query_var key for this taxonomy.
        'rewrite'           => array( 'slug' => 'scholarship-category' ), // Triggers the handling of rewrites for this taxonomy.
        'show_in_rest'      => true, // Whether to expose this taxonomy in the REST API.
    );

    // Register the custom taxonomy and associate it with the 'scholarship' post type.
    register_taxonomy( 'scholarship_category', array( 'scholarship' ), $args );
}
// Hook the taxonomy registration function to the 'init' action.
add_action( 'init', 'scholarship_search_register_taxonomy' );
?>
