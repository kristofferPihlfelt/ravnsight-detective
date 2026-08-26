<?php
/**
 * {{MARKER}} — records fatal errors to the Detective signals table, then
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
			$error = error_get_last();
			if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) && isset( $GLOBALS['wpdb'] ) ) {
				$message = preg_replace( '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}/', '[redacted]', (string) $error['message'] );
				// Same path truncation as the runtime Redactor — identical input, identical fingerprint, ONE row.
				$message = preg_replace( '#(?:/[^\\s:]+)*/(wp-(?:content|includes|admin)/)#', '…/$1', $message );
				$file    = (string) $error['file'];
				$slug    = '';
				if ( preg_match( '#/wp-content/plugins/([^/]+)/#', $file, $m ) ) {
					$slug = $m[1];
				} elseif ( preg_match( '#/wp-content/themes/([^/]+)/#', $file, $m ) ) {
					$slug = 'theme:' . $m[1];
				}
				$ctype       = $slug && ! str_starts_with( $slug, 'theme:' ) ? 'plugin' : ( $slug ? 'theme' : 'server' );
				$cid         = str_replace( 'theme:', '', $slug );
				// Same formula as SignalStore::fingerprint — the drop-in and the runtime handler must land on the SAME row.
				$fingerprint = substr( hash( 'sha256', 'error.php_fatal|' . preg_replace( '/\\d+/', 'N', $message ) . '|' . $ctype . '|' . $cid ), 0, 40 );
				$wpdb        = $GLOBALS['wpdb'];
				$updated     = $wpdb->query( $wpdb->prepare( 'UPDATE {{TABLE}} SET count = count + 1, last_seen = %d WHERE fingerprint = %s', time(), $fingerprint ) );
				if ( ! $updated ) {
					$wpdb->insert( '{{TABLE}}', array(
						'type'           => 'error.php_fatal',
						'fingerprint'    => $fingerprint,
						'severity'       => 'critical',
						'component_type' => $ctype,
						'component_id'   => $cid,
						'message'        => substr( $message, 0, 60000 ),
						'context'        => json_encode( array_filter( array( 'file' => $file, 'line' => $error['line'], 'early' => true, 'cli' => defined( 'WP_CLI' ) && WP_CLI ? true : null ) ) ),
						'scope'          => isset( $_SERVER['REQUEST_URI'] ) ? substr( preg_replace( '/=([^&#]*)/', '=[redacted]', (string) $_SERVER['REQUEST_URI'] ), 0, 128 ) : null,
						'count'          => 1,
						'first_seen'     => time(),
						'last_seen'      => time(),
					) );
				}
			}
		} catch ( \Throwable $t ) { // phpcs:ignore
			// The recorder must never interfere with fatal handling.
		}

		parent::handle();
	}
}

return new Ravnsight_Detective_Fatal_Handler();
