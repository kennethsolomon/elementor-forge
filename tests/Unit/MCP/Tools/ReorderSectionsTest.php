<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\MCP\Tools\ReorderSections;
use ElementorForge\Safety\Gate;
use ElementorForge\Safety\Mode;
use ElementorForge\Settings\Store;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class ReorderSectionsTest extends TestCase {

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
		Functions\when( 'wp_slash' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'wp_json_encode' )->alias( static fn ( $data, $options = 0 ) => json_encode( $data, $options ) );
		Functions\when( 'wp_strip_all_tags' )->alias( static fn ( $s ) => strip_tags( $s ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
		Functions\when( 'delete_post_meta' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );
		Functions\when( 'get_the_title' )->justReturn( 'Test Page' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function make_elementor_data( array $sections ): string {
		return json_encode( $sections, JSON_UNESCAPED_SLASHES );
	}

	private function sample_sections(): array {
		return array(
			array(
				'id'       => 'sec001',
				'elType'   => 'container',
				'settings' => new \stdClass(),
				'elements' => array(
					array(
						'id'         => 'wid001',
						'elType'     => 'widget',
						'widgetType' => 'heading',
						'settings'   => array( 'title' => 'Hello' ),
						'elements'   => array(),
						'isInner'    => true,
					),
				),
				'isInner'  => false,
			),
			array(
				'id'       => 'sec002',
				'elType'   => 'container',
				'settings' => new \stdClass(),
				'elements' => array(
					array(
						'id'         => 'wid002',
						'elType'     => 'widget',
						'widgetType' => 'text-editor',
						'settings'   => array( 'editor' => 'Some text' ),
						'elements'   => array(),
						'isInner'    => true,
					),
				),
				'isInner'  => false,
			),
			array(
				'id'       => 'sec003',
				'elType'   => 'container',
				'settings' => new \stdClass(),
				'elements' => array(),
				'isInner'  => false,
			),
		);
	}

	public function test_execute_returns_error_for_empty_order(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result = ReorderSections::execute( array( 'post_id' => 42, 'order' => array() ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_missing_order', $result->get_error_code() );
	}

	public function test_execute_returns_error_for_out_of_range_index(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result = ReorderSections::execute( array( 'post_id' => 42, 'order' => array( 0, 1, 99 ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_order', $result->get_error_code() );
	}

	public function test_execute_returns_error_for_negative_index(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result = ReorderSections::execute( array( 'post_id' => 42, 'order' => array( 0, 1, -1 ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_order', $result->get_error_code() );
	}

	public function test_execute_returns_error_for_duplicate_index(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result = ReorderSections::execute( array( 'post_id' => 42, 'order' => array( 0, 0, 1 ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_duplicate_index', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_order_count_does_not_match_section_count(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		// 3 sections but only 2 indices provided.
		$result = ReorderSections::execute( array( 'post_id' => 42, 'order' => array( 0, 1 ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_order_mismatch', $result->get_error_code() );
	}

	public function test_execute_reorders_sections_correctly(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$written = null;
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, $value ) use ( &$written ): bool {
				if ( '_elementor_data' === $key ) {
					$written = $value;
				}
				return true;
			}
		);

		// Desired order: [2, 0, 1] → sec003, sec001, sec002.
		$result = ReorderSections::execute( array( 'post_id' => 42, 'order' => array( 2, 0, 1 ) ) );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['reordered'] );

		$decoded = json_decode( $written, true );
		$this->assertCount( 3, $decoded );
		$this->assertSame( 'sec003', $decoded[0]['id'] );
		$this->assertSame( 'sec001', $decoded[1]['id'] );
		$this->assertSame( 'sec002', $decoded[2]['id'] );
	}

	public function test_execute_writes_reordered_content_to_post_meta(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$meta_calls = array();
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, $value ) use ( &$meta_calls ): bool {
				$meta_calls[] = array( 'post_id' => $post_id, 'key' => $key );
				return true;
			}
		);

		ReorderSections::execute( array( 'post_id' => 42, 'order' => array( 2, 0, 1 ) ) );

		$data_calls = array_filter( $meta_calls, static fn ( $c ) => $c['key'] === '_elementor_data' && $c['post_id'] === 42 );
		$this->assertNotEmpty( $data_calls );
	}

	public function test_execute_returns_error_for_invalid_post_id(): void {
		$result = ReorderSections::execute( array( 'post_id' => 0, 'order' => array( 0, 1, 2 ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_post', $result->get_error_code() );
	}

	public function test_execute_respects_gate_in_read_only_mode(): void {
		Store::flush_cache();
		Functions\when( 'get_option' )->justReturn(
			array(
				'safety_mode'             => Mode::READ_ONLY,
				'safety_allowed_post_ids' => '',
			)
		);

		$result = ReorderSections::execute( array( 'post_id' => 42, 'order' => array( 2, 0, 1 ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}

	public function test_execute_same_order_still_succeeds(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$written = null;
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, $value ) use ( &$written ): bool {
				if ( '_elementor_data' === $key ) {
					$written = $value;
				}
				return true;
			}
		);

		$result = ReorderSections::execute( array( 'post_id' => 42, 'order' => array( 0, 1, 2 ) ) );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['reordered'] );
		$decoded = json_decode( $written, true );
		$this->assertSame( 'sec001', $decoded[0]['id'] );
		$this->assertSame( 'sec002', $decoded[1]['id'] );
		$this->assertSame( 'sec003', $decoded[2]['id'] );
	}
}
