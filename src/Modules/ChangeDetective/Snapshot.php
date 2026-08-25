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

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, daily job.
		$previous_row = $wpdb->get_row( $wpdb->prepare( 'SELECT environment FROM %i ORDER BY taken_at DESC LIMIT 1', $table ) );
		$previous     = $previous_row ? json_decode( (string) $previous_row->environment, true ) : null;
		// phpcs:enable

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- own table.
		$wpdb->insert(
			$table,
			array(
				'taken_at'    => time(),
				'environment' => wp_json_encode( $current ),
			)
		);

		// Keep 30 snapshots.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table housekeeping.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE id NOT IN (SELECT id FROM (SELECT id FROM %i ORDER BY taken_at DESC LIMIT 30) keep_rows)', $table, $table ) );
		// phpcs:enable

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

		$themes = array();
		foreach ( wp_get_themes() as $slug => $theme ) {
			$themes[ (string) $slug ] = array(
				'version' => (string) $theme->get( 'Version' ),
				'parent'  => (string) ( $theme->get_template() !== $slug ? $theme->get_template() : '' ),
			);
		}

		return array(
			'wp'          => get_bloginfo( 'version' ),
			'php'         => PHP_VERSION,
			'theme'       => get_stylesheet(),
			'parent'      => get_template(),
			'plugins'     => $plugins,
			'themes'      => $themes,
			'theme_files' => self::theme_file_hashes(),
		);
	}

	/**
	 * Content hashes for the active theme's (and its parent's) PHP files —
	 * the files people edit by hand and forget, and the files malware
	 * touches first. Small directories, hashed once per day.
	 *
	 * @return array<string, string> Relative path => hash.
	 */
	public static function theme_file_hashes() {
		$hashes = array();
		$roots  = array_unique( array( get_stylesheet_directory(), get_template_directory() ) );
		foreach ( $roots as $root ) {
			if ( ! is_dir( $root ) ) {
				continue;
			}
			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
			$count    = 0;
			foreach ( $iterator as $file ) {
				if ( 'php' !== strtolower( $file->getExtension() ) || ++$count > 400 ) {
					continue;
				}
				$relative            = basename( $root ) . '/' . ltrim( str_replace( $root, '', (string) $file->getPathname() ), '/' );
				$hashes[ $relative ] = (string) md5_file( (string) $file->getPathname() );
			}
		}

		return $hashes;
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
			if ( null === $was ) {
				SignalStore::record( 'change.plugin_installed', 'info', sprintf( 'New plugin appeared: %s %s', $slug, $info['version'] ), array( 'type' => 'plugin', 'id' => (string) $slug, 'version' => $info['version'] ) );
			} elseif ( ( $was['version'] ?? '' ) !== $info['version'] ) {
				SignalStore::record( 'change.plugin_updated', 'info', sprintf( 'Plugin version changed outside the upgrader: %s %s → %s', $slug, $was['version'] ?? '?', $info['version'] ), array( 'type' => 'plugin', 'id' => (string) $slug, 'version' => $info['version'] ) );
			}
		}
		foreach ( array_keys( $old_plugins ) as $slug ) {
			if ( ! isset( $new['plugins'][ $slug ] ) ) {
				SignalStore::record( 'change.plugin_removed', 'info', sprintf( 'Plugin removed from the server: %s', $slug ), array( 'type' => 'plugin', 'id' => (string) $slug, 'version' => '' ) );
			}
		}

		// New themes (a child theme appearing is exactly this).
		$old_themes = (array) ( $old['themes'] ?? array() );
		foreach ( (array) ( $new['themes'] ?? array() ) as $slug => $info ) {
			if ( ! isset( $old_themes[ $slug ] ) && ! empty( $old_themes ) ) {
				$label = ! empty( $info['parent'] )
					? sprintf( 'New child theme appeared: %s (child of %s)', $slug, $info['parent'] )
					: sprintf( 'New theme appeared: %s %s', $slug, $info['version'] );
				SignalStore::record( 'change.theme_installed', 'info', $label, array( 'type' => 'theme', 'id' => (string) $slug, 'version' => (string) $info['version'] ) );
			}
		}

		// Edited theme files: the classic "someone changed functions.php".
		$old_files = (array) ( $old['theme_files'] ?? array() );
		$new_files = (array) ( $new['theme_files'] ?? array() );
		if ( ! empty( $old_files ) ) {
			$changed = array();
			foreach ( $new_files as $path => $hash ) {
				if ( isset( $old_files[ $path ] ) && $old_files[ $path ] !== $hash ) {
					$changed[] = array( 'file' => $path, 'change' => 'modified' );
				} elseif ( ! isset( $old_files[ $path ] ) ) {
					$changed[] = array( 'file' => $path, 'change' => 'added' );
				}
			}
			foreach ( array_keys( $old_files ) as $path ) {
				if ( ! isset( $new_files[ $path ] ) ) {
					$changed[] = array( 'file' => $path, 'change' => 'removed' );
				}
			}
			// A theme UPDATE changes most files at once — the update signal already covers that; only report hand-edit-sized diffs.
			if ( ! empty( $changed ) && count( $changed ) <= 15 ) {
				foreach ( $changed as $change ) {
					$theme_slug = strtok( $change['file'], '/' );
					SignalStore::record(
						'change.theme_file_modified',
						'warning',
						sprintf( 'Theme file %s: %s', $change['change'], $change['file'] ),
						array( 'type' => 'theme', 'id' => (string) $theme_slug, 'version' => '' ),
						$change
					);
				}
			}
		}
	}
}
