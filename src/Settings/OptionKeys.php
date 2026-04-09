<?php
/**
 * Canonical option keys. Uninstall reads from this list.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Settings;

/**
 * Every option key the plugin writes is declared here. uninstall.php reads this to purge
 * all plugin state on delete. Adding an option anywhere else is a structural bug.
 */
final class OptionKeys {

	public const VERSION = 'elementor_forge_version';
	public const ACTIVATED_AT = 'elementor_forge_activated_at';
	public const SETTINGS = 'elementor_forge_settings';
	public const SCHEMA_VERSION = 'elementor_forge_schema_version';
	public const SS3_CACHE_DIRTY = 'elementor_forge_ss3_cache_dirty';

	/**
	 * Return every option key the plugin writes.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::VERSION,
			self::ACTIVATED_AT,
			self::SETTINGS,
			self::SCHEMA_VERSION,
			self::SS3_CACHE_DIRTY,
		);
	}
}
