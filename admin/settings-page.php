<?php
/**
 * Admin settings page for Scholarship Search plugin.
 *
 * Handles the registration of settings, display of the settings form,
 * and manual trigger actions for fetching and cleaning up scholarships.
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
 * @since 1.0.0
 */
function scholarship_search_add_admin_menu() {
    add_menu_page(
        __( 'Scholarship Search Settings', 'scholarship-search' ), // Page Title
        __( 'Scholarship Search', 'scholarship-search' ),          // Menu Title
        'manage_options',                                          // Capability
        'scholarship_search_settings_page',                        // Menu Slug (used for do_settings_sections)
        'scholarship_search_settings_page_html',                   // Callback function to render the page
        'dashicons-search',                                        // Icon
        25                                                         // Position
    );
}
add_action( 'admin_menu', 'scholarship_search_add_admin_menu' );

/**
 * Registers all plugin settings, sections, and fields for the admin page.
 *
 * This function is hooked to 'admin_init'. It defines settings for general display,
 * automated scholarship fetching, and expired scholarship cleanup.
 *
 * @since 1.1.0
 */
function scholarship_search_register_settings() {
    $option_group = 'scholarship_search_settings_group'; // Group name for settings_fields()
    $page_slug    = 'scholarship_search_settings_page';  // Slug for add_settings_section() and do_settings_sections()

    // --- General Display Settings Section ---
    add_settings_section(
        'scholarship_search_general_settings_section', // ID
        __( 'General Display Settings', 'scholarship-search' ), // Title
        function() { echo '<p>' . esc_html__( 'Configure general display settings for the plugin on the frontend.', 'scholarship-search' ) . '</p>'; }, // Section description callback
        $page_slug // Page to display on
    );
    register_setting( $option_group, 'scholarship_search_default_listings_count',
        array( 'type' => 'number', 'sanitize_callback' => 'absint', 'default' => 10 )
    );
    add_settings_field( 'scholarship_search_default_listings_count_field', __( 'Default Listings Per Page', 'scholarship-search'),
        'scholarship_search_render_default_listings_count_field_callback', $page_slug, 'scholarship_search_general_settings_section'
    );

    // --- Automated Scholarship Fetching Section ---
    add_settings_section(
        'scholarship_search_scraper_config_section', // ID
        __( 'Automated Scholarship Fetching', 'scholarship-search' ), // Title
        function() { echo '<p>' . esc_html__( 'Configure settings for automatically fetching and importing new scholarships from external sources.', 'scholarship-search' ) . '</p>'; }, // Section description
        $page_slug // Page
    );
    // Keywords Setting
    register_setting( $option_group, 'scholarship_search_scraper_keywords',
        array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'postdoctoral, research fellowship, phd position' ) // Updated default
    );
    add_settings_field( 'scholarship_search_keywords_field', __( 'Default Scraper Keywords', 'scholarship-search' ),
        'scholarship_search_render_keywords_field_callback', $page_slug, 'scholarship_search_scraper_config_section'
    );
    // Fetching Cron Schedule Setting
    register_setting( $option_group, 'scholarship_search_cron_schedule',
        array( 'type' => 'string', 'sanitize_callback' => 'scholarship_search_sanitize_cron_schedule_callback', 'default' => 'never' )
    );
    add_settings_field( 'scholarship_search_cron_schedule_field', __( 'Fetching Schedule', 'scholarship-search' ),
        'scholarship_search_render_cron_schedule_field_callback', $page_slug, 'scholarship_search_scraper_config_section'
    );

    // --- Expired Scholarship Cleanup Section ---
    add_settings_section(
        'scholarship_search_cleanup_config_section', // ID
        __( 'Expired Scholarship Cleanup', 'scholarship-search' ), // Title
        function() { echo '<p>' . esc_html__( 'Configure automated cleanup of scholarships whose application deadlines have passed.', 'scholarship-search' ) . '</p>'; }, // Section description
        $page_slug // Page
    );
    // Cleanup Cron Schedule Setting
    register_setting( $option_group, 'scholarship_search_cleanup_cron_schedule',
        array( 'type' => 'string', 'sanitize_callback' => 'scholarship_search_sanitize_cleanup_schedule_callback', 'default' => 'never' )
    );
    add_settings_field( 'scholarship_search_cleanup_schedule_field', __( 'Cleanup Schedule', 'scholarship-search' ),
        'scholarship_search_render_cleanup_schedule_field_callback', $page_slug, 'scholarship_search_cleanup_config_section'
    );
    // Cleanup Action Setting
    register_setting( $option_group, 'scholarship_search_cleanup_action',
        array( 'type' => 'string', 'sanitize_callback' => 'scholarship_search_sanitize_cleanup_action_callback', 'default' => 'trash' )
    );
    add_settings_field( 'scholarship_search_cleanup_action_field', __( 'Action on Expired Scholarships', 'scholarship-search' ),
        'scholarship_search_render_cleanup_action_field_callback', $page_slug, 'scholarship_search_cleanup_config_section'
    );
}
add_action( 'admin_init', 'scholarship_search_register_settings' );


