<?php
/**
 * Settings repository.
 *
 * @package TraceVaultAuditLog
 */

namespace TraceVaultAuditLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes settings from the custom settings table.
 */
class Settings {
	/**
	 * Database layer.
	 *
	 * @var DB
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param DB $db Database layer.
	 */
	public function __construct( DB $db ) {
		$this->db = $db;
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'retention_days'           => 90,
			'anonymize_ip'             => 0,
			'delete_data_on_uninstall' => 0,
			'capture_option_updates'   => 0,
			'admin_date_format'        => 'wordpress',
			'enabled_events'           => array(),
			'schema_version'           => TRACEVAULT_VERSION,
		);
	}

	/**
	 * Gets one setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		global $wpdb;

		$key = sanitize_key( $key );

		if ( '' === $key ) {
			return $default;
		}

		$cache_key = 'setting_' . $key;
		$cached    = wp_cache_get( $cache_key, 'tracevault_audit_log' );

		if ( false !== $cached ) {
			return $cached;
		}

		$table = $this->db->table( 'settings' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name is generated from a closed allow-list.
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT setting_value FROM {$table} WHERE setting_key = %s", $key ) );

		if ( null === $value ) {
			$defaults = self::defaults();
			$value    = array_key_exists( $key, $defaults ) ? $defaults[ $key ] : $default;
		} else {
			$decoded = json_decode( $value, true );
			$value   = ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : $value;
		}

		wp_cache_set( $cache_key, $value, 'tracevault_audit_log', MINUTE_IN_SECONDS );

		return $value;
	}

	/**
	 * Writes a setting.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	public function set( $key, $value ) {
		global $wpdb;

		$key = sanitize_key( $key );

		if ( '' === $key ) {
			return false;
		}

		if ( is_bool( $value ) ) {
			$value = $value ? 1 : 0;
		}

		$stored = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
		$table  = $this->db->table( 'settings' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name is generated from a closed allow-list.
		$previous_stored = $wpdb->get_var( $wpdb->prepare( "SELECT setting_value FROM {$table} WHERE setting_key = %s", $key ) );
		$previous        = $previous_stored;

		if ( null !== $previous ) {
			$decoded  = json_decode( $previous, true );
			$previous = ( JSON_ERROR_NONE === json_last_error() ) ? $decoded : $previous;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Writing plugin-owned custom settings table.
		$result = $wpdb->replace(
			$table,
			array(
				'setting_key'   => $key,
				'setting_value' => $stored,
			),
			array( '%s', '%s' )
		);

		wp_cache_delete( 'setting_' . $key, 'tracevault_audit_log' );

		if ( $result && $previous_stored !== $stored ) {
			/**
			 * Fires after a TraceVault setting changes.
			 *
			 * @param string $key       Setting key.
			 * @param mixed  $previous  Previous setting value.
			 * @param mixed  $value     New setting value.
			 */
			do_action( 'tracevault_setting_updated', $key, $previous, $value );
		}

		return (bool) $result;
	}

	/**
	 * Gets all supported settings.
	 *
	 * @return array
	 */
	public function all() {
		$settings = array();

		foreach ( self::defaults() as $key => $default ) {
			$settings[ $key ] = $this->get( $key, $default );
		}

		return $settings;
	}

	/**
	 * Persists a sanitized settings payload.
	 *
	 * @param array $input Raw input.
	 * @return void
	 */
	public function save_from_request( array $input ) {
		$this->set( 'retention_days', max( 1, min( 3650, absint( $input['retention_days'] ?? 90 ) ) ) );
		$this->set( 'anonymize_ip', empty( $input['anonymize_ip'] ) ? 0 : 1 );
		$this->set( 'delete_data_on_uninstall', empty( $input['delete_data_on_uninstall'] ) ? 0 : 1 );
		$this->set( 'capture_option_updates', empty( $input['capture_option_updates'] ) ? 0 : 1 );
		$this->set( 'admin_date_format', $this->sanitize_date_format( isset( $input['admin_date_format'] ) ? $input['admin_date_format'] : 'wordpress' ) );
	}

	/**
	 * Sanitizes the admin date format preference.
	 *
	 * @param string $format Date format key.
	 * @return string
	 */
	private function sanitize_date_format( $format ) {
		$format  = sanitize_text_field( $format );
		$allowed = array( 'wordpress', 'relative', 'Y-m-d H:i:s', 'M j, Y g:i a', 'd M Y H:i' );

		return in_array( $format, $allowed, true ) ? $format : 'wordpress';
	}
}
