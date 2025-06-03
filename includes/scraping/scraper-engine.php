<?php
/**
 * Core engine for scraping scholarship data from various sources.
 *
 * This file contains functions to fetch HTML content, parse it using DOMDocument and DOMXPath,
 * and extract relevant scholarship information from predefined websites.
 * It also includes a main aggregator function to call individual scrapers.
 *
 * @package ScholarshipSearch
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Fetches HTML content from a given URL using WordPress HTTP API.
 *
 * This helper function encapsulates the `wp_remote_get` call, providing
 * basic error handling and retrieval of the response body. It sets a user-agent
 * to identify the plugin.
 *
 * @since 1.1.0
 * @param string $url The URL from which to fetch HTML content.
 * @return string|false The HTML body as a string on success, or false on failure.
 */
function scholarship_search_fetch_html_content( $url ) {
    if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Scholarship Scraper Error: Invalid or empty URL provided for fetching HTML: ' . esc_url_raw( $url ) );
        }
        return false;
    }

    // Arguments for wp_remote_get. Includes timeout and a specific user-agent.
    // The user-agent should ideally point to a page explaining the bot's purpose.
    $plugin_url_for_user_agent = home_url('/scholarship-search-info'); // Example, can be refined
    $request_args = array(
        'timeout'    => 30, // seconds
        'user-agent' => 'Mozilla/5.0 (compatible; ScholarshipSearchPlugin/' . SCHOLARSHIP_SEARCH_VERSION . '; +'. $plugin_url_for_user_agent .')',
    );
    $response = wp_remote_get( $url, $request_args );

    if ( is_wp_error( $response ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Scholarship Scraper Error: Failed to fetch URL ' . esc_url_raw( $url ) . ' - ' . $response->get_error_message() );
        }
        return false;
    }

    $body        = wp_remote_retrieve_body( $response );
    $status_code = wp_remote_retrieve_response_code( $response );

    if ( $status_code !== 200 ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Scholarship Scraper Error: URL ' . esc_url_raw( $url ) . ' returned HTTP status ' . $status_code );
        }
        return false;
    }

    if ( empty( $body ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Scholarship Scraper Error: Fetched empty body from URL ' . esc_url_raw( $url ) );
        }
        return false;
    }

    return $body;
}

/**
 * Scrapes scholarship data from scholarshipdb.net.
 *
 * Iterates through pages of scholarship listings for given keywords,
 * parses the HTML, and extracts scholarship details.
 *
 * @since 1.1.0
 * @param string $keywords The search keywords.
 * @param int    $max_pages The maximum number of result pages to scrape. Defaults to 1.
 * @return array An array of scraped scholarship data. Each item is an associative array
 *               containing keys like 'title', 'url', 'country', 'posted_date', 'source'.
 *               Returns an empty array on failure or if no items are found.
 */
