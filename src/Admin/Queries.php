<?php
/**
 * Read-side queries for the admin screens. Table names go through the %i
 * placeholder (WP 6.2+); LIKE patterns are bound parameters.
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
		$error = $wpdb->esc_like( 'error.' ) . '%';
		$chang = $wpdb->esc_like( 'change.' ) . '%';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, admin-only read, indexed columns.
		$errors_24h = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(count),0) FROM %i WHERE type LIKE %s AND last_seen >= %d', $table, $error, $day ) );
		$errors_7d  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(count),0) FROM %i WHERE type LIKE %s AND last_seen >= %d', $table, $error, $week ) );
		$changes_7d = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE type LIKE %s AND last_seen >= %d', $table, $chang, $week ) );
		$fatals_24h = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(count),0) FROM %i WHERE type = %s AND last_seen >= %d', $table, 'error.php_fatal', $day ) );
		$offenders  = $wpdb->get_results( $wpdb->prepare( "SELECT component_type, component_id, SUM(count) hits FROM %i WHERE type LIKE %s AND last_seen >= %d AND component_id IS NOT NULL AND component_id <> '' GROUP BY component_type, component_id ORDER BY hits DESC LIMIT 5", $table, $error, $week ) );
		$latest     = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY last_seen DESC LIMIT 8', $table ) );
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
	public static function timeline( $type_group = '', $severity = '', $sort = 'recent' ) {
		global $wpdb;
		$table = Migrator::table( 'signals' );

		$type_like = in_array( $type_group, array( 'error', 'change' ), true ) ? $wpdb->esc_like( $type_group . '.' ) . '%' : '%';
		// Severity is validated against a fixed list; '%' matches all when unset.
		$severity_like = in_array( $severity, array( 'info', 'warning', 'critical' ), true ) ? $severity : '%';

		// 'severity': unresolved criticals first, then warnings — for when you
		// are hunting one specific problem. 'recent' stays the default: the
		// timeline's whole point is "what changed right before".
		$order = 'severity' === $sort
			? "FIELD(severity, 'critical', 'warning', 'info'), (resolved_detected IS NULL) DESC, last_seen DESC"
			: 'last_seen DESC';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table, admin-only read; $order is built from a fixed whitelist above.
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE type LIKE %s AND severity LIKE %s ORDER BY {$order} LIMIT 200", $table, $type_like, $severity_like ) );
		// phpcs:enable
	}
}
