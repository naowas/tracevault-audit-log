<?php
/**
 * Logger engine.
 *
 * @package TraceVaultAuditLog
 */

namespace TraceVaultAuditLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queues, sanitizes, and flushes audit logs.
 */
class Logger {
	const SEVERITY_INFO     = 1;
	const SEVERITY_NOTICE   = 2;
	const SEVERITY_WARNING  = 3;
	const SEVERITY_CRITICAL = 4;

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
	 * Pending log rows.
	 *
	 * @var array
	 */
	private $queue = array();

	/**
	 * Constructor.
	 *
	 * @param DB       $db       Database layer.
	 * @param Settings $settings Settings repository.
	 */
	public function __construct( DB $db, Settings $settings ) {
		$this->db       = $db;
		$this->settings = $settings;

		add_action( 'shutdown', array( $this, 'flush' ), 1 );
	}

	/**
	 * Creates an audit log entry.
	 *
	 * @param string $event_type Event type.
	 * @param array  $args       Log arguments.
	 * @return bool
	 */
	public function log( $event_type, array $args = array() ) {
		$event_type = $this->normalize_event_type( $event_type );

		if ( '' === $event_type || ! $this->is_allowed_event( $event_type ) ) {
			return false;
		}

		$user = isset( $args['user'] ) && $args['user'] instanceof \WP_User ? $args['user'] : wp_get_current_user();
		$meta = isset( $args['meta'] ) && is_array( $args['meta'] ) ? $args['meta'] : array();

		$data = array(
			'event_type'  => $event_type,
			'severity'    => isset( $args['severity'] ) ? $this->sanitize_severity( $args['severity'] ) : self::SEVERITY_INFO,
			'user_id'     => isset( $args['user_id'] ) ? absint( $args['user_id'] ) : ( $user instanceof \WP_User ? (int) $user->ID : 0 ),
			'username'    => isset( $args['username'] ) ? sanitize_text_field( $args['username'] ) : ( $user instanceof \WP_User ? sanitize_user( $user->user_login ) : '' ),
			'user_role'   => isset( $args['user_role'] ) ? sanitize_key( $args['user_role'] ) : \tracevault_current_user_role( $user ),
			'ip_address'  => isset( $args['ip_address'] ) ? $this->sanitize_ip( $args['ip_address'] ) : $this->current_ip(),
			'user_agent'  => isset( $args['user_agent'] ) ? sanitize_textarea_field( $args['user_agent'] ) : $this->current_user_agent(),
			'object_type' => isset( $args['object_type'] ) ? sanitize_key( $args['object_type'] ) : '',
			'object_id'   => isset( $args['object_id'] ) ? absint( $args['object_id'] ) : 0,
			'message'     => isset( $args['message'] ) ? sanitize_textarea_field( $args['message'] ) : '',
			'meta'        => $this->sanitize_meta( $meta ),
			'created_at'  => current_time( 'mysql', true ),
		);

		/**
		 * Filters log data before it is queued for storage.
		 *
		 * @param array  $data       Sanitized log data.
		 * @param string $event_type Event type.
		 */
		$data = apply_filters( 'tracevault_log_data_before_insert', $data, $event_type );

		if ( ! is_array( $data ) || empty( $data['event_type'] ) ) {
			return false;
		}

		$this->queue[] = $data;

		if ( count( $this->queue ) >= 20 ) {
			$this->flush();
		}

		return true;
	}

	/**
	 * Flushes queued records.
	 *
	 * @return void
	 */
	public function flush() {
		if ( empty( $this->queue ) ) {
			return;
		}

		$queue       = $this->queue;
		$this->queue = array();

		$this->db->insert_logs( $queue );
	}

	/**
	 * Checks if an event is allowed.
	 *
	 * @param string $event_type Event type.
	 * @return bool
	 */
	private function is_allowed_event( $event_type ) {
		$allowed = apply_filters( 'tracevault_allowed_events', array() );

		if ( empty( $allowed ) ) {
			return true;
		}

		return in_array( $event_type, array_map( array( $this, 'normalize_event_type' ), $allowed ), true );
	}

	/**
	 * Normalizes event names while preserving dot-separated namespaces.
	 *
	 * @param string $event_type Event type.
	 * @return string
	 */
	private function normalize_event_type( $event_type ) {
		$event_type = strtolower( sanitize_text_field( (string) $event_type ) );
		$event_type = preg_replace( '/[^a-z0-9._-]/', '', $event_type );
		$event_type = trim( (string) $event_type, '._-' );

		return substr( $event_type, 0, 191 );
	}

	/**
	 * Sanitizes severity values.
	 *
	 * @param mixed $severity Severity.
	 * @return int
	 */
	private function sanitize_severity( $severity ) {
		$severity = absint( $severity );

		if ( $severity < self::SEVERITY_INFO || $severity > self::SEVERITY_CRITICAL ) {
			return self::SEVERITY_INFO;
		}

		return $severity;
	}

	/**
	 * Gets the request IP while honoring anonymization.
	 *
	 * @return string
	 */
	private function current_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $this->sanitize_ip( $ip );
	}

	/**
	 * Sanitizes and optionally anonymizes an IP address.
	 *
	 * @param string $ip IP address.
	 * @return string
	 */
	private function sanitize_ip( $ip ) {
		$ip = sanitize_text_field( $ip );

		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		if ( (int) $this->settings->get( 'anonymize_ip', 0 ) ) {
			if ( false !== strpos( $ip, ':' ) ) {
				return preg_replace( '/:[0-9a-f]{1,4}$/i', ':0000', $ip );
			}

			return preg_replace( '/\.\d+$/', '.0', $ip );
		}

		return $ip;
	}

	/**
	 * Gets the current user agent.
	 *
	 * @return string
	 */
	private function current_user_agent() {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}

		return substr( sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 1000 );
	}

	/**
	 * Recursively sanitizes metadata.
	 *
	 * @param array $meta Meta.
	 * @return array
	 */
	private function sanitize_meta( array $meta ) {
		$clean = array();

		foreach ( $meta as $key => $value ) {
			$key = sanitize_key( $key );

			if ( '' === $key ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$clean[ $key ] = $this->sanitize_meta( $value );
			} elseif ( is_bool( $value ) || is_numeric( $value ) ) {
				$clean[ $key ] = $value;
			} else {
				$clean[ $key ] = sanitize_textarea_field( (string) $value );
			}
		}

		return $clean;
	}
}
