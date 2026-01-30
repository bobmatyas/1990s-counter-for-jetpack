<?php
/**
 * Plugin Name: 1990s Counter for Jetpack
 * Plugin URI: https://github.com/bobmatyas/1990s-counter-for-jetpack
 * Description: Transforms the Jetpack Blog Stats block into a nostalgic 1990s-style hit counter on the frontend.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Bob Matyas
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: 1990s-counter-for-jetpack
 *
 * @package NinetiesCounterForJetpack
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'NINETIES_COUNTER_VERSION', '1.0.0' );
define( 'NINETIES_COUNTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NINETIES_COUNTER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if Jetpack is active and the Blog Stats block is available.
 *
 * This is a soft dependency check. The plugin operates safely without Jetpack,
 * but provides no functionality when Jetpack is not present.
 *
 * @return bool True if Jetpack is active, false otherwise.
 */
function nineties_counter_is_jetpack_active() {
	// Check for Jetpack's main class as the canonical indicator.
	return class_exists( 'Jetpack' );
}

/**
 * Initialize the plugin.
 *
 * Loads dependencies only when Jetpack is available.
 * This ensures clean failure when Jetpack is absent.
 *
 * @return void
 */
function nineties_counter_init() {
	// Early exit if Jetpack is not active.
	// This is intentionally silent - no admin notices or errors.
	// The plugin simply does nothing without Jetpack.
	if ( ! nineties_counter_is_jetpack_active() ) {
		return;
	}

	// Load the core components.
	require_once NINETIES_COUNTER_PLUGIN_DIR . 'includes/class-block-interceptor.php';
	require_once NINETIES_COUNTER_PLUGIN_DIR . 'includes/class-stats-extractor.php';
	require_once NINETIES_COUNTER_PLUGIN_DIR . 'includes/class-counter-renderer.php';
	require_once NINETIES_COUNTER_PLUGIN_DIR . 'includes/class-plugin.php';

	// Bootstrap the plugin.
	$plugin = new Nineties_Counter\Plugin();
	$plugin->init();
}
add_action( 'plugins_loaded', 'nineties_counter_init' );

/**
 * Activation hook.
 *
 * Performs minimal setup on activation.
 *
 * @return void
 */
function nineties_counter_activate() {
	// Set default options if they don't exist.
	if ( false === get_option( 'nineties_counter_settings' ) ) {
		$defaults = array(
			'digit_count' => 6,
			'offset'      => 0,
			'style'       => 'classic',
		);
		add_option( 'nineties_counter_settings', $defaults );
	}
}
register_activation_hook( __FILE__, 'nineties_counter_activate' );

/**
 * Deactivation hook.
 *
 * Cleans up transients but preserves settings.
 *
 * @return void
 */
function nineties_counter_deactivate() {
	// Clear any cached values.
	delete_transient( 'nineties_counter_cached_stats' );
}
register_deactivation_hook( __FILE__, 'nineties_counter_deactivate' );
