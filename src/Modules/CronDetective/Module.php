<?php
/**
 * CronDetective: is the background machinery actually running? A stalled
 * WP-cron or a failing Action Scheduler queue breaks sites silently —
 * scheduled posts, order e-mails and subscription renewals just stop,
 * while every uptime check stays green.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Modules\CronDetective;

use Ravnsight\Detective\Core\ModuleInterface;
use Ravnsight\Detective\Core\SignalStore;

defined( 'ABSPATH' ) || exit;

final class Module implements ModuleInterface {

	/** Overdue by more than this → stalled. */
	const STALL_MINUTES = 30;

	/** Re-check at most this often (seconds). */
	const CHECK_INTERVAL = 900;

	/**
	 * Feature flag key.
	 *
	 * @return string
	 */
	public static function key() {
		return 'cron_detective';
	}

	/**
	 * Hook up.
	 */
	public function boot() {
		// Track that cron actually ran — WordPress itself keeps no record.
		if ( wp_doing_cron() ) {
			add_action( 'init', static function () {
				update_option( 'ravndet_last_cron', time(), false );
			}, 1 );

			return; // Never health-check from inside a cron run.
		}

		add_action( 'shutdown', array( $this, 'check' ), 99 );
	}

	/**
	 * Health check on normal requests, throttled by transient.
	 */
	public function check() {
		if ( get_transient( 'ravndet_cron_checked' ) ) {
			return;
		}
		set_transient( 'ravndet_cron_checked', 1, self::CHECK_INTERVAL );

		$this->check_wp_cron();
		$this->check_action_scheduler();
	}

	/**
	 * Any scheduled event overdue beyond the stall threshold?
	 */
	private function check_wp_cron() {
		$crons = _get_cron_array();
		if ( empty( $crons ) ) {
			return;
		}

		$now     = time();
		$oldest  = 0;
		$overdue = 0;
		foreach ( $crons as $timestamp => $hooks ) {
			if ( $timestamp > $now - self::STALL_MINUTES * MINUTE_IN_SECONDS ) {
				break; // Sorted by timestamp: the rest are current or future.
			}
			$overdue += count( $hooks );
			if ( 0 === $oldest ) {
				$oldest = $now - (int) $timestamp;
			}
		}

		if ( 0 === $overdue ) {
			return;
		}

		SignalStore::record(
			'error.cron_stalled',
			'critical',
			sprintf( 'WP-cron appears stalled: %d scheduled tasks overdue, the oldest by %d minutes. Scheduled posts, e-mails and background jobs are not running.', $overdue, (int) floor( $oldest / 60 ) ),
			array(
				'type' => 'server',
				'id'   => 'wp-cron',
			),
			array(
				'overdue'            => $overdue,
				'oldest_minutes'     => (int) floor( $oldest / 60 ),
				'disable_wp_cron'    => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			)
		);
	}

	/**
	 * Action Scheduler (bundled with WooCommerce and many majors):
	 * failed actions in the last 24 h, and a past-due pending backlog.
	 */
	private function check_action_scheduler() {
		global $wpdb;

		if ( ! class_exists( 'ActionScheduler' ) ) {
			return;
		}
		$table = $wpdb->prefix . 'actionscheduler_actions';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only health check on Action Scheduler's table, throttled to once per 15 min.
		$failed = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s AND scheduled_date_gmt > %s', $table, 'failed', gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) );
		$stuck  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s AND scheduled_date_gmt < %s', $table, 'pending', gmdate( 'Y-m-d H:i:s', time() - self::STALL_MINUTES * MINUTE_IN_SECONDS ) ) );
		// phpcs:enable

		if ( $failed > 0 ) {
			SignalStore::record(
				'error.as_failed',
				$failed >= 10 ? 'critical' : 'warning',
				sprintf( 'Action Scheduler: %d failed background actions in the last 24 hours. On WooCommerce shops these often carry order e-mails, webhooks and subscription renewals.', $failed ),
				array(
					'type' => 'server',
					'id'   => 'action-scheduler',
				),
				array( 'failed_24h' => $failed )
			);
		}

		if ( $stuck >= 20 ) {
			SignalStore::record(
				'error.as_backlog',
				'warning',
				sprintf( 'Action Scheduler backlog: %d pending actions are past due by more than %d minutes — the queue is not keeping up.', $stuck, self::STALL_MINUTES ),
				array(
					'type' => 'server',
					'id'   => 'action-scheduler',
				),
				array( 'past_due' => $stuck )
			);
		}
	}
}
