<?php
/**
 * REST API controller.
 *
 * @package OpenActivityLogger
 */

namespace OpenActivityLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers REST endpoints for logs, stats, and exports.
 */
class Rest {
	const NAMESPACE = 'open-activity-logger/v1';

	/**
	 * Database layer.
	 *
	 * @var DB
	 */
	private $db;

	/**
	 * Exporter.
	 *
	 * @var Exporter
	 */
	private $exporter;

	/**
	 * Constructor.
	 *
	 * @param DB       $db       DB.
	 * @param Exporter $exporter Exporter.
	 */
	public function __construct( DB $db, Exporter $exporter ) {
		$this->db       = $db;
		$this->exporter = $exporter;
	}

	/**
	 * Registers routes.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Adds routes.
	 *
	 * @return void
	 */
	public function routes() {
		register_rest_route(
			self::NAMESPACE,
			'/logs',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_logs' ),
				'permission_callback' => array( $this, 'permissions' ),
				'args'                => $this->collection_args(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/logs/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_log' ),
				'permission_callback' => array( $this, 'permissions' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/stats',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this, 'permissions' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/export',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'export' ),
				'permission_callback' => array( $this, 'export_permissions' ),
				'args'                => array(
					'format' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'default'           => 'csv',
					),
				),
			)
		);
	}

	/**
	 * Checks read permissions.
	 *
	 * @return bool
	 */
	public function permissions() {
		return current_user_can( 'manage_options' ) || current_user_can( 'oal_manage_logs' );
	}

	/**
	 * Checks export permissions.
	 *
	 * @return bool
	 */
	public function export_permissions() {
		return current_user_can( 'manage_options' ) || current_user_can( 'oal_export_logs' );
	}

	/**
	 * Lists logs.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_logs( \WP_REST_Request $request ) {
		$data = $this->db->get_logs( $this->filters_from_request( $request ) );

		return rest_ensure_response( $data );
	}

	/**
	 * Gets a log.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_log( \WP_REST_Request $request ) {
		$log = $this->db->get_log( absint( $request['id'] ) );

		if ( ! $log ) {
			return new \WP_Error( 'oal_not_found', __( 'Log not found.', 'open-activity-logger' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $log );
	}

	/**
	 * Gets stats.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_stats( \WP_REST_Request $request ) {
		return rest_ensure_response( $this->db->get_stats( array( 'days' => absint( $request->get_param( 'days' ) ) ?: 30 ) ) );
	}

	/**
	 * Builds an export payload.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function export( \WP_REST_Request $request ) {
		$export = $this->exporter->build( $request->get_param( 'format' ), $this->filters_from_request( $request ) );

		return rest_ensure_response( $export );
	}

	/**
	 * Route args.
	 *
	 * @return array
	 */
	private function collection_args() {
		return array(
			'page'       => array( 'sanitize_callback' => 'absint', 'default' => 1 ),
			'per_page'   => array( 'sanitize_callback' => 'absint', 'default' => 20 ),
			'event_type' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'severity'   => array( 'sanitize_callback' => 'absint' ),
			'user_id'    => array( 'sanitize_callback' => 'absint' ),
			'user_role'  => array( 'sanitize_callback' => 'sanitize_key' ),
			'ip_address' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'date_from'  => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'date_to'    => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'search'     => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'category'   => array( 'sanitize_callback' => 'sanitize_key' ),
		);
	}

	/**
	 * Sanitized filters from REST request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	private function filters_from_request( \WP_REST_Request $request ) {
		$filters = array();

		foreach ( array_keys( $this->collection_args() ) as $key ) {
			$value = $request->get_param( $key );

			if ( null !== $value && '' !== $value ) {
				$filters[ $key ] = $value;
			}
		}

		return $filters;
	}
}
