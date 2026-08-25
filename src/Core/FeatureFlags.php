<?php
/**
 * Per-module kill switches (module rule 5).
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Core;

defined( 'ABSPATH' ) || exit;

final class FeatureFlags {

	const OPTION = 'ravndet_module_flags';

	/**
	 * Is a module enabled? Default: yes.
	 *
	 * @param string $key Module key.
	 * @return bool
	 */
	public static function enabled( $key ) {
		$flags = get_option( self::OPTION, array() );

		return ! isset( $flags[ $key ] ) || (bool) $flags[ $key ];
	}

	/**
	 * Persist flags from the settings screen.
	 *
	 * @param array $flags key => bool.
	 */
	public static function save( array $flags ) {
		update_option( self::OPTION, array_map( 'boolval', $flags ), false );
	}
}
