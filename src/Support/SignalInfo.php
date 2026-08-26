<?php
/**
 * Human knowledge per signal type: what it is, why it matters, what to do.
 * The difference between a log viewer and a detective.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Support;

defined( 'ABSPATH' ) || exit;

final class SignalInfo {

	/**
	 * Short human label for a signal type.
	 *
	 * @param string $type Signal type.
	 * @return string
	 */
	public static function label( $type ) {
		switch ( $type ) {
			case 'error.php_fatal':
				return __( 'Fatal error', 'ravnsight-detective' );
			case 'error.php_warning':
				return __( 'PHP warning', 'ravnsight-detective' );
			case 'error.php_deprecated':
				return __( 'Deprecation', 'ravnsight-detective' );
			case 'change.plugin_updated':
				return __( 'Plugin updated', 'ravnsight-detective' );
			case 'change.plugin_activated':
				return __( 'Plugin activated', 'ravnsight-detective' );
			case 'change.plugin_deactivated':
				return __( 'Plugin deactivated', 'ravnsight-detective' );
			case 'change.theme_switched':
				return __( 'Theme switched', 'ravnsight-detective' );
			case 'change.theme_updated':
				return __( 'Theme updated', 'ravnsight-detective' );
			case 'change.core_updated':
				return __( 'WordPress updated', 'ravnsight-detective' );
			case 'change.option_changed':
				return __( 'Setting changed', 'ravnsight-detective' );
			case 'change.php_version':
				return __( 'PHP version changed', 'ravnsight-detective' );
			case 'change.plugin_installed':
				return __( 'New plugin appeared', 'ravnsight-detective' );
			case 'change.plugin_removed':
				return __( 'Plugin removed', 'ravnsight-detective' );
			case 'change.theme_installed':
				return __( 'New theme appeared', 'ravnsight-detective' );
			case 'change.theme_file_modified':
				return __( 'Theme file changed', 'ravnsight-detective' );
			case 'perf.request_slow':
				return __( 'Slow request', 'ravnsight-detective' );
			case 'perf.memory_high':
				return __( 'High memory use', 'ravnsight-detective' );
			case 'error.db_error':
				return __( 'Database error', 'ravnsight-detective' );
			case 'error.js_error':
				return __( 'JavaScript error', 'ravnsight-detective' );
			case 'error.mail_failed':
				return __( 'E-mail failed', 'ravnsight-detective' );
			case 'error.wc_order_failed':
				return __( 'WooCommerce order failed', 'ravnsight-detective' );
			case 'error.cron_stalled':
				return __( 'WP-cron stalled', 'ravnsight-detective' );
			case 'error.as_failed':
				return __( 'Failed background actions', 'ravnsight-detective' );
			case 'error.as_backlog':
				return __( 'Background queue backlog', 'ravnsight-detective' );
			default:
				return $type;
		}
	}

	/**
	 * What does this mean, and what should I do?
	 *
	 * @param string $type Signal type.
	 * @return array{what: string, action: string}
	 */
	public static function guidance( $type ) {
		switch ( $type ) {
			case 'error.php_fatal':
				return array(
					'what'   => __( 'The code stopped completely at this point — whatever the visitor or WordPress was doing was aborted. Fatal errors in a plugin usually mean a broken update, a PHP version mismatch, or a conflict with another plugin.', 'ravnsight-detective' ),
					'action' => __( 'Check the timeline: did this start right after an update or activation? If the component below is a plugin you can spare, deactivate it and see if the error stops. Report the full message, file and line to the developer — that is exactly what they need.', 'ravnsight-detective' ),
				);
			case 'error.php_warning':
				return array(
					'what'   => __( 'The code hit something unexpected but kept running. Individual warnings are usually harmless; the same warning thousands of times, or warnings that start suddenly, point at a real defect or a bad interaction.', 'ravnsight-detective' ),
					'action' => __( 'Look at when it first appeared and what changed around then. A warning that appears right after an update is the update\'s fault until proven otherwise. A rising count deserves attention.', 'ravnsight-detective' ),
				);
			case 'error.php_deprecated':
				return array(
					'what'   => __( 'The component uses a PHP or WordPress feature that is on its way out. Nothing is broken today, but this code will fail on a future PHP or WordPress upgrade.', 'ravnsight-detective' ),
					'action' => __( 'No urgency — but many deprecations from a component without recent updates is a sign it has been abandoned. Plan a replacement before your next PHP upgrade.', 'ravnsight-detective' ),
				);
			case 'change.option_changed':
				return array(
					'what'   => __( 'A core WordPress setting changed. The component listed is our best guess at who changed it, based on the code that made the call.', 'ravnsight-detective' ),
					'action' => __( 'If you did not make this change yourself, find out what did — settings that change "by themselves" are how a site ends up suddenly noindexed or redirected.', 'ravnsight-detective' ),
				);
			case 'change.php_version':
				return array(
					'what'   => __( 'The server PHP version changed — usually your host upgrading. New PHP versions break old code; errors that start today often trace back to this.', 'ravnsight-detective' ),
					'action' => __( 'Watch the error columns for the next few days. Deprecations recorded before the upgrade were the warning signs.', 'ravnsight-detective' ),
				);
			case 'perf.request_slow':
				return array(
					'what'   => __( 'A visitor request took longer than the threshold. The detail shows how much of it was database work (query count, and the exact slow query shapes when SAVEQUERIES is enabled in wp-config.php).', 'ravnsight-detective' ),
					'action' => __( 'Many queries (hundreds) usually means a plugin doing N+1 lookups — check which plugins run on this page. High memory with few queries points at heavy page builders or image work. If slowness started on a specific date, check the timeline for what changed.', 'ravnsight-detective' ),
				);
			case 'perf.memory_high':
				return array(
					'what'   => __( 'The request came close to the PHP memory limit. When the limit is actually hit, the visitor gets a white screen and a fatal appears here instead.', 'ravnsight-detective' ),
					'action' => __( 'Recurring on the same page: something on it loads too much at once. Everywhere at once: the limit may simply be low — see the memory limit under Needs attention on the dashboard.', 'ravnsight-detective' ),
				);
			case 'error.db_error':
				return array(
					'what'   => __( 'A database query failed. The component is our best guess at who ran it; the detail shows the query shape with values stripped.', 'ravnsight-detective' ),
					'action' => __( 'A missing-table error right after activating a plugin means its installer failed — deactivate and reactivate it. Syntax errors are bugs to report to the developer. Connection errors are your host.', 'ravnsight-detective' ),
				);
			case 'change.theme_file_modified':
				return array(
					'what'   => __( 'A PHP file in the active theme changed WITHOUT a theme update — someone edited it by hand, a deployment touched it, or in the worst case malware modified it. Theme updates that change many files at once are reported as updates, not as file edits.', 'ravnsight-detective' ),
					'action' => __( 'If nobody on your team edited this file today, treat it seriously: open the file and look at what changed, and compare against a clean copy of the theme. functions.php is the most common target for both hurried developers and malware.', 'ravnsight-detective' ),
				);
			case 'error.mail_failed':
				return array(
					'what'   => __( 'WordPress tried to send an e-mail and the transport refused. This is the silent failure class: order confirmations, password resets and contact forms just stop arriving, and nothing in WordPress tells you. The message above is the mail server\'s actual reason.', 'ravnsight-detective' ),
					'action' => __( '"Could not instantiate mail function" or connection errors mean the host blocks PHP mail — install an SMTP plugin. Authentication errors mean the SMTP plugin\'s credentials or API key expired. "Sender address rejected" points at a From address that does not match the domain (DMARC). The component shows which plugin tried to send.', 'ravnsight-detective' ),
				);
			case 'error.js_error':
				return array(
					'what'   => __( 'JavaScript failed in a visitor\'s browser. Broken JS is invisible in server logs but very visible to visitors: dead buttons, forms that do nothing, carts that will not update.', 'ravnsight-detective' ),
					'action' => __( 'The source file shows which plugin or theme shipped the broken script. Errors from browser extensions and external scripts are attributed as external — those you can usually ignore.', 'ravnsight-detective' ),
				);
			case 'error.cron_stalled':
				return array(
					'what'   => __( 'Scheduled background tasks are not running. Scheduled posts stay unpublished, order e-mails and renewals stop — while the site looks fine to visitors.', 'ravnsight-detective' ),
					'action' => __( 'Visit the site once to kick cron. If it keeps happening, ask your host to run wp-cron.php via a real system cron and set DISABLE_WP_CRON.', 'ravnsight-detective' ),
				);
			case 'error.as_failed':
				return array(
					'what'   => __( 'Background actions (Action Scheduler) are failing. On WooCommerce shops these often carry order e-mails, webhooks and subscription renewals.', 'ravnsight-detective' ),
					'action' => __( 'Open WooCommerce → Status → Scheduled actions, filter on Failed, and read the error of the latest failures.', 'ravnsight-detective' ),
				);
			case 'error.as_backlog':
				return array(
					'what'   => __( 'The background queue is not keeping up — pending actions are past due.', 'ravnsight-detective' ),
					'action' => __( 'Usually a stalled or too-infrequent cron. Fix cron first; if the backlog persists, look for one action type flooding the queue.', 'ravnsight-detective' ),
				);
			case 'error.wc_order_failed':
				return array(
					'what'   => __( 'An order reached the Failed status — almost always the payment step.', 'ravnsight-detective' ),
					'action' => __( 'Check the payment gateway settings and its log (expired API keys and gateway downtime are the usual culprits).', 'ravnsight-detective' ),
				);
			default:
				if ( 0 === strpos( $type, 'change.' ) ) {
					return array(
						'what'   => __( 'A change to the site software. Changes are not problems — but when an error starts, the change right before it is the first suspect.', 'ravnsight-detective' ),
						'action' => __( 'Nothing to do by itself. If errors appear after this point in the timeline, this change is where to start looking.', 'ravnsight-detective' ),
					);
				}

				return array(
					'what'   => '',
					'action' => '',
				);
		}
	}
}