function scholarship_search_scrape_scholarshipdb( $keywords, $max_pages = 1 ) {
    $scraped_scholarships = array();
    $base_url = 'https://scholarshipdb.net'; // Base URL for constructing absolute links.

    for ( $page = 1; $page <= $max_pages; $page++ ) {
        $query_url = add_query_arg(
            array(
                'q'    => rawurlencode( $keywords ), // Ensure keywords are URL-encoded.
                'page' => $page,
            ),
            $base_url . '/scholarships/Program-PhD' // Specific path for PhD programs.
        );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "ScholarshipDB Scraper: Fetching page {$page} for keywords '{$keywords}': " . esc_url_raw( $query_url ) );
        }

        $html_content = scholarship_search_fetch_html_content( $query_url );

        if ( ! $html_content ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "ScholarshipDB Scraper: Failed to fetch HTML for page {$page}, keywords '{$keywords}' from " . esc_url_raw( $query_url ) );
            }
            continue; // Skip to next page if fetching fails.
        }

        // Initialize DOMDocument and DOMXPath for HTML parsing.
        $dom = new DOMDocument();
        libxml_use_internal_errors( true ); // Suppress warnings/errors from potentially malformed HTML.
        // Ensure UTF-8 encoding, as DOMDocument can struggle with improperly declared encodings.
        $dom->loadHTML( mb_convert_encoding( $html_content, 'HTML-ENTITIES', 'UTF-8' ) );
        libxml_clear_errors(); // Clear any errors collected by libxml.
        libxml_use_internal_errors( false ); // Restore error handling.
        $xpath = new DOMXPath( $dom );

        // XPath query to identify individual scholarship listing blocks.
        // This selector is based on observed structure (divs with class 'panel' and 'panel-default').
        // It may need updates if the target website's structure changes.
        $item_query = "//div[contains(@class, 'panel') and contains(@class, 'panel-default')]";
        $scholarship_nodes = $xpath->query( $item_query );

        // Log if no items are found on the first page, as it might indicate a broken selector or site change.
        if ( $scholarship_nodes->length === 0 && $page === 1 && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
             error_log( "ScholarshipDB Scraper: No scholarship items found on first page with query '{$item_query}'. Check XPath selector or site structure. URL: " . esc_url_raw( $query_url ) );
        }

        foreach ( $scholarship_nodes as $node ) {
            // Extract data using XPath queries relative to the current item node ($node).
            $title_node   = $xpath->query( ".//h4/a", $node )->item(0); // Title is in an <a> tag within an <h4>.
            $country_node = $xpath->query( ".//a[contains(@class, 'text-success')]", $node )->item(0); // Country link.
            $date_node    = $xpath->query( ".//span[contains(@class, 'text-muted') and contains(., 'Posted')]", $node )->item(0); // Date text.
            $link_node    = $xpath->query( ".//h4/a/@href", $node )->item(0); // Link is the href attribute of the title's <a> tag.

            $title     = $title_node ? trim( $title_node->nodeValue ) : null;
            $country   = $country_node ? trim( $country_node->nodeValue ) : null;
            $date_text = $date_node ? trim( $date_node->nodeValue ) : null;
            $link      = $link_node ? trim( $link_node->nodeValue ) : null;

            // Skip this item if essential data like title or link is missing.
            if ( empty( $title ) || empty( $link ) ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( "ScholarshipDB Scraper: Skipping item, missing title or link. Title: " . ($title ?? 'N/A') . ", Link: " . ($link ?? 'N/A') );
                }
                continue;
            }

            // Ensure the extracted link is absolute. Prepend base URL if it's relative.
            if ( strpos( $link, 'http' ) !== 0 ) {
                $link = rtrim( $base_url, '/' ) . '/' . ltrim( $link, '/' );
            }

            $posted_date_obj = null;
            if ( $date_text ) {
                // Attempt to parse "X days ago" format.
                if ( str_contains( $date_text, 'days ago' ) ) { // PHP 8+ for str_contains
                    preg_match( '/(\d+)\s+days\s+ago/i', $date_text, $matches );
                    if ( isset( $matches[1] ) ) {
                        $days_ago = (int) $matches[1];
                        try {
                            $posted_date_obj = new DateTime( "now", new DateTimeZone('UTC') ); // Assume dates are relative to UTC.
                            $posted_date_obj->modify( "-{$days_ago} days" );
                        } catch (Exception $e) { /* Silently fail or log specific error if needed */ }
                    }
                // Attempt to parse "Posted on Month D, YYYY" format.
                } elseif ( preg_match( '/Posted on\s+(.*)/i', $date_text, $matches ) ) {
                    $date_str = trim( $matches[1] );
                    try {
                        $posted_date_obj = new DateTime( $date_str, new DateTimeZone('UTC') ); // Assume parsed date is UTC.
                    } catch ( Exception $e ) {
                        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                            error_log( "ScholarshipDB Scraper: Could not parse date string '{$date_str}'. Error: " . $e->getMessage() );
                        }
                    }
                }
            }

            // Format date for storage, or use sanitized original text if parsing failed.
            $final_posted_date = $posted_date_obj ? $posted_date_obj->format( 'Y-m-d H:i:s' ) : ( $date_text ? sanitize_text_field($date_text) : null );

            $scraped_scholarships[] = array(
                'title'       => sanitize_text_field( $title ),
                'url'         => esc_url_raw( $link ),
                'country'     => $country ? sanitize_text_field( $country ) : null,
                'posted_date' => $final_posted_date,
                'deadline'    => null, // Deadline not readily available in list view for this source.
                'description' => '',   // Full description usually on the detail page, not scraped here.
                'source'      => 'scholarshipdb.net',
            );
        }
        // Be a good internet citizen: pause between requests if scraping multiple pages.
        if ( $max_pages > 1 && $page < $max_pages ) {
            sleep( rand( 1, 2 ) ); // Shorter sleep, can be adjusted.
        }
    }
    return $scraped_scholarships;
}

/**
 * Scrapes scholarship data from findaphd.com.
 *
 * Iterates through pages of PhD listings based on keywords and student type,
 * parses HTML, and extracts details.
 *
 * @since 1.1.0
 * @param string $keywords The search keywords.
 * @param int    $max_pages The maximum number of result pages to scrape. Defaults to 1.
 * @param string $student_type Student type ('eu' or 'non-eu') to tailor the search URL. Defaults to 'non-eu'.
 * @return array An array of scraped scholarship data. Each item is an associative array
 *               containing keys like 'title', 'url', 'country', 'deadline', 'source'.
 *               Returns an empty array on failure or if no items are found.
 */
