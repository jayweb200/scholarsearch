<?php
/**
 * Handles WP Cron tasks for the Scholarship Search plugin.
 *
 * This file includes functions for scheduling and executing automated tasks such as
 * fetching new scholarships and cleaning up expired ones.
 *
 * @package ScholarshipSearch
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * The unique hook for the scholarship fetching cron job.
 * Used for scheduling and unscheduling the fetching task.
 * @since 1.1.0
 */
define( 'SCHOLARSHIP_SEARCH_CRON_HOOK', 'scholarship_search_fetch_cron_hook' );

/**
 * The unique hook for the scholarship cleanup cron job.
 * Used for scheduling and unscheduling the cleanup task.
 * @since 1.1.0
 */
define( 'SCHOLARSHIP_SEARCH_CLEANUP_CRON_HOOK', 'scholarship_search_cleanup_expired_hook' );


// --- Fetching Cron Job ---

/**
 * Executes the scholarship fetching process for the cron job.
 *
 * This function is the callback for `SCHOLARSHIP_SEARCH_CRON_HOOK`.
 * It retrieves saved keywords from plugin settings and calls the main importer function.
 * The number of pages to scrape per source during cron can be filtered using `scholarship_search_cron_max_pages`.
 * Logs the outcome of the operation (summary or errors) to a transient and to PHP error log if WP_DEBUG is enabled.
 *
 * @since 1.1.0
 */
function scholarship_search_execute_fetch_cron() {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'Scholarship Search Fetch Cron: Hook ' . SCHOLARSHIP_SEARCH_CRON_HOOK . ' fired.' );
    }

    // Retrieve saved keywords; default to empty if not set, which will prevent scraping.
    $keywords = get_option( 'scholarship_search_scraper_keywords', '' );
    // Allow filtering for the number of pages to scrape during cron; defaults to 1.
    $max_pages_cron = apply_filters( 'scholarship_search_cron_max_pages', 1 );

    if ( empty( $keywords ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Scholarship Search Fetch Cron: No keywords configured. Cron job did not run scraper.' );
        }
        // Optional: Store a notice or send an email if keywords are missing for a scheduled task.
        set_transient('scholarship_search_last_cron_run_summary', __('Fetching cron ran but no keywords were configured.', 'scholarship-search'), DAY_IN_SECONDS);
        return;
    }

    // Ensure the importer function exists (it should if files are included correctly via main plugin file).
    if ( ! function_exists( 'scholarship_search_run_importer' ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Scholarship Search Fetch Cron: CRITICAL - scholarship_search_run_importer() function not found. Importer.php might not be included.' );
        }
        set_transient('scholarship_search_last_cron_run_summary', __('Fetching cron failed: Importer function unavailable.', 'scholarship-search'), DAY_IN_SECONDS);
        return;
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "Scholarship Search Fetch Cron: Running importer with keywords '{$keywords}' and max pages '{$max_pages_cron}'." );
    }

    // Execute the main importer function.
    $result = scholarship_search_run_importer( $keywords, $max_pages_cron );

    // Log the result.
    if ( is_array( $result ) ) {
        $processed_count = absint( $result['processed_count'] ?? 0 );
        $added_count = absint( $result['newly_added_count'] ?? 0 );
        $log_message = sprintf(
            'Scholarship Search Fetch Cron: Import completed. Processed: %d, Added: %d.',
            $processed_count,
            $added_count
        );
        if (isset($result['error']) && !empty($result['error'])) { // If importer returns an error message.
            $log_message .= ' Error: ' . sanitize_text_field($result['error']);
        }
         if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( $log_message );
        }
        // Store a transient with the last run summary for display in admin settings.
        set_transient('scholarship_search_last_cron_run_summary', $log_message, DAY_IN_SECONDS);
    } else {
         if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Scholarship Search Fetch Cron: Importer did not return an array as expected.' );
        }
        set_transient('scholarship_search_last_cron_run_summary', 'Fetching cron run attempted but importer returned unexpected data.', DAY_IN_SECONDS);
    }
}
add_action( SCHOLARSHIP_SEARCH_CRON_HOOK, 'scholarship_search_execute_fetch_cron' );

/**
 * Handles changes to the fetching cron schedule option.
 *
 * This function is hooked to `update_option_scholarship_search_cron_schedule`.
 * It clears any existing fetch cron schedule and sets up a new one if a valid schedule
 * (not 'never') is provided by the user in admin settings.
 *
 * @since 1.1.0
 * @param string $old_value The old value of the 'scholarship_search_cron_schedule' option.
 * @param string $new_value The new value of the 'scholarship_search_cron_schedule' option.
 */
