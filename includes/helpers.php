<?php
/**
 * Global helper functions.
 *
 * @package OpenActivityLogger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'oal' ) ) {
	/**
	 * Returns the plugin singleton.
	 *
	 * @return OpenActivityLogger\Plugin
	 */
	function oal() {
		return OpenActivityLogger\Plugin::instance();
	}
}

if ( ! function_exists( 'oal_log_event' ) ) {
	/**
	 * Public helper for extensions to create an audit event.
	 *
	 * @param string $event_type Event type, for example custom.invoice.paid.
	 * @param array  $args       Event arguments.
	 * @return bool
	 */
	function oal_log_event( $event_type, array $args = array() ) {
		return oal()->logger()->log( $event_type, $args );
	}
}

if ( ! function_exists( 'oal_current_user_role' ) ) {
	/**
	 * Gets the first role for a user.
	 *
	 * @param WP_User|null $user User object.
	 * @return string
	 */
	function oal_current_user_role( $user = null ) {
		if ( null === $user ) {
			$user = wp_get_current_user();
		}

		if ( ! $user instanceof WP_User || empty( $user->roles ) ) {
			return '';
		}

		return sanitize_key( (string) reset( $user->roles ) );
	}
}
