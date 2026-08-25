<?php
/**
 * Contract every module fulfils.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Core;

defined( 'ABSPATH' ) || exit;

interface ModuleInterface {

	/**
	 * Stable module key used by FeatureFlags and settings.
	 *
	 * @return string
	 */
	public static function key();

	/**
	 * Attach hooks. Called once on plugins_loaded when enabled.
	 */
	public function boot();
}