function scholarship_search_handle_cron_schedule_change( $old_value, $new_value ) {
    wp_clear_scheduled_hook( SCHOLARSHIP_SEARCH_CRON_HOOK ); // Always clear the existing schedule first.

    // If the new schedule is not 'never' and is a non-empty string.
    if ( 'never' !== $new_value && ! empty( $new_value ) ) {
        $schedules = wp_get_schedules(); // Get all registered WordPress cron schedules.
        if ( isset( $schedules[ $new_value ] ) ) { // Check if the selected schedule is valid and registered.
            wp_schedule_event( time(), $new_value, SCHOLARSHIP_SEARCH_CRON_HOOK ); // Schedule the event.
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "Scholarship Search: Fetching Cron job scheduled with recurrence '{$new_value}'. Hook: " . SCHOLARSHIP_SEARCH_CRON_HOOK );
            }
        } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "Scholarship Search: Failed to schedule Fetching Cron job. Invalid recurrence '{$new_value}'." );
        }
    } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        // Log if scheduling is explicitly cleared or set to 'never'.
        error_log( "Scholarship Search: Fetching Cron job scheduling cleared or set to 'never'. Hook: " . SCHOLARSHIP_SEARCH_CRON_HOOK );
    }
}
add_action( 'update_option_scholarship_search_cron_schedule', 'scholarship_search_handle_cron_schedule_change', 10, 2 );


// --- Expired Scholarship Cleanup Cron ---

/**
 * Adds custom cron schedules to WordPress.
 *
 * Currently adds a 'weekly' schedule if it's not already defined by another plugin/theme.
 * This function is hooked to the `cron_schedules` filter.
 *
 * @since 1.1.0
 * @param array $schedules Existing cron schedules array.
 * @return array Modified cron schedules array including any custom ones.
 */
function scholarship_search_add_custom_cron_schedules( $schedules ) {
    if ( ! isset( $schedules['weekly'] ) ) {
        $schedules['weekly'] = array(
            'interval' => WEEK_IN_SECONDS, // WordPress constant for 7 days in seconds.
            'display'  => __( 'Once Weekly', 'scholarship-search' ),
        );
    }
    // Example: Adding a 'monthly' schedule (approximate)
    // if ( ! isset( $schedules['monthly'] ) ) {
    //     $schedules['monthly'] = array(
    //         'interval' => MONTH_IN_SECONDS,
    //         'display'  => __( 'Once Monthly' )
    //     );
    // }
    return $schedules;
}
add_filter( 'cron_schedules', 'scholarship_search_add_custom_cron_schedules' );

/**
 * Executes the cleanup process for expired scholarships.
 *
 * This function is the callback for `SCHOLARSHIP_SEARCH_CLEANUP_CRON_HOOK`.
 * It queries for 'scholarship' posts with a `_scholarship_deadline` meta key
 * that is before today's date. Based on the admin setting `scholarship_search_cleanup_action`,
 * it either moves these posts to trash or changes their status to 'draft'.
 * Logs the outcome.
 *
 * @since 1.1.0
 */
function scholarship_search_execute_cleanup_cron() {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'Scholarship Search Cleanup Cron: Hook ' . SCHOLARSHIP_SEARCH_CLEANUP_CRON_HOOK . ' fired.' );
    }

    $cleanup_action = get_option( 'scholarship_search_cleanup_action', 'trash' ); // Default to 'trash'.
    $cleaned_count = 0;
    $today_date = date( 'Y-m-d' ); // Current date in YYYY-MM-DD format.

    // Arguments for WP_Query to find expired scholarships.
    $args = array(
        'post_type'      => 'scholarship',
        'posts_per_page' => -1, // Process all found expired scholarships in one go.
        'post_status'    => 'publish', // Only act on currently published scholarships.
        'meta_query'     => array(
            'relation' => 'AND', // All meta conditions must be met.
            array(
                'key'     => '_scholarship_deadline', // Custom field for deadline.
                'value'   => $today_date,            // Today's date.
                'compare' => '<',                    // Deadline is before today.
                'type'    => 'DATE',                 // Treat value as a date for comparison.
            ),
             array(
                'key'     => '_scholarship_deadline', // Ensure the deadline field actually exists for the post.
                'compare' => 'EXISTS',
            ),
            array(
                'key'     => '_scholarship_deadline', // Ensure the deadline field is not an empty string.
                'value'   => '',
                'compare' => '!=',
            ),
        ),
        'fields' => 'ids', // More efficient, only retrieve post IDs.
    );

    $expired_scholarships_query = new WP_Query( $args );
    $expired_scholarships_ids = $expired_scholarships_query->get_posts();


    if ( ! empty( $expired_scholarships_ids ) ) {
        foreach ( $expired_scholarships_ids as $post_id ) {
            if ( 'trash' === $cleanup_action ) {
                if ( wp_trash_post( $post_id ) ) { // wp_trash_post returns the post data on success, false on failure.
                    $cleaned_count++;
                } else {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( "Scholarship Search Cleanup Cron: Failed to trash post ID {$post_id}." );
                    }
                }
            } elseif ( 'draft' === $cleanup_action ) {
                $update_post_args = array(
                    'ID'          => $post_id,
                    'post_status' => 'draft',
                );
                $updated_post_id = wp_update_post( $update_post_args, true ); // Pass true to return WP_Error on failure.
                if ( ! is_wp_error( $updated_post_id ) && $updated_post_id !== 0 ) { // Check for WP_Error and 0 (on failure).
                    $cleaned_count++;
                } else {
                     if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        $error_message = is_wp_error($updated_post_id) ? $updated_post_id->get_error_message() : 'wp_update_post returned 0 or failed';
                        error_log( "Scholarship Search Cleanup Cron: Failed to set post ID {$post_id} to draft. Error: " . $error_message );
                    }
                }
            }
        }
    }

    $log_message = sprintf(
        'Scholarship Search Cleanup Cron: Process completed. Action performed: %s. Number of scholarships cleaned: %d.',
        esc_html( $cleanup_action ), // Sanitize for logging.
        $cleaned_count
    );
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( $log_message );
    }
    // Store a transient with the last cleanup run summary for display in admin settings.
    set_transient( 'scholarship_search_last_cleanup_cron_summary', $log_message, DAY_IN_SECONDS );
}
add_action( SCHOLARSHIP_SEARCH_CLEANUP_CRON_HOOK, 'scholarship_search_execute_cleanup_cron' );

