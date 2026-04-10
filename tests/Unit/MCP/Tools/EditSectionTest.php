<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\MCP\Tools\EditSection;
use ElementorForge\Safety\Gate;
use ElementorForge\Safety\Mode;
use ElementorForge\Settings\Store;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class EditSectionTest extends TestCase {

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

	public function test_input_schema_requires_post_id_and_block(): void {
		$schema = EditSection::input_schema();
		$this->assertSame( 'object', $schema['type'] );
		$this->assertContains( 'post_id', $schema['required'] );
		$this->assertContains( 'block', $schema['required'] );
		$this->assertFalse( $schema['additionalProperties'] );
	}

	public function test_execute_returns_error_for_invalid_post_id(): void {
		$result = EditSection::execute( array( 'post_id' => 0, 'block' => array( 'type' => 'heading' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_post', $result->get_error_code() );
	}

	public function test_execute_returns_error_for_empty_block(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result = EditSection::execute( array( 'post_id' => 42, 'block' => array() ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_missing_block', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_section_index_out_of_range(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result = EditSection::execute(
			array(
				'post_id'       => 42,
				'block'         => array( 'type' => 'heading', 'settings' => array( 'title' => 'New' ) ),
				'section_index' => 99,
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_section_not_found', $result->get_error_code() );
	}

	public function test_execute_returns_error_when_section_id_not_found(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$result = EditSection::execute(
			array(
				'post_id'    => 42,
				'block'      => array( 'type' => 'heading', 'settings' => array( 'title' => 'New' ) ),
				'section_id' => 'nonexistent',
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_section_not_found', $result->get_error_code() );
	}

	public function test_execute_replaces_section_at_correct_index(): void {
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

		$result = EditSection::execute(
			array(
				'post_id'       => 42,
				'block'         => array( 'type' => 'heading', 'settings' => array( 'title' => 'Replaced' ) ),
				'section_index' => 0,
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['replaced'] );
		$this->assertNotNull( $written );
		$decoded = json_decode( $written, true );
		$this->assertCount( 3, $decoded );
	}

	public function test_execute_replaces_section_by_element_id(): void {
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

		$result = EditSection::execute(
			array(
				'post_id'    => 42,
				'block'      => array( 'type' => 'heading', 'settings' => array( 'title' => 'By ID' ) ),
				'section_id' => 'sec002',
			)
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $result['replaced'] );
		$this->assertNotNull( $written );
	}

	public function test_execute_calls_update_post_meta_with_modified_data(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );

		$meta_calls = array();
		Functions\when( 'update_post_meta' )->alias(
			static function ( int $post_id, string $key, $value ) use ( &$meta_calls ): bool {
				$meta_calls[] = array( 'post_id' => $post_id, 'key' => $key );
				return true;
			}
		);

		EditSection::execute(
			array(
				'post_id'       => 42,
				'block'         => array( 'type' => 'heading', 'settings' => array( 'title' => 'New' ) ),
				'section_index' => 0,
			)
		);

		$data_calls = array_filter( $meta_calls, static fn ( $c ) => $c['key'] === '_elementor_data' && $c['post_id'] === 42 );
		$this->assertNotEmpty( $data_calls );
	}

	public function test_execute_calls_cache_clearer_clear(): void {
		Functions\when( 'get_post_meta' )->justReturn( $this->make_elementor_data( $this->sample_sections() ) );
		Functions\when( 'update_post_meta' )->justReturn( true );

		$delete_calls = array();
		Functions\when( 'delete_post_meta' )->alias(
			static function ( int $post_id, string $key ) use ( &$delete_calls ): bool {
				$delete_calls[] = array( 'post_id' => $post_id, 'key' => $key );
				return true;
			}
		);

		EditSection::execute(
			array(
				'post_id'       => 42,
				'block'         => array( 'type' => 'heading', 'settings' => array( 'title' => 'Cache test' ) ),
				'section_index' => 0,
			)
		);

		// Verify CacheClearer called delete_post_meta for _elementor_css.
		$css_calls = array_filter( $delete_calls, static fn ( $c ) => $c['key'] === '_elementor_css' && $c['post_id'] === 42 );
		$this->assertNotEmpty( $css_calls );
	}

	public function test_execute_respects_gate_in_read_only_mode(): void {
		Store::flush_cache();
		Functions\when( 'get_option' )->justReturn(
			array(
				'safety_mode'             => Mode::READ_ONLY,
				'safety_allowed_post_ids' => '',
			)
		);

		$result = EditSection::execute(
			array(
				'post_id'       => 42,
				'block'         => array( 'type' => 'heading', 'settings' => array( 'title' => 'x' ) ),
				'section_index' => 0,
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}

	public function test_execute_respects_gate_in_page_only_mode_with_allowlist(): void {
		Store::flush_cache();
		Functions\when( 'get_option' )->justReturn(
			array(
				'safety_mode'             => Mode::PAGE_ONLY,
				'safety_allowed_post_ids' => '52',
			)
		);

		$result = EditSection::execute(
			array(
				'post_id'       => 99,
				'block'         => array( 'type' => 'heading', 'settings' => array( 'title' => 'x' ) ),
				'section_index' => 0,
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_POST_NOT_IN_ALLOWLIST, $result->get_error_code() );
	}

	public function test_read_content_returns_error_when_no_elementor_data(): void {
		Functions\when( 'get_post_meta' )->justReturn( '' );

		$result = EditSection::read_content( 42 );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_no_elementor_data', $result->get_error_code() );
	}

	public function test_resolve_index_finds_by_index(): void {
		$content = $this->sample_sections();
		$result  = EditSection::resolve_index( array( 'section_index' => 1 ), $content );
		$this->assertSame( 1, $result );
	}

	public function test_resolve_index_finds_by_id(): void {
		$content = $this->sample_sections();
		$result  = EditSection::resolve_index( array( 'section_id' => 'sec003' ), $content );
		$this->assertSame( 2, $result );
	}

	public function test_resolve_index_returns_null_for_out_of_range_index(): void {
		$content = $this->sample_sections();
		$result  = EditSection::resolve_index( array( 'section_index' => 10 ), $content );
		$this->assertNull( $result );
	}

	public function test_resolve_index_returns_null_for_missing_id(): void {
		$content = $this->sample_sections();
		$result  = EditSection::resolve_index( array( 'section_id' => 'bogus' ), $content );
		$this->assertNull( $result );
	}

	public function test_resolve_index_returns_null_when_no_locator_given(): void {
		$content = $this->sample_sections();
		$result  = EditSection::resolve_index( array(), $content );
		$this->assertNull( $result );
	}
}
