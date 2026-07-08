<?php
/**
 * Admin UI.
 *
 * @package TraceVaultAuditLog
 */

namespace TraceVaultAuditLog;

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
		add_action( 'wp_ajax_tracevault_logs', array( $this, 'ajax_logs' ) );
		add_action( 'wp_ajax_tracevault_stats', array( $this, 'ajax_stats' ) );
		add_action( 'wp_ajax_tracevault_delete_log', array( $this, 'ajax_delete_log' ) );
		add_action( 'wp_ajax_tracevault_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'admin_post_tracevault_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_tracevault_export', array( $this, 'download_export' ) );
	}

	/**
	 * Capability check.
	 *
	 * @return bool
	 */
	private function can_manage() {
		return current_user_can( 'manage_options' ) || current_user_can( 'tracevault_manage_logs' );
	}

	/**
	 * Export capability check.
	 *
	 * @return bool
	 */
	private function can_export() {
		return current_user_can( 'manage_options' ) || current_user_can( 'tracevault_export_logs' );
	}

	/**
	 * Adds menu pages.
	 *
	 * @return void
	 */
	public function menu() {
		$capability = current_user_can( 'tracevault_manage_logs' ) ? 'tracevault_manage_logs' : 'manage_options';

		add_menu_page(
			__( 'TraceVault Audit Log', 'tracevault-audit-log' ),
			__( 'Activity Logs', 'tracevault-audit-log' ),
			$capability,
			'tracevault-audit-log',
			array( $this, 'overview_page' ),
			'dashicons-shield-alt',
			58
		);

		add_submenu_page( 'tracevault-audit-log', __( 'Activity Logs', 'tracevault-audit-log' ), __( 'Activity Logs', 'tracevault-audit-log' ), $capability, 'tracevault-audit-log', array( $this, 'overview_page' ) );
		add_submenu_page( 'tracevault-audit-log', __( 'Settings', 'tracevault-audit-log' ), __( 'Settings', 'tracevault-audit-log' ), $capability, 'tracevault-settings', array( $this, 'settings_page' ) );
	}

	/**
	 * Enqueues assets.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function assets( $hook ) {
		if ( false === strpos( $hook, 'tracevault-audit-log' ) && false === strpos( $hook, 'tracevault-' ) ) {
			return;
		}

		wp_enqueue_style( 'tracevault-admin', TRACEVAULT_PLUGIN_URL . 'assets/css/admin.css', array(), TRACEVAULT_VERSION );
		wp_enqueue_script( 'tracevault-admin', TRACEVAULT_PLUGIN_URL . 'assets/js/admin.js', array(), TRACEVAULT_VERSION, true );
		wp_localize_script(
			'tracevault-admin',
			'tracevaultAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'tracevault_admin' ),
				'i18n'    => array(
					'loading' => __( 'Loading activity...', 'tracevault-audit-log' ),
					'empty'   => __( 'No activity found.', 'tracevault-audit-log' ),
					'error'   => __( 'Unable to load activity.', 'tracevault-audit-log' ),
					'confirmDelete' => __( 'Delete this log entry?', 'tracevault-audit-log' ),
					'confirmClear'  => __( 'Delete all activity logs? This cannot be undone.', 'tracevault-audit-log' ),
					'delete'        => __( 'Delete', 'tracevault-audit-log' ),
					'events'        => $this->event_labels(),
					'severity' => array(
						1 => __( 'Info', 'tracevault-audit-log' ),
						2 => __( 'Notice', 'tracevault-audit-log' ),
						3 => __( 'Warning', 'tracevault-audit-log' ),
						4 => __( 'Critical', 'tracevault-audit-log' ),
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
		$this->render(
			'settings',
			array(
				'settings' => $this->settings->all(),
				'updated'  => (bool) filter_input( INPUT_GET, 'updated', FILTER_VALIDATE_BOOLEAN ),
			)
		);
	}

	/**
	 * AJAX log query.
	 *
	 * @return void
	 */
	public function ajax_logs() {
		check_ajax_referer( 'tracevault_admin', 'nonce' );

		if ( ! $this->can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'tracevault-audit-log' ) ), 403 );
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
		check_ajax_referer( 'tracevault_admin', 'nonce' );

		if ( ! $this->can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'tracevault-audit-log' ) ), 403 );
		}

		wp_send_json_success( $this->db->get_stats( array( 'days' => isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30, 'exclude_verbose' => 1 ) ) );
	}

	/**
	 * Deletes one log entry over AJAX.
	 *
	 * @return void
	 */
	public function ajax_delete_log() {
		check_ajax_referer( 'tracevault_admin', 'nonce' );

		if ( ! $this->can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'tracevault-audit-log' ) ), 403 );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id || ! $this->db->delete_log( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Log could not be deleted.', 'tracevault-audit-log' ) ), 400 );
		}

		wp_send_json_success();
	}

	/**
	 * Clears all log entries over AJAX.
	 *
	 * @return void
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'tracevault_admin', 'nonce' );

		if ( ! $this->can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'tracevault-audit-log' ) ), 403 );
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
			wp_die( esc_html__( 'Permission denied.', 'tracevault-audit-log' ), 403 );
		}

		check_admin_referer( 'tracevault_save_settings' );
		$this->settings->save_from_request( wp_unslash( $_POST ) );

		wp_safe_redirect( add_query_arg( array( 'page' => 'tracevault-settings', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Downloads export.
	 *
	 * @return void
	 */
	public function download_export() {
		if ( ! $this->can_export() ) {
			wp_die( esc_html__( 'Permission denied.', 'tracevault-audit-log' ), 403 );
		}

		check_admin_referer( 'tracevault_export' );
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
			wp_die( esc_html__( 'Permission denied.', 'tracevault-audit-log' ), 403 );
		}

		$view = sanitize_key( $view );
		$path = TRACEVAULT_PLUGIN_DIR . 'admin/views/' . $view . '.php';

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
				__( '%s ago', 'tracevault-audit-log' ),
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
			'user.login'                        => __( 'User login', 'tracevault-audit-log' ),
			'user.logout'                       => __( 'User logout', 'tracevault-audit-log' ),
			'user.login_failed'                 => __( 'Failed login', 'tracevault-audit-log' ),
			'user.profile_update'               => __( 'Profile update', 'tracevault-audit-log' ),
			'user.password_change'              => __( 'Password change', 'tracevault-audit-log' ),
			'user.role_change'                  => __( 'Role change', 'tracevault-audit-log' ),
			'user.create'                       => __( 'User created', 'tracevault-audit-log' ),
			'user.delete'                       => __( 'User deleted', 'tracevault-audit-log' ),
			'content.create'                    => __( 'Content created', 'tracevault-audit-log' ),
			'content.update'                    => __( 'Content updated', 'tracevault-audit-log' ),
			'content.delete'                    => __( 'Content deleted', 'tracevault-audit-log' ),
			'media.upload'                      => __( 'Media uploaded', 'tracevault-audit-log' ),
			'media.delete'                      => __( 'Media deleted', 'tracevault-audit-log' ),
			'comment.create'                    => __( 'Comment created', 'tracevault-audit-log' ),
			'comment.status_change'             => __( 'Comment status', 'tracevault-audit-log' ),
			'comment.delete'                    => __( 'Comment deleted', 'tracevault-audit-log' ),
			'system.plugin_activate'            => __( 'Plugin activated', 'tracevault-audit-log' ),
			'system.plugin_deactivate'          => __( 'Plugin deactivated', 'tracevault-audit-log' ),
			'system.plugin_delete'              => __( 'Plugin deleted', 'tracevault-audit-log' ),
			'system.plugin_install'             => __( 'Plugin installed', 'tracevault-audit-log' ),
			'system.plugin_update'              => __( 'Plugin updated', 'tracevault-audit-log' ),
			'system.theme_install'              => __( 'Theme installed', 'tracevault-audit-log' ),
			'system.theme_update'               => __( 'Theme updated', 'tracevault-audit-log' ),
			'system.theme_switch'               => __( 'Theme switched', 'tracevault-audit-log' ),
			'system.option_update'              => __( 'Setting changed', 'tracevault-audit-log' ),
			'woocommerce.order_create'          => __( 'Order created', 'tracevault-audit-log' ),
			'woocommerce.order_update'          => __( 'Order updated', 'tracevault-audit-log' ),
			'woocommerce.order_status_change'   => __( 'Order status', 'tracevault-audit-log' ),
			'woocommerce.product_create'        => __( 'Product created', 'tracevault-audit-log' ),
			'woocommerce.product_update'        => __( 'Product updated', 'tracevault-audit-log' ),
			'woocommerce.coupon_create'         => __( 'Coupon created', 'tracevault-audit-log' ),
			'woocommerce.coupon_update'         => __( 'Coupon updated', 'tracevault-audit-log' ),
		);
	}
}
