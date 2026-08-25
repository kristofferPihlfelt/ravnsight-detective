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

		return <<<PHP
<?php
/**
 * {$marker} — records fatal errors to the Detective signals table, then
 * hands over to WordPress core's own fatal handler (recovery mode etc.).
 * Safe to delete at any time; Ravnsight Detective reinstalls it only when
 * asked to in its settings.
 */

if ( ! class_exists( 'WP_Fatal_Error_Handler' ) ) {
	require_once ABSPATH . WPINC . '/class-wp-fatal-error-handler.php';
}

class Ravnsight_Detective_Fatal_Handler extends WP_Fatal_Error_Handler {
	public function handle() {
		try {
			\$error = error_get_last();
			if ( \$error && in_array( \$error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) && isset( \$GLOBALS['wpdb'] ) ) {
				\$message = preg_replace( '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}/', '[redacted]', (string) \$error['message'] );
				// Same path truncation as the runtime Redactor — identical input, identical fingerprint, ONE row.
				\$message = preg_replace( '#(?:/[^\\s:]+)*/(wp-(?:content|includes|admin)/)#', '…/\$1', \$message );
				\$file    = (string) \$error['file'];
				\$slug    = '';
				if ( preg_match( '#/wp-content/plugins/([^/]+)/#', \$file, \$m ) ) {
					\$slug = \$m[1];
				} elseif ( preg_match( '#/wp-content/themes/([^/]+)/#', \$file, \$m ) ) {
					\$slug = 'theme:' . \$m[1];
				}
				\$ctype       = \$slug && ! str_starts_with( \$slug, 'theme:' ) ? 'plugin' : ( \$slug ? 'theme' : 'server' );
				\$cid         = str_replace( 'theme:', '', \$slug );
				// Same formula as SignalStore::fingerprint — the drop-in and the runtime handler must land on the SAME row.
				\$fingerprint = substr( hash( 'sha256', 'error.php_fatal|' . preg_replace( '/\\d+/', 'N', \$message ) . '|' . \$ctype . '|' . \$cid ), 0, 40 );
				\$wpdb        = \$GLOBALS['wpdb'];
				\$updated     = \$wpdb->query( \$wpdb->prepare( 'UPDATE {$table} SET count = count + 1, last_seen = %d WHERE fingerprint = %s', time(), \$fingerprint ) );
				if ( ! \$updated ) {
					\$wpdb->insert( '{$table}', array(
						'type'           => 'error.php_fatal',
						'fingerprint'    => \$fingerprint,
						'severity'       => 'critical',
						'component_type' => \$ctype,
						'component_id'   => \$cid,
						'message'        => substr( \$message, 0, 60000 ),
						'context'        => json_encode( array( 'file' => \$file, 'line' => \$error['line'], 'early' => true ) ),
						'count'          => 1,
						'first_seen'     => time(),
						'last_seen'      => time(),
					) );
				}
			}
		} catch ( \Throwable \$t ) { // phpcs:ignore
			// The recorder must never interfere with fatal handling.
		}

		parent::handle();
	}
}

return new Ravnsight_Detective_Fatal_Handler();
PHP;
	}
}
