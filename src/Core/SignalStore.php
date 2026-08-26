<?php
/**
 * The single write path for signals. Grouping happens here: an existing
 * fingerprint bumps count/last_seen — 50 000 identical warnings must be a
 * handful of rows, never 50 000.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Core;

use Ravnsight\Detective\Support\Redactor;

defined( 'ABSPATH' ) || exit;

final class SignalStore {

	/**
	 * Record (or bump) a signal. Everything is redacted here — no caller
	 * may bypass this method (DATA-POLICY: single write path, redaction
	 * failures fail closed).
	 *
	 * @param string $type       Signal type from contracts/signal-types.md.
	 * @param string $severity   info|warning|critical.
	 * @param string $message    Human message (will be redacted).
	 * @param array  $component  ['type' => plugin|theme|core|server, 'id' => slug, 'version' => x].
	 * @param array  $context    Extra context (will be redacted, JSON-encoded).
	 * @param string $scope      Optional scope (e.g. request URI, redacted).
	 * @return bool
	 */
	public static function record( $type, $severity, $message, array $component = array(), array $context = array(), $scope = '' ) {
		global $wpdb;

		$message = Redactor::text( (string) $message );
		$scope   = Redactor::uri( (string) $scope );
		$context = Redactor::context( $context );
		if ( null === $message || null === $context ) {
			return false; // Redaction failed → drop, never store raw.
		}

		$fingerprint = self::fingerprint( $type, $message, $component );
		$now         = time();
		$table       = Migrator::table( 'signals' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, the single upsert write path.
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET count = count + 1, last_seen = %d, severity = %s, resolved_detected = NULL, scope = COALESCE(scope, %s) WHERE fingerprint = %s',
				$table,
				$now,
				$severity,
				'' !== $scope ? $scope : null,
				$fingerprint
			)
		);
		// phpcs:enable

		if ( $updated ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- own table.
		$wpdb->insert(
			$table,
			array(
				'type'              => $type,
				'fingerprint'       => $fingerprint,
				'severity'          => $severity,
				'component_type'    => $component['type'] ?? null,
				'component_id'      => isset( $component['id'] ) ? substr( (string) $component['id'], 0, 255 ) : null,
				'component_version' => isset( $component['version'] ) ? substr( (string) $component['version'], 0, 64 ) : null,
				'scope'             => '' !== $scope ? substr( $scope, 0, 128 ) : null,
				'message'           => $message,
				'context'           => wp_json_encode( $context ),
				'count'             => 1,
				'first_seen'        => $now,
				'last_seen'         => $now,
			)
		);

		return true;
	}

	/**
	 * Stable fingerprint: type + normalised message + component. The same
	 * error at the same place is the same row, whatever the timestamps say.
	 *
	 * @param string $type      Signal type.
	 * @param string $message   Redacted message.
	 * @param array  $component Component array.
	 * @return string
	 */
	public static function fingerprint( $type, $message, array $component ) {
		$normalised = preg_replace( '/\d+/', 'N', $message );

		return substr( hash( 'sha256', $type . '|' . $normalised . '|' . ( $component['type'] ?? '' ) . '|' . ( $component['id'] ?? '' ) ), 0, 40 );
	}

	/**
	 * Prune rows older than the retention window.
	 */
	/**
	 * Silence scan (daily): an error that has stopped occurring is a
	 * RESOLUTION — and the real stop time is the last occurrence, which is
	 * what the reverse correlator needs. Threshold: 24 h, or 3× the
	 * signal\'s own average interval for high-frequency signals (clamped
	 * to 72 h so a quiet weekend never marks a weekly error resolved).
	 */
	public static function detect_resolved() {
		global $wpdb;

		$table = Migrator::table( 'signals' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table housekeeping.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, fingerprint, count, first_seen, last_seen FROM %i WHERE resolved_detected IS NULL AND ( type LIKE %s OR type LIKE %s ) AND last_seen < %d", $table, $wpdb->esc_like( 'error.' ) . '%', $wpdb->esc_like( 'perf.' ) . '%', time() - DAY_IN_SECONDS ) );

		foreach ( (array) $rows as $row ) {
			$avg_interval = $row->count > 1 ? ( (int) $row->last_seen - (int) $row->first_seen ) / ( (int) $row->count - 1 ) : 0;
			$threshold    = (int) min( max( 3 * $avg_interval, DAY_IN_SECONDS ), 3 * DAY_IN_SECONDS );
			if ( time() - (int) $row->last_seen < $threshold ) {
				continue;
			}
			$wpdb->update( $table, array( 'resolved_detected' => time() ), array( 'id' => (int) $row->id ), array( '%d' ), array( '%d' ) );
		}
		// phpcs:enable
	}

	public static function prune() {
		global $wpdb;

		$days = (int) get_option( 'ravndet_retention_days', 7 );
		$days = min( 7, max( 1, $days ) );
		
		$table = Migrator::table( 'signals' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table housekeeping.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE last_seen < %d', $table, time() - $days * DAY_IN_SECONDS ) );
		// phpcs:enable
	}
}
