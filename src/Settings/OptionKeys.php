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
	 * Safety sub-setting keys stored inside the SETTINGS option array.
	 * These are NOT top-level WP options — they live inside the plugin
	 * settings row so they share the Settings API pipeline with the other
	 * four toggles.
	 */
	public const SAFETY_MODE             = 'safety_mode';
	public const SAFETY_ALLOWED_POST_IDS = 'safety_allowed_post_ids';

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
