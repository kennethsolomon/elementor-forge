<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Settings;

use ElementorForge\Settings\OptionKeys;
use PHPUnit\Framework\TestCase;

final class OptionKeysTest extends TestCase {

	public function test_version_constant(): void {
		$this->assertSame( 'elementor_forge_version', OptionKeys::VERSION );
	}

	public function test_activated_at_constant(): void {
		$this->assertSame( 'elementor_forge_activated_at', OptionKeys::ACTIVATED_AT );
	}

	public function test_settings_constant(): void {
		$this->assertSame( 'elementor_forge_settings', OptionKeys::SETTINGS );
	}

	public function test_schema_version_constant(): void {
		$this->assertSame( 'elementor_forge_schema_version', OptionKeys::SCHEMA_VERSION );
	}

	public function test_ss3_cache_dirty_constant(): void {
		$this->assertSame( 'elementor_forge_ss3_cache_dirty', OptionKeys::SS3_CACHE_DIRTY );
	}

	public function test_safety_mode_constant(): void {
		$this->assertSame( 'safety_mode', OptionKeys::SAFETY_MODE );
	}

	public function test_safety_allowed_post_ids_constant(): void {
		$this->assertSame( 'safety_allowed_post_ids', OptionKeys::SAFETY_ALLOWED_POST_IDS );
	}

	public function test_all_returns_five_top_level_option_keys(): void {
		$all = OptionKeys::all();
		$this->assertCount( 5, $all );
	}

	public function test_all_contains_version(): void {
		$this->assertContains( OptionKeys::VERSION, OptionKeys::all() );
	}

	public function test_all_contains_activated_at(): void {
		$this->assertContains( OptionKeys::ACTIVATED_AT, OptionKeys::all() );
	}

	public function test_all_contains_settings(): void {
		$this->assertContains( OptionKeys::SETTINGS, OptionKeys::all() );
	}

	public function test_all_contains_schema_version(): void {
		$this->assertContains( OptionKeys::SCHEMA_VERSION, OptionKeys::all() );
	}

	public function test_all_contains_ss3_cache_dirty(): void {
		$this->assertContains( OptionKeys::SS3_CACHE_DIRTY, OptionKeys::all() );
	}

	public function test_all_does_not_include_safety_sub_keys(): void {
		$all = OptionKeys::all();
		$this->assertNotContains( OptionKeys::SAFETY_MODE, $all );
		$this->assertNotContains( OptionKeys::SAFETY_ALLOWED_POST_IDS, $all );
	}

	public function test_all_keys_are_prefixed_with_elementor_forge(): void {
		foreach ( OptionKeys::all() as $key ) {
			$this->assertStringStartsWith( 'elementor_forge_', $key, "Key '{$key}' should start with 'elementor_forge_'" );
		}
	}

	public function test_all_returns_only_strings(): void {
		foreach ( OptionKeys::all() as $key ) {
			$this->assertIsString( $key );
		}
	}
}
