<?php
/**
 * Error handling utilities.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Support;

use RuntimeException;
use WP_Error;

/**
 * Shared error-handling helpers.
 */
final class ErrorHandling {

	/**
	 * Throw a RuntimeException if the value is a WP_Error.
	 *
	 * Use at the boundary between WordPress API calls and internal logic
	 * to convert WP_Error into exceptions for cleaner control flow.
	 *
	 * @param mixed $value Return value from a WordPress function.
	 * @return void
	 * @throws RuntimeException If $value is a WP_Error.
	 */
	public static function throw_if_wp_error( $value ): void {
		if ( is_wp_error( $value ) ) {
			/** @var WP_Error $value */
			throw new RuntimeException(
				$value->get_error_message() ? $value->get_error_message() : 'Unknown WordPress error.',
				(int) $value->get_error_code()
			);
		}
	}
}
