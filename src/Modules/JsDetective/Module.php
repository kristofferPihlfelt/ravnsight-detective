<?php
/**
 * JsDetective wiring: front-end reporter + REST intake.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Modules\JsDetective;

use Ravnsight\Detective\Core\ModuleInterface;

defined( 'ABSPATH' ) || exit;

final class Module implements ModuleInterface {

	/**
	 * Module key.
	 *
	 * @return string
	 */
	public static function key() {
		return 'js_detective';
	}

	/**
	 * Enqueue the reporter and register the intake route.
	 */
	public function boot() {
		add_action( 'rest_api_init', array( Intake::class, 'register_route' ) );
		add_action(
			'wp_enqueue_scripts',
			function () {
				$file = RAVNDET_PATH . 'assets/js/reporter.js';
				wp_enqueue_script( 'ravndet-reporter', RAVNDET_URL . 'assets/js/reporter.js', array(), RAVNDET_VERSION . '-' . (string) filemtime( $file ), array( 'strategy' => 'defer' ) );
				wp_localize_script( 'ravndet-reporter', 'ravndetReporter', array( 'endpoint' => esc_url_raw( rest_url( 'ravnsight/v1/js-error' ) ) ) );
			}
		);
	}
}
