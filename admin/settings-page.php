<?php
/**
 * Admin settings page for Scholarship Search plugin.
 *
 * @package ScholarshipSearch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Adds the Scholarship Search admin menu item.
 *
 * This function is hooked into 'admin_menu' and uses add_menu_page()
 * to create a top-level menu item for the plugin's settings.
 *
 * @since 1.0.0
 */
function scholarship_search_add_admin_menu() {
    add_menu_page(
        __( 'Scholarship Search Settings', 'scholarship-search' ), // Page title displayed in the browser title bar.
        __( 'Scholarship Search', 'scholarship-search' ),          // Menu title displayed in the admin menu.
        'manage_options',                                          // Capability required to access this menu page.
        'scholarship_search_settings',                             // Unique menu slug.
        'scholarship_search_settings_page_html',                   // Callback function to render the page content.
        'dashicons-search',                                        // Icon for the menu item (WordPress Dashicon).
        25                                                         // Position in the menu order.
    );
}
// Hook the menu registration function to the 'admin_menu' action.
add_action( 'admin_menu', 'scholarship_search_add_admin_menu' );

/**
 * Renders the HTML content for the Scholarship Search settings page.
 *
 * This function is the callback for the admin menu page. It outputs
 * introductory information about managing scholarships, categories,
 * and using the search form shortcode.
 *
 * @since 1.0.0
 */
function scholarship_search_settings_page_html() {
    // Check if the current user has the 'manage_options' capability.
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'scholarship-search' ) );
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); // Display the page title. ?></h1>

        <p>
            <?php esc_html_e( 'Welcome to the Scholarship Search plugin settings. You can manage individual scholarships under the "Scholarships" menu item in the admin sidebar.', 'scholarship-search' ); ?>
        </p>
        <p>
            <?php esc_html_e( 'Scholarship categories, such as "Undergraduate" or "Engineering", can be managed under "Scholarships" > "Scholarship Categories". An initial set of categories is created upon plugin activation.', 'scholarship-search' ); ?>
        </p>

        <h2><?php esc_html_e( 'Displaying the Search Form', 'scholarship-search' ); ?></h2>
        <p>
            <?php
            /* translators: %s: Shortcode string */
            printf(
                esc_html__( 'To display the scholarship search form on any page or post, use the following shortcode: %s', 'scholarship-search' ),
                '<code>[scholarship_search_form]</code>'
            );
            ?>
        </p>
        <p>
            <?php esc_html_e( 'This form allows visitors to search scholarships by keyword, category, or select from featured scholarships.', 'scholarship-search' ); ?>
        </p>

        <h2><?php esc_html_e( 'Managing Scholarship Details', 'scholarship-search' ); ?></h2>
        <p>
            <?php esc_html_e( 'When adding or editing a scholarship, you can use the standard WordPress "Custom Fields" metabox to add specific details:', 'scholarship-search' ); ?>
        </p>
        <ul>
            <li><strong><code>_scholarship_url</code></strong>: <?php esc_html_e( 'The direct URL to the scholarship application or information page (e.g., https://example.com/scholarship-info).', 'scholarship-search' ); ?></li>
            <li><strong><code>_scholarship_deadline</code></strong>: <?php esc_html_e( 'The application deadline (e.g., 2024-12-31).', 'scholarship-search' ); ?></li>
            <li><strong><code>_scholarship_country</code></strong>: <?php esc_html_e( 'The country associated with the scholarship (e.g., USA, Canada).', 'scholarship-search' ); ?></li>
            <li><strong><code>_scholarship_is_featured</code></strong>: <?php esc_html_e( "Set to '1' or 'true' to make the scholarship appear in the 'Featured Scholarships' dropdown on the search form.", 'scholarship-search' ); ?></li>
        </ul>
        <p>
            <em><?php esc_html_e( '(In the future, more settings related to search result display and custom field management might be added here using the WordPress Settings API.)', 'scholarship-search' ); ?></em>
        </p>
    </div>
    <?php
}
?>
