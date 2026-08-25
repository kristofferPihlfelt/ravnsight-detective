<?php
/**
 * Scrubs anything that could identify people before it is stored —
 * and, in Pro, before anything is ever transmitted. The rules mirror
 * docs/DATA-POLICY.md in the platform repo; both sides run the same rules.
 * A failure to redact fails CLOSED: callers drop the payload.
 *
 * @package Ravnsight\Detective
 */

namespace Ravnsight\Detective\Support;

defined( 'ABSPATH' ) || exit;

final class Redactor {

	const MASK = '[redacted]';

	/**
	 * Redact free text (error messages). Null on failure.
	 *
	 * @param string $text Raw text.
	 * @return string|null
	 */
	public static function text( $text ) {
		$patterns = array(
			'/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/'  => self::MASK, // e-mail
			'/\b(?:\d[ -]?){13,19}\b/'                          => self::MASK, // card-like
			'/\b[A-Z]{2}\d{2}[A-Z0-9]{10,30}\b/'                => self::MASK, // IBAN-like
			'/\b\+?\d[\d \-()]{7,}\d\b/'                        => self::MASK, // phone-like
		);

		$result = preg_replace( array_keys( $patterns ), array_values( $patterns ), $text );

		return null === $result ? null : self::truncate_path( $result );
	}

	/**
	 * Redact a URI: keep the path, strip every query value.
	 *
	 * @param string $uri Raw URI.
	 * @return string
	 */
	public static function uri( $uri ) {
		if ( '' === $uri ) {
			return '';
		}
		$parts = explode( '?', $uri, 2 );
		$path  = $parts[0];
		if ( ! isset( $parts[1] ) || '' === $parts[1] ) {
			return $path;
		}
		$keys = array();
		foreach ( explode( '&', $parts[1] ) as $pair ) {
			$keys[] = strtok( $pair, '=' ) . '=' . self::MASK;
		}

		return $path . '?' . implode( '&', $keys );
	}

	/**
	 * Redact a context array recursively. Null on failure.
	 *
	 * @param array $context Raw context.
	 * @return array|null
	 */
	public static function context( array $context ) {
		$clean = array();
		foreach ( $context as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = self::context( $value );
				if ( null === $value ) {
					return null;
				}
			} elseif ( is_string( $value ) ) {
				$value = self::text( $value );
				if ( null === $value ) {
					return null;
				}
			} elseif ( ! is_scalar( $value ) && null !== $value ) {
				continue; // Objects/resources are never stored.
			}
			$clean[ (string) $key ] = $value;
		}

		return $clean;
	}

	/**
	 * Truncate absolute paths to wp-content/… — full server paths are
	 * never needed for attribution (DATA-POLICY).
	 *
	 * @param string $text Text possibly containing paths.
	 * @return string
	 */
	public static function truncate_path( $text ) {
		return preg_replace( '#(?:/[^\s:]+)*/(wp-(?:content|includes|admin)/)#', '…/$1', $text );
	}
}
