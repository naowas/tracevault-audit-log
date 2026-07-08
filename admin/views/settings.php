<?php
/**
 * Settings page.
 *
 * @package TraceVaultAuditLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tracevault_settings = isset( $data['settings'] ) ? $data['settings'] : array();
?>
<div class="wrap tracevault-wrap tracevault-settings-wrap">
	<div class="tracevault-page-heading">
		<div>
			<h1><?php esc_html_e( 'Settings', 'tracevault-audit-log' ); ?></h1>
			<p class="tracevault-subtitle"><?php esc_html_e( 'Control retention, privacy, display, and cleanup behavior for activity logs.', 'tracevault-audit-log' ); ?></p>
		</div>
	</div>

	<?php if ( ! empty( $data['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible tracevault-notice"><p><?php esc_html_e( 'Settings saved.', 'tracevault-audit-log' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tracevault-settings-form">
		<input type="hidden" name="action" value="tracevault_save_settings">
		<?php wp_nonce_field( 'tracevault_save_settings' ); ?>

		<section class="tracevault-panel tracevault-settings-panel">
			<div class="tracevault-panel-header">
				<div>
					<h2><?php esc_html_e( 'General', 'tracevault-audit-log' ); ?></h2>
					<p><?php esc_html_e( 'Choose how long logs stay available and how dates appear in the admin table.', 'tracevault-audit-log' ); ?></p>
				</div>
			</div>

			<div class="tracevault-settings-list">
				<div class="tracevault-setting-row">
					<div class="tracevault-setting-copy">
						<label for="tracevault-retention"><?php esc_html_e( 'Log retention', 'tracevault-audit-log' ); ?></label>
						<p><?php esc_html_e( 'Automatically remove old logs during the daily cleanup task.', 'tracevault-audit-log' ); ?></p>
					</div>
					<div class="tracevault-setting-control tracevault-inline-control">
						<input id="tracevault-retention" type="number" name="retention_days" min="1" max="3650" value="<?php echo esc_attr( (int) ( $tracevault_settings['retention_days'] ?? 90 ) ); ?>">
						<span><?php esc_html_e( 'days', 'tracevault-audit-log' ); ?></span>
					</div>
				</div>

				<div class="tracevault-setting-row">
					<div class="tracevault-setting-copy">
						<label for="tracevault-date-format"><?php esc_html_e( 'Date display', 'tracevault-audit-log' ); ?></label>
						<p><?php esc_html_e( 'Select the timestamp format shown in the Activity Logs table.', 'tracevault-audit-log' ); ?></p>
					</div>
					<div class="tracevault-setting-control">
						<select id="tracevault-date-format" name="admin_date_format">
							<option value="wordpress" <?php selected( $tracevault_settings['admin_date_format'] ?? 'wordpress', 'wordpress' ); ?>><?php esc_html_e( 'Use WordPress date/time format', 'tracevault-audit-log' ); ?></option>
							<option value="relative" <?php selected( $tracevault_settings['admin_date_format'] ?? 'wordpress', 'relative' ); ?>><?php esc_html_e( 'Relative time, such as 5 minutes ago', 'tracevault-audit-log' ); ?></option>
							<option value="Y-m-d H:i:s" <?php selected( $tracevault_settings['admin_date_format'] ?? 'wordpress', 'Y-m-d H:i:s' ); ?>><?php echo esc_html( gmdate( 'Y-m-d H:i:s' ) ); ?></option>
							<option value="M j, Y g:i a" <?php selected( $tracevault_settings['admin_date_format'] ?? 'wordpress', 'M j, Y g:i a' ); ?>><?php echo esc_html( gmdate( 'M j, Y g:i a' ) ); ?></option>
							<option value="d M Y H:i" <?php selected( $tracevault_settings['admin_date_format'] ?? 'wordpress', 'd M Y H:i' ); ?>><?php echo esc_html( gmdate( 'd M Y H:i' ) ); ?></option>
						</select>
					</div>
				</div>
			</div>
		</section>

		<section class="tracevault-panel tracevault-settings-panel">
			<div class="tracevault-panel-header">
				<div>
					<h2><?php esc_html_e( 'Privacy & Noise Control', 'tracevault-audit-log' ); ?></h2>
					<p><?php esc_html_e( 'Keep useful audit data while avoiding unnecessary personal or noisy system data.', 'tracevault-audit-log' ); ?></p>
				</div>
			</div>

			<div class="tracevault-settings-list">
				<div class="tracevault-setting-row">
					<div class="tracevault-setting-copy">
						<label for="tracevault-anonymize-ip"><?php esc_html_e( 'IP anonymization', 'tracevault-audit-log' ); ?></label>
						<p><?php esc_html_e( 'Store masked IP addresses instead of full visitor IP addresses.', 'tracevault-audit-log' ); ?></p>
					</div>
					<div class="tracevault-setting-control">
						<label class="tracevault-switch">
							<input id="tracevault-anonymize-ip" type="checkbox" name="anonymize_ip" value="1" <?php checked( ! empty( $tracevault_settings['anonymize_ip'] ) ); ?>>
							<span><?php esc_html_e( 'Enabled', 'tracevault-audit-log' ); ?></span>
						</label>
					</div>
				</div>

				<div class="tracevault-setting-row">
					<div class="tracevault-setting-copy">
						<label for="tracevault-capture-options"><?php esc_html_e( 'Verbose option logs', 'tracevault-audit-log' ); ?></label>
						<p><?php esc_html_e( 'Also log extra plugin option changes beyond WordPress and WooCommerce settings. Cache, transient, and noisy builder options are still ignored.', 'tracevault-audit-log' ); ?></p>
					</div>
					<div class="tracevault-setting-control">
						<label class="tracevault-switch">
							<input id="tracevault-capture-options" type="checkbox" name="capture_option_updates" value="1" <?php checked( ! empty( $tracevault_settings['capture_option_updates'] ) ); ?>>
							<span><?php esc_html_e( 'Enabled', 'tracevault-audit-log' ); ?></span>
						</label>
					</div>
				</div>
			</div>
		</section>

		<section class="tracevault-panel tracevault-settings-panel">
			<div class="tracevault-panel-header">
				<div>
					<h2><?php esc_html_e( 'Uninstall', 'tracevault-audit-log' ); ?></h2>
					<p><?php esc_html_e( 'Choose whether audit data should remain available after plugin removal.', 'tracevault-audit-log' ); ?></p>
				</div>
			</div>

			<div class="tracevault-settings-list">
				<div class="tracevault-setting-row tracevault-setting-row-danger">
					<div class="tracevault-setting-copy">
						<label for="tracevault-delete-data"><?php esc_html_e( 'Delete data on uninstall', 'tracevault-audit-log' ); ?></label>
						<p><?php esc_html_e( 'Remove plugin tables and logs when the plugin is uninstalled.', 'tracevault-audit-log' ); ?></p>
					</div>
					<div class="tracevault-setting-control">
						<label class="tracevault-switch">
							<input id="tracevault-delete-data" type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( ! empty( $tracevault_settings['delete_data_on_uninstall'] ) ); ?>>
							<span><?php esc_html_e( 'Delete', 'tracevault-audit-log' ); ?></span>
						</label>
					</div>
				</div>
			</div>
		</section>

		<div class="tracevault-settings-footer">
			<?php submit_button( __( 'Save Settings', 'tracevault-audit-log' ), 'primary', 'submit', false ); ?>
		</div>
	</form>
</div>
