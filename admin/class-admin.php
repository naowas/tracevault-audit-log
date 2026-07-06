<?php
/**
 * Admin UI.
 *
 * @package OpenActivityLogger
 */

namespace OpenActivityLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers admin pages, assets, AJAX, settings, and exports.
 */
class Admin {
	/**
	 * DB.
	 *
	 * @var DB
	 */
	private $db;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

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
	 * @param Settings $settings Settings.
	 * @param Exporter $exporter Exporter.
	 */
	public function __construct( DB $db, Settings $settings, Exporter $exporter ) {
		$this->db       = $db;
		$this->settings = $settings;
		$this->exporter = $exporter;
	}

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'wp_ajax_oal_logs', array( $this, 'ajax_logs' ) );
		add_action( 'wp_ajax_oal_stats', array( $this, 'ajax_stats' ) );
		add_action( 'wp_ajax_oal_delete_log', array( $this, 'ajax_delete_log' ) );
		add_action( 'wp_ajax_oal_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'admin_post_oal_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_oal_export', array( $this, 'download_export' ) );
	}

	/**
	 * Capability check.
	 *
	 * @return bool
	 */
	private function can_manage() {
		return current_user_can( 'manage_options' ) || current_user_can( 'oal_manage_logs' );
	}

	/**
	 * Export capability check.
	 *
	 * @return bool
	 */
	private function can_export() {
		return current_user_can( 'manage_options' ) || current_user_can( 'oal_export_logs' );
	}

	/**
	 * Adds menu pages.
	 *
	 * @return void
	 */
	public function menu() {
		$capability = current_user_can( 'oal_manage_logs' ) ? 'oal_manage_logs' : 'manage_options';

		add_menu_page(
			__( 'Open Activity Logger', 'open-activity-logger' ),
			__( 'Activity Logs', 'open-activity-logger' ),
			$capability,
			'open-activity-logger',
			array( $this, 'overview_page' ),
			'dashicons-shield-alt',
			58
		);

		add_submenu_page( 'open-activity-logger', __( 'Activity Logs', 'open-activity-logger' ), __( 'Activity Logs', 'open-activity-logger' ), $capability, 'open-activity-logger', array( $this, 'overview_page' ) );
		add_submenu_page( 'open-activity-logger', __( 'Settings', 'open-activity-logger' ), __( 'Settings', 'open-activity-logger' ), $capability, 'oal-settings', array( $this, 'settings_page' ) );
	}

	/**
	 * Enqueues assets.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function assets( $hook ) {
		if ( false === strpos( $hook, 'open-activity-logger' ) && false === strpos( $hook, 'oal-' ) ) {
			return;
		}

		wp_enqueue_style( 'oal-admin', OAL_PLUGIN_URL . 'assets/css/admin.css', array(), OAL_VERSION );
		wp_enqueue_script( 'oal-admin', OAL_PLUGIN_URL . 'assets/js/admin.js', array(), OAL_VERSION, true );
		wp_localize_script(
			'oal-admin',
			'oalAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'oal_admin' ),
				'i18n'    => array(
					'loading' => __( 'Loading activity...', 'open-activity-logger' ),
					'empty'   => __( 'No activity found.', 'open-activity-logger' ),
					'error'   => __( 'Unable to load activity.', 'open-activity-logger' ),
					'confirmDelete' => __( 'Delete this log entry?', 'open-activity-logger' ),
					'confirmClear'  => __( 'Delete all activity logs? This cannot be undone.', 'open-activity-logger' ),
					'delete'        => __( 'Delete', 'open-activity-logger' ),
					'events'        => $this->event_labels(),
					'severity' => array(
						1 => __( 'Info', 'open-activity-logger' ),
						2 => __( 'Notice', 'open-activity-logger' ),
						3 => __( 'Warning', 'open-activity-logger' ),
						4 => __( 'Critical', 'open-activity-logger' ),
					),
				),
				'canDelete' => $this->can_manage(),
			)
		);
	}

	/**
	 * Renders overview.
	 *
	 * @return void
	 */
	public function overview_page() {
		$this->render( 'overview', array( 'stats' => $this->db->get_stats( array( 'exclude_verbose' => 1 ) ) ) );
	}

	/**
	 * Renders settings.
	 *
	 * @return void
	 */
	public function settings_page() {
		$this->render( 'settings', array( 'settings' => $this->settings->all() ) );
	}

	/**
	 * AJAX log query.
	 *
	 * @return void
	 */
	public function ajax_logs() {
		check_ajax_referer( 'oal_admin', 'nonce' );

		if ( ! $this->can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'open-activity-logger' ) ), 403 );
		}

		$filters = $this->filters_from_request( $_GET );
		if ( empty( $filters['category'] ) ) {
			$filters['exclude_verbose'] = 1;
		}

		$result = $this->db->get_logs( $filters );
		foreach ( $result['items'] as $index => $item ) {
			$result['items'][ $index ]['display_time'] = $this->format_log_time( $item['created_at'] );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX stats.
	 *
	 * @return void
	 */
	public function ajax_stats() {
		check_ajax_referer( 'oal_admin', 'nonce' );

		if ( ! $this->can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'open-activity-logger' ) ), 403 );
		}

		wp_send_json_success( $this->db->get_stats( array( 'days' => isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30, 'exclude_verbose' => 1 ) ) );
	}

	/**
	 * Deletes one log entry over AJAX.
	 *
	 * @return void
	 */
	public function ajax_delete_log() {
		check_ajax_referer( 'oal_admin', 'nonce' );

		if ( ! $this->can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'open-activity-logger' ) ), 403 );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id || ! $this->db->delete_log( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Log could not be deleted.', 'open-activity-logger' ) ), 400 );
		}

		wp_send_json_success();
	}

	/**
	 * Clears all log entries over AJAX.
	 *
	 * @return void
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'oal_admin', 'nonce' );

		if ( ! $this->can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'open-activity-logger' ) ), 403 );
		}

		$this->db->clear_logs();
		wp_send_json_success();
	}

	/**
	 * Saves settings.
	 *
	 * @return void
	 */
	public function save_settings() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'open-activity-logger' ), 403 );
		}

		check_admin_referer( 'oal_save_settings' );
		$this->settings->save_from_request( wp_unslash( $_POST ) );

		wp_safe_redirect( add_query_arg( array( 'page' => 'oal-settings', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Downloads export.
	 *
	 * @return void
	 */
	public function download_export() {
		if ( ! $this->can_export() ) {
			wp_die( esc_html__( 'Permission denied.', 'open-activity-logger' ), 403 );
		}

		check_admin_referer( 'oal_export' );
		$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'csv';
		$this->exporter->download( $format, $this->filters_from_request( $_GET ) );
	}

	/**
	 * Renders a view.
	 *
	 * @param string $view View name.
	 * @param array  $data View data.
	 * @return void
	 */
	private function render( $view, array $data = array() ) {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'Permission denied.', 'open-activity-logger' ), 403 );
		}

		$view = sanitize_key( $view );
		$path = OAL_PLUGIN_DIR . 'admin/views/' . $view . '.php';

		if ( ! file_exists( $path ) ) {
			return;
		}

		$data = $data;
		include $path;
	}

	/**
	 * Sanitizes filters.
	 *
	 * @param array $source Request source.
	 * @return array
	 */
	private function filters_from_request( array $source ) {
		$filters = array();
		$map = array(
			'page'       => 'absint',
			'per_page'   => 'absint',
			'event_type' => 'sanitize_text_field',
			'severity'   => 'absint',
			'user_id'    => 'absint',
			'user_role'  => 'sanitize_key',
			'ip_address' => 'sanitize_text_field',
			'date_from'  => 'sanitize_text_field',
			'date_to'    => 'sanitize_text_field',
			'search'     => 'sanitize_text_field',
			'category'   => 'sanitize_key',
		);

		foreach ( $map as $key => $callback ) {
			if ( isset( $source[ $key ] ) && '' !== $source[ $key ] ) {
				$value = wp_unslash( $source[ $key ] );
				if ( is_array( $value ) ) {
					continue;
				}
				$filters[ $key ] = call_user_func( $callback, $value );
			}
		}

		return $filters;
	}

	/**
	 * Formats a UTC log timestamp for admin display.
	 *
	 * @param string $created_at UTC mysql timestamp.
	 * @return string
	 */
	private function format_log_time( $created_at ) {
		$timestamp = strtotime( get_date_from_gmt( $created_at, 'Y-m-d H:i:s' ) );
		$format    = $this->settings->get( 'admin_date_format', 'wordpress' );

		if ( ! $timestamp ) {
			return $created_at;
		}

		if ( 'relative' === $format ) {
			return sprintf(
				/* translators: %s: human time difference. */
				__( '%s ago', 'open-activity-logger' ),
				human_time_diff( $timestamp, current_time( 'timestamp' ) )
			);
		}

		if ( 'wordpress' === $format ) {
			$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		}

		return date_i18n( $format, $timestamp );
	}

	/**
	 * Human readable event labels for the simplified admin table.
	 *
	 * @return array
	 */
	private function event_labels() {
		return array(
			'user.login'                        => __( 'User login', 'open-activity-logger' ),
			'user.logout'                       => __( 'User logout', 'open-activity-logger' ),
			'user.login_failed'                 => __( 'Failed login', 'open-activity-logger' ),
			'user.profile_update'               => __( 'Profile update', 'open-activity-logger' ),
			'user.password_change'              => __( 'Password change', 'open-activity-logger' ),
			'user.role_change'                  => __( 'Role change', 'open-activity-logger' ),
			'user.create'                       => __( 'User created', 'open-activity-logger' ),
			'user.delete'                       => __( 'User deleted', 'open-activity-logger' ),
			'content.create'                    => __( 'Content created', 'open-activity-logger' ),
			'content.update'                    => __( 'Content updated', 'open-activity-logger' ),
			'content.delete'                    => __( 'Content deleted', 'open-activity-logger' ),
			'media.upload'                      => __( 'Media uploaded', 'open-activity-logger' ),
			'media.delete'                      => __( 'Media deleted', 'open-activity-logger' ),
			'comment.create'                    => __( 'Comment created', 'open-activity-logger' ),
			'comment.status_change'             => __( 'Comment status', 'open-activity-logger' ),
			'comment.delete'                    => __( 'Comment deleted', 'open-activity-logger' ),
			'system.plugin_activate'            => __( 'Plugin activated', 'open-activity-logger' ),
			'system.plugin_deactivate'          => __( 'Plugin deactivated', 'open-activity-logger' ),
			'system.plugin_delete'              => __( 'Plugin deleted', 'open-activity-logger' ),
			'system.plugin_install'             => __( 'Plugin installed', 'open-activity-logger' ),
			'system.plugin_update'              => __( 'Plugin updated', 'open-activity-logger' ),
			'system.theme_install'              => __( 'Theme installed', 'open-activity-logger' ),
			'system.theme_update'               => __( 'Theme updated', 'open-activity-logger' ),
			'system.theme_switch'               => __( 'Theme switched', 'open-activity-logger' ),
			'system.option_update'              => __( 'Setting changed', 'open-activity-logger' ),
			'woocommerce.order_create'          => __( 'Order created', 'open-activity-logger' ),
			'woocommerce.order_update'          => __( 'Order updated', 'open-activity-logger' ),
			'woocommerce.order_status_change'   => __( 'Order status', 'open-activity-logger' ),
			'woocommerce.product_create'        => __( 'Product created', 'open-activity-logger' ),
			'woocommerce.product_update'        => __( 'Product updated', 'open-activity-logger' ),
			'woocommerce.coupon_create'         => __( 'Coupon created', 'open-activity-logger' ),
			'woocommerce.coupon_update'         => __( 'Coupon updated', 'open-activity-logger' ),
		);
	}
}
