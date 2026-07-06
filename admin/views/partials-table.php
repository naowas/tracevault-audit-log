<?php
/**
 * Shared AJAX table markup.
 *
 * @package OpenActivityLogger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="oal-table-shell">
	<table class="widefat striped oal-log-table" data-oal-table="logs">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time', 'open-activity-logger' ); ?></th>
				<th><?php esc_html_e( 'Activity', 'open-activity-logger' ); ?></th>
				<th><?php esc_html_e( 'User', 'open-activity-logger' ); ?></th>
				<th><?php esc_html_e( 'IP', 'open-activity-logger' ); ?></th>
				<th><?php esc_html_e( 'Details', 'open-activity-logger' ); ?></th>
				<th><?php esc_html_e( 'Action', 'open-activity-logger' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr><td colspan="6"><?php esc_html_e( 'Loading activity...', 'open-activity-logger' ); ?></td></tr>
		</tbody>
	</table>
	<div class="oal-pagination">
		<button type="button" class="button" data-oal-page="prev"><?php esc_html_e( 'Previous', 'open-activity-logger' ); ?></button>
		<span data-oal-page-label>1</span>
		<button type="button" class="button" data-oal-page="next"><?php esc_html_e( 'Next', 'open-activity-logger' ); ?></button>
	</div>
</div>
