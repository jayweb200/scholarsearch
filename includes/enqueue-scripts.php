<?php
/**
 * Enqueues scripts and styles for the Scholarship Search plugin.
 *
 * @package ScholarshipSearch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Enqueues the frontend stylesheet for the scholarship search plugin.
 *
 * This function is hooked into 'wp_enqueue_scripts' to ensure the CSS
 * is loaded only on the frontend of the site.
 *
 * @since 1.0.0
 */
function scholarship_search_enqueue_styles() {
    // A more advanced check could be to only enqueue if the shortcode `[scholarship_search_form]`
    // is present on the current page, or if viewing a 'scholarship' CPT archive/single page.
    // However, for simplicity and to ensure styling is available if the shortcode is used
    // in less predictable places (e.g., widgets, theme templates), it's enqueued more broadly.
    // If performance becomes a concern, this logic can be refined.

    wp_enqueue_style(
        'scholarship-search-style', // Unique handle for the stylesheet.
        SCHOLARSHIP_SEARCH_PLUGIN_URL . 'public/css/scholarship-search-style.css', // Full URL to the stylesheet.
        array(), // Array of handles of any stylesheets that this stylesheet depends on (none in this case).
        SCHOLARSHIP_SEARCH_VERSION, // Version number of the stylesheet (uses plugin version).
        'all' // Media for which this stylesheet has been defined (all, screen, print, handheld).
    );
}
// Hook the style enqueue function to the 'wp_enqueue_scripts' action.
add_action( 'wp_enqueue_scripts', 'scholarship_search_enqueue_styles' );

/**
 * Placeholder for enqueuing admin-specific styles or scripts.
 *
 * This function is commented out but provides a structure for adding
 * admin-side CSS or JavaScript if needed in the future.
 *
 * @since 1.0.0
 */
/*
function scholarship_search_enqueue_admin_assets() {
    // Example: Enqueue an admin-specific stylesheet.
    // wp_enqueue_style(
    //     'scholarship-search-admin-style',
    //     SCHOLARSHIP_SEARCH_PLUGIN_URL . 'admin/css/scholarship-admin-style.css',
    //     array(),
    //     SCHOLARSHIP_SEARCH_VERSION
    // );

    // Example: Enqueue an admin-specific JavaScript file.
    // wp_enqueue_script(
    //     'scholarship-search-admin-script',
    //     SCHOLARSHIP_SEARCH_PLUGIN_URL . 'admin/js/scholarship-admin-script.js',
    //     array( 'jquery' ), // Dependencies
    //     SCHOLARSHIP_SEARCH_VERSION,
    //     true // Load in footer
    // );
}
add_action( 'admin_enqueue_scripts', 'scholarship_search_enqueue_admin_assets' );
*/
?>
