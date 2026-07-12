=== TraceVault Audit Log ===
Contributors: naowas
Tags: activity log, audit log, security, monitoring, woocommerce
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Security-focused activity logging, audit reporting, retention controls, privacy tools, and filtered exports for WordPress.

== Description ==

TraceVault Audit Log gives administrators a clear, searchable audit trail for important WordPress activity. It records user, content, media, comment, plugin, theme, settings, and optional WooCommerce events into optimized custom tables, then makes that activity easy to review from wp-admin.

Use it to investigate account activity, track content changes, review system-level events, export filtered reports, and control how long audit data stays on the site.

= Key Features =

* Custom database tables for audit logs, metadata, and settings.
* Batched log writes to reduce runtime overhead.
* Indexed filters for event type, severity, user, role, IP address, object, date, and keyword search.
* Activity Logs screen with readable event labels, quick filters, CSV/JSON exports, and clear controls.
* Configurable admin date display, including WordPress format, relative time, and fixed timestamp formats.
* REST API endpoints for logs, stats, and exports.
* Retention controls, IP anonymization, personal data export, and personal data erasure support.
* Multisite activation support.
* Developer hooks and filters for custom event types.
* Quiet defaults: verbose option-update logging is disabled unless enabled, and noisy cache-style options are ignored.

= Logged Activity =

TraceVault Audit Log can record:

* Users: login, logout, failed login attempts, profile updates, password changes, role changes, user creation, and deletion.
* Content: post and page creation, updates, deletion, media upload/deletion, comment creation, comment status changes, and comment deletion.
* System: plugin activation/deactivation/deletion, plugin and theme install/update completion, theme switch, and selected settings changes.
* WooCommerce: order create/update/status change, product create/update, and coupon create/update when WooCommerce is active.

= Privacy and Retention =

Audit logs can contain operationally sensitive data. TraceVault Audit Log keeps the controls local to your WordPress install:

* No external logging service is required.
* Retention defaults to 90 days and can be changed from settings.
* IP anonymization can be enabled before storage.
* Personal data exporter and eraser callbacks are registered for WordPress privacy tools.
* Data deletion on uninstall is opt-in so audit history is not removed accidentally.

= Developer Friendly =

Developers can create custom events with `tracevault_log_event()` and extend behavior with filters such as `tracevault_allowed_events` and `tracevault_log_data_before_insert`.

== Installation ==

1. Upload the `tracevault-audit-log` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Open **Activity Logs** in wp-admin.
4. Configure retention, privacy, and date display settings.

== Frequently Asked Questions ==

= Does this plugin use custom tables? =

Yes. It creates `wp_tracevault_logs`, `wp_tracevault_meta`, and `wp_tracevault_settings` for each site.

= Does it send logs to an external service? =

No. Logs are stored in your WordPress database using the site's configured database connection.

= Does it support multisite? =

Yes. Network activation creates tables for each site. Each site keeps its own audit tables using the site table prefix.

= Does it store passwords or sensitive form fields? =

No. Password hooks intentionally ignore password values, and option updates log the option key without serializing old or new option values.

= Can logs be deleted on uninstall? =

Yes, but only if you enable the uninstall deletion setting before uninstalling.

= Can developers add custom audit events? =

Yes. Use `tracevault_log_event()` to write custom events and filters to control allowed event types or modify log data before storage.

== Screenshots ==

1. Activity Logs dashboard with summary cards, filters, exports, and the audit log table.
2. Log details modal with event context, severity, user, IP address, user agent, and metadata.
3. Settings screen for retention, privacy, date display, noisy option logs, and uninstall behavior.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
