<?php
/**
 * Shortcode for displaying the scholarship search form and handling search results.
 *
 * @package ScholarshipSearch
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Handles the [scholarship_search_form] shortcode.
 *
 * This function is responsible for:
 * 1. Displaying the scholarship search form.
 * 2. Processing search requests submitted via the form (from $_GET parameters).
 * 3. Verifying a WordPress nonce for security.
 * 4. Handling redirection if a "featured scholarship" is directly selected.
 * 5. Querying scholarships based on keyword, category, and pagination.
 * 6. Displaying search results or a "no results" message.
 * 7. Displaying pagination links for search results.
 *
 * @since 1.0.0
 * @param array $atts Shortcode attributes (not currently used but standard for shortcode handlers).
 * @return string HTML output for the search form and results.
 */
function scholarship_search_form_shortcode_handler( $atts ) {
    // Suppress errors for $atts if not used.
    // $atts = shortcode_atts( array(), $atts, 'scholarship_search_form' );

    // --- Fetch Scholarship Categories for the dropdown ---
    $categories_args = array(
        'taxonomy'   => 'scholarship_category',
        'hide_empty' => false, // Show all categories, even if they have no scholarships.
        'orderby'    => 'name',
        'order'      => 'ASC',
    );
    $categories = get_terms( $categories_args );

    // --- Fetch Featured Scholarships for the optional dropdown ---
    $featured_scholarships_query_args = array(
        'post_type'      => 'scholarship',
        'posts_per_page' => -1, // Retrieve all featured scholarships.
        'meta_query'     => array(
            array(
                'key'     => '_scholarship_is_featured', // Custom field key.
                'value'   => '1',                        // Value indicating "featured".
                'compare' => '=',
            ),
        ),
        'orderby'        => 'title',
        'order'          => 'ASC',
    );
    $featured_scholarships = get_posts( $featured_scholarships_query_args );

    // Start output buffering to capture all HTML.
    ob_start();

    // --- Initialize variables for search results ---
    $search_results_html = '';
    $search_performed    = false; // Flag to indicate if a search attempt was made.

    // --- Handle Search Submission and Results ---
    // Check if the nonce field is set in the URL (i.e., form submitted).
    if ( isset( $_GET['scholarship_search_nonce'] ) ) {
        // Verify the nonce for security.
        if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['scholarship_search_nonce'] ) ), 'scholarship_search_action' ) ) {
            $search_performed = true;

            // Sanitize search parameters from $_GET.
            $s_keyword     = isset( $_GET['s_keyword'] ) ? sanitize_text_field( wp_unslash( $_GET['s_keyword'] ) ) : '';
            $s_category    = isset( $_GET['s_category'] ) ? sanitize_text_field( wp_unslash( $_GET['s_category'] ) ) : '';
            $s_featured_id = isset( $_GET['s_featured_id'] ) ? absint( $_GET['s_featured_id'] ) : 0;

            // --- Handle Featured Scholarship Direct Selection & Redirect ---
            if ( $s_featured_id > 0 ) {
                $featured_post = get_post( $s_featured_id );
                // Ensure the selected ID is a valid, published scholarship.
                if ( $featured_post && 'scholarship' === $featured_post->post_type && 'publish' === $featured_post->post_status ) {
                    $permalink = get_permalink( $s_featured_id );
                    if ( $permalink ) {
                        wp_safe_redirect( $permalink ); // Redirect to the scholarship's page.
                        exit; // Always exit after a redirect.
                    }
                }
            }

            // --- Perform Search Query (if not a featured redirect) ---
            $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : ( isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
            $query_args = array(
                'post_type'      => 'scholarship',
                'posts_per_page' => 10, // Number of results per page (consider making this a setting).
                'paged'          => $paged,
                's'              => $s_keyword, // WordPress search parameter for keywords.
            );

            // Add taxonomy query if a category is selected.
            if ( ! empty( $s_category ) ) {
                $query_args['tax_query'] = array(
                    array(
                        'taxonomy' => 'scholarship_category',
                        'field'    => 'slug', // Search by term slug.
                        'terms'    => $s_category,
                    ),
                );
            }

            // Exclude the current post from results if the shortcode is on a single scholarship page.
            // This prevents the current scholarship from appearing in its own "related" search.
            if ( is_singular( 'scholarship' ) ) {
                $query_args['post__not_in'] = array( get_the_ID() );
            }

            $query = new WP_Query( $query_args );

            // --- Generate HTML for Search Results ---
            if ( $query->have_posts() ) {
                $search_results_html .= '<div class="scholarship-search-results">';
                $search_results_html .= '<h3>' . esc_html__( 'Search Results', 'scholarship-search' ) . '</h3>';
                $search_results_html .= '<ul>';
                while ( $query->have_posts() ) {
                    $query->the_post();
                    $search_results_html .= '<li>';
                    $search_results_html .= '<h4><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h4>';
                    $search_results_html .= '<div class="scholarship-excerpt">' . wp_kses_post( get_the_excerpt() ) . '</div>';
                    // Example for displaying custom fields (ensure these fields exist and are sanitized):
                    // $deadline = get_post_meta( get_the_ID(), '_scholarship_deadline', true );
                    // if ( $deadline ) {
                    //     $search_results_html .= '<p><strong>' . esc_html__( 'Deadline:', 'scholarship-search' ) . '</strong> ' . esc_html( $deadline ) . '</p>';
                    // }
                    $search_results_html .= '</li>';
                }
                $search_results_html .= '</ul>';

                // Pagination links.
                $big = 999999999; // An unlikely integer for str_replace.
                $pagination_args = array(
                    'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
                    'format'    => '?paged=%#%', // WordPress default if permalinks are not '?page=%#%'
                    'current'   => max( 1, $paged ),
                    'total'     => $query->max_num_pages,
                    'prev_text' => __( '&laquo; Previous', 'scholarship-search' ),
                    'next_text' => __( 'Next &raquo;', 'scholarship-search' ),
                );
                 // Add search query parameters to pagination links
                $pagination_args['add_args'] = array(
                    's_keyword' => $s_keyword,
                    's_category' => $s_category,
                    's_featured_id' => (isset($_GET['s_featured_id']) ? absint($_GET['s_featured_id']) : ''), // Persist even if 0
                    'scholarship_search_nonce' => wp_create_nonce('scholarship_search_action') // Re-add nonce for next page
                );

                $search_results_html .= '<div class="scholarship-pagination">' . paginate_links( $pagination_args ) . '</div>';
                $search_results_html .= '</div>'; // .scholarship-search-results
            } else {
                // No results found.
                $search_results_html .= '<div class="scholarship-search-no-results">';
                $search_results_html .= '<p>' . esc_html__( 'No scholarships found matching your criteria.', 'scholarship-search' ) . '</p>';
                $search_results_html .= '</div>';
            }
            wp_reset_postdata(); // Restore original post data.
        } else {
            // Nonce verification failed.
            $search_results_html .= '<div class="scholarship-search-error">';
            $search_results_html .= '<p>' . esc_html__( 'Security check failed. Please try searching again.', 'scholarship-search' ) . '</p>';
            $search_results_html .= '</div>';
            $search_performed = true; // Still set to true to display the error message.
        }
    }

    // --- Display Search Form ---
    // The form action submits to the current page URL.
    // home_url(add_query_arg(null, null)) is a way to get the current URL with its query string.
    ?>
    <form role="search" method="get" class="scholarship-search-form" action="<?php echo esc_url( home_url( add_query_arg( array(), null ) ) ); ?>">
        <?php wp_nonce_field( 'scholarship_search_action', 'scholarship_search_nonce' ); ?>

        <div class="search-form-row">
            <label for="s_keyword"><?php esc_html_e( 'Keyword:', 'scholarship-search' ); ?></label>
            <input type="text" name="s_keyword" id="s_keyword" value="<?php echo isset( $_GET['s_keyword'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['s_keyword'] ) ) ) : ''; ?>" placeholder="<?php esc_attr_e( 'Enter keyword(s)', 'scholarship-search' ); ?>" />
        </div>

        <div class="search-form-row">
            <label for="s_category"><?php esc_html_e( 'Category:', 'scholarship-search' ); ?> <span class="required">*</span></label>
            <select name="s_category" id="s_category" required>
                <option value=""><?php esc_html_e( 'Select Category', 'scholarship-search' ); ?></option>
                <?php if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
                    <?php foreach ( $categories as $category ) : ?>
                        <option value="<?php echo esc_attr( $category->slug ); ?>" <?php selected( isset( $_GET['s_category'] ) ? sanitize_text_field( wp_unslash( $_GET['s_category'] ) ) : '', $category->slug ); ?>>
                            <?php echo esc_html( $category->name ); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>

        <?php if ( ! empty( $featured_scholarships ) ) : ?>
        <div class="search-form-row">
            <label for="s_featured_id"><?php esc_html_e( 'Or Select a Featured Scholarship:', 'scholarship-search' ); ?></label>
            <select name="s_featured_id" id="s_featured_id">
                <option value=""><?php esc_html_e( 'Select specific scholarship (optional)', 'scholarship-search' ); ?></option>
                <?php foreach ( $featured_scholarships as $scholarship ) : ?>
                    <option value="<?php echo esc_attr( $scholarship->ID ); ?>" <?php selected( isset( $_GET['s_featured_id'] ) ? absint( $_GET['s_featured_id'] ) : '', $scholarship->ID ); ?>>
                        <?php echo esc_html( $scholarship->post_title ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="search-form-row">
            <input type="submit" value="<?php esc_attr_e( 'Search Scholarships', 'scholarship-search' ); ?>" />
        </div>
    </form>

    <?php
    // Display search results or nonce error message if a search was performed or nonce failed.
    if ( $search_performed ) {
        // The $search_results_html is constructed using WordPress escaping functions (esc_html, esc_url, etc.),
        // so it's generally considered safe for output. wp_kses_post could be used for an extra layer
        // if user-generated HTML was more directly involved.
        // In this context, the primary concern is ensuring all dynamic data *within* the construction
        // of $search_results_html was properly sanitized/escaped, which has been done.
        echo $search_results_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    // Return the complete buffered HTML content.
    return ob_get_clean();
}
// Register the shortcode handler.
add_shortcode( 'scholarship_search_form', 'scholarship_search_form_shortcode_handler' );
?>
