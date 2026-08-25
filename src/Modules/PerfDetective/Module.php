<?php
/**
 * PerfDetective wiring.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Modules\PerfDetective;

use Ravnsight\Detective\Core\ModuleInterface;

defined( 'ABSPATH' ) || exit;

final class Module implements ModuleInterface {

	/**
	 * Module key.
	 *
	 * @return string
	 */
	public static function key() {
		return 'perf_detective';
	}

	/**
	 * All measurement happens on shutdown — nothing during the request.
	 */
	public function boot() {
		register_shutdown_function( array( Profiler::class, 'on_shutdown' ) );
	}
}
