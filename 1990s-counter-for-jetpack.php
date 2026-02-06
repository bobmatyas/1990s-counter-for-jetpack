<?php
/**
 * Plugin Name: 1990s Counter for Jetpack
 * Plugin URI: https://github.com/bobmatyas/1990s-counter-for-jetpack
 * Description: Transforms the Jetpack Blog Stats block into a nostalgic 1990s-style hit counter on the frontend.
 * Version: 1.0.3
 * Requires at least: 6.5
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

	// Remove Blog Stats block styles in editor (JS) and on init (PHP fallback).
	add_action( 'enqueue_block_editor_assets', 'nineties_counter_enqueue_editor_script' );
	// Register counter CSS for the block editor so it loads inside the editor iframe (WP 6.3+).
	// add_editor_style() is processed by get_block_editor_theme_styles() and injected into the iframe.
	add_action( 'after_setup_theme', 'nineties_counter_add_editor_styles', 20 );
	// Fallback: inject raw CSS into editor settings for contexts where theme styles are used.
	add_filter( 'block_editor_settings_all', 'nineties_counter_inject_editor_styles', 10, 2 );
	add_action( 'init', 'nineties_counter_remove_blog_stats_block_styles', 20 );
}
add_action( 'plugins_loaded', 'nineties_counter_init' );

/**
 * Register counter CSS for the block editor (iframe).
 *
 * add_editor_style() registers stylesheets that get_block_editor_theme_styles()
 * processes and injects into the block editor iframe. This is the correct way
 * to style block content in the editor when using the iframed editor (WP 6.3+).
 * Plugins must pass a full URL; see add_editor_style() docs.
 *
 * @return void
 */
function nineties_counter_add_editor_styles() {
	if ( ! nineties_counter_is_jetpack_active() ) {
		return;
	}

	add_editor_style( NINETIES_COUNTER_PLUGIN_URL . 'assets/css/counter.css' );
}

/**
 * Inject counter CSS into the block editor iframe via editor settings.
 *
 * This ensures the 1990s counter styles are present in the iframed editor
 * even when enqueue_block_assets does not include them.
 *
 * @param array  $editor_settings Current editor settings.
 * @param object $editor_context  The block editor context.
 * @return array Modified editor settings.
 */
function nineties_counter_inject_editor_styles( $editor_settings, $editor_context ) {
	if ( ! nineties_counter_is_jetpack_active() ) {
		return $editor_settings;
	}

	$css_file = NINETIES_COUNTER_PLUGIN_DIR . 'assets/css/counter.css';
	if ( ! file_exists( $css_file ) || ! is_readable( $css_file ) ) {
		return $editor_settings;
	}

	$css = file_get_contents( $css_file );
	if ( false === $css || '' === trim( $css ) ) {
		return $editor_settings;
	}

	if ( ! isset( $editor_settings['styles'] ) || ! is_array( $editor_settings['styles'] ) ) {
		$editor_settings['styles'] = array();
	}

	$editor_settings['styles'][] = array( 'css' => $css );

	return $editor_settings;
}

/**
 * Enqueue editor script to unregister Jetpack Blog Stats block styles.
 *
 * Jetpack registers block styles in JavaScript; this script unregisters them
 * so the styles panel is empty for the Blog Stats block.
 *
 * @return void
 */
function nineties_counter_enqueue_editor_script() {
	if ( ! nineties_counter_is_jetpack_active() ) {
		return;
	}

	$script_path = NINETIES_COUNTER_PLUGIN_DIR . 'assets/js/editor.js';
	$style_path  = NINETIES_COUNTER_PLUGIN_DIR . 'assets/css/editor.css';
	$version     = file_exists( $script_path ) ? (string) filemtime( $script_path ) : NINETIES_COUNTER_VERSION;
	$style_ver   = file_exists( $style_path ) ? (string) filemtime( $style_path ) : NINETIES_COUNTER_VERSION;

	wp_enqueue_style(
		'nineties-counter-editor',
		NINETIES_COUNTER_PLUGIN_URL . 'assets/css/editor.css',
		array(),
		$style_ver
	);

	wp_enqueue_script(
		'nineties-counter-editor',
		NINETIES_COUNTER_PLUGIN_URL . 'assets/js/editor.js',
		array(
			'wp-blocks',
			'wp-dom-ready',
			'wp-edit-post',
			'wp-element',
			'wp-components',
			'wp-block-editor',
			'wp-hooks',
		),
		$version,
		true
	);

	wp_localize_script(
		'nineties-counter-editor',
		'ninetiesCounterEditor',
		array(
			'settingsUrl' => admin_url( 'options-general.php?page=nineties-counter' ),
		)
	);
}

/**
 * Remove block styles panel for Jetpack Blog Stats block in the editor (PHP).
 *
 * Catches any styles registered server-side. Jetpack typically registers
 * in JS, so the editor script is the main fix.
 *
 * @return void
 */
function nineties_counter_remove_blog_stats_block_styles() {
	if ( ! nineties_counter_is_jetpack_active() ) {
		return;
	}

	$registry = \WP_Block_Styles_Registry::get_instance();
	$styles   = $registry->get_registered_styles_for_block( 'jetpack/blog-stats' );

	if ( ! empty( $styles ) ) {
		foreach ( array_keys( $styles ) as $style_name ) {
			unregister_block_style( 'jetpack/blog-stats', $style_name );
		}
	}
}

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
