# Scholarship Search Plugin

A WordPress plugin that allows administrators to create and manage a database of scholarships, with options for automated fetching from external sources and cleanup of expired listings. It provides a searchable interface for website visitors.

## Description

This plugin creates a "Scholarships" custom post type and a "Scholarship Categories" taxonomy. Administrators can easily add new scholarships, categorize them, and provide details such as application URLs, deadlines, and associated countries.

Key features include:
*   **Manual & Automated Scholarship Fetching:** Scrape scholarship data from scholarshipdb.net and findaphd.com. Configure keywords and a schedule (hourly, daily, etc.) for automated fetching, or trigger fetches manually.
*   **Expired Scholarship Cleanup:** Automatically set scholarships to 'trash' or 'draft' status after their deadline has passed, based on a configurable schedule (daily, weekly).
*   **Customizable Frontend Display:** Use the `[scholarship_search_form]` shortcode to display a search form and listings. Control how many listings appear by default on initial page load.
*   **Detailed Admin Settings:** Manage scraper keywords, fetching and cleanup schedules, cleanup actions, and default display counts from a dedicated settings page.

## Installation

1.  Download the plugin (zip file).
2.  In your WordPress admin panel, go to **Plugins > Add New**.
3.  Click **Upload Plugin** and choose the downloaded zip file.
4.  Activate the plugin through the 'Plugins' menu in WordPress.

## Usage

### Managing Scholarships

1.  After activation, a new "Scholarships" menu will appear in your WordPress admin sidebar.
2.  To add a new scholarship, go to **Scholarships > Add New**.
    *   **Title:** The name of the scholarship.
    *   **Editor:** A detailed description of the scholarship.
    *   **Scholarship Categories:** Assign one or more relevant categories. An initial list of common categories is populated upon plugin activation. Scraped scholarships are automatically assigned categories like "ScholarshipDB Import" or "FindAPhD Import". You can manage all categories under **Scholarships > Scholarship Categories**.
    *   **Custom Fields:** When editing a scholarship, you can add/edit the following custom fields using the standard WordPress "Custom Fields" metabox (if it's not visible, check "Screen Options" at the top right of the edit page):
        *   `_scholarship_url`: The direct URL to the scholarship application or information page.
        *   `_scholarship_deadline`: The application deadline (format: YYYY-MM-DD). This is crucial for the automated cleanup feature.
        *   `_scholarship_country`: The country associated with the scholarship.
        *   `_scholarship_is_featured`: Set to `1` or `true` if you want this scholarship to appear in the "Featured Scholarships" dropdown in the search form.
        *   `_posted_date`: The date the scholarship was originally posted on the source site or scraped (format: YYYY-MM-DD HH:MM:SS). Used for sorting "Recently Added Scholarships". Automatically set by scraper.
        *   `_scholarship_source`: The source website where the scholarship was found (e.g., `scholarshipdb.net`, `findaphd.com`). Automatically set by scraper.

### Displaying the Search Form & Listings

1.  Create a new Page or Post (or edit an existing one).
2.  Add the following shortcode to the content area: `[scholarship_search_form]`
3.  Save or publish the page/post. Visitors will now see the scholarship search form.
    *   By default, before a search is performed, this page will show recently added scholarships (ordered by `_posted_date`). The number of listings shown is configurable in the admin settings.

### Admin Settings

Navigate to the "Scholarship Search" menu in your WordPress admin panel to configure the plugin. The settings page is organized into several sections:

#### General Display Settings
*   **Default Listings Per Page:** Control how many scholarships are displayed on the shortcode page by default, before any search is performed.

#### Automated Scholarship Fetching
*   **Default Scraper Keywords:** Enter comma-separated keywords (e.g., "computer science, AI, engineering") used by the automated fetching cron job.
*   **Fetching Schedule:** Choose how often the plugin should automatically fetch new scholarships (Never, Hourly, Twice Daily, Daily). The next scheduled fetch time is displayed.
*   **Last Fetch Summary:** Shows a summary of the last automated fetch (processed items, new items added).
*   **Manual Scholarship Fetch (Button & Form):**
    *   Manually trigger the fetching process.
    *   Optionally provide different keywords for this specific manual run.
    *   Set the maximum number of pages to scrape per source for this run (1-5).
    *   Results of the manual fetch will be displayed as an admin notice.

#### Expired Scholarship Cleanup
*   **Cleanup Schedule:** Choose how often the plugin should automatically clean up expired scholarships (Never, Daily, Weekly). The next scheduled cleanup time is displayed.
*   **Action on Expired Scholarships:** Select what happens to scholarships whose deadlines have passed:
    *   Move to Trash
    *   Change to Draft status
*   **Last Cleanup Summary:** Shows a summary of the last automated cleanup.
*   **Manual Expired Scholarship Cleanup (Button):**
    *   Manually trigger the cleanup process based on the saved "Action on Expired Scholarships" setting.
    *   Results of the manual cleanup will be displayed as an admin notice.

## Future Enhancements (Examples)

*   Advanced settings for search results display (e.g., fields to show).
*   Custom metaboxes for easier input of scholarship custom fields in the admin.
*   AJAX-powered search form for a smoother user experience.
*   More scraper sources and advanced scraper configuration.
*   Email notifications for cron job summaries or errors.
