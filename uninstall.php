<?php
/**
 * Uninstall cleanup.
 *
 * @package TraceVaultAuditLog
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Drops plugin tables for the current blog when the safe toggle is enabled.
 *
 * @return void
 */
function tracevault_uninstall_blog() {
	global $wpdb;

	$settings_table = esc_sql( $wpdb->prefix . 'tracevault_settings' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Reads plugin-owned custom table to honor uninstall preference.
	$delete = $wpdb->get_var( $wpdb->prepare( "SELECT setting_value FROM {$settings_table} WHERE setting_key = %s", 'delete_data_on_uninstall' ) );

	if ( '1' !== (string) $delete ) {
		return;
	}

	foreach ( array( 'tracevault_meta', 'tracevault_logs', 'tracevault_settings' ) as $suffix ) {
		$table = esc_sql( $wpdb->prefix . $suffix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Uninstall cleanup for plugin-owned custom tables.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}
}

wp_clear_scheduled_hook( 'tracevault_daily_cleanup' );

if ( is_multisite() ) {
	$tracevault_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $tracevault_site_ids as $tracevault_site_id ) {
		switch_to_blog( (int) $tracevault_site_id );
		tracevault_uninstall_blog();
		restore_current_blog();
	}
} else {
	tracevault_uninstall_blog();
}
