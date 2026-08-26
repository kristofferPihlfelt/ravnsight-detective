<?php
/**
 * File path (or backtrace) → which plugin, theme or core caused this.
 * The heart of attribution: an error is only useful when it points at
 * the component that produced it.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Support;

defined( 'ABSPATH' ) || exit;

final class ComponentResolver {

	/**
	 * Resolve a file path to a component.
	 *
	 * @param string $file Absolute file path.
	 * @return array{type: string, id: string, version: string}
	 */
	public static function from_file( $file ) {
		$file = wp_normalize_path( (string) $file );

		$plugins_dir = wp_normalize_path( WP_PLUGIN_DIR ) . '/';
		if ( str_starts_with( $file, $plugins_dir ) ) {
			$slug = strtok( substr( $file, strlen( $plugins_dir ) ), '/' );

			return array( 'type' => 'plugin', 'id' => (string) $slug, 'version' => self::plugin_version( (string) $slug ) );
		}

		$mu_dir = wp_normalize_path( WPMU_PLUGIN_DIR ) . '/';
		if ( str_starts_with( $file, $mu_dir ) ) {
			return array( 'type' => 'plugin', 'id' => 'mu:' . basename( $file, '.php' ), 'version' => '' );
		}

		$themes_dir = wp_normalize_path( get_theme_root() ) . '/';
		if ( str_starts_with( $file, $themes_dir ) ) {
			$slug  = strtok( substr( $file, strlen( $themes_dir ) ), '/' );
			$theme = wp_get_theme( (string) $slug );

			return array( 'type' => 'theme', 'id' => (string) $slug, 'version' => $theme->exists() ? (string) $theme->get( 'Version' ) : '' );
		}

		$abspath = wp_normalize_path( ABSPATH );
		if ( str_starts_with( $file, $abspath . 'wp-includes/' ) || str_starts_with( $file, $abspath . 'wp-admin/' ) ) {
			return array( 'type' => 'core', 'id' => 'wordpress', 'version' => get_bloginfo( 'version' ) );
		}

		return array( 'type' => 'server', 'id' => '', 'version' => '' );
	}

	/**
	 * Resolve from a debug backtrace: the first frame outside this plugin wins.
	 *
	 * @param array $trace debug_backtrace() output.
	 * @return array{type: string, id: string, version: string}
	 */
	public static function from_trace( array $trace ) {
		foreach ( $trace as $frame ) {
			if ( empty( $frame['file'] ) ) {
				continue;
			}
			$file = wp_normalize_path( $frame['file'] );
			if ( str_contains( $file, '/ravnsight-detective' ) ) {
				continue; // Never attribute to ourselves.
			}

			$component = self::from_file( $file );
			if ( 'server' !== $component['type'] ) {
				return $component;
			}
		}

		return array( 'type' => 'server', 'id' => '', 'version' => '' );
	}

	/**
	 * Version for an active plugin slug (cheap, cached by WP).
	 *
	 * @param string $slug Plugin directory slug.
	 * @return string
	 */
	/**
	 * Current installed version of a component slug (plugin or theme),
	 * '' when unknown. Used at resolve-time: the version NOW is the fix
	 * version.
	 *
	 * @param string $slug Component slug.
	 * @return string
	 */
	public static function current_version( $slug ) {
		$plugin = self::plugin_version( $slug );
		if ( '' !== $plugin ) {
			return $plugin;
		}
		$theme = wp_get_theme( $slug );

		return $theme->exists() ? (string) $theme->get( 'Version' ) : '';
	}

	private static function plugin_version( $slug ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( get_plugins() as $basename => $data ) {
			if ( str_starts_with( $basename, $slug . '/' ) || $basename === $slug . '.php' ) {
				return (string) ( $data['Version'] ?? '' );
			}
		}

		return '';
	}
}
