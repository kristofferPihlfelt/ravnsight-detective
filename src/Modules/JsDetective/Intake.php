<?php
/**
 * REST intake for front-end JS errors. Public by necessity (visitors are
 * not authenticated) — hardened accordingly.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Modules\JsDetective;

use Ravnsight\Detective\Core\SignalStore;

defined( 'ABSPATH' ) || exit;

final class Intake {

	/**
	 * Register the route.
	 */
	public static function register_route() {
		register_rest_route(
			'ravnsight/v1',
			'/js-error',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( self::class, 'permitted' ),
				'callback'            => array( self::class, 'handle' ),
			)
		);
	}

	/**
	 * Same-site + throttle gate.
	 *
	 * @return bool
	 */
	public static function permitted() {
		// Same-site: the reporter always sends from our own pages.
		$referer = wp_get_raw_referer();
		if ( ! $referer || 0 !== strpos( (string) $referer, home_url( '/' ) ) ) {
			return false;
		}

		// Per-IP throttle: 10 reports/minute is plenty for real errors.
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'ravndet_jsr_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 10 ) {
			return false;
		}
		set_transient( $key, $n + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Record the error.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle( $request ) {
		$message = substr( sanitize_text_field( (string) $request->get_param( 'message' ) ), 0, 500 );
		$source  = substr( sanitize_text_field( (string) $request->get_param( 'source' ) ), 0, 300 );
		$line    = absint( $request->get_param( 'line' ) );
		$page    = substr( sanitize_text_field( (string) $request->get_param( 'page' ) ), 0, 200 );

		if ( '' === $message ) {
			return new \WP_REST_Response( null, 400 );
		}

		// Attribute by script source when it points into our own wp-content.
		$component = array( 'type' => 'external', 'id' => '', 'version' => '' );
		if ( preg_match( '#/wp-content/plugins/([^/]+)/#', $source, $m ) ) {
			$component = array( 'type' => 'plugin', 'id' => $m[1], 'version' => '' );
		} elseif ( preg_match( '#/wp-content/themes/([^/]+)/#', $source, $m ) ) {
			$component = array( 'type' => 'theme', 'id' => $m[1], 'version' => '' );
		}

		SignalStore::record(
			'error.js_error',
			'warning',
			$message,
			$component,
			array( 'source' => $source, 'line' => $line ),
			$page
		);

		return new \WP_REST_Response( null, 204 );
	}
}
