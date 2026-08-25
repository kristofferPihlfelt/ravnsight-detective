<?php
/**
 * ErrorDetective module wiring.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Modules\ErrorDetective;

use Ravnsight\Detective\Core\ModuleInterface;

defined( 'ABSPATH' ) || exit;

final class Module implements ModuleInterface {

	/**
	 * Module key.
	 *
	 * @return string
	 */
	public static function key() {
		return 'error_detective';
	}

	/**
	 * Arm the handlers.
	 */
	public function boot() {
		Handler::arm();
	}
}