/**
 * Handles changes to the cleanup cron schedule option.
 *
 * This function is hooked to `update_option_scholarship_search_cleanup_cron_schedule`.
 * It clears any existing cleanup cron schedule and sets up a new one if a valid schedule
 * (not 'never') is provided by the user in admin settings.
 *
 * @since 1.1.0
 * @param string $old_value The old value of the 'scholarship_search_cleanup_cron_schedule' option.
 * @param string $new_value The new value of the 'scholarship_search_cleanup_cron_schedule' option.
 */
function scholarship_search_handle_cleanup_schedule_change( $old_value, $new_value ) {
    wp_clear_scheduled_hook( SCHOLARSHIP_SEARCH_CLEANUP_CRON_HOOK ); // Always clear the existing schedule.

    if ( 'never' !== $new_value && ! empty( $new_value ) ) {
        $schedules = wp_get_schedules(); // Includes our custom 'weekly' schedule due to the filter.
        if ( isset( $schedules[ $new_value ] ) ) { // Check if the selected schedule is valid.
            wp_schedule_event( time(), $new_value, SCHOLARSHIP_SEARCH_CLEANUP_CRON_HOOK ); // Schedule the event.
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "Scholarship Search: Cleanup Cron job scheduled with recurrence '{$new_value}'. Hook: " . SCHOLARSHIP_SEARCH_CLEANUP_CRON_HOOK );
            }
        } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
             error_log( "Scholarship Search: Failed to schedule Cleanup Cron job. Invalid recurrence '{$new_value}'." );
        }
    } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        // Log if scheduling is cleared or explicitly set to 'never'.
        error_log( "Scholarship Search: Cleanup Cron job scheduling cleared or set to 'never'. Hook: " . SCHOLARSHIP_SEARCH_CLEANUP_CRON_HOOK );
    }
}
add_action( 'update_option_scholarship_search_cleanup_cron_schedule', 'scholarship_search_handle_cleanup_schedule_change', 10, 2 );


// --- Plugin Activation/Deactivation Hook Helpers ---

/**
 * Sets default options for cron schedules on plugin activation.
 *
 * Ensures that cron-related options have a safe default ('never' for schedules,
 * 'trash' for cleanup action) if they are not already set. This prevents cron jobs
 * from running unintentionally until configured by an admin.
 *
 * @since 1.1.0
 */
function scholarship_search_activation_default_cron_schedule() {
    // Default for fetching cron schedule.
    if ( false === get_option( 'scholarship_search_cron_schedule' ) ) {
        update_option( 'scholarship_search_cron_schedule', 'never' );
    }
    // Defaults for cleanup cron schedule and action.
    if ( false === get_option( 'scholarship_search_cleanup_cron_schedule' ) ) {
        update_option( 'scholarship_search_cleanup_cron_schedule', 'never' );
    }
    if ( false === get_option( 'scholarship_search_cleanup_action' ) ) {
        update_option( 'scholarship_search_cleanup_action', 'trash' ); // Default cleanup action.
    }
    // Note: Actual scheduling/unscheduling based on these options is handled by the
    // 'update_option_{option_name}' hooks. This function just ensures the options
    // exist with a safe default upon plugin activation.
}

/**
 * Clears all scheduled cron jobs for this plugin on deactivation.
 *
 * This function is intended to be called from the main plugin deactivation hook
 * (`scholarship_search_deactivate_plugin` in `includes/plugin-activation.php`)
 * to ensure no scheduled tasks linger after the plugin is disabled.
 *
 * @since 1.1.0
 */
function scholarship_search_clear_scheduled_events() {
    // Clear fetching cron.
    wp_clear_scheduled_hook( SCHOLARSHIP_SEARCH_CRON_HOOK );
    // Clear cleanup cron.
    wp_clear_scheduled_hook( SCHOLARSHIP_SEARCH_CLEANUP_CRON_HOOK );

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'Scholarship Search: Cleared all scheduled cron events on deactivation.' );
    }
}
?>
