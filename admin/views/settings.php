<?php
/**
 * Settings page.
 *
 * @package OpenActivityLogger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = isset( $data['settings'] ) ? $data['settings'] : array();
?>
<div class="wrap oal-wrap oal-settings-wrap">
	<div class="oal-page-heading">
		<div>
			<h1><?php esc_html_e( 'Settings', 'open-activity-logger' ); ?></h1>
			<p class="oal-subtitle"><?php esc_html_e( 'Control retention, privacy, display, and cleanup behavior for activity logs.', 'open-activity-logger' ); ?></p>
		</div>
	</div>

	<?php if ( ! empty( $data['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible oal-notice"><p><?php esc_html_e( 'Settings saved.', 'open-activity-logger' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="oal-settings-form">
		<input type="hidden" name="action" value="oal_save_settings">
		<?php wp_nonce_field( 'oal_save_settings' ); ?>

		<section class="oal-panel oal-settings-panel">
			<div class="oal-panel-header">
				<div>
					<h2><?php esc_html_e( 'General', 'open-activity-logger' ); ?></h2>
					<p><?php esc_html_e( 'Choose how long logs stay available and how dates appear in the admin table.', 'open-activity-logger' ); ?></p>
				</div>
			</div>

			<div class="oal-settings-list">
				<div class="oal-setting-row">
					<div class="oal-setting-copy">
						<label for="oal-retention"><?php esc_html_e( 'Log retention', 'open-activity-logger' ); ?></label>
						<p><?php esc_html_e( 'Automatically remove old logs during the daily cleanup task.', 'open-activity-logger' ); ?></p>
					</div>
					<div class="oal-setting-control oal-inline-control">
						<input id="oal-retention" type="number" name="retention_days" min="1" max="3650" value="<?php echo esc_attr( (int) ( $settings['retention_days'] ?? 90 ) ); ?>">
						<span><?php esc_html_e( 'days', 'open-activity-logger' ); ?></span>
					</div>
				</div>

				<div class="oal-setting-row">
					<div class="oal-setting-copy">
						<label for="oal-date-format"><?php esc_html_e( 'Date display', 'open-activity-logger' ); ?></label>
						<p><?php esc_html_e( 'Select the timestamp format shown in the Activity Logs table.', 'open-activity-logger' ); ?></p>
					</div>
					<div class="oal-setting-control">
						<select id="oal-date-format" name="admin_date_format">
							<option value="wordpress" <?php selected( $settings['admin_date_format'] ?? 'wordpress', 'wordpress' ); ?>><?php esc_html_e( 'Use WordPress date/time format', 'open-activity-logger' ); ?></option>
							<option value="relative" <?php selected( $settings['admin_date_format'] ?? 'wordpress', 'relative' ); ?>><?php esc_html_e( 'Relative time, such as 5 minutes ago', 'open-activity-logger' ); ?></option>
							<option value="Y-m-d H:i:s" <?php selected( $settings['admin_date_format'] ?? 'wordpress', 'Y-m-d H:i:s' ); ?>><?php echo esc_html( gmdate( 'Y-m-d H:i:s' ) ); ?></option>
							<option value="M j, Y g:i a" <?php selected( $settings['admin_date_format'] ?? 'wordpress', 'M j, Y g:i a' ); ?>><?php echo esc_html( gmdate( 'M j, Y g:i a' ) ); ?></option>
							<option value="d M Y H:i" <?php selected( $settings['admin_date_format'] ?? 'wordpress', 'd M Y H:i' ); ?>><?php echo esc_html( gmdate( 'd M Y H:i' ) ); ?></option>
						</select>
					</div>
				</div>
			</div>
		</section>

		<section class="oal-panel oal-settings-panel">
			<div class="oal-panel-header">
				<div>
					<h2><?php esc_html_e( 'Privacy & Noise Control', 'open-activity-logger' ); ?></h2>
					<p><?php esc_html_e( 'Keep useful audit data while avoiding unnecessary personal or noisy system data.', 'open-activity-logger' ); ?></p>
				</div>
			</div>

			<div class="oal-settings-list">
				<div class="oal-setting-row">
					<div class="oal-setting-copy">
						<label for="oal-anonymize-ip"><?php esc_html_e( 'IP anonymization', 'open-activity-logger' ); ?></label>
						<p><?php esc_html_e( 'Store masked IP addresses instead of full visitor IP addresses.', 'open-activity-logger' ); ?></p>
					</div>
					<div class="oal-setting-control">
						<label class="oal-switch">
							<input id="oal-anonymize-ip" type="checkbox" name="anonymize_ip" value="1" <?php checked( ! empty( $settings['anonymize_ip'] ) ); ?>>
							<span><?php esc_html_e( 'Enabled', 'open-activity-logger' ); ?></span>
						</label>
					</div>
				</div>

				<div class="oal-setting-row">
					<div class="oal-setting-copy">
						<label for="oal-capture-options"><?php esc_html_e( 'Verbose system logs', 'open-activity-logger' ); ?></label>
						<p><?php esc_html_e( 'Log option and settings changes. Cache, transient, and noisy builder options are still ignored.', 'open-activity-logger' ); ?></p>
					</div>
					<div class="oal-setting-control">
						<label class="oal-switch">
							<input id="oal-capture-options" type="checkbox" name="capture_option_updates" value="1" <?php checked( ! empty( $settings['capture_option_updates'] ) ); ?>>
							<span><?php esc_html_e( 'Enabled', 'open-activity-logger' ); ?></span>
						</label>
					</div>
				</div>
			</div>
		</section>

		<section class="oal-panel oal-settings-panel">
			<div class="oal-panel-header">
				<div>
					<h2><?php esc_html_e( 'Uninstall', 'open-activity-logger' ); ?></h2>
					<p><?php esc_html_e( 'Choose whether audit data should remain available after plugin removal.', 'open-activity-logger' ); ?></p>
				</div>
			</div>

			<div class="oal-settings-list">
				<div class="oal-setting-row oal-setting-row-danger">
					<div class="oal-setting-copy">
						<label for="oal-delete-data"><?php esc_html_e( 'Delete data on uninstall', 'open-activity-logger' ); ?></label>
						<p><?php esc_html_e( 'Remove plugin tables and logs when the plugin is uninstalled.', 'open-activity-logger' ); ?></p>
					</div>
					<div class="oal-setting-control">
						<label class="oal-switch">
							<input id="oal-delete-data" type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?>>
							<span><?php esc_html_e( 'Delete', 'open-activity-logger' ); ?></span>
						</label>
					</div>
				</div>
			</div>
		</section>

		<div class="oal-settings-footer">
			<?php submit_button( __( 'Save Settings', 'open-activity-logger' ), 'primary', 'submit', false ); ?>
		</div>
	</form>
</div>
