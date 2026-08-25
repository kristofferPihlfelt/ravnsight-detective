<?php
/**
 * The one place modules are registered (module rule 4).
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Holds module classes, boots the enabled ones.
 */
final class ModuleRegistry {

	/**
	 * Registered module class names.
	 *
	 * @var string[]
	 */
	private $classes = array();

	/**
	 * Booted module instances, keyed by module key.
	 *
	 * @var array<string, ModuleInterface>
	 */
	private $booted = array();

	/**
	 * Register a module class. One line per module — nothing else.
	 *
	 * @param string $class_name Class implementing ModuleInterface.
	 */
	public function register( $class_name ) {
		$this->classes[] = $class_name;
	}

	/**
	 * Boot every enabled module. A module disabled via FeatureFlags is
	 * simply never constructed — nothing else may break (module rule 5).
	 */
	public function boot() {
		foreach ( $this->classes as $class_name ) {
			$key = $class_name::key();
			if ( ! FeatureFlags::enabled( $key ) ) {
				continue;
			}
			$module = new $class_name();
			$module->boot();
			$this->booted[ $key ] = $module;
		}
	}

	/**
	 * Booted module keys (for the settings screen).
	 *
	 * @return string[]
	 */
	public function booted_keys() {
		return array_keys( $this->booted );
	}

	/**
	 * All registered module keys.
	 *
	 * @return string[]
	 */
	public function all_keys() {
		return array_map( static fn( $c ) => $c::key(), $this->classes );
	}
}
