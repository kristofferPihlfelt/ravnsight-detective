<?php
/**
 * Environment health: pending updates and version status — the "does my
 * site need attention?" half of the dashboard. Reads WordPress's own
 * update data; performs no external requests of its own.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Admin;

defined( 'ABSPATH' ) || exit;

final class Health {

	/**
	 * Pending updates + environment facts.
	 *
	 * @return array
	 */
	public static function overview() {
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_updates = array();
		foreach ( (array) get_plugin_updates() as $basename => $plugin ) {
			$plugin_updates[] = array(
				'name'       => (string) $plugin->Name, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP core object.
				'slug'       => strtok( (string) $basename, '/' ),
				'current'    => (string) $plugin->Version, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WP core object.
				'new'        => (string) ( $plugin->update->new_version ?? '' ),
				// An update that exists but has no download package is the
				// classic signature of a premium plugin whose licence/
				// subscription has lapsed — the vendor announces the version
				// but will not hand over the file.
				'no_package' => empty( $plugin->update->package ),
			);
		}

		$theme_updates = array();
		foreach ( (array) get_theme_updates() as $slug => $theme ) {
			$theme_updates[] = array(
				'name'    => (string) $theme->get( 'Name' ),
				'slug'    => (string) $slug,
				'current' => (string) $theme->get( 'Version' ),
				'new'     => (string) ( $theme->update['new_version'] ?? '' ),
			);
		}

		$core_update = null;
		$core        = get_core_updates( array( 'dismissed' => false ) );
		if ( is_array( $core ) && ! empty( $core ) && 'upgrade' === ( $core[0]->response ?? '' ) ) {
			$core_update = (string) $core[0]->current;
		}

		$disk_free  = @disk_free_space( ABSPATH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- open_basedir may forbid it; null is a valid answer.

		return array(
			'server'         => array(
				'memory_limit'    => (string) ini_get( 'memory_limit' ),
				'memory_low'      => wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) ) > 0 && wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) ) < 128 * 1048576,
				'upload_max'      => (string) ini_get( 'upload_max_filesize' ),
				'post_max'        => (string) ini_get( 'post_max_size' ),
				'max_exec'        => (int) ini_get( 'max_execution_time' ),
				'disk_free_gb'    => false !== $disk_free ? round( $disk_free / 1073741824, 1 ) : null,
				// Absolute free space only. A percentage rule misfires on
				// shared hosting: 42 GB free is never "low" even if it is
				// 8% of a huge shared volume.
				'disk_low'        => false !== $disk_free && $disk_free < 2 * 1073741824,
			),
			'plugin_updates' => $plugin_updates,
			'theme_updates'  => $theme_updates,
			'core_update'    => $core_update,
			'wp_version'     => get_bloginfo( 'version' ),
			'php_version'    => PHP_VERSION,
			'php_old'        => version_compare( PHP_VERSION, '8.1', '<' ),
		);
	}

	/**
	 * The "paste this to support" block: everything a support tech asks
	 * for, as plain text. In Pro the same snapshot is visible directly on
	 * the Ravnsight platform.
	 *
	 * @return string
	 */
	/**
	 * Compact structured environment summary for the platform (Pro
	 * heartbeat). Everything here is setup data, not content: versions,
	 * limits, theme, builder, update needs. Lists are capped — this is an
	 * overview, never an audit.
	 *
	 * @return array<string, mixed>
	 */
	public static function environment_summary() {
		global $wpdb;

		$health = self::overview();
		$theme  = wp_get_theme();
		$parent = $theme->parent();

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active = (array) get_option( 'active_plugins', array() );
		$all    = get_plugins();

		// Plugins not updated in a long time — measured locally: the main
		// file's mtime is when THIS site last installed/updated it.
		$stale = array();
		foreach ( $active as $basename ) {
			if ( ! isset( $all[ $basename ] ) ) {
				continue;
			}
			$file = WP_PLUGIN_DIR . '/' . $basename;
			if ( ! file_exists( $file ) ) {
				continue;
			}
			$months = (int) floor( ( time() - (int) filemtime( $file ) ) / ( 30 * DAY_IN_SECONDS ) );
			if ( $months >= 12 ) {
				$stale[] = array(
					'name'    => (string) $all[ $basename ]['Name'],
					'version' => (string) $all[ $basename ]['Version'],
					'months'  => $months,
				);
			}
		}
		usort( $stale, static fn( $a, $b ) => $b['months'] <=> $a['months'] );

		// Page-builder detection: setups with builders fail differently.
		$builder  = '';
		$builders = array(
			'elementor/elementor.php'          => 'Elementor',
			'js_composer/js_composer.php'      => 'WPBakery',
			'beaver-builder-lite-version/fl-builder.php' => 'Beaver Builder',
			'oxygen/functions.php'             => 'Oxygen',
			'fusion-builder/fusion-builder.php' => 'Avada Builder',
			'breakdance/plugin.php'            => 'Breakdance',
		);
		foreach ( $builders as $basename => $name ) {
			if ( in_array( $basename, $active, true ) ) {
				$builder = $name;
				break;
			}
		}
		if ( '' === $builder && in_array( strtolower( (string) $theme->get_template() ), array( 'divi', 'bricks' ), true ) ) {
			$builder = ucfirst( strtolower( (string) $theme->get_template() ) );
		}

		$updates = array();
		foreach ( array_slice( $health['plugin_updates'], 0, 15 ) as $up ) {
			$updates[] = array(
				'name'       => (string) $up['name'],
				'current'    => (string) $up['current'],
				'new'        => (string) $up['new'],
				'no_package' => ! empty( $up['no_package'] ),
			);
		}
		$theme_updates = array();
		foreach ( array_slice( $health['theme_updates'], 0, 5 ) as $up ) {
			$theme_updates[] = array(
				'name'    => (string) $up['name'],
				'current' => (string) $up['current'],
				'new'     => (string) $up['new'],
			);
		}

		return array(
			'wp'            => array(
				'version' => (string) $health['wp_version'],
				'update'  => (string) ( $health['core_update'] ?? '' ),
			),
			'php'           => array(
				'version' => PHP_VERSION,
				'old'     => (bool) $health['php_old'],
				'memory'  => (string) $health['server']['memory_limit'],
				'upload'  => (string) $health['server']['upload_max'],
			),
			'server'        => array(
				'disk_free_gb' => $health['server']['disk_free_gb'],
				'disk_low'     => (bool) $health['server']['disk_low'],
				'db'           => (string) $wpdb->db_server_info(),
			),
			'theme'         => array(
				'name'    => (string) $theme->get( 'Name' ),
				'version' => (string) $theme->get( 'Version' ),
				'parent'  => $parent ? (string) $parent->get( 'Name' ) : '',
			),
			'builder'       => $builder,
			'plugins'       => array(
				'total'   => count( $all ),
				'active'  => count( $active ),
				'updates' => $updates,
				'stale'   => array_slice( $stale, 0, 5 ),
			),
			'theme_updates' => $theme_updates,
		);
	}

	public static function site_info() {
		global $wpdb;

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$theme  = wp_get_theme();
		$parent = $theme->parent();
		$lines  = array(
			'== Site info (Ravnsight Detective ' . RAVNDET_VERSION . ') ==',
			'Site URL:        ' . home_url(),
			'WordPress:       ' . get_bloginfo( 'version' ) . ( is_multisite() ? ' (multisite)' : '' ),
			'PHP:             ' . PHP_VERSION . ' (' . PHP_SAPI . ')',
			'Database:        ' . $wpdb->db_server_info(),
			'Web server:      ' . ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'unknown' ),
			'HTTPS:           ' . ( is_ssl() ? 'yes' : 'no' ),
			'Language:        ' . get_locale(),
			'Timezone:        ' . wp_timezone_string(),
			'Memory limit:    ' . ini_get( 'memory_limit' ) . ' (WP_MEMORY_LIMIT ' . ( defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '-' ) . ')',
			'Upload limits:   upload_max_filesize ' . ini_get( 'upload_max_filesize' ) . ', post_max_size ' . ini_get( 'post_max_size' ),
			'Max execution:   ' . ini_get( 'max_execution_time' ) . ' s',
			'WP_DEBUG:        ' . ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'on' : 'off' ),
			'Object cache:    ' . ( wp_using_ext_object_cache() ? 'external' : 'default' ),
			'Permalinks:      ' . ( get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : 'plain' ),
			'Theme:           ' . $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) . ( $parent ? ' (child of ' . $parent->get( 'Name' ) . ' ' . $parent->get( 'Version' ) . ')' : '' ),
			'',
			'== Active plugins ==',
		);

		$active = (array) get_option( 'active_plugins', array() );
		foreach ( get_plugins() as $basename => $data ) {
			if ( in_array( $basename, $active, true ) ) {
				$lines[] = str_pad( substr( (string) $data['Name'], 0, 40 ), 42 ) . ( $data['Version'] ?? '' );
			}
		}

		return implode( "\n", $lines );
	}
}