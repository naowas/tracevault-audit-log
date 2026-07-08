<?php
/**
 * Plugin Name: TraceVault Audit Log
 * Description: Secure, extensible activity logging and audit reporting for WordPress.
 * Version:     1.0.0
 * Author:      Naowas Morshed Eimon
 * Author URI:  https://naowas.github.io
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tracevault-audit-log
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package TraceVaultAuditLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TRACEVAULT_VERSION', '1.0.0' );
define( 'TRACEVAULT_MINIMUM_WP_VERSION', '5.0' );
define( 'TRACEVAULT_MINIMUM_PHP_VERSION', '7.4' );
define( 'TRACEVAULT_PLUGIN_FILE', __FILE__ );
define( 'TRACEVAULT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'TRACEVAULT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TRACEVAULT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'TraceVaultAuditLog\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$basename = strtolower( str_replace( '_', '-', basename( str_replace( '\\', '/', $relative ) ) ) );
		$paths    = array(
			TRACEVAULT_PLUGIN_DIR . 'includes/class-' . $basename . '.php',
			TRACEVAULT_PLUGIN_DIR . 'admin/class-' . $basename . '.php',
		);

		foreach ( $paths as $path ) {
			if ( file_exists( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
);

require_once TRACEVAULT_PLUGIN_DIR . 'includes/helpers.php';

register_activation_hook( __FILE__, array( 'TraceVaultAuditLog\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TraceVaultAuditLog\\Deactivator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		if ( version_compare( PHP_VERSION, TRACEVAULT_MINIMUM_PHP_VERSION, '<' ) || version_compare( get_bloginfo( 'version' ), TRACEVAULT_MINIMUM_WP_VERSION, '<' ) ) {
			add_action(
				'admin_notices',
				static function () {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'TraceVault Audit Log requires WordPress 5.0+ and PHP 7.4+.', 'tracevault-audit-log' )
					);
				}
			);
			return;
		}

		TraceVaultAuditLog\Plugin::instance()->run();
	}
);
