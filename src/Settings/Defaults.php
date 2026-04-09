<?php
/**
 * Default plugin settings.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Settings;

/**
 * Canonical defaults for the four plugin-level toggles locked with Kenneth on 2026-04-09.
 *
 * @psalm-type SettingsArray = array{
 *     acf_mode: 'free'|'pro',
 *     ucaddon_shim: 'preserve'|'strip',
 *     mcp_server: 'enabled'|'disabled',
 *     header_pattern: 'service_business'|'ecommerce'
 * }
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
	 * @return array{acf_mode: string, ucaddon_shim: string, mcp_server: string, header_pattern: string}
	 */
	public static function all(): array {
		return array(
			'acf_mode'       => self::ACF_MODE_FREE,
			'ucaddon_shim'   => self::UCADDON_SHIM_PRESERVE,
			'mcp_server'     => self::MCP_SERVER_ENABLED,
			'header_pattern' => self::HEADER_PATTERN_SERVICE_BUSINESS,
		);
	}
}
