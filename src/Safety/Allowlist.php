<?php
/**
 * Post ID allowlist for the Elementor Forge safety feature.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Safety;

use ElementorForge\Settings\OptionKeys;

/**
 * Immutable value object representing the list of post IDs the add_section
 * tool is allowed to modify when scope mode is {@see Mode::PAGE_ONLY}.
 *
 * Stored on disk as a comma-separated string in the plugin settings option so
 * it round-trips cleanly through the WP Settings API without nested-array
 * sanitization. Parsing normalizes whitespace, strips duplicates, and rejects
 * any value that is not a positive integer.
 */
final class Allowlist {

	/**
	 * @var list<int>
	 */
	private array $post_ids;

	/**
	 * @param array<int, int|string> $post_ids
	 */
	public function __construct( array $post_ids = array() ) {
		$clean = array();
		foreach ( $post_ids as $value ) {
			$int = (int) $value;
			if ( $int > 0 ) {
				$clean[] = $int;
			}
		}
		$clean = array_values( array_unique( $clean ) );
		sort( $clean );
		$this->post_ids = $clean;
	}

	/**
	 * Parse a comma-separated string. Accepts whitespace, tolerates trailing
	 * commas, ignores empty tokens. Any non-positive integer (0, negatives, or
	 * non-numeric strings) is stripped silently so the caller never has to
	 * handle a partial input error.
	 */
	public static function from_string( string $csv ): self {
		if ( '' === trim( $csv ) ) {
			return new self( array() );
		}
		$parts = preg_split( '/[,\s]+/', $csv );
		if ( false === $parts ) {
			return new self( array() );
		}
		$ids = array();
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( '' === $part ) {
				continue;
			}
			if ( ! preg_match( '/^\d+$/', $part ) ) {
				continue;
			}
			$int = (int) $part;
			if ( $int > 0 ) {
				$ids[] = $int;
			}
		}
		return new self( $ids );
	}

	/**
	 * Read the allowlist from the stored plugin settings.
	 *
	 * Kept as a separate factory so unit tests can instantiate an Allowlist
	 * without stubbing get_option().
	 */
	public static function from_stored(): self {
		if ( ! function_exists( 'get_option' ) ) {
			return new self( array() );
		}
		$settings = get_option( OptionKeys::SETTINGS, array() );
		if ( ! is_array( $settings ) ) {
			return new self( array() );
		}
		$raw = isset( $settings['safety_allowed_post_ids'] ) && is_string( $settings['safety_allowed_post_ids'] )
			? $settings['safety_allowed_post_ids']
			: '';
		return self::from_string( $raw );
	}

	public function contains( int $post_id ): bool {
		return in_array( $post_id, $this->post_ids, true );
	}

	public function is_empty(): bool {
		return array() === $this->post_ids;
	}

	/**
	 * @return list<int>
	 */
	public function to_array(): array {
		return $this->post_ids;
	}

	public function to_string(): string {
		return implode( ',', $this->post_ids );
	}
}
