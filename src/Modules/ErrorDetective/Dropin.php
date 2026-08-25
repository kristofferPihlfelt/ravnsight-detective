<?php
/**
 * Manages the optional fatal-error-handler.php drop-in. The drop-in catches
 * fatals that occur BEFORE plugins_loaded (where our shutdown handler is
 * not yet armed) — e.g. a parse error in another plugin's bootstrap.
 *
 * Iron rules (master plan §10.1 spirit): if a foreign drop-in exists we
 * REFUSE to install and tell the user — never overwrite, never wrap blindly.
 * Our drop-in always delegates to WordPress core's handler afterwards.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Modules\ErrorDetective;

defined( 'ABSPATH' ) || exit;

final class Dropin {

	const MARKER = 'Ravnsight Detective drop-in';

	/**
	 * Path to the drop-in location.
	 *
	 * @return string
	 */
	public static function path() {
		return WP_CONTENT_DIR . '/fatal-error-handler.php';
	}

	/**
	 * Status: 'absent' | 'ours' | 'foreign'.
	 *
	 * @return string
	 */
	public static function status() {
		$path = self::path();
		if ( ! file_exists( $path ) ) {
			return 'absent';
		}

		return str_contains( (string) file_get_contents( $path ), self::MARKER ) ? 'ours' : 'foreign';
	}

	/**
	 * Install the drop-in. Refuses over a foreign one.
	 *
	 * @return true|\WP_Error
	 */
	public static function install() {
		if ( 'foreign' === self::status() ) {
			return new \WP_Error( 'ravndet_dropin_foreign', __( 'Another fatal-error-handler.php already exists. Ravnsight Detective will not overwrite it — remove it first if you want early-fatal capture.', 'ravnsight-detective' ) );
		}
		if ( ! wp_is_writable( WP_CONTENT_DIR ) ) {
			return new \WP_Error( 'ravndet_dropin_unwritable', __( 'wp-content is not writable.', 'ravnsight-detective' ) );
		}

		$table = $GLOBALS['wpdb']->prefix . 'ravndet_signals';
		$code  = self::template( $table );
		file_put_contents( self::path(), $code ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP_Filesystem is not reliably available for wp-content root writes from admin-post; path is fixed and content is generated, not user input.

		return true;
	}

	/**
	 * Remove OUR drop-in (never a foreign one).
	 */
	public static function uninstall() {
		if ( 'ours' === self::status() ) {
			wp_delete_file( self::path() );
		}
	}

	/**
	 * The drop-in source. Standalone on purpose: at fatal time our plugin
	 * may be the thing that failed to load, so it depends only on core.
	 *
	 * @param string $table Signals table name.
	 * @return string
	 */
	private static function template( $table ) {
		$marker = self::MARKER;

		$template = (string) file_get_contents( __DIR__ . '/dropin-template.tpl' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local bundled template.

		return str_replace( array( '{{MARKER}}', '{{TABLE}}' ), array( $marker, $table ), $template );
	}
}
