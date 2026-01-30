<?php
/**
 * Uninstall Script
 *
 * Fired when the plugin is deleted (not just deactivated).
 * Cleans up all plugin data from the database.
 *
 * @package NinetiesCounterForJetpack
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options.
delete_option( 'nineties_counter_settings' );

// Delete transients.
delete_transient( 'nineties_counter_cached_stats' );

// Clean up any multisite options if applicable.
if ( is_multisite() ) {
	// Get all sites.
	$sites = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );

		delete_option( 'nineties_counter_settings' );
		delete_transient( 'nineties_counter_cached_stats' );

		restore_current_blog();
	}
}
