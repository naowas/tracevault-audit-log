<?php
/**
 * Privacy integrations.
 *
 * @package OpenActivityLogger
 */

namespace OpenActivityLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds personal data export and erasure support.
 */
class Privacy {
	/**
	 * Database.
	 *
	 * @var DB
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param DB $db DB.
	 */
	public function __construct( DB $db ) {
		$this->db = $db;
	}

	/**
	 * Registers privacy hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Registers exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['open-activity-logger'] = array(
			'exporter_friendly_name' => __( 'Open Activity Logger', 'open-activity-logger' ),
			'callback'               => array( $this, 'export_user_data' ),
		);

		return $exporters;
	}

	/**
	 * Registers eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['open-activity-logger'] = array(
			'eraser_friendly_name' => __( 'Open Activity Logger', 'open-activity-logger' ),
			'callback'             => array( $this, 'erase_user_data' ),
		);

		return $erasers;
	}

	/**
	 * Exports user logs.
	 *
	 * @param string $email_address Email.
	 * @param int    $page Page.
	 * @return array
	 */
	public function export_user_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}

		$result = $this->db->get_logs(
			array(
				'user_id'  => $user->ID,
				'page'     => max( 1, absint( $page ) ),
				'per_page' => 50,
			)
		);

		$data = array();

		foreach ( $result['items'] as $log ) {
			$data[] = array(
				'group_id'    => 'open-activity-logger',
				'group_label' => __( 'Open Activity Logger', 'open-activity-logger' ),
				'item_id'     => 'oal-log-' . $log['id'],
				'data'        => array(
					array( 'name' => __( 'Event', 'open-activity-logger' ), 'value' => $log['event_type'] ),
					array( 'name' => __( 'Message', 'open-activity-logger' ), 'value' => $log['message'] ),
					array( 'name' => __( 'IP Address', 'open-activity-logger' ), 'value' => $log['ip_address'] ),
					array( 'name' => __( 'Created', 'open-activity-logger' ), 'value' => $log['created_at'] ),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => absint( $page ) >= $result['total_pages'],
		);
	}

	/**
	 * Anonymizes user log data.
	 *
	 * @param string $email_address Email.
	 * @param int    $page Page.
	 * @return array
	 */
	public function erase_user_data( $email_address, $page = 1 ) {
		unset( $page );
		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}

		$this->db->anonymize_user( $user->ID );

		return array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array( __( 'Activity log personal data was anonymized.', 'open-activity-logger' ) ),
			'done'           => true,
		);
	}
}
