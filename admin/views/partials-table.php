<?php
/**
 * Shared AJAX table markup.
 *
 * @package TraceVaultAuditLog
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="tracevault-table-shell">
	<table class="widefat striped tracevault-log-table" data-tracevault-table="logs">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time', 'tracevault-audit-log' ); ?></th>
				<th><?php esc_html_e( 'Activity', 'tracevault-audit-log' ); ?></th>
				<th><?php esc_html_e( 'User', 'tracevault-audit-log' ); ?></th>
				<th><?php esc_html_e( 'IP', 'tracevault-audit-log' ); ?></th>
				<th><?php esc_html_e( 'Details', 'tracevault-audit-log' ); ?></th>
				<th><?php esc_html_e( 'Action', 'tracevault-audit-log' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr><td colspan="6"><?php esc_html_e( 'Loading activity...', 'tracevault-audit-log' ); ?></td></tr>
		</tbody>
	</table>
	<div class="tracevault-pagination">
		<button type="button" class="button" data-tracevault-page="prev"><?php esc_html_e( 'Previous', 'tracevault-audit-log' ); ?></button>
		<span data-tracevault-page-label>1</span>
		<button type="button" class="button" data-tracevault-page="next"><?php esc_html_e( 'Next', 'tracevault-audit-log' ); ?></button>
	</div>
</div>
