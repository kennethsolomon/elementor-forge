<?php
/**
 * Elementor v0.4 spacing + size units.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Schema;

/**
 * Canonical list of measurement units Elementor accepts in spacing,
 * size, and typography settings. Used by {@see \ElementorForge\Elementor\Emitter\Size}
 * and the container spacing helpers to coerce inputs to valid JSON shapes.
 */
final class Units {

	public const PX  = 'px';
	public const EM  = 'em';
	public const REM = 'rem';
	public const PCT = '%';
	public const VH  = 'vh';
	public const VW  = 'vw';
	public const FR  = 'fr';
	public const DEG = 'deg';
	public const S   = 's';
	public const MS  = 'ms';

	/**
	 * Units allowed in spacing dicts (padding, margin, flex_gap).
	 *
	 * @return list<string>
	 */
	public static function spacing(): array {
		return array( self::PX, self::EM, self::REM, self::PCT );
	}

	/**
	 * Units allowed in size dicts (width, height, font-size).
	 *
	 * @return list<string>
	 */
	public static function size(): array {
		return array( self::PX, self::EM, self::REM, self::PCT, self::VH, self::VW, self::FR, self::DEG );
	}
}
