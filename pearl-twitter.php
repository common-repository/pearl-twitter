<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://themeforest.net/user/pearlthemes
 * @since             1.0.0
 * @package           Pearl_Twitter
 *
 * @wordpress-plugin
 * Plugin Name:       Easy Twitter Widget
 * Plugin URI:        https://profiles.wordpress.org/pearlthemes
 * Description:       A light weight plugin to display recent Tweets with awesome customizability options.
 * Version:           1.2.0
 * Author:            PearlThemes
 * Author URI:        https://themeforest.net/user/pearlthemes
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       pearl-twitter
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-pearl-twitter-activator.php
 */
function activate_pearl_twitter() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-pearl-twitter-activator.php';
	Pearl_Twitter_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-pearl-twitter-deactivator.php
 */
function deactivate_pearl_twitter() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-pearl-twitter-deactivator.php';
	Pearl_Twitter_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_pearl_twitter' );
register_deactivation_hook( __FILE__, 'deactivate_pearl_twitter' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-pearl-twitter.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_pearl_twitter() {

	$plugin = new Pearl_Twitter();
	$plugin->run();

}
run_pearl_twitter();
