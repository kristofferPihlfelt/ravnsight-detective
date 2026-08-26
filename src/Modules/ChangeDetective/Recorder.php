<?php
/**
 * Turns WordPress change hooks into change.* signals.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Modules\ChangeDetective;

use Ravnsight\Detective\Core\SignalStore;
use Ravnsight\Detective\Support\ComponentResolver;

defined( 'ABSPATH' ) || exit;

final class Recorder {

	/**
	 * Options whose VALUES may be recorded. Everything else: name only,
	 * and only if listed here. This list is a data boundary (DATA-POLICY),
	 * not a convenience — extend it deliberately.
	 *
	 * @var string[]
	 */
	const OPTION_ALLOWLIST = array(
		'blog_public',
		'template',
		'stylesheet',
		'active_plugins',
		'permalink_structure',
		'siteurl',
		'home',
		'WPLANG',
		'default_role',
		'users_can_register',
	);

	/**
	 * Plugin/theme/core updates via the upgrader.
	 *
	 * @param object $upgrader Upgrader instance.
	 * @param array  $extra    Hook payload.
	 */
	public function on_upgrade( $upgrader, $extra ) {
		if ( empty( $extra['type'] ) || 'update' !== ( $extra['action'] ?? '' ) ) {
			return;
		}

		if ( 'plugin' === $extra['type'] ) {
			foreach ( (array) ( $extra['plugins'] ?? array() ) as $basename ) {
				$slug = strtok( (string) $basename, '/' );
				$new_version = $this->plugin_version( (string) $basename );
				SignalStore::record(
					'change.plugin_updated',
					'info',
					sprintf( 'Plugin updated: %s', $slug ),
					array( 'type' => 'plugin', 'id' => $slug, 'version' => $new_version ),
					$this->is_self( $slug ) ? array( 'self' => true ) : array()
				);
				if ( ! $this->is_self( $slug ) ) {
					SignalStore::note_component_fix( $slug, 'updated', $new_version );
				}
			}
		} elseif ( 'theme' === $extra['type'] ) {
			foreach ( (array) ( $extra['themes'] ?? array() ) as $slug ) {
				$theme_version = (string) wp_get_theme( (string) $slug )->get( 'Version' );
				SignalStore::record(
					'change.theme_updated',
					'info',
					sprintf( 'Theme updated: %s', $slug ),
					array( 'type' => 'theme', 'id' => (string) $slug, 'version' => $theme_version )
				);
				SignalStore::note_component_fix( (string) $slug, 'updated', $theme_version );
			}
		} elseif ( 'core' === $extra['type'] ) {
			$this->on_core_updated( get_bloginfo( 'version' ) );
		}
	}

	/**
	 * Plugin activated.
	 *
	 * @param string $basename Plugin basename.
	 */
	public function on_plugin_activated( $basename ) {
		$slug = strtok( (string) $basename, '/' );
		SignalStore::record( 'change.plugin_activated', 'info', sprintf( 'Plugin activated: %s', $slug ), array( 'type' => 'plugin', 'id' => $slug, 'version' => $this->plugin_version( (string) $basename ) ), $this->is_self( $slug ) ? array( 'self' => true ) : array() );
	}

	// (Re)activation is deliberately NOT a fix event: activating a plugin
	// does not repair its errors.


	/**
	 * Plugin deactivated.
	 *
	 * @param string $basename Plugin basename.
	 */
	public function on_plugin_deactivated( $basename ) {
		$slug = strtok( (string) $basename, '/' );
		SignalStore::record( 'change.plugin_deactivated', 'info', sprintf( 'Plugin deactivated: %s', $slug ), array( 'type' => 'plugin', 'id' => $slug, 'version' => $this->plugin_version( (string) $basename ) ), $this->is_self( $slug ) ? array( 'self' => true ) : array() );
		if ( ! $this->is_self( $slug ) ) {
			SignalStore::note_component_fix( $slug, 'deactivated' );
		}
	}

	/**
	 * Theme switched.
	 *
	 * @param string $name  New theme name.
	 * @param object $theme WP_Theme.
	 */
	public function on_theme_switch( $name, $theme ) {
		SignalStore::record( 'change.theme_switched', 'info', sprintf( 'Theme switched to %s', $name ), array( 'type' => 'theme', 'id' => $theme->get_stylesheet(), 'version' => (string) $theme->get( 'Version' ) ) );
	}

	/**
	 * Core updated.
	 *
	 * @param string $version New version.
	 */
	public function on_core_updated( $version ) {
		SignalStore::record( 'change.core_updated', 'info', sprintf( 'WordPress core updated to %s', $version ), array( 'type' => 'core', 'id' => 'wordpress', 'version' => (string) $version ) );
	}

	/**
	 * Option changed — allowlist only.
	 *
	 * @param string $option Option name.
	 * @param mixed  $old    Old value.
	 * @param mixed  $value  New value.
	 */
	public function on_option_updated( $option, $old, $value ) {
		if ( ! in_array( $option, self::OPTION_ALLOWLIST, true ) ) {
			return;
		}
		if ( 'active_plugins' === $option ) {
			return; // Covered precisely by (de)activated_plugin above.
		}

		SignalStore::record(
			'change.option_changed',
			'info',
			sprintf( 'Option changed: %s', $option ),
			ComponentResolver::from_trace( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 ) ), // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- attribution: which component changed the option.
			array(
				'option' => $option,
				'old'    => $this->printable( $old ),
				'new'    => $this->printable( $value ),
			)
		);
	}

	/**
	 * Values become short printable strings — never dumps.
	 *
	 * @param mixed $value Any value.
	 * @return string
	 */
	private function printable( $value ) {
		if ( is_scalar( $value ) || null === $value ) {
			return substr( (string) $value, 0, 200 );
		}

		return substr( (string) wp_json_encode( $value ), 0, 200 );
	}

	/**
	 * Version from a plugin basename.
	 *
	 * @param string $basename Basename.
	 * @return string
	 */
	private function plugin_version( $basename ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all = get_plugins();

		return (string) ( $all[ $basename ]['Version'] ?? '' );
	}

		/**
	 * Whether a plugin slug is the Detective family itself (free, pro or
	 * this white-label build). Own changes are recorded for the history,
	 * but flagged so no correlator ever lists us as a suspect.
	 *
	 * @param string $slug Plugin directory slug.
	 * @return bool
	 */
	private function is_self( $slug ) {
		$own = array( dirname( RAVNDET_BASENAME ), RAVNDET_SLUG, RAVNDET_SLUG . '-pro' );

		return in_array( (string) $slug, $own, true );
	}

	/**
	 * Detect environment drift: PHP, database server or WordPress version
	 * changed since last seen. Host-side upgrades (new PHP in the control
	 * panel, MySQL→MariaDB migration) fire no WordPress hook — this compare
	 * is the only way to catch them, and they are prime correlation
	 * material ("plugin X fatal 10 min after PHP 8.1 → 8.3").
	 */
	public function check_environment() {
		global $wp_version, $wpdb;

		$current = array(
			'php' => PHP_VERSION,
			'db'  => (string) $wpdb->db_server_info(),
			'wp'  => (string) $wp_version,
		);

		$known = get_option( 'ravndet_environment' );
		if ( ! is_array( $known ) ) {
			update_option( 'ravndet_environment', $current, true );

			return;
		}
		if ( $known === $current ) {
			return;
		}

		if ( ( $known['php'] ?? '' ) !== $current['php'] ) {
			SignalStore::record(
				'change.php_version',
				'info',
				sprintf( 'PHP version changed: %s -> %s', $known['php'] ?? '?', $current['php'] ),
				array(
					'type' => 'server',
					'id'   => 'php',
				),
				array(
					'from' => (string) ( $known['php'] ?? '' ),
					'to'   => $current['php'],
				)
			);
		}
		if ( ( $known['db'] ?? '' ) !== $current['db'] ) {
			SignalStore::record(
				'change.db_version',
				'info',
				sprintf( 'Database server changed: %s -> %s', $known['db'] ?? '?', $current['db'] ),
				array(
					'type' => 'server',
					'id'   => 'database',
				),
				array(
					'from' => (string) ( $known['db'] ?? '' ),
					'to'   => $current['db'],
				)
			);
		}
		if ( ( $known['wp'] ?? '' ) !== $current['wp'] ) {
			SignalStore::record(
				'change.core_updated',
				'info',
				sprintf( 'WordPress version changed: %s -> %s', $known['wp'] ?? '?', $current['wp'] ),
				array(
					'type'    => 'core',
					'id'      => 'wordpress',
					'version' => $current['wp'],
				),
				array(
					'from' => (string) ( $known['wp'] ?? '' ),
					'to'   => $current['wp'],
				)
			);
		}

		update_option( 'ravndet_environment', $current, true );
	}
}
