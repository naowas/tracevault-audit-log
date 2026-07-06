<?php
/**
 * Overview page.
 *
 * @package OpenActivityLogger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$stats = isset( $data['stats'] ) ? $data['stats'] : array();
?>
<div class="wrap oal-wrap">
	<h1><?php esc_html_e( 'Activity Logs', 'open-activity-logger' ); ?></h1>
	<p class="oal-subtitle"><?php esc_html_e( 'Recent important activity from users, content, plugins, themes, and WooCommerce.', 'open-activity-logger' ); ?></p>

	<div class="oal-cards oal-cards-compact">
		<div class="oal-card">
			<span class="oal-card-label"><?php esc_html_e( 'Last 30 days', 'open-activity-logger' ); ?></span>
			<strong><?php echo esc_html( isset( $stats['total'] ) ? number_format_i18n( $stats['total'] ) : 0 ); ?></strong>
		</div>
		<div class="oal-card">
			<span class="oal-card-label"><?php esc_html_e( 'Retention', 'open-activity-logger' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( (int) oal()->settings()->get( 'retention_days', 90 ) ) ); ?> <?php esc_html_e( 'days', 'open-activity-logger' ); ?></strong>
		</div>
		<div class="oal-card">
			<span class="oal-card-label"><?php esc_html_e( 'Privacy', 'open-activity-logger' ); ?></span>
			<strong><?php echo (int) oal()->settings()->get( 'anonymize_ip', 0 ) ? esc_html__( 'IP anonymization on', 'open-activity-logger' ) : esc_html__( 'Full IP logging', 'open-activity-logger' ); ?></strong>
		</div>
	</div>

	<section class="oal-panel">
		<div class="oal-panel-header">
			<h2><?php esc_html_e( 'Logs', 'open-activity-logger' ); ?></h2>
			<div class="oal-actions">
				<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'oal_export', 'format' => 'csv' ), admin_url( 'admin-post.php' ) ), 'oal_export' ) ); ?>"><?php esc_html_e( 'Export CSV', 'open-activity-logger' ); ?></a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'oal_export', 'format' => 'json' ), admin_url( 'admin-post.php' ) ), 'oal_export' ) ); ?>"><?php esc_html_e( 'Export JSON', 'open-activity-logger' ); ?></a>
				<button type="button" class="button button-link-delete" data-oal-clear><?php esc_html_e( 'Clear logs', 'open-activity-logger' ); ?></button>
			</div>
		</div>
		<form class="oal-filters oal-filters-simple" data-oal-filters>
			<select name="category">
				<option value=""><?php esc_html_e( 'All activity', 'open-activity-logger' ); ?></option>
				<option value="user"><?php esc_html_e( 'Users', 'open-activity-logger' ); ?></option>
				<option value="content"><?php esc_html_e( 'Content', 'open-activity-logger' ); ?></option>
				<option value="media"><?php esc_html_e( 'Media', 'open-activity-logger' ); ?></option>
				<option value="comment"><?php esc_html_e( 'Comments', 'open-activity-logger' ); ?></option>
				<option value="system"><?php esc_html_e( 'Plugins & themes', 'open-activity-logger' ); ?></option>
				<option value="settings"><?php esc_html_e( 'Settings changes', 'open-activity-logger' ); ?></option>
				<option value="woocommerce"><?php esc_html_e( 'WooCommerce', 'open-activity-logger' ); ?></option>
			</select>
			<select name="severity">
				<option value=""><?php esc_html_e( 'Any severity', 'open-activity-logger' ); ?></option>
				<option value="1"><?php esc_html_e( 'Info', 'open-activity-logger' ); ?></option>
				<option value="2"><?php esc_html_e( 'Notice', 'open-activity-logger' ); ?></option>
				<option value="3"><?php esc_html_e( 'Warning', 'open-activity-logger' ); ?></option>
				<option value="4"><?php esc_html_e( 'Critical', 'open-activity-logger' ); ?></option>
			</select>
			<input type="search" name="search" placeholder="<?php esc_attr_e( 'Search logs...', 'open-activity-logger' ); ?>">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'open-activity-logger' ); ?></button>
			<button type="button" class="button" data-oal-reset><?php esc_html_e( 'Reset', 'open-activity-logger' ); ?></button>
		</form>
		<?php include OAL_PLUGIN_DIR . 'admin/views/partials-table.php'; ?>
	</section>
</div>
