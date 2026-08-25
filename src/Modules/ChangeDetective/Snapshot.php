<?php
/**
 * Daily environment snapshot + diff → change signals for things that
 * changed without going through the hooks (manual FTP swaps, host-side
 * PHP upgrades).
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Modules\ChangeDetective;

use Ravnsight\Detective\Core\Migrator;
use Ravnsight\Detective\Core\SignalStore;

defined( 'ABSPATH' ) || exit;

final class Snapshot {

	/**
	 * Take today's snapshot and diff against the previous one.
	 */
	public static function take() {
		global $wpdb;

		$current = self::capture();
		$table   = Migrator::table( 'snapshots' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table.
		$previous_row = $wpdb->get_row( "SELECT environment FROM {$table} ORDER BY taken_at DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table.
		$previous     = $previous_row ? json_decode( (string) $previous_row->environment, true ) : null;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- own table.
		$wpdb->insert(
			$table,
			array(
				'taken_at'    => time(),
				'environment' => wp_json_encode( $current ),
			)
		);

		// Keep 30 snapshots.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table housekeeping.
		$wpdb->query( "DELETE FROM {$table} WHERE id NOT IN (SELECT id FROM (SELECT id FROM {$table} ORDER BY taken_at DESC LIMIT 30) keep_rows)" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- own table.

		if ( is_array( $previous ) ) {
			self::diff( $previous, $current );
		}
	}

	/**
	 * What the environment looks like right now. Names and versions only —
	 * no paths, no configuration values (DATA-POLICY).
	 *
	 * @return array
	 */
	public static function capture() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = array();
		$active  = (array) get_option( 'active_plugins', array() );
		foreach ( get_plugins() as $basename => $data ) {
			$plugins[ strtok( $basename, '/' ) ] = array(
				'version' => (string) ( $data['Version'] ?? '' ),
				'active'  => in_array( $basename, $active, true ),
			);
		}

		return array(
			'wp'      => get_bloginfo( 'version' ),
			'php'     => PHP_VERSION,
			'theme'   => get_stylesheet(),
			'plugins' => $plugins,
		);
	}

	/**
	 * Diff two snapshots into change signals.
	 *
	 * @param array $old Previous snapshot.
	 * @param array $new Current snapshot.
	 */
	private static function diff( array $old, array $new ) {
		if ( ( $old['php'] ?? '' ) !== $new['php'] ) {
			SignalStore::record( 'change.php_version', 'warning', sprintf( 'PHP version changed: %s → %s', $old['php'] ?? '?', $new['php'] ), array( 'type' => 'server', 'id' => 'php', 'version' => $new['php'] ) );
		}
		if ( ( $old['wp'] ?? '' ) !== $new['wp'] ) {
			SignalStore::record( 'change.core_updated', 'info', sprintf( 'WordPress version changed: %s → %s', $old['wp'] ?? '?', $new['wp'] ), array( 'type' => 'core', 'id' => 'wordpress', 'version' => $new['wp'] ) );
		}

		$old_plugins = (array) ( $old['plugins'] ?? array() );
		foreach ( (array) $new['plugins'] as $slug => $info ) {
			$was = $old_plugins[ $slug ] ?? null;
			if ( null !== $was && ( $was['version'] ?? '' ) !== $info['version'] ) {
				SignalStore::record( 'change.plugin_updated', 'info', sprintf( 'Plugin version changed outside the upgrader: %s %s → %s', $slug, $was['version'] ?? '?', $info['version'] ), array( 'type' => 'plugin', 'id' => (string) $slug, 'version' => $info['version'] ) );
			}
		}
	}
}
