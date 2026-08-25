=== Ravnsight Detective – Error & Change Monitoring ===
Contributors: ravnsight
Tags: error log, error monitoring, debugging, change log, activity log
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See your site from the inside: PHP errors grouped and attributed to the plugin that caused them, plus a timeline of every plugin, theme and core change.

== Description ==

When a WordPress site breaks, the question is never just "what is the error?" — it is "**what changed right before it started?**". Ravnsight Detective answers both, locally, on your own site:

* **Error Detective** — catches PHP warnings, deprecations and fatal errors, groups identical errors into one row (a hundred thousand repeats is one line, not a flooded log), and attributes each one to the **plugin, theme or core** that caused it.
* **Change Detective** — records every plugin/theme/core install, update, activation and switch, changes to important options, and takes a daily environment snapshot so even manual FTP changes and host-side PHP upgrades are noticed.
* **Timeline** — errors and changes on one axis. The debut of an error right after an update stops being a mystery.
* **Spike detection** — a dashboard warning when the last 24 hours carry more errors than the rest of the week.

Everything is stored in your own database and pruned automatically. Sensitive data (e-mail addresses, phone numbers, card-like numbers, query-string values, full server paths) is redacted **before** it is stored.

= Privacy =

The free plugin makes **no external requests whatsoever**. Nothing is transmitted anywhere — there is no phone-home, no telemetry, no tracking. All data stays in your database.

== Frequently Asked Questions ==

= Does this plugin send any data anywhere? =

No. The free version performs zero external requests. All recorded data stays in your own database.

= Will it slow down my site? =

The error handler only does work when an error actually occurs, and repeated identical errors are a single database update. Change recording runs on admin actions, not on visitor requests.

= How long is history kept? =

Up to 7 days (configurable). Old rows are pruned daily.

= Can it fix the errors it finds? =

No, by design. Detective observes, groups and attributes — it never changes your site, never auto-updates anything and never "optimises" anything.

== Changelog ==

= 0.1.0 =
* Initial release: Error Detective, Change Detective, timeline, daily snapshots, spike detection.
