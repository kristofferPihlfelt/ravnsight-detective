<?php
/**
 * The error handler: chained, grouping, attributing.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Modules\ErrorDetective;

use Ravnsight\Detective\Core\SignalStore;
use Ravnsight\Detective\Support\ComponentResolver;

defined( 'ABSPATH' ) || exit;

final class Handler {

	/**
	 * The handler we replaced — ALWAYS delegated to.
	 *
	 * @var callable|null
	 */
	private static $previous = null;

	/**
	 * Re-entrancy guard: an error inside our own recording must never recurse.
	 *
	 * @var bool
	 */
	private static $recording = false;

	/**
	 * Register the chained handler and the fatal catcher.
	 */
	public static function arm() {
		self::$previous = set_error_handler( array( self::class, 'on_error' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- error observation IS the product; the previous handler is always delegated to, site behaviour is unchanged.
		register_shutdown_function( array( self::class, 'on_shutdown' ) );
	}

	/**
	 * Error handler. Records, then delegates — behaviour of the site is
	 * never changed by our presence.
	 *
	 * @param int    $errno   Error level.
	 * @param string $errstr  Message.
	 * @param string $errfile File.
	 * @param int    $errline Line.
	 * @return bool
	 */
	public static function on_error( $errno, $errstr, $errfile = '', $errline = 0 ) {
		/*
		 * Record independent of the global error_reporting mask: sites run
		 * with WP_DEBUG off, and the whole point is seeing deprecations,
		 * warnings and notices WITHOUT turning debug display on. The only
		 * thing honoured is the @ operator (PHP 8 sets error_reporting to
		 * exactly the fatal-only mask during suppression).
		 */
		$suppressed = 0 === error_reporting() || ( E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR | E_PARSE ) === error_reporting(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting, PluginCheck.CodeAnalysis.PHPErrorReporting.DirectErrorReportingCall -- READ-ONLY check for @-suppression; nothing is changed.
		if ( ! self::$recording && ! $suppressed ) {
			self::$recording = true;
			try {
				self::record( $errno, (string) $errstr, (string) $errfile, (int) $errline );
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- recording must never break the site.
				// Swallow: the detective must never be the culprit.
			}
			self::$recording = false;
		}

		if ( is_callable( self::$previous ) ) {
			return (bool) call_user_func( self::$previous, $errno, $errstr, $errfile, $errline );
		}

		return false; // Let PHP's internal handler proceed as if we were not here.
	}

	/**
	 * Fatal/OOM catcher.
	 */
	public static function on_shutdown() {
		self::record_db_errors();
		$error = error_get_last();
		if ( null === $error || ! in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			return;
		}
		if ( self::$recording ) {
			return;
		}
		self::$recording = true;
		try {
			self::record( $error['type'], (string) $error['message'], (string) $error['file'], (int) $error['line'] );
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- shutdown must stay silent.
			// Swallow.
		}
		self::$recording = false;
	}

	/**
	 * Map, attribute and store.
	 *
	 * @param int    $errno Error level.
	 * @param string $message Message.
	 * @param string $file File.
	 * @param int    $line Line.
	 */
	private static function record( $errno, $message, $file, $line ) {
		list( $type, $severity ) = self::classify( $errno );
		$component               = ComponentResolver::from_file( $file );
		if ( 'plugin' === $component['type'] && 'ravnsight-detective' === $component['id'] ) {
			return; // Own noise is a bug to fix, not a signal to show.
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only diagnostic scope, redacted before storage.

		SignalStore::record(
			$type,
			$severity,
			$message,
			$component,
			array_filter(
				array(
					'file' => $file,
					'line' => $line,
					// Errors under WP-CLI (host cron, deploy scripts, wp eval)
					// never hit a visitor — mark them so nobody chases a
					// "works in the browser" ghost.
					'cli'  => defined( 'WP_CLI' ) && WP_CLI ? true : null,
				)
			),
			$uri
		);
	}

	/**
	 * Database errors collected by wpdb during the request ($EZSQL_ERROR).
	 * Zero cost when the request had none.
	 */
	private static function record_db_errors() {
		global $EZSQL_ERROR;

		if ( empty( $EZSQL_ERROR ) || ! is_array( $EZSQL_ERROR ) || self::$recording ) {
			return;
		}
		self::$recording = true;
		try {
			foreach ( array_slice( $EZSQL_ERROR, 0, 10 ) as $db_error ) {
				$query = (string) ( $db_error['query'] ?? '' );
				// Normalise: strip literals so the same broken query is ONE row.
				$shape = preg_replace( array( "/'[^']*'/", '/\d+/' ), array( '?', 'N' ), $query );
				\Ravnsight\Detective\Core\SignalStore::record(
					'error.db_error',
					'critical',
					(string) ( $db_error['error_str'] ?? 'Database error' ),
					\Ravnsight\Detective\Support\ComponentResolver::from_trace( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 ) ), // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- attribution.
					array( 'query_shape' => substr( (string) $shape, 0, 500 ) )
				);
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- shutdown must stay silent.
			// Swallow.
		}
		self::$recording = false;
	}

	/**
	 * PHP error level → signal type + severity (contracts/signal-types.md).
	 *
	 * @param int $errno Error level.
	 * @return array{0: string, 1: string}
	 */
	private static function classify( $errno ) {
		switch ( $errno ) {
			case E_ERROR:
			case E_PARSE:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
			case E_RECOVERABLE_ERROR:
				return array( 'error.php_fatal', 'critical' );
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				return array( 'error.php_deprecated', 'info' );
			default:
				return array( 'error.php_warning', 'warning' );
		}
	}
}
