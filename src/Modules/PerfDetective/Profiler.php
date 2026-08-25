<?php
/**
 * Shutdown-time performance analysis.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Modules\PerfDetective;

use Ravnsight\Detective\Core\SignalStore;

defined( 'ABSPATH' ) || exit;

final class Profiler {

	/**
	 * Measure the finished request. Cheap: arithmetic + at most a couple
	 * of signal upserts, and only when thresholds are crossed.
	 */
	public static function on_shutdown() {
		try {
			self::check_slow_request();
			self::check_memory();
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- shutdown must stay silent.
			// Swallow.
		}
	}

	/**
	 * Slow request: duration over the threshold. Admin, cron and CLI are
	 * excluded — visitors are who slowness hurts.
	 */
	private static function check_slow_request() {
		if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		// Server-populated float, not user input — cast IS the sanitisation.
		$start = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) wp_unslash( $_SERVER['REQUEST_TIME_FLOAT'] ) : 0.0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cast to float.
		if ( $start <= 0 ) {
			return;
		}
		$duration_ms = (int) ( ( microtime( true ) - $start ) * 1000 );

		/**
		 * Filter the slow-request threshold in milliseconds.
		 *
		 * @param int $threshold Default 3000.
		 */
		$threshold = (int) apply_filters( 'ravndet_slow_request_ms', 3000 );
		if ( $duration_ms < $threshold ) {
			return;
		}

		global $wpdb;
		$uri     = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- diagnostic scope, redacted in SignalStore.
		$context = array(
			'duration_ms' => $duration_ms,
			'queries'     => (int) ( $wpdb->num_queries ?? 0 ),
			'memory_mb'   => round( memory_get_peak_usage( true ) / 1048576, 1 ),
		);
		$context = $context + self::slow_queries();

		SignalStore::record(
			'perf.request_slow',
			'warning',
			sprintf( 'Slow request: %d ms, %d database queries, %s MB memory', $duration_ms, $context['queries'], $context['memory_mb'] ),
			array( 'type' => 'server', 'id' => 'request', 'version' => '' ),
			$context,
			$uri
		);
	}

	/**
	 * Slow query shapes — only available when SAVEQUERIES is enabled
	 * (WordPress's own opt-in profiling switch).
	 *
	 * @return array
	 */
	private static function slow_queries() {
		global $wpdb;

		if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES || empty( $wpdb->queries ) ) {
			return array();
		}
		$slow = array();
		foreach ( $wpdb->queries as $q ) {
			$time = (float) ( $q[1] ?? 0 );
			if ( $time >= 0.05 ) {
				$shape  = preg_replace( array( "/'[^']*'/", '/\d+/', '/\s+/' ), array( '?', 'N', ' ' ), (string) $q[0] );
				$slow[] = array( 'ms' => (int) ( $time * 1000 ), 'query' => substr( trim( (string) $shape ), 0, 300 ) );
			}
		}
		usort( $slow, static fn( $a, $b ) => $b['ms'] <=> $a['ms'] );

		return $slow ? array( 'slow_queries' => array_slice( $slow, 0, 5 ) ) : array();
	}

	/**
	 * Memory near the limit — the precursor to white-screen OOM fatals.
	 */
	private static function check_memory() {
		$limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		if ( $limit <= 0 ) {
			return;
		}
		$peak = memory_get_peak_usage( true );

		/**
		 * Filter the memory warning ratio.
		 *
		 * @param float $ratio Default 0.85.
		 */
		$ratio = (float) apply_filters( 'ravndet_memory_warn_ratio', 0.85 );
		if ( $peak < $limit * $ratio ) {
			return;
		}

		SignalStore::record(
			'perf.memory_high',
			'warning',
			sprintf( 'Memory peaked at %s MB of the %s MB limit', round( $peak / 1048576 ), round( $limit / 1048576 ) ),
			array( 'type' => 'server', 'id' => 'memory', 'version' => '' ),
			array( 'peak_mb' => round( $peak / 1048576, 1 ), 'limit_mb' => round( $limit / 1048576, 1 ) )
		);
	}
}
