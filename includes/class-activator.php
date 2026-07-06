<?php
/**
 * Activation routines.
 *
 * @package OpenActivityLogger
 */

namespace OpenActivityLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles install and network activation.
 */
final class Activator {
	/**
	 * Runs activation.
	 *
	 * @param bool $network_wide Whether the plugin was network activated.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		$db = new DB();

		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				$db->create_tables();
				$db->ensure_default_settings();
				self::add_capabilities();
				restore_current_blog();
			}
		} else {
			$db->create_tables();
			$db->ensure_default_settings();
			self::add_capabilities();
		}

		Scheduler::schedule();
	}

	/**
	 * Adds audit capabilities to administrators.
	 *
	 * @return void
	 */
	private static function add_capabilities() {
		$role = get_role( 'administrator' );

		if ( $role ) {
			$role->add_cap( 'oal_manage_logs' );
			$role->add_cap( 'oal_export_logs' );
		}
	}
}
