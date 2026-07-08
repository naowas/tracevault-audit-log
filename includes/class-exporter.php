<?php
/**
 * Export service.
 *
 * @package TraceVaultAuditLog
 */

namespace TraceVaultAuditLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Produces filtered CSV and JSON exports.
 */
class Exporter {
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
	 * Builds an export.
	 *
	 * @param string $format  csv or json.
	 * @param array  $filters Query filters.
	 * @return array
	 */
	public function build( $format, array $filters = array() ) {
		$format = 'json' === strtolower( $format ) ? 'json' : 'csv';
		$result = $this->db->get_logs(
			array_merge(
				$filters,
				array(
					'page'     => 1,
					'per_page' => 100,
				)
			)
		);

		$items = $result['items'];
		$page  = 2;

		while ( count( $items ) < 5000 && $page <= $result['total_pages'] ) {
			$next = $this->db->get_logs(
				array_merge(
					$filters,
					array(
						'page'     => $page,
						'per_page' => 100,
					)
				)
			);

			$items = array_merge( $items, $next['items'] );
			$page++;
		}

		if ( 'json' === $format ) {
			return array(
				'filename' => 'tracevault-audit-log-' . gmdate( 'Ymd-His' ) . '.json',
				'mime'     => 'application/json',
				'content'  => wp_json_encode( $items, JSON_PRETTY_PRINT ),
			);
		}

		return array(
			'filename' => 'tracevault-audit-log-' . gmdate( 'Ymd-His' ) . '.csv',
			'mime'     => 'text/csv',
			'content'  => $this->to_csv( $items ),
		);
	}

	/**
	 * Streams an export download.
	 *
	 * @param string $format  Format.
	 * @param array  $filters Filters.
	 * @return void
	 */
	public function download( $format, array $filters = array() ) {
		$export = $this->build( $format, $filters );

		nocache_headers();
		header( 'Content-Type: ' . $export['mime'] . '; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $export['filename'] ) . '"' );
		echo $export['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Export payload is generated from sanitized records.
		exit;
	}

	/**
	 * Converts logs to CSV.
	 *
	 * @param array $items Logs.
	 * @return string
	 */
	private function to_csv( array $items ) {
		$fields = array( 'id', 'event_type', 'severity', 'user_id', 'username', 'user_role', 'ip_address', 'object_type', 'object_id', 'message', 'created_at', 'meta' );
		$lines  = array( $this->csv_row( $fields ) );

		foreach ( $items as $item ) {
			$row = array();

			foreach ( $fields as $field ) {
				$row[] = 'meta' === $field ? wp_json_encode( $item['meta'] ) : ( isset( $item[ $field ] ) ? $item[ $field ] : '' );
			}

			$lines[] = $this->csv_row( $row );
		}

		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Converts a row to RFC 4180-compatible CSV without filesystem streams.
	 *
	 * @param array $row Row values.
	 * @return string
	 */
	private function csv_row( array $row ) {
		$escaped = array();

		foreach ( $row as $value ) {
			$value     = str_replace( '"', '""', (string) $value );
			$escaped[] = '"' . $value . '"';
		}

		return implode( ',', $escaped );
	}
}