function scholarship_search_scrape_findaphd( $keywords, $max_pages = 1, $student_type = 'non-eu' ) {
    $scraped_scholarships = array();
    $base_url = 'https://www.findaphd.com';

    for ( $page = 1; $page <= $max_pages; $page++ ) {
        $query_params = array(
            'Keywords' => rawurlencode( $keywords ),
            'PG'       => $page, // Page number parameter for findaphd.com.
        );

        // Adjust URL path and specific query parameter based on student type.
        if ( 'eu' === $student_type ) {
            $path = '/phds/eu-students/';
            $query_params['01g0'] = ''; // Parameter specific to EU student search.
        } else { // Default to non-eu
            $path = '/phds/non-eu-students/';
            $query_params['01w0'] = ''; // Parameter specific to Non-EU student search.
        }
        $query_url = add_query_arg( $query_params, $base_url . $path );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "FindAPhD Scraper: Fetching page {$page} for keywords '{$keywords}', type '{$student_type}': " . esc_url_raw( $query_url ) );
        }

        $html_content = scholarship_search_fetch_html_content( $query_url );

        if ( ! $html_content ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "FindAPhD Scraper: Failed to fetch HTML for page {$page}, keywords '{$keywords}', type '{$student_type}' from " . esc_url_raw( $query_url ) );
            }
            continue;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        $dom->loadHTML( mb_convert_encoding( $html_content, 'HTML-ENTITIES', 'UTF-8' ) );
        libxml_clear_errors();
        libxml_use_internal_errors( false );
        $xpath = new DOMXPath( $dom );

        // XPath for individual scholarship items. Based on observed structure (divs with class 'phd-result' and 'card').
        $item_query = "//div[contains(@class, 'phd-result') and contains(@class, 'card')]";
        $scholarship_nodes = $xpath->query( $item_query );

        if ( $scholarship_nodes->length === 0 && $page === 1 && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
             error_log( "FindAPhD Scraper: No scholarship items found on first page with query '{$item_query}'. Check XPath selector or site structure. URL: " . esc_url_raw( $query_url ) );
        }

        foreach ( $scholarship_nodes as $node ) {
            // Title and link are usually within an <h4> containing an <a>.
            $title_link_node = $xpath->query( ".//h4[contains(@class, 'text-dark') and contains(@class, 'mx-0') and contains(@class, 'mb-3')]/a", $node )->item(0);
            $title = $title_link_node ? trim( $title_link_node->nodeValue ) : null;
            $link  = $title_link_node ? $title_link_node->getAttribute('href') : null;

            // Country is often in the 'title' attribute of an <img> with class 'country-flag'.
            $country_node = $xpath->query( ".//img[contains(@class, 'country-flag')]/@title", $node )->item(0);
            $country = $country_node ? trim( $country_node->nodeValue ) : null;

            // Deadline date extraction. Looks for text like "Closing date: ...".
            $date_node = $xpath->query( ".//div[contains(@class, 'py-2') and contains(@class, 'small') and contains(., 'Closing date:')]", $node )->item(0);
            $date_text = $date_node ? trim( $date_node->nodeValue ) : null;

            if ( empty( $title ) || empty( $link ) ) {
                 if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( "FindAPhD Scraper: Skipping item, missing title or link. Title: " . ($title ?? 'N/A') . ", Link: " . ($link ?? 'N/A') );
                }
                continue;
            }

            // Make link absolute.
            if ( strpos( $link, 'http' ) !== 0 ) {
                $link = rtrim( $base_url, '/' ) . '/' . ltrim( $link, '/' );
            }

            $deadline_date_obj = null;
            if ( $date_text ) {
                // Example: "Closing date: 24 Jul 2024"
                if ( preg_match( '/Closing date:\s*(.*)/i', $date_text, $matches ) ) {
                    $date_str = trim( $matches[1] );
                    try {
                        // Handles formats like "24 Jul 2024" or "1st October 2024". Assumes UTC.
                        $deadline_date_obj = new DateTime( $date_str, new DateTimeZone('UTC') );
                    } catch ( Exception $e ) {
                         if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                            error_log( "FindAPhD Scraper: Could not parse date string '{$date_str}'. Error: " . $e->getMessage() );
                        }
                    }
                }
            }
            // Format deadline for storage, or use sanitized original text if parsing failed.
            $final_deadline_date = $deadline_date_obj ? $deadline_date_obj->format( 'Y-m-d' ) : ($date_text ? sanitize_text_field($date_text) : null);

            $scraped_scholarships[] = array(
                'title'       => sanitize_text_field( $title ),
                'url'         => esc_url_raw( $link ),
                'country'     => $country ? sanitize_text_field( $country ) : null,
                'posted_date' => null, // Posted date is not readily available in list view for this source.
                'deadline'    => $final_deadline_date,
                'description' => '',   // Full description usually on the detail page.
                'source'      => 'findaphd.com',
            );
        }
        if ( $max_pages > 1 && $page < $max_pages ) {
            sleep( rand( 1, 2 ) ); // Pause between requests.
        }
    }
    return $scraped_scholarships;
}

