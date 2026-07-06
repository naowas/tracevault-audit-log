<?php
/**
 * Uninstall cleanup.
 *
 * @package OpenActivityLogger
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Drops plugin tables for the current blog when the safe toggle is enabled.
 *
 * @return void
 */
function oal_uninstall_blog() {
	global $wpdb;

	$settings_table = $wpdb->prefix . 'oal_settings';
	$delete = $wpdb->get_var( $wpdb->prepare( "SELECT setting_value FROM {$settings_table} WHERE setting_key = %s", 'delete_data_on_uninstall' ) );

	if ( '1' !== (string) $delete ) {
		return;
	}

	foreach ( array( 'oal_meta', 'oal_logs', 'oal_settings' ) as $suffix ) {
		$table = $wpdb->prefix . $suffix;
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}

wp_clear_scheduled_hook( 'oal_daily_cleanup' );

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		oal_uninstall_blog();
		restore_current_blog();
	}
} else {
	oal_uninstall_blog();
}
