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

    // Start output buffering to capture all HTML for the shortcode.
    ob_start();

    // --- Always Display Search Form First ---
    ?>
    <form role="search" method="get" class="scholarship-search-form" action="<?php echo esc_url( home_url( add_query_arg( array(), null ) ) ); ?>">
        <?php wp_nonce_field( 'scholarship_search_action', 'scholarship_search_nonce' ); // Correct nonce field name used in checks ?>

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
    $form_html = ob_get_clean(); // Store form HTML

    // --- Initialize variables for results display ---
    $results_output_html = '';
    $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : ( isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );

    // --- Determine if a search action is being performed ---
    $is_search_action = false;
    $s_featured_id_get = isset( $_GET['s_featured_id'] ) ? absint( $_GET['s_featured_id'] ) : 0;

    if ( isset( $_GET['scholarship_search_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['scholarship_search_nonce'] ) ), 'scholarship_search_action' ) ) {
        $is_search_action = true;
        // Security check passed, proceed with search logic.
        // Sanitize search parameters from $_GET.
        $s_keyword     = isset( $_GET['s_keyword'] ) ? sanitize_text_field( wp_unslash( $_GET['s_keyword'] ) ) : '';
        $s_category    = isset( $_GET['s_category'] ) ? sanitize_text_field( wp_unslash( $_GET['s_category'] ) ) : '';

        // Handle Featured Scholarship Direct Selection & Redirect (if selected and nonce is valid)
        if ( $s_featured_id_get > 0 ) {
            $featured_post = get_post( $s_featured_id_get );
            if ( $featured_post && 'scholarship' === $featured_post->post_type && 'publish' === $featured_post->post_status ) {
                $permalink = get_permalink( $s_featured_id_get );
                if ( $permalink ) {
                    wp_safe_redirect( $permalink );
                    exit;
                }
            }
        }

        // --- Perform Search Query ---
        $query_args = array(
            'post_type'      => 'scholarship',
            'posts_per_page' => get_option( 'scholarship_search_results_per_page', 10 ), // Use option or default
            'paged'          => $paged,
            's'              => $s_keyword,
        );
        if ( ! empty( $s_category ) ) {
            $query_args['tax_query'] = array( array( 'taxonomy' => 'scholarship_category', 'field' => 'slug', 'terms' => $s_category ) );
        }
        if ( is_singular( 'scholarship' ) ) {
            $query_args['post__not_in'] = array( get_the_ID() );
        }
        $query = new WP_Query( $query_args );

        ob_start(); // Start buffer for search results
        if ( $query->have_posts() ) {
            echo '<div class="scholarship-search-results">';
            echo '<h3>' . esc_html__( 'Search Results', 'scholarship-search' ) . '</h3>';
            echo '<ul>';
            while ( $query->have_posts() ) {
                $query->the_post();
                echo '<li><h4><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h4>';
                echo '<div class="scholarship-excerpt">' . wp_kses_post( get_the_excerpt() ) . '</div></li>';
            }
            echo '</ul>';
            // Pagination
            $pagination_args_search = array(
                'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
                'format'    => '?paged=%#%',
                'current'   => max( 1, $paged ),
                'total'     => $query->max_num_pages,
                'prev_text' => __( '&laquo; Previous', 'scholarship-search' ),
                'next_text' => __( 'Next &raquo;', 'scholarship-search' ),
                'add_args'  => array( // Preserve search query parameters
                    's_keyword'                => $s_keyword,
                    's_category'               => $s_category,
                    's_featured_id'            => $s_featured_id_get, // Persist even if 0 for consistency in URL
                    'scholarship_search_nonce' => wp_create_nonce('scholarship_search_action'),
                ),
            );
            echo '<div class="scholarship-pagination">' . paginate_links( $pagination_args_search ) . '</div>';
            echo '</div>';
        } else {
            echo '<div class="scholarship-search-no-results"><p>' . esc_html__( 'No scholarships found matching your criteria.', 'scholarship-search' ) . '</p></div>';
        }
        wp_reset_postdata();
        $results_output_html = ob_get_clean();

    } elseif (isset( $_GET['scholarship_search_nonce'] ) && !wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['scholarship_search_nonce'] ) ), 'scholarship_search_action' )) {
        // Nonce verification failed
        ob_start();
        echo '<div class="scholarship-search-error"><p>' . esc_html__( 'Security check failed. Please try searching again.', 'scholarship-search' ) . '</p></div>';
        $results_output_html = ob_get_clean();
        $is_search_action = true; // To ensure this error message is displayed instead of default listings
    } elseif ( $s_featured_id_get > 0 && !isset($_GET['scholarship_search_nonce']) ) {
        // Handling direct featured ID link without a full search submission (e.g. link from elsewhere)
        // This case might lead to a redirect if we want to directly go to the post.
        // Or, display it as a single item if the intent is to show it on the current page.
        // For now, let's assume a redirect is preferred for direct featured ID access.
        // If a nonce is not present, it's not a form submission, so a redirect is safer.
        $featured_post = get_post( $s_featured_id_get );
        if ( $featured_post && 'scholarship' === $featured_post->post_type && 'publish' === $featured_post->post_status ) {
            $permalink = get_permalink( $s_featured_id_get );
            if ( $permalink ) {
                wp_safe_redirect( $permalink );
                exit;
            }
        }
        // If redirect fails or post not valid, it will fall through to default listings or no results.
        $is_search_action = true; // Still consider it an action to prevent default listing if param is present
         ob_start();
        echo '<div class="scholarship-search-no-results"><p>' . esc_html__( 'The selected featured scholarship could not be found.', 'scholarship-search' ) . '</p></div>';
        $results_output_html = ob_get_clean();
    }


    // --- Default Display Logic (if not a search action) ---
    if ( ! $is_search_action ) {
        ob_start(); // Start buffer for default listings
        echo '<div class="scholarship-default-listings">';
        echo '<h3>' . esc_html__( 'Recently Added Scholarships', 'scholarship-search' ) . '</h3>';

        $default_posts_per_page = get_option('scholarship_search_default_listings_count', 10);
        $default_args = array(
            'post_type'      => 'scholarship',
            'posts_per_page' => $default_posts_per_page,
            'paged'          => $paged,
            'orderby'        => 'meta_value', // Order by custom field
            'meta_key'       => '_posted_date', // Custom field for scraped post date
            'order'          => 'DESC',
            // Fallback if _posted_date is not reliable or widely used yet
            // 'orderby'        => 'date',
            // 'order'          => 'DESC',
        );

        // Check if any post has the _posted_date meta key. If not, fallback to post_date (date).
        global $wpdb;
        $meta_exists_check = $wpdb->get_var( $wpdb->prepare(
            "SELECT EXISTS (SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1)",
            '_posted_date'
        ) );

        if ( ! $meta_exists_check ) {
            $default_args['orderby'] = 'date'; // WordPress post publish date
            unset($default_args['meta_key']); // No need for meta_key if ordering by date
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log("Scholarship Search Shortcode: No '_posted_date' meta found, defaulting to 'post_date' for ordering recent scholarships.");
            }
        }


        $default_query = new WP_Query( $default_args );

        if ( $default_query->have_posts() ) {
            echo '<ul>';
            while ( $default_query->have_posts() ) {
                $default_query->the_post();
                echo '<li><h4><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h4>';
                echo '<div class="scholarship-excerpt">' . wp_kses_post( get_the_excerpt() ) . '</div></li>';
            }
            echo '</ul>';
            // Pagination for default listings
            $pagination_args_default = array(
                'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
                'format'    => '?paged=%#%',
                'current'   => max( 1, $paged ),
                'total'     => $default_query->max_num_pages,
                'prev_text' => __( '&laquo; Previous', 'scholarship-search' ),
                'next_text' => __( 'Next &raquo;', 'scholarship-search' ),
            );
            echo '<div class="scholarship-pagination">' . paginate_links( $pagination_args_default ) . '</div>';
        } else {
            echo '<p>' . esc_html__( 'No scholarships have been added yet.', 'scholarship-search' ) . '</p>';
        }
        wp_reset_postdata();
        echo '</div>'; // .scholarship-default-listings
        $results_output_html = ob_get_clean();
    }

    // Combine form and results/default listings.
    return $form_html . $results_output_html;
}
// Register the shortcode handler.
add_shortcode( 'scholarship_search_form', 'scholarship_search_form_shortcode_handler' );
?>
