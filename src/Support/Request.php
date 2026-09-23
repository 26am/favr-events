<?php
/**
 * Safe access to query-string values.
 *
 * @package FavrEvents
 */

declare(strict_types=1);

namespace FavrEvents\Support;

/**
 * Query parameters can be arrays (?x[]=1); every public read goes through here so a crafted
 * URL can never reach a string-only sanitizer.
 */
final class Request {

	/**
	 * A GET value as an unslashed string ('' when missing or not a string).
	 *
	 * @param string $key Parameter.
	 */
	public static function get( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- read-only; unslashed below, callers sanitize.
		$value = $_GET[ $key ] ?? '';
		return is_string( $value ) ? (string) wp_unslash( $value ) : '';
	}
}
