<?php
/**
 * Handles processing of scraped scholarship data and importing it into WordPress posts.
 *
 * @package ScholarshipSearch
 * Handles processing of scraped scholarship data and importing it into WordPress posts.
 *
 * This file contains functions to take an array of scraped scholarship items,
 * check for duplicates, create new 'scholarship' CPT posts, and populate
 * custom fields and taxonomies.
 *
 * @package ScholarshipSearch
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Processes an array of scraped scholarship data and creates WordPress posts.
 *
 * For each scholarship item, it checks for duplicates based on the URL (`_scholarship_url` meta field).
 * If not a duplicate, it creates a new 'scholarship' post and populates
 * its title, content (from description or a default), custom fields (URL, country, source,
 * posted date, deadline), and assigns a category based on the source.
 *
 * @since 1.1.0
 * @param array $scraped_scholarships_array Array of scholarship data items from scrapers.
 *                                         Each item is an associative array with keys like
 *                                         'title', 'url', 'country', 'posted_date', 'deadline', 'source', 'description'.
 * @return array A summary array containing 'processed_count' (total items received)
 *               and 'newly_added_count' (new posts created). May include an 'error' key on failure.
 */
function scholarship_search_process_scraped_scholarships( $scraped_scholarships_array ) {
    $processed_count = 0;
    $newly_added_count = 0;

    // Validate input: ensure it's an array and not empty.
    if ( ! is_array( $scraped_scholarships_array ) || empty( $scraped_scholarships_array ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Scholarship Importer: No scraped data provided or data is not an array.' );
        }
        return array( 'processed_count' => 0, 'newly_added_count' => 0, 'error' => 'No data to process.' );
    }

    foreach ( $scraped_scholarships_array as $item ) {
        $processed_count++;

        // Essential data check: URL and Title are required to create a meaningful post.
        if ( empty( $item['url'] ) || ! filter_var( $item['url'], FILTER_VALIDATE_URL ) || empty( $item['title'] ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Scholarship Importer: Skipping item due to missing/invalid URL or missing title. URL: ' . esc_url_raw( $item['url'] ?? '' ) . ', Title: ' . esc_html( $item['title'] ?? '' ) );
            }
            continue; // Skip to the next item.
        }

        // --- Duplicate Checking: Query for existing posts with the same scholarship URL. ---
        $existing_scholarship_args = array(
            'post_type'      => 'scholarship', // Target our CPT.
            'posts_per_page' => 1,             // We only need to know if one exists.
            'meta_query'     => array(
                array(
                    'key'     => '_scholarship_url',         // Custom field storing the unique URL.
                    'value'   => esc_url_raw( $item['url'] ), // Compare against the current item's URL.
                    'compare' => '=',
                ),
            ),
            'fields' => 'ids', // More efficient, only retrieve post IDs.
        );
        $existing_scholarship_query = new WP_Query( $existing_scholarship_args );

        if ( $existing_scholarship_query->have_posts() ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Scholarship Importer: Duplicate found for URL: ' . esc_url_raw( $item['url'] ) . '. Post ID: ' . $existing_scholarship_query->posts[0] . '. Skipping.' );
            }
            // Future enhancement: Implement update logic here if desired (e.g., update deadline if changed).
            continue; // Skip this item as it's a duplicate.
        }

        // --- Post Creation: Prepare data for wp_insert_post ---
        $post_content = !empty($item['description']) ? wp_kses_post( $item['description'] ) : sprintf(esc_html__('This scholarship, titled "%s", was sourced from %s. Please visit the scholarship URL for full details.', 'scholarship-search'), sanitize_text_field($item['title']), esc_html( $item['source'] ?? 'N/A' ) );

        $post_data = array(
            'post_title'   => sanitize_text_field( $item['title'] ),
            'post_content' => $post_content,
            'post_status'  => 'publish',    // Default to published; could be 'draft' for review.
            'post_type'    => 'scholarship', // Our Custom Post Type.
        );

        // Handle 'posted_date': If available and valid, use it for the post_date.
        // This helps in ordering by when the scholarship was actually posted/found.
        $date_obj = null; // To reuse for meta field if valid
        if ( ! empty( $item['posted_date'] ) ) {
            try {
                // Attempt to parse the date (assuming it's in a format DateTime can handle, or already Y-m-d H:i:s).
                // Dates from scrapers are set to UTC.
                $date_obj = new DateTime( $item['posted_date'], new DateTimeZone('UTC') );
                $post_data['post_date']     = $date_obj->format( 'Y-m-d H:i:s' );
                $post_data['post_date_gmt'] = $date_obj->format( 'Y-m-d H:i:s' ); // Already UTC.
            } catch ( Exception $e ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Scholarship Importer: Invalid posted_date format for "' . sanitize_text_field($item['title']) . '". Date: ' . sanitize_text_field($item['posted_date']) . '. Error: ' . $e->getMessage() . '. WordPress will use current time.' );
                }
                // If parsing fails, WordPress will use the current time for post_date.
            }
        }

        // Insert the post into the database.
        $post_id = wp_insert_post( $post_data, true ); // `true` enables WP_Error return on failure.

        if ( is_wp_error( $post_id ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Scholarship Importer: Failed to insert post for "' . sanitize_text_field($item['title']) . '". Error: ' . $post_id->get_error_message() );
            }
            continue; // Skip to the next item.
        }

        // --- Update Custom Fields (Post Meta) for the new post ---
        update_post_meta( $post_id, '_scholarship_url', esc_url_raw( $item['url'] ) );

        if ( ! empty( $item['country'] ) ) {
            update_post_meta( $post_id, '_scholarship_country', sanitize_text_field( $item['country'] ) );
        }
        if ( ! empty( $item['source'] ) ) {
            update_post_meta( $post_id, '_scholarship_source', sanitize_text_field( $item['source'] ) );
        }
        // Store the validated posted_date (if available) or the original string.
        if ( $date_obj ) {
            update_post_meta( $post_id, '_posted_date', $date_obj->format( 'Y-m-d H:i:s' ) );
        } elseif (!empty( $item['posted_date'] )){
             update_post_meta( $post_id, '_posted_date', sanitize_text_field($item['posted_date']) );
        }

        // Store the deadline, attempting to format it as YYYY-MM-DD.
        if ( ! empty( $item['deadline'] ) ) {
            $deadline_meta_value = sanitize_text_field( $item['deadline'] );
            try {
                // Dates from scrapers are set to UTC or assumed to be day-specific without time.
                $deadline_obj = new DateTime( $item['deadline'] );
                $deadline_meta_value = $deadline_obj->format( 'Y-m-d' );
            } catch ( Exception $e ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Scholarship Importer: Invalid deadline format for "' . sanitize_text_field($item['title']) . '". Date: ' . sanitize_text_field($item['deadline']) . '. Error: ' . $e->getMessage() . '. Storing as text.' );
                }
                // If parsing fails, store the original (sanitized) string.
            }
            update_post_meta( $post_id, '_scholarship_deadline', $deadline_meta_value );
        }

        // --- Category Assignment (Basic): Assign a category based on the source. ---
        $source_category_name_base = 'Scraped Scholarship'; // Generic fallback
        if ( ! empty( $item['source'] ) ) {
            if ( $item['source'] === 'scholarshipdb.net' ) {
                $source_category_name_base = 'ScholarshipDB Import';
            } elseif ( $item['source'] === 'findaphd.com' ) {
                $source_category_name_base = 'FindAPhD Import';
            }
            // Could add more source-specific categories here.
        }

        // Ensure the category exists or create it.
        $term_slug = sanitize_title( $source_category_name_base );
        $term = term_exists( $term_slug, 'scholarship_category' ); // Check by slug for consistency

        if ( ! $term ) {
            $term_description = sprintf( esc_html__('Scholarships imported from the source: %s', 'scholarship-search'), sanitize_text_field($item['source'] ?? 'N/A') );
            $term = wp_insert_term( $source_category_name_base, 'scholarship_category', array('slug' => $term_slug, 'description'=> $term_description ) );
        }

        if ( $term && ! is_wp_error( $term ) ) {
            // Assign the term to the post. Replaces existing terms in this taxonomy.
            wp_set_object_terms( $post_id, (int) $term['term_id'], 'scholarship_category', false );
        } elseif (is_wp_error($term) && defined( 'WP_DEBUG' ) && WP_DEBUG) {
             error_log( 'Scholarship Importer: Error creating/assigning term "' . sanitize_text_field($source_category_name_base) . '": ' . $term->get_error_message() );
        }

        $newly_added_count++;
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Scholarship Importer: Successfully added new scholarship: "' . sanitize_text_field($item['title']) . '" (Post ID: ' . $post_id . ')' );
        }
    }

    return array(
        'processed_count'   => $processed_count,
        'newly_added_count' => $newly_added_count,
    );
}

