=== Open Activity Logger ===
Contributors: naowas
Tags: activity log, audit log, security, monitoring, woocommerce
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure, extensible activity logging and audit reporting for WordPress.

== Description ==

Open Activity Logger records important WordPress activity into optimized custom tables and provides a modern wp-admin dashboard for overview analytics, live activity, advanced search, user reports, filtered exports, settings, and retention controls.

The plugin is designed for transparency, security, and operational control. It logs user, content, comment, media, plugin, theme, option, and optional WooCommerce events.

Core features:

* Custom database tables for audit logs, metadata, and settings.
* Batched log writes for lower runtime overhead.
* Indexed filters for fast search and pagination.
* Simple Activity Logs screen with readable labels, quick filters, CSV/JSON exports, and delete controls.
* Configurable admin date display.
* REST API endpoints for logs, stats, and exports.
* GDPR-ready retention, IP anonymization, data export, and data erasure support.
* Multisite activation support.
* Developer hooks and filters.
* Quiet defaults: verbose option-update logging is disabled unless you enable it, and noisy cache options are ignored.

== Installation ==

1. Upload the `open-activity-logger` folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins screen.
3. Open **Activity Logger** in wp-admin.
4. Configure retention and privacy settings.

== Frequently Asked Questions ==

= Does this plugin use custom tables? =

Yes. It creates `wp_oal_logs`, `wp_oal_meta`, and `wp_oal_settings` for each site.

= Does it support multisite? =

Yes. Network activation creates tables for each site. Each site keeps its own audit tables using the site table prefix.

= Does it store passwords or sensitive form fields? =

No. Password hooks intentionally ignore password values, and option updates log the option key without serializing old or new option values.

= Can logs be deleted on uninstall? =

Yes, but only if you enable the uninstall deletion setting before uninstalling.

== Screenshots ==

1. Overview analytics dashboard.
2. Live activity feed.
3. Advanced search filters.
4. Export center.
5. Settings screen.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
