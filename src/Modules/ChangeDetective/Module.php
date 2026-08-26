<?php
/**
 * ChangeDetective module wiring.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Modules\ChangeDetective;

use Ravnsight\Detective\Core\ModuleInterface;

defined( 'ABSPATH' ) || exit;

final class Module implements ModuleInterface {

	/**
	 * Module key.
	 *
	 * @return string
	 */
	public static function key() {
		return 'change_detective';
	}

	/**
	 * Attach hooks.
	 */
	public function boot() {
		$recorder = new Recorder();

		add_action( 'upgrader_process_complete', array( $recorder, 'on_upgrade' ), 10, 2 );
		add_action( 'activated_plugin', array( $recorder, 'on_plugin_activated' ) );
		add_action( 'deactivated_plugin', array( $recorder, 'on_plugin_deactivated' ) );
		add_action( 'switch_theme', array( $recorder, 'on_theme_switch' ), 10, 2 );
		add_action( '_core_updated_successfully', array( $recorder, 'on_core_updated' ) );
		add_action( 'updated_option', array( $recorder, 'on_option_updated' ), 10, 3 );
		add_action( 'ravndet_daily', array( Snapshot::class, 'take' ) );

		// Environment drift: PHP/database/WordPress version changed under
		// our feet (host-side upgrades never fire WP hooks). One autoloaded
		// option compare per request; signals only on actual change.
		add_action( 'init', array( $recorder, 'check_environment' ), 5 );
	}
}
