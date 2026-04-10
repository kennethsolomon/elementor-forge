<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Kit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\Elementor\Kit\KitWriter;
use ElementorForge\Settings\Store;
use PHPUnit\Framework\TestCase;

final class KitWriterTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Store::flush_cache();
		Functions\when( 'absint' )->alias( static fn ( $v ): int => abs( (int) $v ) );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'delete_post_meta' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}


	public function test_active_kit_id_returns_zero_when_option_not_set(): void {
		Functions\when( 'get_option' )->alias(
			static fn ( string $key, $default = false ) => 'elementor_active_kit' === $key ? 0 : $default
		);

		$this->assertSame( 0, KitWriter::active_kit_id() );
	}

	public function test_active_kit_id_returns_correct_int_from_option(): void {
		Functions\when( 'get_option' )->alias(
			static fn ( string $key, $default = false ) => 'elementor_active_kit' === $key ? 42 : $default
		);

		$this->assertSame( 42, KitWriter::active_kit_id() );
	}

	public function test_active_kit_id_returns_zero_for_non_numeric_option(): void {
		Functions\when( 'get_option' )->alias(
			static fn ( string $key, $default = false ) => 'elementor_active_kit' === $key ? 'not-a-number' : $default
		);

		$this->assertSame( 0, KitWriter::active_kit_id() );
	}


	public function test_write_returns_kit_id_zero_when_no_active_kit(): void {
		Functions\when( 'get_option' )->alias(
			static fn ( string $key, $default = false ) => 'elementor_active_kit' === $key ? 0 : $default
		);

		$result = KitWriter::write( array( 'colors' => array( 'primary' => '#ff0000' ) ) );

		$this->assertSame( 0, $result['kit_id'] );
		$this->assertSame( array(), $result['updated'] );
	}


	public function test_write_applies_colors_to_system_colors_in_page_settings(): void {
		$captured = array();

		Functions\when( 'get_option' )->alias(
			static fn ( string $key, $default = false ) => 'elementor_active_kit' === $key ? 10 : $default
		);
		Functions\when( 'get_post_meta' )->justReturn( array() );
		Functions\when( 'sanitize_hex_color' )->alias(
			static fn ( string $color ): string => $color
		);
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, $value ) use ( &$captured ): bool {
				$captured[ $key ] = $value;
				return true;
			}
		);

		KitWriter::write( array( 'colors' => array( 'primary' => '#1a73e8' ) ) );

		$this->assertArrayHasKey( '_elementor_page_settings', $captured );
		$system_colors = $captured['_elementor_page_settings']['system_colors'];
		$ids           = array_column( $system_colors, '_id' );
		$this->assertContains( 'primary', $ids );
	}

	public function test_write_applies_typography_to_system_typography(): void {
		$captured = array();

		Functions\when( 'get_option' )->alias(
			static fn ( string $key, $default = false ) => 'elementor_active_kit' === $key ? 10 : $default
		);
		Functions\when( 'get_post_meta' )->justReturn( array() );
		Functions\when( 'sanitize_hex_color' )->justReturn( '' );
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, $value ) use ( &$captured ): bool {
				$captured[ $key ] = $value;
				return true;
			}
		);

		KitWriter::write(
			array(
				'typography' => array(
					'primary' => array(
						'font_family' => 'Inter',
						'font_size'   => 16,
						'font_weight' => '600',
					),
				),
			)
		);

		$this->assertArrayHasKey( '_elementor_page_settings', $captured );
		$system_typo = $captured['_elementor_page_settings']['system_typography'];
		$ids         = array_column( $system_typo, '_id' );
		$this->assertContains( 'primary', $ids );
		$entry = array_values( array_filter( $system_typo, static fn ( $e ) => $e['_id'] === 'primary' ) )[0];
		$this->assertSame( 'Inter', $entry['typography_font_family'] );
		$this->assertSame( 'custom', $entry['typography_typography'] );
	}

	public function test_write_applies_button_styles_with_correct_key_mappings(): void {
		$captured = array();

		Functions\when( 'get_option' )->alias(
			static fn ( string $key, $default = false ) => 'elementor_active_kit' === $key ? 10 : $default
		);
		Functions\when( 'get_post_meta' )->justReturn( array() );
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, $value ) use ( &$captured ): bool {
				$captured[ $key ] = $value;
				return true;
			}
		);

		KitWriter::write(
			array(
				'button' => array(
					'text_color'       => '#ffffff',
					'background_color' => '#000000',
					'border_radius'    => '4px',
					'font_family'      => 'Roboto',
				),
			)
		);

		$this->assertArrayHasKey( '_elementor_page_settings', $captured );
		$ps = $captured['_elementor_page_settings'];
		$this->assertSame( '#ffffff', $ps['button_text_color'] );
		$this->assertSame( '#000000', $ps['button_background_color'] );
		$this->assertSame( '4px', $ps['button_border_radius'] );
		$this->assertSame( 'Roboto', $ps['button_typography_font_family'] );
	}

	public function test_write_calls_update_post_meta_and_cache_clearer(): void {
		$update_called = false;

		Functions\when( 'get_option' )->alias(
			static fn ( string $key, $default = false ) => 'elementor_active_kit' === $key ? 7 : $default
		);
		Functions\when( 'get_post_meta' )->justReturn( array() );
		Functions\when( 'sanitize_hex_color' )->alias( static fn ( $v ): string => (string) $v );
		Functions\when( 'update_post_meta' )->alias(
			static function () use ( &$update_called ): bool {
				$update_called = true;
				return true;
			}
		);

		KitWriter::write( array( 'colors' => array( 'primary' => '#aabbcc' ) ) );

		$this->assertTrue( $update_called );
	}

	public function test_write_returns_updated_map_for_each_applied_setting(): void {
		Functions\when( 'get_option' )->alias(
			static fn ( string $key, $default = false ) => 'elementor_active_kit' === $key ? 5 : $default
		);
		Functions\when( 'get_post_meta' )->justReturn( array() );
		Functions\when( 'sanitize_hex_color' )->alias( static fn ( $v ): string => (string) $v );
		Functions\when( 'update_post_meta' )->justReturn( true );

		$result = KitWriter::write(
			array(
				'colors'     => array( 'accent' => '#ea4335' ),
				'typography' => array( 'secondary' => array( 'font_family' => 'Open Sans' ) ),
				'button'     => array( 'font_family' => 'Lato' ),
			)
		);

		$this->assertSame( 5, $result['kit_id'] );
		$this->assertTrue( $result['updated']['colors'] );
		$this->assertTrue( $result['updated']['typography'] );
		$this->assertTrue( $result['updated']['button'] );
	}


	public function test_apply_colors_sanitizes_hex_values_via_sanitize_hex_color(): void {
		$sanitize_called = false;

		Functions\when( 'get_option' )->alias(
			static fn ( string $key, $default = false ) => 'elementor_active_kit' === $key ? 3 : $default
		);
		Functions\when( 'get_post_meta' )->justReturn( array() );
		Functions\when( 'sanitize_hex_color' )->alias(
			static function ( $v ) use ( &$sanitize_called ): string {
				$sanitize_called = true;
				return is_string( $v ) ? $v : '';
			}
		);
		Functions\when( 'update_post_meta' )->justReturn( true );

		KitWriter::write( array( 'colors' => array( 'primary' => '#123456' ) ) );

		$this->assertTrue( $sanitize_called );
	}

	public function test_apply_colors_skips_empty_hex_after_sanitization(): void {
		$captured = array();

		Functions\when( 'get_option' )->alias(
			static fn ( string $key, $default = false ) => 'elementor_active_kit' === $key ? 9 : $default
		);
		Functions\when( 'get_post_meta' )->justReturn( array() );
		// sanitize_hex_color returns null for invalid colors.
		Functions\when( 'sanitize_hex_color' )->justReturn( null );
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, $value ) use ( &$captured ): bool {
				$captured[ $key ] = $value;
				return true;
			}
		);

		KitWriter::write( array( 'colors' => array( 'primary' => 'not-a-color' ) ) );

		// system_colors should be empty because sanitize returned null.
		$system_colors = $captured['_elementor_page_settings']['system_colors'] ?? array();
		$this->assertSame( array(), $system_colors );
	}


	public function test_system_color_primary_gets_title_primary(): void {
		$captured = array();

		Functions\when( 'get_option' )->alias(
			static fn ( string $key, $default = false ) => 'elementor_active_kit' === $key ? 1 : $default
		);
		Functions\when( 'get_post_meta' )->justReturn( array() );
		Functions\when( 'sanitize_hex_color' )->alias( static fn ( $v ): string => (string) $v );
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, $value ) use ( &$captured ): bool {
				$captured[ $key ] = $value;
				return true;
			}
		);

		KitWriter::write(
			array(
				'colors' => array(
					'primary'   => '#aaa',
					'secondary' => '#bbb',
					'text'      => '#ccc',
					'accent'    => '#ddd',
				),
			)
		);

		$system_colors = $captured['_elementor_page_settings']['system_colors'];
		$by_id         = array_column( $system_colors, null, '_id' );

		$this->assertSame( 'Primary', $by_id['primary']['title'] );
		$this->assertSame( 'Secondary', $by_id['secondary']['title'] );
		$this->assertSame( 'Text', $by_id['text']['title'] );
		$this->assertSame( 'Accent', $by_id['accent']['title'] );
	}


	public function test_system_colors_constant_contains_four_ids(): void {
		$this->assertCount( 4, KitWriter::SYSTEM_COLORS );
		$this->assertContains( 'primary', KitWriter::SYSTEM_COLORS );
		$this->assertContains( 'secondary', KitWriter::SYSTEM_COLORS );
		$this->assertContains( 'text', KitWriter::SYSTEM_COLORS );
		$this->assertContains( 'accent', KitWriter::SYSTEM_COLORS );
	}
}
