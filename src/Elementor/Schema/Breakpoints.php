<?php
/**
 * Elementor v0.4 responsive breakpoint suffixes.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Schema;

/**
 * Canonical list of the responsive breakpoint suffixes Elementor v0.4 uses
 * when layering responsive overrides on a setting key.
 *
 * Example: `padding` is the desktop value; `padding_tablet`, `padding_mobile`,
 * `padding_laptop`, `padding_widescreen` are the overrides.
 */
final class Breakpoints {

	public const DESKTOP    = '';
	public const LAPTOP     = '_laptop';
	public const TABLET     = '_tablet';
	public const TABLET_EXT = '_tablet_extra';
	public const MOBILE     = '_mobile';
	public const MOBILE_EXT = '_mobile_extra';
	public const WIDESCREEN = '_widescreen';

	/**
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::DESKTOP,
			self::LAPTOP,
			self::TABLET,
			self::TABLET_EXT,
			self::MOBILE,
			self::MOBILE_EXT,
			self::WIDESCREEN,
		);
	}
}
