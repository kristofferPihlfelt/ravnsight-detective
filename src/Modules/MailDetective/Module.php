<?php
/**
 * MailDetective wiring.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Modules\MailDetective;

use Ravnsight\Detective\Core\ModuleInterface;
use Ravnsight\Detective\Core\SignalStore;
use Ravnsight\Detective\Support\ComponentResolver;

defined( 'ABSPATH' ) || exit;

final class Module implements ModuleInterface {

	/**
	 * Module key.
	 *
	 * @return string
	 */
	public static function key() {
		return 'mail_detective';
	}

	/**
	 * Hook mail failures.
	 */
	public function boot() {
		add_action( 'wp_mail_failed', array( $this, 'on_mail_failed' ) );
	}

	/**
	 * Record a failed wp_mail() with the transport's real error.
	 *
	 * @param \WP_Error $error Error from PHPMailer.
	 */
	public function on_mail_failed( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return;
		}
		$data    = (array) $error->get_error_data();
		$to      = (array) ( $data['to'] ?? array() );
		$domains = array();
		foreach ( $to as $address ) {
			$at = strrchr( (string) $address, '@' );
			if ( $at ) {
				$domains[] = substr( $at, 1 );
			}
		}

		SignalStore::record(
			'error.mail_failed',
			'critical',
			$error->get_error_message(),
			ComponentResolver::from_trace( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 15 ) ), // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- attribution: which component tried to send.
			array(
				'to_domains' => array_values( array_unique( $domains ) ),
				'subject'    => substr( (string) ( $data['subject'] ?? '' ), 0, 120 ),
			)
		);
	}
}
