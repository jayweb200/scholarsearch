<?php
/**
 * Plugin Name: Scholarship Search Plugin
 * Plugin URI: https://example.com/scholarship-search-plugin
 * Description: A plugin to search and display scholarships.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: scholarship-search
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die( 'Silence is golden.' ); // Added a more standard WordPress message.
}

/**
 * Plugin version.
 *
 * @since 1.0.0
 */
define( 'SCHOLARSHIP_SEARCH_VERSION', '1.0.0' );

/**
 * The main plugin directory path.
 *
 * @since 1.0.0
 */
define( 'SCHOLARSHIP_SEARCH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * The main plugin directory URL.
 *
 * @since 1.0.0
 */
define( 'SCHOLARSHIP_SEARCH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include CPT registration functionality.
require_once SCHOLARSHIP_SEARCH_PLUGIN_DIR . 'includes/post-types.php';

// Include taxonomy registration functionality.
require_once SCHOLARSHIP_SEARCH_PLUGIN_DIR . 'includes/taxonomies.php';

// Include admin settings page functionality.
require_once SCHOLARSHIP_SEARCH_PLUGIN_DIR . 'admin/settings-page.php';

// Include public shortcodes functionality.
require_once SCHOLARSHIP_SEARCH_PLUGIN_DIR . 'public/shortcodes.php';

// Include script and style enqueueing functionality.
require_once SCHOLARSHIP_SEARCH_PLUGIN_DIR . 'includes/enqueue-scripts.php';

// Include plugin activation/deactivation hooks.
require_once SCHOLARSHIP_SEARCH_PLUGIN_DIR . 'includes/plugin-activation.php';

// Register WordPress hooks.
register_activation_hook( __FILE__, 'scholarship_search_activate_plugin' );
register_deactivation_hook( __FILE__, 'scholarship_search_deactivate_plugin' );

// Future: If a main plugin class is introduced for OOP structure.
// require_once SCHOLARSHIP_SEARCH_PLUGIN_DIR . 'includes/class-scholarship-search.php';

// Future: Initialization of the main plugin class.
// add_action( 'plugins_loaded', array( 'Scholarship_Search', 'get_instance' ) );
?>
