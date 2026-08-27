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

		$stack   = \Ravnsight\Detective\Support\StackDetector::detect();
		$builder = $stack['builder'];

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
			'stack'         => array(
				'cache'    => $stack['cache'],
				'security' => $stack['security'],
				'seo'      => $stack['seo'],
				'backup'   => $stack['backup'],
			),
			'debug'         => array(
				'wp_debug'        => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'debug_display'   => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
				'display_errors'  => (bool) ini_get( 'display_errors' ),
				'disable_wp_cron' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			),
			'exec'          => array(
				'max_execution' => (int) ini_get( 'max_execution_time' ),
				'object_cache'  => wp_using_ext_object_cache() ? 'external' : 'default',
				'https'         => is_ssl(),
			),
			'plugins'       => array(
				'total'   => count( $all ),
				'active'  => count( $active ),
				'updates' => $updates,
				'stale'   => array_slice( $stale, 0, 5 ),
				'list'    => self::active_plugin_list( $active, $all ),
			),
			'theme_updates' => $theme_updates,
		);
	}

	/**
	 * Compact active-plugin list for the platform: name, version and role
	 * tag, capped so the payload stays small.
	 *
	 * @param array<int, string>            $active Active basenames.
	 * @param array<string, array<string>>  $all    get_plugins() output.
	 * @return array<int, array{name: string, version: string, cat: string}>
	 */
	private static function active_plugin_list( array $active, array $all ) {
		$own  = array( dirname( RAVNDET_BASENAME ), RAVNDET_SLUG, RAVNDET_SLUG . '-pro' );
		$list = array();
		foreach ( $active as $basename ) {
			if ( ! isset( $all[ $basename ] ) || count( $list ) >= 60 ) {
				continue;
			}
			$list[] = array(
				'name'    => mb_substr( (string) $all[ $basename ]['Name'], 0, 60 ),
				'version' => (string) ( $all[ $basename ]['Version'] ?? '' ),
				'cat'     => in_array( strtok( (string) $basename, '/' ), $own, true ) ? 'ravnsight' : \Ravnsight\Detective\Support\StackDetector::category_of( (string) $basename ),
			);
		}

		return $list;
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
			'Memory limit:    ' . ini_get( 'memory_limit' ) . ' (WP_MEMORY_LIMIT ' . ( defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '-' ) . ', WP_MAX_MEMORY_LIMIT ' . ( defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : '-' ) . ')',
			'Upload limits:   upload_max_filesize ' . ini_get( 'upload_max_filesize' ) . ', post_max_size ' . ini_get( 'post_max_size' ) . ', max_input_vars ' . ini_get( 'max_input_vars' ),
			'Max execution:   ' . ini_get( 'max_execution_time' ) . ' s',
			'Object cache:    ' . ( wp_using_ext_object_cache() ? 'external' : 'default' ),
			'Permalinks:      ' . ( get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : 'plain' ),
			'Theme:           ' . $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ) . ( $parent ? ' (child of ' . $parent->get( 'Name' ) . ' ' . $parent->get( 'Version' ) . ')' : '' ),
			'',
			'== Debug & config ==',
			'WP_DEBUG:        ' . ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'on' : 'off' ),
			'WP_DEBUG_LOG:    ' . ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? 'on' : 'off' ),
			'WP_DEBUG_DISPLAY:' . ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ? 'on' : 'off' ),
			'SCRIPT_DEBUG:    ' . ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? 'on' : 'off' ),
			'display_errors:  ' . ( ini_get( 'display_errors' ) ? 'on' : 'off' ),
			'WP_CACHE:        ' . ( defined( 'WP_CACHE' ) && WP_CACHE ? 'on' : 'off' ),
			'DISABLE_WP_CRON: ' . ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? 'on (real cron expected)' : 'off (visitor-triggered)' ),
			'AUTOMATIC UPDATES:' . ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED ? ' disabled' : ' default' ),
		);

		$stack     = \Ravnsight\Detective\Support\StackDetector::detect();
		$tool_lines = array();
		foreach ( array( 'builder' => 'Page builder', 'cache' => 'Cache', 'security' => 'Security', 'seo' => 'SEO', 'backup' => 'Backup' ) as $key => $label ) {
			$tool_lines[] = str_pad( $label . ':', 17 ) . ( '' !== $stack[ $key ] ? $stack[ $key ] : 'none detected' );
		}
		$lines[] = '';
		$lines[] = '== Detected tooling ==';
		$lines   = array_merge( $lines, $tool_lines );

		$lines[] = '';
		$lines[] = '== Active plugins ==';
		$active  = (array) get_option( 'active_plugins', array() );
		foreach ( get_plugins() as $basename => $data ) {
			if ( in_array( $basename, $active, true ) ) {
				$cat  = \Ravnsight\Detective\Support\StackDetector::category_of( (string) $basename );
				$tag  = '' !== $cat ? ' [' . $cat . ']' : '';
				$lines[] = str_pad( substr( (string) $data['Name'], 0, 40 ), 42 ) . str_pad( (string) ( $data['Version'] ?? '' ), 12 ) . $tag;
			}
		}

		return implode( "\n", $lines );
	}
}