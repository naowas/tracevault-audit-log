<?php
/**
 * Overview page.
 *
 * @package TraceVaultAuditLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tracevault_stats = isset( $data['stats'] ) ? $data['stats'] : array();
?>
<div class="wrap tracevault-wrap">
	<h1><?php esc_html_e( 'Activity Logs', 'tracevault-audit-log' ); ?></h1>
	<p class="tracevault-subtitle"><?php esc_html_e( 'Recent important activity from users, content, plugins, themes, and WooCommerce.', 'tracevault-audit-log' ); ?></p>

	<div class="tracevault-cards tracevault-cards-compact">
		<div class="tracevault-card">
			<span class="tracevault-card-label"><?php esc_html_e( 'Last 30 days', 'tracevault-audit-log' ); ?></span>
			<strong><?php echo esc_html( isset( $tracevault_stats['total'] ) ? number_format_i18n( $tracevault_stats['total'] ) : 0 ); ?></strong>
		</div>
		<div class="tracevault-card">
			<span class="tracevault-card-label"><?php esc_html_e( 'Retention', 'tracevault-audit-log' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( (int) tracevault()->settings()->get( 'retention_days', 90 ) ) ); ?> <?php esc_html_e( 'days', 'tracevault-audit-log' ); ?></strong>
		</div>
		<div class="tracevault-card">
			<span class="tracevault-card-label"><?php esc_html_e( 'Privacy', 'tracevault-audit-log' ); ?></span>
			<strong><?php echo (int) tracevault()->settings()->get( 'anonymize_ip', 0 ) ? esc_html__( 'IP anonymization on', 'tracevault-audit-log' ) : esc_html__( 'Full IP logging', 'tracevault-audit-log' ); ?></strong>
		</div>
	</div>

	<section class="tracevault-panel">
		<div class="tracevault-panel-header">
			<h2><?php esc_html_e( 'Logs', 'tracevault-audit-log' ); ?></h2>
			<div class="tracevault-actions">
				<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'tracevault_export', 'format' => 'csv' ), admin_url( 'admin-post.php' ) ), 'tracevault_export' ) ); ?>"><?php esc_html_e( 'Export CSV', 'tracevault-audit-log' ); ?></a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'tracevault_export', 'format' => 'json' ), admin_url( 'admin-post.php' ) ), 'tracevault_export' ) ); ?>"><?php esc_html_e( 'Export JSON', 'tracevault-audit-log' ); ?></a>
				<button type="button" class="button button-link-delete" data-tracevault-clear><?php esc_html_e( 'Clear logs', 'tracevault-audit-log' ); ?></button>
			</div>
		</div>
		<form class="tracevault-filters tracevault-filters-simple" data-tracevault-filters>
			<select name="category">
				<option value=""><?php esc_html_e( 'All activity', 'tracevault-audit-log' ); ?></option>
				<option value="user"><?php esc_html_e( 'Users', 'tracevault-audit-log' ); ?></option>
				<option value="content"><?php esc_html_e( 'Content', 'tracevault-audit-log' ); ?></option>
				<option value="media"><?php esc_html_e( 'Media', 'tracevault-audit-log' ); ?></option>
				<option value="comment"><?php esc_html_e( 'Comments', 'tracevault-audit-log' ); ?></option>
				<option value="system"><?php esc_html_e( 'Plugins & themes', 'tracevault-audit-log' ); ?></option>
				<option value="settings"><?php esc_html_e( 'Settings changes', 'tracevault-audit-log' ); ?></option>
				<option value="woocommerce"><?php esc_html_e( 'WooCommerce', 'tracevault-audit-log' ); ?></option>
			</select>
			<select name="severity">
				<option value=""><?php esc_html_e( 'Any severity', 'tracevault-audit-log' ); ?></option>
				<option value="1"><?php esc_html_e( 'Info', 'tracevault-audit-log' ); ?></option>
				<option value="2"><?php esc_html_e( 'Notice', 'tracevault-audit-log' ); ?></option>
				<option value="3"><?php esc_html_e( 'Warning', 'tracevault-audit-log' ); ?></option>
				<option value="4"><?php esc_html_e( 'Critical', 'tracevault-audit-log' ); ?></option>
			</select>
			<input type="search" name="search" placeholder="<?php esc_attr_e( 'Search logs...', 'tracevault-audit-log' ); ?>">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'tracevault-audit-log' ); ?></button>
			<button type="button" class="button" data-tracevault-reset><?php esc_html_e( 'Reset', 'tracevault-audit-log' ); ?></button>
		</form>
		<?php include TRACEVAULT_PLUGIN_DIR . 'admin/views/partials-table.php'; ?>
	</section>
</div>
