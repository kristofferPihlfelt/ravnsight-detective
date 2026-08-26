<?php
/**
 * dbDelta migrations. Tables mirror contracts/signal/v1 — same fields,
 * same types, same fingerprints — so the cloud connection (Pro, Phase 6)
 * is a transport, never a translation.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Core;

defined( 'ABSPATH' ) || exit;

final class Migrator {

	const SCHEMA_VERSION = 4;
	const OPTION         = 'ravndet_db_version';

	/**
	 * Table name helper.
	 *
	 * @param string $name Bare name (signals|snapshots).
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;

		return $wpdb->prefix . 'ravndet_' . $name;
	}

	/**
	 * Migrate when the stored schema version is behind.
	 */
	public static function maybe_migrate() {
		if ( (int) get_option( self::OPTION, 0 ) < self::SCHEMA_VERSION ) {
			self::migrate();
		}
	}

	/**
	 * Run dbDelta for all tables.
	 */
	public static function migrate() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset  = $wpdb->get_charset_collate();
		$signals  = self::table( 'signals' );
		$snapshot = self::table( 'snapshots' );

		dbDelta(
			"CREATE TABLE {$signals} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				type VARCHAR(64) NOT NULL,
				fingerprint VARCHAR(64) NOT NULL,
				severity VARCHAR(10) NOT NULL DEFAULT 'info',
				component_type VARCHAR(10) NULL,
				component_id VARCHAR(255) NULL,
				component_version VARCHAR(64) NULL,
				scope VARCHAR(128) NULL,
				scope_local VARCHAR(191) NULL,
				message TEXT NULL,
				context LONGTEXT NULL,
				count INT UNSIGNED NOT NULL DEFAULT 1,
				first_seen INT UNSIGNED NOT NULL,
				last_seen INT UNSIGNED NOT NULL,
				resolved_detected INT UNSIGNED NULL,
				resolution TEXT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY fingerprint (fingerprint),
				KEY type_last_seen (type, last_seen),
				KEY last_seen (last_seen)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$snapshot} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				taken_at INT UNSIGNED NOT NULL,
				environment LONGTEXT NOT NULL,
				PRIMARY KEY  (id),
				KEY taken_at (taken_at)
			) {$charset};"
		);

		update_option( self::OPTION, self::SCHEMA_VERSION, true );
	}
}
