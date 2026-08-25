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

		return array(
			'plugin_updates' => $plugin_updates,
			'theme_updates'  => $theme_updates,
			'core_update'    => $core_update,
			'wp_version'     => get_bloginfo( 'version' ),
			'php_version'    => PHP_VERSION,
			'php_old'        => version_compare( PHP_VERSION, '8.1', '<' ),
		);
	}
}
