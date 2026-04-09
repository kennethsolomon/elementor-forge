<?php
/**
 * Elementor Kit global reference helpers.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter;

/**
 * Helpers for building Elementor Kit global-reference strings used in the
 * `__globals__` subobject of any widget or container `settings`. A Kit global
 * looks like:
 *
 *     "globals/colors?id=primary"
 *     "globals/colors?id=5585a52"
 *     "globals/typography?id=70fe5a0"
 *
 * And the consuming widget embeds it like:
 *
 *     "settings": {
 *         "__globals__": {
 *             "title_color": "globals/colors?id=primary"
 *         }
 *     }
 */
final class KitTag {

	public const COLOR_PRIMARY   = 'primary';
	public const COLOR_SECONDARY = 'secondary';
	public const COLOR_TEXT      = 'text';
	public const COLOR_ACCENT    = 'accent';

	/**
	 * Build a Kit global color reference.
	 */
	public static function color( string $color_id ): string {
		return 'globals/colors?id=' . $color_id;
	}

	/**
	 * Build a Kit global typography reference.
	 */
	public static function typography( string $typography_id ): string {
		return 'globals/typography?id=' . $typography_id;
	}

	/**
	 * Wrap a dict of setting-key → global-ref pairs in the `__globals__` shape
	 * so it can be merged directly into a widget's settings array.
	 *
	 * @param array<string, string> $map
	 * @return array{__globals__: array<string, string>}
	 */
	public static function globals( array $map ): array {
		return array( '__globals__' => $map );
	}
}
