# Open Activity Logger

Open Activity Logger is a production-focused WordPress activity logging plugin for WordPress 5.0+ and PHP 7.4+. It records security, content, system, and optional WooCommerce events into optimized custom tables and exposes them through a native admin dashboard, AJAX tables, REST API endpoints, and CSV/JSON exports.

**Author:** Naowas Morshed Eimon  
**Author URL:** <https://naowas.github.io>  
**License:** GPLv2 or later  
**Text domain:** `open-activity-logger`

## Highlights

- Custom tables only: `wp_oal_logs`, `wp_oal_meta`, and `wp_oal_settings`
- Batched writes flushed on shutdown
- Indexed filters for event type, severity, user, IP, object, and date
- Multisite activation support
- WooCommerce integration when WooCommerce is active
- Simplified admin UX: one Activity Logs screen plus Settings
- Admin date display setting: WordPress format, relative time, or fixed formats
- REST API: `GET /logs`, `GET /logs/{id}`, `GET /stats`, `POST /export`
- GDPR-ready retention, IP anonymization, personal data export, and erasure hooks
- CSV and JSON filtered exports
- Extensible hooks: `oal_log_created`, `oal_log_deleted`, `oal_allowed_events`, `oal_log_data_before_insert`

## Logged Events

Users: login, logout, failed login attempts, profile updates, password changes, role changes, user creation, and deletion.

Content: post/page creation, updates, deletion, media upload/deletion, comment creation, status changes, and deletion.

System: plugin activation/deactivation/deletion, plugin/theme install or update completion, and theme switch. Verbose option-update logging is available in settings but disabled by default, and cache/transient-style option updates are ignored.

WooCommerce: order create/update/status change, product create/update, and coupon create/update.

## Installation

1. Upload the `open-activity-logger` folder to `wp-content/plugins/`.
2. Activate **Open Activity Logger** in WordPress.
3. Visit **Activity Logger** in wp-admin.
4. Adjust retention, privacy, and uninstall preferences in **Activity Logger > Settings**.

## Example Usage

Create a custom log:

```php
oal_log_event(
    'custom.invoice_paid',
    array(
        'severity'    => 2,
        'object_type' => 'invoice',
        'object_id'   => 123,
        'message'     => 'Invoice #123 was paid.',
        'meta'        => array(
            'gateway' => 'stripe',
        ),
    )
);
```

Restrict allowed event types:

```php
add_filter(
    'oal_allowed_events',
    function ( $events ) {
        $events[] = 'custom.invoice_paid';
        return $events;
    }
);
```

Modify log data before storage:

```php
add_filter(
    'oal_log_data_before_insert',
    function ( $data, $event_type ) {
        if ( 'custom.invoice_paid' === $event_type ) {
            $data['severity'] = 2;
        }

        return $data;
    },
    10,
    2
);
```

Listen for new logs:

```php
add_action(
    'oal_log_created',
    function ( $log_id, $log ) {
        error_log( 'Audit log created: ' . $log_id );
    },
    10,
    2
);
```

## REST API

Base namespace: `/wp-json/open-activity-logger/v1`

- `GET /logs`: supports `page`, `per_page`, `event_type`, `severity`, `user_id`, `user_role`, `ip_address`, `date_from`, `date_to`, and `search`.
- `GET /logs/{id}`: returns a single log entry.
- `GET /stats`: accepts `days`.
- `POST /export`: accepts `format` (`csv` or `json`) plus filters.

Requests require an authenticated user with `manage_options`, `oal_manage_logs`, or `oal_export_logs` for export.

## Data Retention and Privacy

The plugin schedules a daily cleanup job using WP-Cron. Retention defaults to 90 days and can be changed in settings. IP anonymization applies before storage. WordPress personal data exporter and eraser callbacks are registered for audit-log personal data.

## Development Notes

The plugin avoids Composer and uses a small PSR-4-like autoloader from the bootstrap file. All database reads use prepared values, writes use WordPress database APIs or prepared multi-row inserts, and admin actions use capabilities plus nonces.
