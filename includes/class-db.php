<?php
/**
 * Database layer.
 *
 * @package TraceVaultAuditLog
 */

namespace TraceVaultAuditLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom table storage and query API.
 */
class DB {
	/**
	 * Known table keys.
	 *
	 * @var string[]
	 */
	private $tables = array( 'logs', 'meta', 'settings' );

	/**
	 * Gets a fully-qualified table name.
	 *
	 * @param string $table Table key.
	 * @return string
	 */
	public function table( $table ) {
		global $wpdb;

		if ( ! in_array( $table, $this->tables, true ) ) {
			return '';
		}

		return esc_sql( $wpdb->prefix . 'tracevault_' . $table );
	}

	/**
	 * Creates or upgrades custom tables.
	 *
	 * @return void
	 */
	public function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$logs    = $this->table( 'logs' );
		$meta    = $this->table( 'meta' );
		$settings = $this->table( 'settings' );

		$sql = array();

		$sql[] = "CREATE TABLE {$logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(191) NOT NULL,
			severity tinyint(3) unsigned NOT NULL DEFAULT 1,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			username varchar(191) NOT NULL DEFAULT '',
			user_role varchar(191) NOT NULL DEFAULT '',
			ip_address varchar(45) NOT NULL DEFAULT '',
			user_agent text NULL,
			object_type varchar(100) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			message text NULL,
			meta longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY severity (severity),
			KEY user_id (user_id),
			KEY ip_address (ip_address),
			KEY object_lookup (object_type, object_id),
			KEY created_at (created_at)
		) {$charset};";

		$sql[] = "CREATE TABLE {$meta} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			log_id bigint(20) unsigned NOT NULL,
			meta_key varchar(191) NOT NULL,
			meta_value longtext NULL,
			PRIMARY KEY  (id),
			KEY log_id (log_id),
			KEY meta_key (meta_key)
		) {$charset};";

		$sql[] = "CREATE TABLE {$settings} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			setting_key varchar(191) NOT NULL,
			setting_value longtext NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY setting_key (setting_key)
		) {$charset};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Inserts default settings without overwriting site preferences.
	 *
	 * @return void
	 */
	public function ensure_default_settings() {
		global $wpdb;

		$defaults = Settings::defaults();
		$table    = $this->table( 'settings' );

		foreach ( $defaults as $key => $value ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name is generated from a closed allow-list.
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE setting_key = %s", $key ) );

			if ( $exists ) {
				continue;
			}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Inserting default values into plugin-owned settings table on activation.
				$wpdb->insert(
				$table,
				array(
					'setting_key'   => $key,
					'setting_value' => is_scalar( $value ) ? (string) $value : wp_json_encode( $value ),
				),
				array( '%s', '%s' )
			);
		}
	}

	/**
	 * Inserts logs in a batch and writes searchable meta rows.
	 *
	 * @param array $logs Logs.
	 * @return int Number inserted.
	 */
	public function insert_logs( array $logs ) {
		global $wpdb;

		if ( empty( $logs ) ) {
			return 0;
		}

		$table  = $this->table( 'logs' );
		$values = array();
		$rows   = array();
		$cols   = array( 'event_type', 'severity', 'user_id', 'username', 'user_role', 'ip_address', 'user_agent', 'object_type', 'object_id', 'message', 'meta', 'created_at' );

		foreach ( $logs as $log ) {
			$rows[] = '(%s,%d,%d,%s,%s,%s,%s,%s,%d,%s,%s,%s)';

			$values[] = $log['event_type'];
			$values[] = (int) $log['severity'];
			$values[] = (int) $log['user_id'];
			$values[] = $log['username'];
			$values[] = $log['user_role'];
			$values[] = $log['ip_address'];
			$values[] = $log['user_agent'];
			$values[] = $log['object_type'];
			$values[] = (int) $log['object_id'];
			$values[] = $log['message'];
			$values[] = wp_json_encode( $log['meta'] );
			$values[] = $log['created_at'];
		}

		$sql      = 'INSERT INTO ' . $table . ' (`' . implode( '`,`', $cols ) . '`) VALUES ' . implode( ',', $rows );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Multi-row insert uses a generated placeholder list and a validated custom table name.
			$inserted = $wpdb->query( $wpdb->prepare( $sql, $values ) );

		if ( false === $inserted ) {
			return 0;
		}

		$first_id = (int) $wpdb->insert_id;
		$meta_rows = array();

		foreach ( array_values( $logs ) as $index => $log ) {
			$log_id = $first_id + $index;

			$this->queue_meta_rows( $meta_rows, $log_id, $log['meta'] );
			do_action( 'tracevault_log_created', $log_id, $log );
		}

		$this->insert_meta_rows( $meta_rows );

		return (int) $inserted;
	}

	/**
	 * Adds meta rows for scalar metadata.
	 *
	 * @param array $rows   Existing rows.
	 * @param int   $log_id Log ID.
	 * @param array $meta   Meta array.
	 * @return void
	 */
	private function queue_meta_rows( array &$rows, $log_id, array $meta ) {
		foreach ( $meta as $key => $value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				$value = wp_json_encode( $value );
			}

			$rows[] = array(
				'log_id'     => (int) $log_id,
				'meta_key'   => sanitize_key( $key ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Custom audit metadata table, not wp_postmeta.
				'meta_value' => maybe_serialize( $value ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Custom audit metadata table, not wp_postmeta.
			);
		}
	}

	/**
	 * Bulk inserts meta rows.
	 *
	 * @param array $rows Meta rows.
	 * @return void
	 */
	private function insert_meta_rows( array $rows ) {
		global $wpdb;

		if ( empty( $rows ) ) {
			return;
		}

		$table  = $this->table( 'meta' );
		$sql_rows = array();
		$values = array();

		foreach ( $rows as $row ) {
			$sql_rows[] = '(%d,%s,%s)';
			$values[]   = (int) $row['log_id'];
			$values[]   = $row['meta_key'];
			$values[]   = $row['meta_value'];
		}

		$sql = 'INSERT INTO ' . $table . ' (`log_id`,`meta_key`,`meta_value`) VALUES ' . implode( ',', $sql_rows );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Multi-row insert uses a generated placeholder list and a validated custom table name.
			$wpdb->query( $wpdb->prepare( $sql, $values ) );
	}

	/**
	 * Lists logs with indexed filters and pagination.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function get_logs( array $args = array() ) {
		global $wpdb;

		$table    = $this->table( 'logs' );
		$defaults = array(
			'page'       => 1,
			'per_page'   => 20,
			'event_type' => '',
			'severity'   => '',
			'user_id'    => '',
			'user_role'  => '',
			'ip_address' => '',
			'date_from'  => '',
			'date_to'    => '',
			'search'     => '',
			'category'   => '',
			'exclude_verbose' => 0,
		);
		$args     = wp_parse_args( $args, $defaults );
		$where    = array( '1 = %d' );
		$values   = array( 1 );

		if ( '' !== $args['event_type'] ) {
			$where[]  = 'event_type = %s';
			$values[] = sanitize_text_field( $args['event_type'] );
		}

		if ( '' !== $args['category'] ) {
			if ( 'settings' === $args['category'] ) {
				$where[] = 'event_type = %s';
				$values[] = 'system.option_update';
			} elseif ( 'system' === $args['category'] ) {
				$where[] = 'event_type LIKE %s';
				$values[] = 'system.%';
				$where[] = 'event_type <> %s';
				$values[] = 'system.option_update';
			} else {
				$where[]  = 'event_type LIKE %s';
				$values[] = $wpdb->esc_like( sanitize_key( $args['category'] ) ) . '.%';
			}
		}

		if ( ! empty( $args['exclude_verbose'] ) ) {
			$where[] = 'event_type <> %s';
			$values[] = 'system.option_update';
		}

		if ( '' !== $args['severity'] ) {
			$where[]  = 'severity = %d';
			$values[] = absint( $args['severity'] );
		}

		if ( '' !== $args['user_id'] ) {
			$where[]  = 'user_id = %d';
			$values[] = absint( $args['user_id'] );
		}

		if ( '' !== $args['user_role'] ) {
			$where[]  = 'user_role = %s';
			$values[] = sanitize_key( $args['user_role'] );
		}

		if ( '' !== $args['ip_address'] ) {
			$where[]  = 'ip_address = %s';
			$values[] = sanitize_text_field( $args['ip_address'] );
		}

		if ( '' !== $args['date_from'] ) {
			$timestamp = strtotime( sanitize_text_field( $args['date_from'] ) );
			if ( $timestamp ) {
				$where[]  = 'created_at >= %s';
				$values[] = gmdate( 'Y-m-d H:i:s', $timestamp );
			}
		}

		if ( '' !== $args['date_to'] ) {
			$timestamp = strtotime( sanitize_text_field( $args['date_to'] ) );
			if ( $timestamp ) {
				$where[]  = 'created_at <= %s';
				$values[] = gmdate( 'Y-m-d H:i:s', $timestamp );
			}
		}

		if ( '' !== $args['search'] ) {
			$where[]  = '(message LIKE %s OR username LIKE %s OR object_type LIKE %s)';
			$like     = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$page     = max( 1, absint( $args['page'] ) );
		$per_page = min( 100, max( 1, absint( $args['per_page'] ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic WHERE fragments are assembled only from fixed clauses with prepared values.
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) );

		$query_values = array_merge( $values, array( $per_page, $offset ) );
		$sql          = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic WHERE fragments are assembled only from fixed clauses with prepared values.
		$items        = $wpdb->get_results( $wpdb->prepare( $sql, $query_values ), ARRAY_A );

		return array(
			'items'       => array_map( array( $this, 'decode_log' ), $items ? $items : array() ),
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
			'page'        => $page,
			'per_page'    => $per_page,
		);
	}

	/**
	 * Gets one log.
	 *
	 * @param int $id Log ID.
	 * @return array|null
	 */
	public function get_log( $id ) {
		global $wpdb;

		$id    = absint( $id );
		$cache = wp_cache_get( 'log_' . $id, 'tracevault_audit_log' );

		if ( false !== $cache ) {
			return $cache;
		}

		$table = $this->table( 'logs' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name is generated from a closed allow-list.
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		if ( ! $row ) {
			return null;
		}

		$log = $this->decode_log( $row );
		wp_cache_set( 'log_' . $id, $log, 'tracevault_audit_log', MINUTE_IN_SECONDS );

		return $log;
	}

	/**
	 * Deletes one log.
	 *
	 * @param int $id Log ID.
	 * @return bool
	 */
	public function delete_log( $id ) {
		global $wpdb;

		$id = absint( $id );

		if ( ! $id ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Deleting from plugin-owned custom table after nonce/capability checks.
		$wpdb->delete( $this->table( 'meta' ), array( 'log_id' => $id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Deleting from plugin-owned custom table after nonce/capability checks.
		$deleted = $wpdb->delete( $this->table( 'logs' ), array( 'id' => $id ), array( '%d' ) );
		wp_cache_delete( 'log_' . $id, 'tracevault_audit_log' );

		if ( $deleted ) {
			do_action( 'tracevault_log_deleted', $id );
		}

		return (bool) $deleted;
	}

	/**
	 * Deletes all logs and searchable meta rows.
	 *
	 * @return int|false Deleted log rows.
	 */
	public function clear_logs() {
		global $wpdb;

		$meta = $this->table( 'meta' );
		$logs = $this->table( 'logs' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name is generated from a closed allow-list.
		$wpdb->query( "DELETE FROM {$meta}" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name is generated from a closed allow-list.
		$deleted = $wpdb->query( "DELETE FROM {$logs}" );

		return $deleted;
	}

	/**
	 * Deletes records older than a retention period.
	 *
	 * @param int $days Days to retain.
	 * @return int Deleted rows.
	 */
	public function delete_older_than( $days ) {
		global $wpdb;

		$days = absint( $days );

		if ( $days < 1 ) {
			return 0;
		}

		$table = $this->table( 'logs' );
		$meta  = $this->table( 'meta' );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name is generated from a closed allow-list.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE created_at < %s LIMIT 5000", $cutoff ) );

		if ( empty( $ids ) ) {
			return 0;
		}

		$ids = array_map( 'absint', $ids );
		$in  = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- IN placeholders are generated from sanitized integer IDs.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$meta} WHERE log_id IN ({$in})", $ids ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- IN placeholders are generated from sanitized integer IDs.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$in})", $ids ) );
	}

	/**
	 * Returns aggregate statistics.
	 *
	 * @param array $args Stats args.
	 * @return array
	 */
	public function get_stats( array $args = array() ) {
		global $wpdb;

		$table = $this->table( 'logs' );
		$days  = isset( $args['days'] ) ? min( 365, max( 1, absint( $args['days'] ) ) ) : 30;
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$where = 'created_at >= %s';
		$values = array( $since );

		if ( ! empty( $args['exclude_verbose'] ) ) {
			$where .= ' AND event_type <> %s';
			$values[] = 'system.option_update';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name is validated and WHERE contains only fixed prepared fragments.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $values ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name is validated and WHERE contains only fixed prepared fragments.
		$events = $wpdb->get_results( $wpdb->prepare( "SELECT event_type, COUNT(*) AS total FROM {$table} WHERE {$where} GROUP BY event_type ORDER BY total DESC LIMIT 10", $values ), ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name is validated and WHERE contains only fixed prepared fragments.
		$severity = $wpdb->get_results( $wpdb->prepare( "SELECT severity, COUNT(*) AS total FROM {$table} WHERE {$where} GROUP BY severity ORDER BY severity ASC", $values ), ARRAY_A );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table name is validated and WHERE contains only fixed prepared fragments.
		$daily = $wpdb->get_results( $wpdb->prepare( "SELECT DATE(created_at) AS day, COUNT(*) AS total FROM {$table} WHERE {$where} GROUP BY DATE(created_at) ORDER BY day ASC", $values ), ARRAY_A );

		return array(
			'total'    => $total,
			'events'   => $events ? $events : array(),
			'severity' => $severity ? $severity : array(),
			'daily'    => $daily ? $daily : array(),
			'days'     => $days,
		);
	}

	/**
	 * Anonymizes logs for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int|false
	 */
	public function anonymize_user( $user_id ) {
		global $wpdb;

		$table = $this->table( 'logs' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Updating plugin-owned custom table during privacy erasure.
		return $wpdb->update(
			$table,
			array(
				'username'   => __( 'Anonymized user', 'tracevault-audit-log' ),
				'ip_address' => '',
				'user_agent' => '',
				'meta'       => '{}',
			),
			array( 'user_id' => absint( $user_id ) ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Decodes a stored log row.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private function decode_log( array $row ) {
		$row['id']        = (int) $row['id'];
		$row['severity']  = (int) $row['severity'];
		$row['user_id']   = (int) $row['user_id'];
		$row['object_id'] = (int) $row['object_id'];
		$row['meta']      = $row['meta'] ? json_decode( $row['meta'], true ) : array();

		if ( ! is_array( $row['meta'] ) ) {
			$row['meta'] = array();
		}

		return $row;
	}
}
