<?php
/**
 * Cleanup scheduler.
 *
 * @package OpenActivityLogger
 */

namespace OpenActivityLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WP-Cron retention cleanup.
 */
class Scheduler {
	const CLEANUP_HOOK = 'oal_daily_cleanup';

	/**
	 * Database layer.
	 *
	 * @var DB
	 */
	private $db;

	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param DB       $db       DB.
	 * @param Settings $settings Settings.
	 */
	public function __construct( DB $db, Settings $settings ) {
		$this->db       = $db;
		$this->settings = $settings;
	}

	/**
	 * Registers cron callback.
	 *
	 * @return void
	 */
	public function register() {
		add_action( self::CLEANUP_HOOK, array( $this, 'cleanup' ) );
		self::schedule();
	}

	/**
	 * Schedules cleanup.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Clears cleanup schedule.
	 *
	 * @return void
	 */
	public static function clear() {
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
	}

	/**
	 * Deletes logs past the retention window.
	 *
	 * @return void
	 */
	public function cleanup() {
		$retention = absint( $this->settings->get( 'retention_days', 90 ) );

		if ( $retention > 0 ) {
			$this->db->delete_older_than( $retention );
		}
	}
}
