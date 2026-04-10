<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Settings;

use ElementorForge\Safety\Mode;
use ElementorForge\Settings\Defaults;
use PHPUnit\Framework\TestCase;

final class DefaultsTest extends TestCase {

	public function test_acf_mode_constants_match_expected_strings(): void {
		$this->assertSame( 'free', Defaults::ACF_MODE_FREE );
		$this->assertSame( 'pro', Defaults::ACF_MODE_PRO );
	}

	public function test_ucaddon_shim_constants_match_expected_strings(): void {
		$this->assertSame( 'preserve', Defaults::UCADDON_SHIM_PRESERVE );
		$this->assertSame( 'strip', Defaults::UCADDON_SHIM_STRIP );
	}

	public function test_mcp_server_constants_match_expected_strings(): void {
		$this->assertSame( 'enabled', Defaults::MCP_SERVER_ENABLED );
		$this->assertSame( 'disabled', Defaults::MCP_SERVER_DISABLED );
	}

	public function test_header_pattern_constants_match_expected_strings(): void {
		$this->assertSame( 'service_business', Defaults::HEADER_PATTERN_SERVICE_BUSINESS );
		$this->assertSame( 'ecommerce', Defaults::HEADER_PATTERN_ECOMMERCE );
	}

	public function test_all_returns_six_keys(): void {
		$all = Defaults::all();
		$this->assertCount( 6, $all );
	}

	public function test_all_contains_expected_keys(): void {
		$all = Defaults::all();
		$expected_keys = array(
			'acf_mode',
			'ucaddon_shim',
			'mcp_server',
			'header_pattern',
			'safety_mode',
			'safety_allowed_post_ids',
		);
		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $all, "Missing key: {$key}" );
		}
	}

	public function test_all_default_values_are_correct(): void {
		$all = Defaults::all();
		$this->assertSame( Defaults::ACF_MODE_FREE, $all['acf_mode'] );
		$this->assertSame( Defaults::UCADDON_SHIM_PRESERVE, $all['ucaddon_shim'] );
		$this->assertSame( Defaults::MCP_SERVER_ENABLED, $all['mcp_server'] );
		$this->assertSame( Defaults::HEADER_PATTERN_SERVICE_BUSINESS, $all['header_pattern'] );
		$this->assertSame( Mode::FULL, $all['safety_mode'] );
		$this->assertSame( '', $all['safety_allowed_post_ids'] );
	}

	public function test_safety_mode_default_is_full(): void {
		$all = Defaults::all();
		$this->assertSame( 'full', $all['safety_mode'] );
	}

	public function test_safety_allowed_post_ids_default_is_empty_string(): void {
		$all = Defaults::all();
		$this->assertSame( '', $all['safety_allowed_post_ids'] );
	}

	public function test_all_returns_only_string_values(): void {
		foreach ( Defaults::all() as $key => $value ) {
			$this->assertIsString( $value, "Value for key '{$key}' should be a string" );
		}
	}
}