/**
 * Main importer function that fetches and processes scholarships.
 *
 * Calls the scraper engine to get data, then processes this data
 * to create or update scholarship posts.
 *
 * @since 1.1.0
 * @param string $keywords Keywords to search for.
 * @param int    $max_pages_per_source Maximum number of pages to scrape from each source.
 * @return array|false Summary from processing, or false if fetching failed.
 */
function scholarship_search_run_importer( $keywords, $max_pages_per_source = 1 ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "Scholarship Importer: Starting import process for keywords '{$keywords}', max pages: {$max_pages_per_source}." );
    }

    // Ensure scraper functions are available
    if ( ! function_exists( 'scholarship_search_fetch_all_scholarships' ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Scholarship Importer: Scraper engine function scholarship_search_fetch_all_scholarships() not found. Is scraper-engine.php included?' );
        }
        return false; // Or handle error appropriately
    }

    $scraped_data = scholarship_search_fetch_all_scholarships( $keywords, $max_pages_per_source );

    if ( empty( $scraped_data ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Scholarship Importer: No data returned from scrapers.' );
        }
        return array( 'processed_count' => 0, 'newly_added_count' => 0, 'message' => 'No data fetched from sources.' );
    }

    $result_summary = scholarship_search_process_scraped_scholarships( $scraped_data );

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "Scholarship Importer: Import process finished. Processed: {$result_summary['processed_count']}, Added: {$result_summary['newly_added_count']}." );
    }

    return $result_summary;
}
?>