// --- Render Callbacks for Settings Fields ---

/**
 * Renders the input field for 'scholarship_search_default_listings_count'.
 * @since 1.1.0
 */
function scholarship_search_render_default_listings_count_field_callback() {
    $count = get_option( 'scholarship_search_default_listings_count', 10 );
    echo '<input type="number" name="scholarship_search_default_listings_count" value="' . esc_attr( $count ) . '" class="small-text" min="1" max="50" />';
    echo '<p class="description">' . esc_html__( 'Number of scholarships shown by default on the search page before a search is performed.', 'scholarship-search' ) . '</p>';
}

/**
 * Renders the input field for 'scholarship_search_scraper_keywords'.
 * @since 1.1.0
 */
function scholarship_search_render_keywords_field_callback() {
    $keywords = get_option( 'scholarship_search_scraper_keywords', 'postdoctoral, research fellowship, phd position' );
    echo '<input type="text" name="scholarship_search_scraper_keywords" value="' . esc_attr( $keywords ) . '" class="regular-text" />';
    echo '<p class="description">' . esc_html__( 'Comma-separated keywords used by the automated fetching job. Example: engineering, computer science, art history', 'scholarship-search' ) . '</p>';
}

/**
 * Renders the select field for 'scholarship_search_cron_schedule' (Fetching Cron).
 * Displays next scheduled time and last run summary.
 * @since 1.1.0
 */
