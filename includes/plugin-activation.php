<?php
/**
 * Plugin activation and deactivation hooks.
 *
 * @package ScholarshipSearch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Plugin activation callback.
 *
 * This function is triggered when the plugin is activated.
 * It registers the custom post type ('scholarship') and custom taxonomy ('scholarship_category')
 * to ensure they are available immediately. It also adds a predefined list of
 * scholarship categories as terms to the 'scholarship_category' taxonomy if they don't already exist.
 * Finally, it flushes WordPress rewrite rules to ensure the new CPT and taxonomy URLs are correctly recognized.
 *
 * @since 1.0.0
 */
function scholarship_search_activate_plugin() {
    // --- Register 'scholarship' Custom Post Type ---
    // This is a direct registration call, mirroring the one in 'includes/post-types.php'
    // to ensure CPT is available during activation before the 'init' hook fires.
    $cpt_labels = array(
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
        'featured_image'        => _x( 'Scholarship Logo', 'Overrides the “Featured Image” phrase for this post type.', 'scholarship-search' ),
        'set_featured_image'    => _x( 'Set scholarship logo', 'Overrides the “Set featured image” phrase for this post type.', 'scholarship-search' ),
        'remove_featured_image' => _x( 'Remove scholarship logo', 'Overrides the “Remove featured image” phrase for this post type.', 'scholarship-search' ),
        'use_featured_image'    => _x( 'Use as scholarship logo', 'Overrides the “Use as featured image” phrase for this post type.', 'scholarship-search' ),
        'archives'              => _x( 'Scholarship archives', 'The post type archive label used in nav menus.', 'scholarship-search' ),
        'insert_into_item'      => _x( 'Insert into scholarship', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post).', 'scholarship-search' ),
        'uploaded_to_this_item' => _x( 'Uploaded to this scholarship', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post).', 'scholarship-search' ),
        'filter_items_list'     => _x( 'Filter scholarships list', 'Screen reader text for the filter links heading on the post type listing screen.', 'scholarship-search' ),
        'items_list_navigation' => _x( 'Scholarships list navigation', 'Screen reader text for the pagination heading on the post type listing screen.', 'scholarship-search' ),
        'items_list'            => _x( 'Scholarships list', 'Screen reader text for the items list heading on the post type listing screen.', 'scholarship-search' ),
    );
    $cpt_args = array(
        'labels'             => $cpt_labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'scholarships' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 5, // Position below Posts
        'supports'           => array( 'title', 'editor', 'custom-fields', 'thumbnail' ),
        'show_in_rest'       => true,
        'menu_icon'          => 'dashicons-awards',
    );
    register_post_type( 'scholarship', $cpt_args );

    // --- Register 'scholarship_category' Custom Taxonomy ---
    // Similar to CPT, this is a direct registration for availability during activation.
    $tax_labels = array(
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
    $tax_args = array(
        'hierarchical'      => true,
        'labels'            => $tax_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'scholarship-category' ),
        'show_in_rest'      => true,
    );
    register_taxonomy( 'scholarship_category', array( 'scholarship' ), $tax_args );

    // --- Add Default Scholarship Categories ---
    // These categories are added to provide a starting point for users.
    $default_categories = array(
        'Academic Major Specific',
        'Engineering',
        'Arts',
        'Humanities',
        'Computer Science',
        'Nursing',
        'Business Administration',
        'Student Type',
        'Undergraduate',
        'Postgraduate',
        'Masters Program',
        'PhD Program',
        'International Student',
        'Minority Student',
        'Women in STEM',
        'Veterans',
        'Funding Type',
        'Merit-Based',
        'Need-Based',
        'Athletic Scholarship',
        'Community Service',
        'Special Conditions',
        'Disability-Specific',
        'First-Generation College Students',
        'Location Specific', // Example: For scholarships tied to specific regions if not using custom fields for this.
        // 'Europe',
        // 'North America',
    );

    foreach ( $default_categories as $category_name ) {
        // Check if the term already exists to avoid errors or duplicates.
        if ( ! term_exists( $category_name, 'scholarship_category' ) ) {
            // Insert the term into the 'scholarship_category' taxonomy.
            wp_insert_term( $category_name, 'scholarship_category' );
            // Error handling for wp_insert_term can be added here if necessary.
        }
    }

    // --- Flush Rewrite Rules ---
    // This is crucial to ensure that the new CPT and taxonomy permalinks work correctly immediately after activation.
    flush_rewrite_rules();

    // --- Set Default Cron Schedule on Activation ---
    // This function is defined in includes/cron.php, ensure it's loaded if called directly here.
    // For safety, check if function exists or ensure cron.php is included before this activation hook runs,
    // or include it temporarily if necessary. However, typical plugin structure includes all files first.
    if ( function_exists( 'scholarship_search_activation_default_cron_schedule' ) ) {
        scholarship_search_activation_default_cron_schedule();
    } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log('Scholarship Search: scholarship_search_activation_default_cron_schedule() not found during activation.');
    }
}

/**
 * Plugin deactivation callback.
 *
 * This function is triggered when the plugin is deactivated.
 * It unregisters the 'scholarship' custom post type and the 'scholarship_category' custom taxonomy.
 * It also flushes rewrite rules and clears any scheduled cron jobs for the plugin.
 * Note that this does not delete any data (posts, terms, or metadata).
 *
 * @since 1.0.0 (modified 1.1.0)
 */
function scholarship_search_deactivate_plugin() {
    // Unregister the post type.
    // This does not remove any 'scholarship' posts from the database.
    unregister_post_type( 'scholarship' );

    // Unregister the taxonomy.
    // This does not remove any 'scholarship_category' terms or their associations from the database.
    unregister_taxonomy( 'scholarship_category' );

    // Flush rewrite rules to remove the CPT and taxonomy rules.
    flush_rewrite_rules();

    // --- Clear Scheduled Cron Events ---
    // This function is defined in includes/cron.php.
    if ( function_exists( 'scholarship_search_clear_scheduled_events' ) ) {
        scholarship_search_clear_scheduled_events();
    } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log('Scholarship Search: scholarship_search_clear_scheduled_events() not found during deactivation.');
    }
}
?>
