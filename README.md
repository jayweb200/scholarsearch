# Scholarship Search Plugin

A WordPress plugin that allows administrators to create and manage a database of scholarships and provides a searchable interface for website visitors.

## Description

This plugin creates a "Scholarships" custom post type and a "Scholarship Categories" taxonomy. Administrators can easily add new scholarships, categorize them, and provide details such as application URLs, deadlines, and associated countries. Users can mark specific scholarships as "featured" to highlight them.

A shortcode `[scholarship_search_form]` can be placed on any page or post to display a search form. Visitors can search for scholarships by keyword, select a category, or choose directly from a list of featured scholarships.

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
    *   **Scholarship Categories:** Assign one or more relevant categories (e.g., Undergraduate, Engineering, Merit-Based). You can manage categories under **Scholarships > Scholarship Categories**. An initial list of common categories is populated upon plugin activation.
    *   **Custom Fields:** When editing a scholarship, you can add the following custom fields using the standard WordPress "Custom Fields" metabox (if it's not visible, check "Screen Options" at the top right of the edit page):
        *   `_scholarship_url`: The direct URL to the scholarship application or information page (e.g., `https://example.com/scholarship-info`).
        *   `_scholarship_deadline`: The application deadline (e.g., `2024-12-31`).
        *   `_scholarship_country`: The country associated with the scholarship (e.g., `USA`, `Canada`).
        *   `_scholarship_is_featured`: Set to `1` or `true` if you want this scholarship to appear in the "Featured Scholarships" dropdown in the search form. Otherwise, omit this field or set it to `0`.

### Displaying the Search Form

1.  Create a new Page or Post (or edit an existing one).
2.  Add the following shortcode to the content area: `[scholarship_search_form]`
3.  Save or publish the page/post. Visitors will now see the scholarship search form on this page.

### Admin Settings

Basic information and shortcode usage instructions can be found under the "Scholarship Search" menu in the admin panel.

## Future Enhancements (Examples)

*   Advanced settings for search results display.
*   Custom metaboxes for easier input of scholarship custom fields.
*   AJAX-powered search for a smoother user experience.