/**
 * Fetches scholarships from all configured scraping sources.
 *
 * Calls individual scraper functions for each source (scholarshipdb.net, findaphd.com for EU & Non-EU)
 * and merges their results into a single array. Includes basic logging for monitoring and
 * a simple de-duplication step based on scholarship URLs.
 *
 * @since 1.1.0
 * @param string $keywords The search keywords to be used by the scrapers.
 * @param int    $max_pages_per_source Maximum number of pages to scrape from each source. Defaults to 1.
 * @return array An array of all scraped (and de-duplicated) scholarship data.
 *               Returns an empty array if no scholarships are found or an error occurs.
 */
function scholarship_search_fetch_all_scholarships( $keywords, $max_pages_per_source = 1 ) {
    $all_scholarships = array();

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "Scholarship Aggregator: Starting all scrapers for keywords: '{$keywords}', max pages per source: {$max_pages_per_source}" );
    }

    // Scrape from scholarshipdb.net
    $scholarshipdb_results = scholarship_search_scrape_scholarshipdb( $keywords, $max_pages_per_source );
    if ( is_array( $scholarshipdb_results ) && ! empty( $scholarshipdb_results ) ) {
        $all_scholarships = array_merge( $all_scholarships, $scholarshipdb_results );
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "Scholarship Aggregator: ScholarshipDB Scraper returned " . count( $scholarshipdb_results ) . " results." );
        }
    } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "Scholarship Aggregator: ScholarshipDB Scraper returned no results or an error for keywords '{$keywords}'." );
    }

    // Scrape from findaphd.com (Non-EU)
    $findaphd_non_eu_results = scholarship_search_scrape_findaphd( $keywords, $max_pages_per_source, 'non-eu' );
    if ( is_array( $findaphd_non_eu_results ) && ! empty( $findaphd_non_eu_results ) ) {
        $all_scholarships = array_merge( $all_scholarships, $findaphd_non_eu_results );
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "Scholarship Aggregator: FindAPhD (Non-EU) Scraper returned " . count( $findaphd_non_eu_results ) . " results." );
        }
    } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "Scholarship Aggregator: FindAPhD (Non-EU) Scraper returned no results or an error for keywords '{$keywords}'." );
    }

    // Scrape from findaphd.com (EU)
    $findaphd_eu_results = scholarship_search_scrape_findaphd( $keywords, $max_pages_per_source, 'eu' );
    if ( is_array( $findaphd_eu_results ) && ! empty( $findaphd_eu_results ) ) {
        $all_scholarships = array_merge( $all_scholarships, $findaphd_eu_results );
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "Scholarship Aggregator: FindAPhD (EU) Scraper returned " . count( $findaphd_eu_results ) . " results." );
        }
    } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "Scholarship Aggregator: FindAPhD (EU) Scraper returned no results or an error for keywords '{$keywords}'." );
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "Scholarship Aggregator: All scrapers finished. Total scholarships fetched (before de-duplication): " . count( $all_scholarships ) );
    }

    // Basic de-duplication based on URL to avoid identical entries.
    $unique_scholarships = array();
    $seen_urls = array(); // Keep track of URLs already processed.
    foreach ( $all_scholarships as $scholarship ) {
        // Ensure URL exists, is a string, and is not empty before using it as an array key.
        if ( isset( $scholarship['url'] ) && is_string( $scholarship['url'] ) && ! empty( $scholarship['url'] ) ) {
            if ( ! isset( $seen_urls[ $scholarship['url'] ] ) ) {
                $unique_scholarships[] = $scholarship;
                $seen_urls[ $scholarship['url'] ] = true; // Mark URL as seen.
            } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
               error_log( "Scholarship Aggregator: Duplicate URL found and skipped during de-duplication: " . esc_url_raw( $scholarship['url'] ) );
            }
        } else {
            // If a scholarship item has no URL or an invalid URL, include it but log if debugging.
            $unique_scholarships[] = $scholarship;
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
               error_log( "Scholarship Aggregator: Scholarship item processed with missing or invalid URL during de-duplication. Title: " . ($scholarship['title'] ?? 'N/A') );
            }
        }
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && (count($all_scholarships) !== count($unique_scholarships)) ) {
        error_log( "Scholarship Aggregator: De-duplication reduced items from " . count($all_scholarships) . " to " . count($unique_scholarships) );
    }

    return $unique_scholarships;
}
?>
