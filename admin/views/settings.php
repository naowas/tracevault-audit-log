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
<div class="wrap oal-wrap">
	<h1><?php esc_html_e( 'Settings', 'open-activity-logger' ); ?></h1>
	<?php if ( ! empty( $data['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'open-activity-logger' ); ?></p></div>
	<?php endif; ?>
	<section class="oal-panel">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="oal_save_settings">
			<?php wp_nonce_field( 'oal_save_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="oal-retention"><?php esc_html_e( 'Log retention', 'open-activity-logger' ); ?></label></th>
					<td><input id="oal-retention" type="number" name="retention_days" min="1" max="3650" value="<?php echo esc_attr( (int) ( $settings['retention_days'] ?? 90 ) ); ?>"> <?php esc_html_e( 'days', 'open-activity-logger' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="oal-date-format"><?php esc_html_e( 'Date display', 'open-activity-logger' ); ?></label></th>
					<td>
						<select id="oal-date-format" name="admin_date_format">
							<option value="wordpress" <?php selected( $settings['admin_date_format'] ?? 'wordpress', 'wordpress' ); ?>><?php esc_html_e( 'Use WordPress date/time format', 'open-activity-logger' ); ?></option>
							<option value="relative" <?php selected( $settings['admin_date_format'] ?? 'wordpress', 'relative' ); ?>><?php esc_html_e( 'Relative time, such as 5 minutes ago', 'open-activity-logger' ); ?></option>
							<option value="Y-m-d H:i:s" <?php selected( $settings['admin_date_format'] ?? 'wordpress', 'Y-m-d H:i:s' ); ?>><?php echo esc_html( gmdate( 'Y-m-d H:i:s' ) ); ?></option>
							<option value="M j, Y g:i a" <?php selected( $settings['admin_date_format'] ?? 'wordpress', 'M j, Y g:i a' ); ?>><?php echo esc_html( gmdate( 'M j, Y g:i a' ) ); ?></option>
							<option value="d M Y H:i" <?php selected( $settings['admin_date_format'] ?? 'wordpress', 'd M Y H:i' ); ?>><?php echo esc_html( gmdate( 'd M Y H:i' ) ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Privacy', 'open-activity-logger' ); ?></th>
					<td><label><input type="checkbox" name="anonymize_ip" value="1" <?php checked( ! empty( $settings['anonymize_ip'] ) ); ?>> <?php esc_html_e( 'Anonymize IP addresses before storage', 'open-activity-logger' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'System Events', 'open-activity-logger' ); ?></th>
					<td>
						<label><input type="checkbox" name="capture_option_updates" value="1" <?php checked( ! empty( $settings['capture_option_updates'] ) ); ?>> <?php esc_html_e( 'Verbose mode: log option and settings changes', 'open-activity-logger' ); ?></label>
						<p class="description"><?php esc_html_e( 'Keep this off unless you are debugging. Cache, transient, and noisy builder options are still ignored.', 'open-activity-logger' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Uninstall', 'open-activity-logger' ); ?></th>
					<td><label><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?>> <?php esc_html_e( 'Delete plugin tables when the plugin is uninstalled', 'open-activity-logger' ); ?></label></td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Settings', 'open-activity-logger' ) ); ?>
		</form>
	</section>
</div>
