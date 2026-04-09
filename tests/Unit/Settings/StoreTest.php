<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Settings;

use ElementorForge\Settings\Defaults;
use ElementorForge\Settings\Store;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

final class StoreTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_all_falls_back_to_defaults_on_empty_option(): void {
		Functions\expect( 'get_option' )->andReturn( array() );

		$all = Store::all();

		$this->assertSame( Defaults::all(), $all );
	}

	public function test_all_merges_stored_values_over_defaults(): void {
		Functions\expect( 'get_option' )->andReturn( array( 'acf_mode' => 'pro' ) );

		$all = Store::all();

		$this->assertSame( 'pro', $all['acf_mode'] );
		$this->assertSame( Defaults::UCADDON_SHIM_PRESERVE, $all['ucaddon_shim'] );
	}

	public function test_sanitize_rejects_invalid_enum_values(): void {
		$clean = Store::sanitize(
			array(
				'acf_mode'       => 'nonsense',
				'ucaddon_shim'   => 'strip',
				'mcp_server'     => 'enabled',
				'header_pattern' => 'weirdo',
			)
		);

		$this->assertArrayNotHasKey( 'acf_mode', $clean );
		$this->assertArrayNotHasKey( 'header_pattern', $clean );
		$this->assertSame( 'strip', $clean['ucaddon_shim'] );
		$this->assertSame( 'enabled', $clean['mcp_server'] );
	}

	public function test_sanitize_drops_unknown_keys(): void {
		$clean = Store::sanitize( array( 'random_key' => 'value' ) );
		$this->assertEmpty( $clean );
	}

	public function test_boolean_helpers(): void {
		Functions\expect( 'get_option' )->andReturn( array( 'mcp_server' => 'disabled', 'acf_mode' => 'pro', 'ucaddon_shim' => 'strip', 'header_pattern' => 'ecommerce' ) );

		$this->assertFalse( Store::is_mcp_enabled() );
		$this->assertTrue( Store::is_acf_pro_mode() );
		$this->assertFalse( Store::is_ucaddon_preserve() );
		$this->assertTrue( Store::is_ecommerce_header() );
	}
}
