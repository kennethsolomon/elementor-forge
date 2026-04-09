<?php
/**
 * Default plugin settings.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Settings;

use ElementorForge\Safety\Mode;

/**
 * Canonical defaults for the plugin-level toggles.
 *
 * The original four (acf_mode, ucaddon_shim, mcp_server, header_pattern) were
 * locked with Kenneth on 2026-04-09. The two safety keys were added in v0.4.0
 * to back the scope_mode feature — default is {@see Mode::FULL} so existing
 * installs preserve prior behavior on upgrade.
 */
final class Defaults {

	public const ACF_MODE_FREE = 'free';
	public const ACF_MODE_PRO  = 'pro';

	public const UCADDON_SHIM_PRESERVE = 'preserve';
	public const UCADDON_SHIM_STRIP    = 'strip';

	public const MCP_SERVER_ENABLED  = 'enabled';
	public const MCP_SERVER_DISABLED = 'disabled';

	public const HEADER_PATTERN_SERVICE_BUSINESS = 'service_business';
	public const HEADER_PATTERN_ECOMMERCE        = 'ecommerce';

	/**
	 * Return the canonical default settings.
	 *
	 * @return array{acf_mode: string, ucaddon_shim: string, mcp_server: string, header_pattern: string, safety_mode: string, safety_allowed_post_ids: string}
	 */
	public static function all(): array {
		return array(
			'acf_mode'                => self::ACF_MODE_FREE,
			'ucaddon_shim'            => self::UCADDON_SHIM_PRESERVE,
			'mcp_server'              => self::MCP_SERVER_ENABLED,
			'header_pattern'          => self::HEADER_PATTERN_SERVICE_BUSINESS,
			'safety_mode'             => Mode::FULL,
			'safety_allowed_post_ids' => '',
		);
	}
}
