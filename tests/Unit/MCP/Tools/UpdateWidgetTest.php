<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\MCP\Tools\UpdateWidget;
use ElementorForge\Safety\Gate;
use ElementorForge\Safety\Mode;
use ElementorForge\Settings\Store;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class UpdateWidgetTest extends TestCase {

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

	public function test_execute_returns_error_for_missing_widget_id(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result = UpdateWidget::execute(
			array(
				'post_id'   => 42,
				'widget_id' => '',
				'settings'  => array( 'title' => 'New' ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_missing_widget_id', $result->get_error_code() );
	}

	public function test_execute_returns_error_for_empty_settings(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result = UpdateWidget::execute(
			array(
				'post_id'   => 42,
				'widget_id' => 'wid001',
				'settings'  => array(),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_empty_settings', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_widget_not_found(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result = UpdateWidget::execute(
			array(
				'post_id'   => 42,
				'widget_id' => 'nonexistent',
				'settings'  => array( 'title' => 'New' ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_widget_not_found', $result->get_error_code() );
	}

	public function test_execute_merges_new_settings_into_existing_widget_settings(): void {
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

		$result = UpdateWidget::execute(
			array(
				'post_id'   => 42,
				'widget_id' => 'wid001',
				'settings'  => array( 'title' => 'Updated Title', 'align' => 'center' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['updated'] );
		$this->assertSame( 42, $result['post_id'] );

		$decoded = json_decode( $written, true );
		$widget  = $decoded[0]['elements'][0];
		$this->assertSame( 'Updated Title', $widget['settings']['title'] );
		$this->assertSame( 'center', $widget['settings']['align'] );
	}

	public function test_execute_finds_deeply_nested_widget(): void {
		$sections = array(
			array(
				'id'       => 'outer001',
				'elType'   => 'container',
				'settings' => new \stdClass(),
				'elements' => array(
					array(
						'id'       => 'inner001',
						'elType'   => 'container',
						'settings' => new \stdClass(),
						'elements' => array(
							array(
								'id'         => 'deep_wid',
								'elType'     => 'widget',
								'widgetType' => 'heading',
								'settings'   => array( 'title' => 'Deep' ),
								'elements'   => array(),
								'isInner'    => true,
							),
						),
						'isInner'  => true,
					),
				),
				'isInner'  => false,
			),
		);
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $sections ) );

		$written = null;
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, $value ) use ( &$written ): bool {
				if ( '_elementor_data' === $key ) {
					$written = $value;
				}
				return true;
			}
		);

		$result = UpdateWidget::execute(
			array(
				'post_id'   => 42,
				'widget_id' => 'deep_wid',
				'settings'  => array( 'title' => 'Found Deep' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['updated'] );

		$decoded     = json_decode( $written, true );
		$deep_widget = $decoded[0]['elements'][0]['elements'][0];
		$this->assertSame( 'Found Deep', $deep_widget['settings']['title'] );
	}

	public function test_execute_writes_updated_content_to_post_meta(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$meta_calls = array();
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, $value ) use ( &$meta_calls ): bool {
				$meta_calls[] = array( 'post_id' => $post_id, 'key' => $key );
				return true;
			}
		);

		UpdateWidget::execute(
			array(
				'post_id'   => 42,
				'widget_id' => 'wid001',
				'settings'  => array( 'title' => 'Written' ),
			)
		);

		$data_calls = array_filter( $meta_calls, static fn ( $c ) => $c['key'] === '_elementor_data' && $c['post_id'] === 42 );
		$this->assertNotEmpty( $data_calls );
	}

	public function test_execute_returns_error_for_invalid_post_id(): void {
		$result = UpdateWidget::execute(
			array(
				'post_id'   => 0,
				'widget_id' => 'wid001',
				'settings'  => array( 'title' => 'x' ),
			)
		);
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

		$result = UpdateWidget::execute(
			array(
				'post_id'   => 42,
				'widget_id' => 'wid001',
				'settings'  => array( 'title' => 'x' ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}

	public function test_execute_respects_gate_in_page_only_mode_with_non_allowlisted_post(): void {
		Store::flush_cache();
		Functions\when( 'get_option' )->justReturn(
			array(
				'safety_mode'             => Mode::PAGE_ONLY,
				'safety_allowed_post_ids' => '52',
			)
		);

		$result = UpdateWidget::execute(
			array(
				'post_id'   => 99,
				'widget_id' => 'wid001',
				'settings'  => array( 'title' => 'x' ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_POST_NOT_IN_ALLOWLIST, $result->get_error_code() );
	}
}
