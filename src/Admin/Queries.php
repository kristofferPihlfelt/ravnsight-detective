<?php
/**
 * Read-side queries for the admin screens.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Admin;

use Ravnsight\Detective\Core\Migrator;

defined( 'ABSPATH' ) || exit;

final class Queries {

	/**
	 * Dashboard numbers: last 24 h and 7 d, top offenders, spike flag.
	 *
	 * @return array
	 */
	public static function dashboard() {
		global $wpdb;
		$table = Migrator::table( 'signals' );
		$day   = time() - DAY_IN_SECONDS;
		$week  = time() - WEEK_IN_SECONDS;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table, admin read.
		$errors_24h = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(count),0) FROM {$table} WHERE type LIKE 'error.%%' AND last_seen >= %d", $day ) );
		$errors_7d  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(count),0) FROM {$table} WHERE type LIKE 'error.%%' AND last_seen >= %d", $week ) );
		$changes_7d = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE type LIKE 'change.%%' AND last_seen >= %d", $week ) );
		$fatals_24h = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(count),0) FROM {$table} WHERE type = 'error.php_fatal' AND last_seen >= %d", $day ) );
		$offenders  = $wpdb->get_results( $wpdb->prepare( "SELECT component_type, component_id, SUM(count) hits FROM {$table} WHERE type LIKE 'error.%%' AND last_seen >= %d AND component_id IS NOT NULL AND component_id <> '' GROUP BY component_type, component_id ORDER BY hits DESC LIMIT 5", $week ) );
		$latest     = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY last_seen DESC LIMIT 8" );
		// phpcs:enable

		return array(
			'errors_24h' => $errors_24h,
			'errors_7d'  => $errors_7d,
			'changes_7d' => $changes_7d,
			'fatals_24h' => $fatals_24h,
			// Spike: the last day carries more than half of the whole week.
			'spike'      => $errors_7d >= 20 && $errors_24h > $errors_7d / 2,
			'offenders'  => $offenders,
			'latest'     => $latest,
		);
	}

	/**
	 * Timeline rows, filterable.
	 *
	 * @param string $type_group '' | error | change.
	 * @param string $severity   '' | info | warning | critical.
	 * @return array
	 */
	public static function timeline( $type_group = '', $severity = '' ) {
		global $wpdb;
		$table = Migrator::table( 'signals' );

		$where  = array( '1=1' );
		$params = array();
		if ( in_array( $type_group, array( 'error', 'change' ), true ) ) {
			$where[]  = 'type LIKE %s';
			$params[] = $type_group . '.%';
		}
		if ( in_array( $severity, array( 'info', 'warning', 'critical' ), true ) ) {
			$where[]  = 'severity = %s';
			$params[] = $severity;
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY last_seen DESC LIMIT 200';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- own table; $sql assembled from fixed fragments, params prepared below.
		return $params ? $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) ) : $wpdb->get_results( $sql );
		// phpcs:enable
	}
}
