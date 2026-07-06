<?php
/**
 * Deactivation routines.
 *
 * @package OpenActivityLogger
 */

namespace OpenActivityLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clears scheduled work on deactivation.
 */
final class Deactivator {
	/**
	 * Runs deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		Scheduler::clear();
	}
}