function scholarship_search_render_cron_schedule_field_callback() {
    $current_schedule = get_option( 'scholarship_search_cron_schedule', 'never' );
    $schedules = array('never' => __('Never (Off)'), 'hourly' => __('Hourly'), 'twicedaily' => __('Twice Daily'), 'daily' => __('Daily'));
    echo '<select name="scholarship_search_cron_schedule" id="scholarship_search_cron_schedule">';
    foreach ( $schedules as $value => $label ) {
        echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current_schedule, $value, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select>';
    $timestamp = wp_next_scheduled( 'scholarship_search_fetch_cron_hook' ); // Use hook string directly
    echo '<p class="description">';
    if ( $timestamp ) {
        $local_time_str = get_date_from_gmt( date( 'Y-m-d H:i:s', $timestamp ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
        /* translators: 1: formatted date/time string, 2: time difference string, 3: current server time string */
        printf( esc_html__( 'Next scheduled fetch: %1$s (%2$s from now). Current server time: %3$s.', 'scholarship-search' ), '<strong>' . esc_html( $local_time_str ) . '</strong>', esc_html( human_time_diff( $timestamp, time() ) ), esc_html( date_i18n( get_option('date_format') . ' ' . get_option('time_format') ) ) );
    } else { esc_html_e( 'Fetching not currently scheduled.', 'scholarship-search' ); }
    echo '</p>';
    $last_run_summary = get_transient('scholarship_search_last_cron_run_summary');
    if ( $last_run_summary ) { echo '<p class="description"><em>' . esc_html__( 'Last fetch summary: ', 'scholarship-search') . esc_html( $last_run_summary ) . '</em></p>'; }
}

/**
 * Renders the select field for 'scholarship_search_cleanup_cron_schedule' (Cleanup Cron).
 * Displays next scheduled time and last run summary.
 * @since 1.1.0
 */
function scholarship_search_render_cleanup_schedule_field_callback() {
    $current_schedule = get_option( 'scholarship_search_cleanup_cron_schedule', 'never' );
    $schedules = array('never' => __('Never (Off)'), 'daily' => __('Daily'), 'weekly' => __('Weekly'));
    $wp_schedules = wp_get_schedules();
    if(isset($wp_schedules['weekly'])) { $schedules['weekly'] = $wp_schedules['weekly']['display']; } // Ensure 'weekly' display name is used if available

    echo '<select name="scholarship_search_cleanup_cron_schedule" id="scholarship_search_cleanup_cron_schedule">';
    foreach ( $schedules as $value => $label ) {
        echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current_schedule, $value, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select>';
    $timestamp = wp_next_scheduled( 'scholarship_search_cleanup_expired_hook' ); // Use hook string directly
    echo '<p class="description">';
    if ( $timestamp ) {
        $local_time_str = get_date_from_gmt( date( 'Y-m-d H:i:s', $timestamp ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
        /* translators: 1: formatted date/time string, 2: time difference string */
        printf( esc_html__( 'Next scheduled cleanup: %1$s (%2$s from now).', 'scholarship-search' ), '<strong>' . esc_html( $local_time_str ) . '</strong>', esc_html( human_time_diff( $timestamp, time() ) ) );
    } else { esc_html_e( 'Cleanup not currently scheduled.', 'scholarship-search' ); }
    echo '</p>';
    $last_run_summary = get_transient('scholarship_search_last_cleanup_cron_summary');
    if ( $last_run_summary ) { echo '<p class="description"><em>' . esc_html__( 'Last cleanup summary: ', 'scholarship-search') . esc_html( $last_run_summary ) . '</em></p>'; }
}

/**
 * Renders the select field for 'scholarship_search_cleanup_action'.
 * @since 1.1.0
 */
function scholarship_search_render_cleanup_action_field_callback() {
    $current_action = get_option( 'scholarship_search_cleanup_action', 'trash' );
    $actions = array('trash' => __('Move to Trash'), 'draft' => __('Change to Draft status'));
    echo '<select name="scholarship_search_cleanup_action" id="scholarship_search_cleanup_action">';
    foreach ( $actions as $value => $label ) {
        echo '<option value="' . esc_attr( $value ) . '" ' . selected( $current_action, $value, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__( 'Select the action to perform on scholarships whose application deadlines have passed.', 'scholarship-search' ) . '</p>';
}


// --- Sanitize Callbacks for Settings ---

/**
 * Sanitizes the fetching cron schedule option.
 * @since 1.1.0
 */
function scholarship_search_sanitize_cron_schedule_callback( $input ) {
    $schedules = wp_get_schedules(); $valid_keys = array_keys( $schedules ); $valid_keys[] = 'never';
    if ( in_array( $input, $valid_keys, true ) ) { return $input; }
    add_settings_error( 'scholarship_search_cron_schedule', 'invalid_schedule', __('Invalid fetching schedule selected. Defaulting to "Never".', 'scholarship-search'), 'error');
    return 'never';
}

/**
 * Sanitizes the cleanup cron schedule option.
 * @since 1.1.0
 */
function scholarship_search_sanitize_cleanup_schedule_callback( $input ) {
    $schedules = wp_get_schedules(); $valid_keys = array_keys( $schedules ); $valid_keys[] = 'never'; // 'weekly' should be in $schedules
    if ( in_array( $input, $valid_keys, true ) ) { return $input; }
    add_settings_error( 'scholarship_search_cleanup_cron_schedule', 'invalid_schedule', __('Invalid cleanup schedule selected. Defaulting to "Never".', 'scholarship-search'), 'error');
    return 'never';
}

/**
 * Sanitizes the cleanup action option.
 * @since 1.1.0
 */
function scholarship_search_sanitize_cleanup_action_callback( $input ) {
    $valid_actions = array( 'trash', 'draft' );
    if ( in_array( $input, $valid_actions, true ) ) { return $input; }
    add_settings_error( 'scholarship_search_cleanup_action', 'invalid_action', __('Invalid cleanup action selected. Defaulting to "Trash".', 'scholarship-search'), 'error');
    return 'trash';
}


// --- Main Settings Page HTML ---

/**
 * Renders the HTML for the main Scholarship Search settings page.
 *
 * This function handles the display of settings forms (main settings, manual fetch, manual cleanup)
 * and processes the submissions for manual actions.
 *
 * @since 1.0.0 (structure revised 1.1.0)
 */
function scholarship_search_settings_page_html() {
    // Security check: ensure user has appropriate capabilities.
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'scholarship-search' ) );
    }

    // --- Handle Manual Fetch Trigger ---
    if ( isset( $_POST['manual_fetch_scholarships_button'] ) && check_admin_referer( 'scholarship_search_manual_fetch_nonce_action', 'scholarship_search_manual_fetch_nonce_field' ) ) {
        $manual_keywords_raw = isset( $_POST['manual_keywords'] ) ? trim( sanitize_text_field( $_POST['manual_keywords'] ) ) : '';
        $keywords_to_use = !empty($manual_keywords_raw) ? $manual_keywords_raw : get_option( 'scholarship_search_scraper_keywords', 'scholarship, phd' ); // Fallback keywords
        $max_pages_manual = isset( $_POST['manual_max_pages'] ) ? absint( $_POST['manual_max_pages'] ) : 1;
        $max_pages_manual = max(1, min(5, $max_pages_manual)); // Clamp between 1 and 5 for safety.

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log("Scholarship Search Admin: Manual Scrape Triggered. Keywords: {$keywords_to_use}, Max Pages: {$max_pages_manual}"); }

        if ( function_exists( 'scholarship_search_run_importer' ) ) {
            $result = scholarship_search_run_importer( $keywords_to_use, $max_pages_manual );
            if ( is_array( $result ) ) {
                $notice = sprintf( esc_html__( 'Manual fetch complete. Processed %d items, added %d new scholarships.', 'scholarship-search' ),
                                   absint($result['processed_count'] ?? 0),
                                   absint($result['newly_added_count'] ?? 0) );
                if (isset($result['error'])) { $notice .= ' Error: ' . esc_html($result['error']); }
                add_settings_error('scholarship_search_manual_fetch', 'manual_fetch_success', $notice, 'updated');
            } else {
                add_settings_error('scholarship_search_manual_fetch', 'manual_fetch_error', __('Manual fetch process did not return expected results.', 'scholarship-search'), 'error');
            }
        } else {
            add_settings_error('scholarship_search_manual_fetch', 'manual_fetch_error', __('Importer function (scholarship_search_run_importer) not found.', 'scholarship-search'), 'error');
        }
    }

    // --- Handle Manual Cleanup Trigger ---
    if ( isset( $_POST['manual_cleanup_scholarships_button'] ) && check_admin_referer( 'scholarship_search_manual_cleanup_nonce_action', 'scholarship_search_manual_cleanup_nonce_field' ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log("Scholarship Search Admin: Manual Cleanup Triggered."); }

        if ( function_exists( 'scholarship_search_execute_cleanup_cron' ) ) {
            scholarship_search_execute_cleanup_cron(); // This function sets its own transient for summary.
            $summary = get_transient('scholarship_search_last_cleanup_cron_summary');
            if($summary){
                add_settings_error('scholarship_search_manual_cleanup', 'manual_cleanup_success', $summary . __(' (Manual Run)', 'scholarship-search'), 'updated');
            } else {
                // This case might occur if the cleanup function itself had an issue setting the transient.
                add_settings_error('scholarship_search_manual_cleanup', 'manual_cleanup_notice', __('Manual cleanup process initiated. Check logs for details if summary is not available.', 'scholarship-search'), 'info');
            }
        } else {
            add_settings_error('scholarship_search_manual_cleanup', 'manual_cleanup_error', __('Cleanup function (scholarship_search_execute_cleanup_cron) not found.', 'scholarship-search'), 'error');
        }
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'Scholarship Search Settings', 'scholarship-search' ); ?></h1>
        <?php settings_errors(); // Display all admin notices (settings updated, manual action results, etc.) ?>

        <form method="post" action="options.php">
            <?php
            settings_fields( 'scholarship_search_settings_group' ); // Option group for all settings.
            // Renders all sections and fields registered to the '$page_slug' (scholarship_search_settings_page).
            do_settings_sections( 'scholarship_search_settings_page' );
            submit_button( __( 'Save All Settings', 'scholarship-search' ) ); // Single save button for all settings.
            ?>
        </form>

        <hr>
        <h2><?php esc_html_e( 'Manual Actions', 'scholarship-search' ); ?></h2>
        <p><?php esc_html_e( 'Manually trigger plugin actions. These do not affect the saved settings above but use them if specific inputs below are left empty.', 'scholarship-search' ); ?></p>

        <div class="manual-action-box" style="margin-bottom: 20px; padding:15px; background-color: #fff; border: 1px solid #ccd0d4; border-radius:4px;">
            <h3><?php esc_html_e( 'Manual Scholarship Fetch', 'scholarship-search' ); ?></h3>
            <p><?php esc_html_e('Trigger the scholarship scraping and import process. Uses keywords below, or saved defaults if empty.', 'scholarship-search'); ?></p>
            <form method="post" action=""> <?php // Form submits to the current page to handle action at the top. ?>
                <?php wp_nonce_field( 'scholarship_search_manual_fetch_nonce_action', 'scholarship_search_manual_fetch_nonce_field' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="manual_keywords"><?php esc_html_e( 'Keywords for this Run', 'scholarship-search' ); ?></label></th>
                        <td>
                            <input type="text" name="manual_keywords" id="manual_keywords" class="regular-text" placeholder="<?php echo esc_attr(get_option('scholarship_search_scraper_keywords')); ?>" />
                            <p class="description"><?php esc_html_e( 'If empty, uses "Default Scraper Keywords" saved above.', 'scholarship-search' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="manual_max_pages"><?php esc_html_e( 'Max Pages per Source', 'scholarship-search' ); ?></label></th>
                        <td>
                            <input type="number" name="manual_max_pages" id="manual_max_pages" class="small-text" value="1" min="1" max="5" />
                            <p class="description"><?php esc_html_e( 'Maximum pages to fetch from each source for this run (1-5). Use with caution.', 'scholarship-search' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Manually Fetch Scholarships', 'scholarship-search' ), 'secondary', 'manual_fetch_scholarships_button' ); ?>
            </form>
        </div>

        <div class="manual-action-box" style="padding:15px; background-color: #fff; border: 1px solid #ccd0d4; border-radius:4px;">
            <h3><?php esc_html_e( 'Manual Expired Scholarship Cleanup', 'scholarship-search' ); ?></h3>
            <p><?php esc_html_e('Trigger the cleanup of expired scholarships. This uses the saved "Action on Expired Scholarships" setting from above.', 'scholarship-search'); ?></p>
            <form method="post" action=""> <?php // Form submits to the current page. ?>
                <?php wp_nonce_field( 'scholarship_search_manual_cleanup_nonce_action', 'scholarship_search_manual_cleanup_nonce_field' ); ?>
                <?php submit_button( __( 'Manually Cleanup Expired Scholarships', 'scholarship-search' ), 'secondary', 'manual_cleanup_scholarships_button' ); ?>
            </form>
        </div>

        <hr style="margin-top: 30px;">
        <h2><?php esc_html_e( 'Plugin Information & Usage', 'scholarship-search' ); ?></h2>
        <p>
            <?php esc_html_e( 'Manage scholarships via the "Scholarships" menu. Categories are under "Scholarships > Scholarship Categories".', 'scholarship-search' ); ?>
        </p>
        <h3><?php esc_html_e( 'Displaying the Search Form', 'scholarship-search' ); ?></h3>
        <p>
            <?php printf( esc_html__( 'Use the shortcode %s on any page or post to display the search form and scholarship listings.', 'scholarship-search' ), '<code>[scholarship_search_form]</code>'); ?>
        </p>
        <h3><?php esc_html_e( 'Custom Fields for Scholarships', 'scholarship-search' ); ?></h3>
        <p>
            <?php esc_html_e( 'When adding or editing a scholarship, use these custom fields (managed via the WordPress Custom Fields metabox):', 'scholarship-search' ); ?>
        </p>
        <ul>
            <li><strong><code>_scholarship_url</code></strong>: <?php esc_html_e( 'Direct URL to the scholarship application or information page.', 'scholarship-search' ); ?></li>
            <li><strong><code>_scholarship_deadline</code></strong>: <?php esc_html_e( 'Application deadline (format: YYYY-MM-DD). Essential for cleanup.', 'scholarship-search' ); ?></li>
            <li><strong><code>_scholarship_country</code></strong>: <?php esc_html_e( 'Country associated with the scholarship.', 'scholarship-search' ); ?></li>
            <li><strong><code>_scholarship_is_featured</code></strong>: <?php esc_html_e( "Set to '1' or 'true' to feature in the search form's dropdown.", 'scholarship-search' ); ?></li>
            <li><strong><code>_scholarship_source</code></strong>: <?php esc_html_e( 'The source website where the scholarship was found (e.g., scholarshipdb.net). Automatically set by the scraper.', 'scholarship-search' ); ?></li>
            <li><strong><code>_posted_date</code></strong>: <?php esc_html_e( 'The date the scholarship was posted on the source site or scraped (format: YYYY-MM-DD HH:MM:SS). Used for sorting "Recently Added" listings. Automatically set by the scraper.', 'scholarship-search' ); ?></li>
        </ul>
    </div>
    <?php
}
?>
