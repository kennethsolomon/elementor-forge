<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\Elementor\Footer\FooterPresets;
use ElementorForge\MCP\Tools\CreateFooter;
use ElementorForge\Safety\Gate;
use ElementorForge\Safety\Mode;
use ElementorForge\Settings\Store;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class CreateFooterTest extends TestCase {

	/** @var int */
	private int $next_id = 300;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Store::flush_cache();

		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'absint' )->alias( static fn ( $v ): int => abs( (int) $v ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof WP_Error );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn(
			array(
				'safety_mode'             => 'full',
				'safety_allowed_post_ids' => '',
			)
		);

		// WP stubs needed by Installer / Encoder / CacheClearer.
		Functions\when( 'wp_json_encode' )->alias(
			static fn ( $data, int $options = 0, int $depth = 512 ) => json_encode( $data, $options, $depth )
		);
		Functions\when( 'wp_slash' )->alias(
			static function ( $value ) {
				if ( is_array( $value ) ) {
					return array_map( 'wp_slash', $value );
				}
				return is_string( $value ) ? addslashes( $value ) : $value;
			}
		);
		Functions\when( 'wp_insert_post' )->alias(
			function () {
				return $this->next_id++;
			}
		);
		Functions\when( 'wp_update_post' )->alias( static fn ( array $arr ) => (int) ( $arr['ID'] ?? 0 ) );
		Functions\when( 'update_post_meta' )->justReturn( true );
		Functions\when( 'get_posts' )->justReturn( array() );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.com/footer' );
		Functions\when( 'delete_post_meta' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );
		// FooterPresets::copyright_text calls wp_kses_post when overriding.
		Functions\when( 'wp_kses_post' )->returnArg();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}


	public function test_input_schema_requires_preset(): void {
		$schema = CreateFooter::input_schema();

		$this->assertSame( 'object', $schema['type'] );
		$this->assertContains( 'preset', $schema['required'] );
	}

	public function test_input_schema_preset_enum_matches_presets_constant(): void {
		$schema = CreateFooter::input_schema();

		$this->assertSame( FooterPresets::PRESETS, $schema['properties']['preset']['enum'] );
	}


	public function test_output_schema_has_post_id_preset_url(): void {
		$schema = CreateFooter::output_schema();

		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'post_id', $schema['properties'] );
		$this->assertArrayHasKey( 'preset', $schema['properties'] );
		$this->assertArrayHasKey( 'url', $schema['properties'] );
		$this->assertSame( 'integer', $schema['properties']['post_id']['type'] );
	}


	public function test_permission_requires_manage_options(): void {
		$this->assertTrue( CreateFooter::permission() );
	}


	public function test_execute_respects_gate_check_in_read_only_mode(): void {
		Store::flush_cache();
		Functions\when( 'get_option' )->justReturn(
			array(
				'safety_mode'             => Mode::READ_ONLY,
				'safety_allowed_post_ids' => '',
			)
		);

		$result = CreateFooter::execute( array( 'preset' => 'simple' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}


	public function test_execute_returns_error_for_invalid_preset(): void {
		$result = CreateFooter::execute( array( 'preset' => 'nonexistent_preset' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_preset', $result->get_error_code() );
	}


	public function test_execute_calls_installer_and_returns_post_id_on_success(): void {
		$result = CreateFooter::execute( array( 'preset' => 'simple' ) );

		$this->assertIsArray( $result );
		$this->assertGreaterThan( 0, $result['post_id'] );
	}

	public function test_execute_returns_preset_in_result(): void {
		$result = CreateFooter::execute( array( 'preset' => 'minimal' ) );

		$this->assertIsArray( $result );
		$this->assertSame( 'minimal', $result['preset'] );
	}

	public function test_execute_returns_url_in_result(): void {
		$result = CreateFooter::execute( array( 'preset' => 'simple' ) );

		$this->assertIsArray( $result );
		$this->assertSame( 'https://example.com/footer', $result['url'] );
	}

	public function test_execute_returns_error_when_installer_returns_zero(): void {
		Functions\when( 'wp_insert_post' )->justReturn( 0 );

		$result = CreateFooter::execute( array( 'preset' => 'simple' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_footer_install_failed', $result->get_error_code() );
	}

	// ── execute — all presets ─────────────────────────────────────────────────

	/**
	 * @dataProvider presetProvider
	 */
	public function test_execute_succeeds_for_all_valid_presets( string $preset ): void {
		$result = CreateFooter::execute( array( 'preset' => $preset ) );

		$this->assertIsArray( $result );
		$this->assertGreaterThan( 0, $result['post_id'] );
		$this->assertSame( $preset, $result['preset'] );
	}

	/**
	 * @return list<array{string}>
	 */
	public static function presetProvider(): array {
		return array_map( static fn ( $p ) => array( $p ), FooterPresets::PRESETS );
	}


	public function test_execute_forwards_overrides_to_footer_presets(): void {
		$result = CreateFooter::execute(
			array(
				'preset'    => 'simple',
				'overrides' => array( 'copyright_text' => 'Custom &copy; 2026' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertGreaterThan( 0, $result['post_id'] );
	}
}
