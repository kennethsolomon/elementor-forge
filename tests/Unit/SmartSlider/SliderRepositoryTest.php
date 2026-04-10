<?php
/**
 * SliderRepository tests against the bootstrap wpdb stub.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\SmartSlider;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\SmartSlider\SliderRepository;
use ElementorForge\SmartSlider\SmartSliderUnavailable;
use PHPUnit\Framework\TestCase;

final class SliderRepositoryTest extends TestCase {

	private \wpdb $wpdb;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->wpdb = new \wpdb();
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( '3.5.1.34' );
		Functions\when( 'current_time' )->justReturn( '2026-04-08 12:00:00' );
		Functions\when( 'wp_json_encode' )->alias(
			static function ( $value ) {
				return json_encode( $value );
			}
		);
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'wp_kses_post' )->alias(
			static function ( string $data ): string {
				// Minimal kses — strip script/style tags for sanitization tests.
				return preg_replace( '/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $data ) ?? $data;
			}
		);

		if ( ! defined( 'NEXTEND_SMARTSLIDER_3_URL_PATH' ) ) {
			define( 'NEXTEND_SMARTSLIDER_3_URL_PATH', 'smart-slider3' );
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_create_slider_inserts_with_canonical_columns(): void {
		$repo = new SliderRepository( $this->wpdb );

		$id = $repo->create_slider( 'Hero Carousel', array( 'background-color' => 'FFFFFF00' ) );

		self::assertGreaterThan( 0, $id );
		self::assertCount( 1, $this->wpdb->inserts );
		$insert = $this->wpdb->inserts[0];
		self::assertSame( 'wp_nextend2_smartslider3_sliders', $insert['table'] );
		self::assertSame( 'Hero Carousel', $insert['data']['title'] );
		self::assertSame( 'simple', $insert['data']['type'] );
		self::assertSame( 'published', $insert['data']['slider_status'] );
		self::assertNull( $insert['data']['alias'] );
		self::assertIsString( $insert['data']['params'] );
		self::assertNotFalse( json_decode( $insert['data']['params'], true ) );
	}

	public function test_create_slider_throws_when_smart_slider_missing(): void {
		// Brain Monkey can't undefine constants — use a separate test class for the missing path.
		// Here we simulate the gate's negative path with a version OPTION that returns empty.
		Functions\when( 'get_option' )->justReturn( '' );

		$this->expectException( SmartSliderUnavailable::class );
		( new SliderRepository( $this->wpdb ) )->create_slider( 'x' );
	}

	public function test_create_slider_throws_when_version_below_min(): void {
		Functions\when( 'get_option' )->justReturn( '3.4.0' );

		$this->expectException( SmartSliderUnavailable::class );
		( new SliderRepository( $this->wpdb ) )->create_slider( 'x' );
	}

	public function test_create_slider_throws_when_version_above_max(): void {
		Functions\when( 'get_option' )->justReturn( '3.7.5' );

		$this->expectException( SmartSliderUnavailable::class );
		( new SliderRepository( $this->wpdb ) )->create_slider( 'x' );
	}

	public function test_create_slider_throws_when_user_lacks_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->expectException( SmartSliderUnavailable::class );
		( new SliderRepository( $this->wpdb ) )->create_slider( 'x' );
	}

	public function test_create_slider_throws_when_insert_returns_false(): void {
		$this->wpdb->insert_return = false;
		$this->wpdb->last_error    = 'fake DB error';

		$this->expectException( SmartSliderUnavailable::class );
		( new SliderRepository( $this->wpdb ) )->create_slider( 'x' );
	}

	public function test_update_slider_writes_title_params_and_time(): void {
		$repo = new SliderRepository( $this->wpdb );

		$ok = $repo->update_slider( 42, 'Renamed', array( 'aria-label' => 'Renamed' ) );

		self::assertTrue( $ok );
		self::assertCount( 1, $this->wpdb->updates );
		$update = $this->wpdb->updates[0];
		self::assertSame( 'wp_nextend2_smartslider3_sliders', $update['table'] );
		self::assertSame( array( 'id' => 42 ), $update['where'] );
		self::assertSame( 'Renamed', $update['data']['title'] );
		self::assertArrayHasKey( 'params', $update['data'] );
		self::assertArrayHasKey( 'time', $update['data'] );
	}

	public function test_get_slider_decodes_params_json(): void {
		$this->wpdb->row_return = array(
			'id'            => 5,
			'title'         => 'Test',
			'type'          => 'simple',
			'params'        => '{"aria-label":"Test","background-color":"FFFFFF00"}',
			'slider_status' => 'published',
		);

		$row = ( new SliderRepository( $this->wpdb ) )->get_slider( 5 );

		self::assertNotNull( $row );
		self::assertSame( 5, $row['id'] );
		self::assertSame( 'Test', $row['title'] );
		self::assertSame( 'FFFFFF00', $row['params']['background-color'] );
	}

	public function test_get_slider_returns_null_when_row_missing(): void {
		$this->wpdb->row_return = null;

		$row = ( new SliderRepository( $this->wpdb ) )->get_slider( 999 );

		self::assertNull( $row );
	}

	public function test_delete_slider_cascades_to_slides_and_xref(): void {
		$repo = new SliderRepository( $this->wpdb );

		$ok = $repo->delete_slider( 7 );

		self::assertTrue( $ok );
		self::assertCount( 3, $this->wpdb->deletes );
		self::assertSame( 'wp_nextend2_smartslider3_slides', $this->wpdb->deletes[0]['table'] );
		self::assertSame( array( 'slider' => 7 ), $this->wpdb->deletes[0]['where'] );
		self::assertSame( 'wp_nextend2_smartslider3_sliders_xref', $this->wpdb->deletes[1]['table'] );
		self::assertSame( array( 'slider_id' => 7 ), $this->wpdb->deletes[1]['where'] );
		self::assertSame( 'wp_nextend2_smartslider3_sliders', $this->wpdb->deletes[2]['table'] );
		self::assertSame( array( 'id' => 7 ), $this->wpdb->deletes[2]['where'] );
	}

	public function test_add_slide_marks_first_slide_as_first_one(): void {
		$this->wpdb->var_return = 0;
		$repo = new SliderRepository( $this->wpdb );

		$id = $repo->add_slide( 10, array( 'title' => 'Slide A', 'body' => 'body' ) );

		self::assertGreaterThan( 0, $id );
		self::assertCount( 1, $this->wpdb->inserts );
		$insert = $this->wpdb->inserts[0];
		self::assertSame( 'wp_nextend2_smartslider3_slides', $insert['table'] );
		self::assertSame( 10, $insert['data']['slider'] );
		self::assertSame( 1, $insert['data']['first'] );
		self::assertSame( 0, $insert['data']['ordering'] );
		self::assertSame( 1, $insert['data']['published'] );
		self::assertIsString( $insert['data']['slide'] );
	}

	public function test_add_slide_after_existing_slides_uses_next_ordering(): void {
		$this->wpdb->var_return = 3;
		$repo = new SliderRepository( $this->wpdb );

		$repo->add_slide( 10, array( 'title' => 'Slide D' ) );

		self::assertSame( 0, $this->wpdb->inserts[0]['data']['first'] );
		self::assertSame( 3, $this->wpdb->inserts[0]['data']['ordering'] );
	}

	public function test_add_slide_accepts_explicit_layers_array(): void {
		$this->wpdb->var_return = 0;
		$repo = new SliderRepository( $this->wpdb );

		$repo->add_slide( 10, array( 'title' => 'Custom', 'layers' => array( array( 'type' => 'content' ) ) ) );

		$insert = $this->wpdb->inserts[0];
		self::assertSame( '[{"type":"content"}]', $insert['data']['slide'] );
	}

	public function test_update_slide_only_writes_supplied_columns(): void {
		$this->wpdb->var_return = 10;
		$repo = new SliderRepository( $this->wpdb );

		$ok = $repo->update_slide( 99, array( 'title' => 'Renamed' ) );

		self::assertTrue( $ok );
		self::assertCount( 1, $this->wpdb->updates );
		$update = $this->wpdb->updates[0];
		self::assertSame( array( 'title' => 'Renamed' ), $update['data'] );
		self::assertSame( array( 'id' => 99 ), $update['where'] );
	}

	public function test_update_slide_returns_false_when_no_fields_supplied(): void {
		$repo = new SliderRepository( $this->wpdb );

		$ok = $repo->update_slide( 99, array() );

		self::assertFalse( $ok );
		self::assertCount( 0, $this->wpdb->updates );
	}

	public function test_delete_slide_issues_one_delete(): void {
		$this->wpdb->var_return = 10;
		$repo = new SliderRepository( $this->wpdb );

		$ok = $repo->delete_slide( 99 );

		self::assertTrue( $ok );
		self::assertCount( 1, $this->wpdb->deletes );
		self::assertSame( 'wp_nextend2_smartslider3_slides', $this->wpdb->deletes[0]['table'] );
		self::assertSame( array( 'id' => 99 ), $this->wpdb->deletes[0]['where'] );
	}

	public function test_list_sliders_normalizes_each_row(): void {
		$this->wpdb->results_return = array(
			array( 'id' => '1', 'title' => 'A', 'slider_status' => 'published', 'type' => 'simple' ),
			array( 'id' => '2', 'title' => 'B', 'slider_status' => 'trash', 'type' => 'block' ),
		);

		$rows = ( new SliderRepository( $this->wpdb ) )->list_sliders();

		self::assertCount( 2, $rows );
		self::assertSame( 1, $rows[0]['id'] );
		self::assertSame( 'A', $rows[0]['title'] );
		self::assertSame( 'trash', $rows[1]['status'] );
	}

	public function test_is_available_true_when_constants_and_version_match(): void {
		$repo = new SliderRepository( $this->wpdb );

		self::assertTrue( $repo->is_available() );
	}

	public function test_is_available_false_when_version_unparseable(): void {
		Functions\when( 'get_option' )->justReturn( 'not-a-version' );

		self::assertFalse( ( new SliderRepository( $this->wpdb ) )->is_available() );
	}

	public function test_detect_version_extracts_dotted_prefix(): void {
		Functions\when( 'get_option' )->justReturn( '3.5.1.34' );
		self::assertSame( '3.5.1.34', ( new SliderRepository( $this->wpdb ) )->detect_version() );
	}

	public function test_detect_version_returns_empty_when_option_missing(): void {
		Functions\when( 'get_option' )->justReturn( false );
		self::assertSame( '', ( new SliderRepository( $this->wpdb ) )->detect_version() );
	}

	public function test_create_slider_strips_script_tags_from_title(): void {
		$repo = new SliderRepository( $this->wpdb );

		$repo->create_slider( '<script>alert(1)</script>My Slider', array() );

		$insert = $this->wpdb->inserts[0];
		self::assertStringNotContainsString( '<script', $insert['data']['title'] );
		self::assertStringNotContainsString( 'alert(1)', $insert['data']['title'] );
		self::assertStringContainsString( 'My Slider', $insert['data']['title'] );
		// params.aria-label was derived from the title and must also be stripped.
		$decoded_params = json_decode( $insert['data']['params'], true );
		self::assertIsArray( $decoded_params );
		self::assertStringNotContainsString( '<script', (string) $decoded_params['aria-label'] );
	}

	public function test_add_slide_sanitizes_nested_layer_content(): void {
		$this->wpdb->var_return = 0;
		$repo = new SliderRepository( $this->wpdb );

		$repo->add_slide(
			10,
			array(
				'title'  => 'Slide',
				'layers' => array(
					array(
						'type'     => 'content',
						'children' => array(
							array(
								'type' => 'layer',
								'item' => array(
									'type'   => 'text',
									'values' => array(
										'content' => '<script>steal()</script>Hello',
									),
								),
							),
						),
					),
				),
			)
		);

		$insert = $this->wpdb->inserts[0];
		self::assertIsString( $insert['data']['slide'] );
		self::assertStringNotContainsString( '<script', $insert['data']['slide'] );
		self::assertStringNotContainsString( 'steal()', $insert['data']['slide'] );
		self::assertStringContainsString( 'Hello', $insert['data']['slide'] );
	}

	public function test_sanitize_preserves_safe_html(): void {
		$this->wpdb->var_return = 0;
		$repo                   = new SliderRepository( $this->wpdb );

		$repo->add_slide(
			10,
			array(
				'title'  => 'Slide',
				'layers' => array(
					array(
						'type'     => 'content',
						'children' => array(
							array(
								'type' => 'layer',
								'item' => array(
									'type'   => 'text',
									'values' => array(
										'content' => '<strong>Bold</strong> text with <a href="https://example.com">link</a>',
									),
								),
							),
						),
					),
				),
			)
		);

		$insert = $this->wpdb->inserts[0];
		$decoded = json_decode( $insert['data']['slide'], true );
		self::assertIsArray( $decoded );
		$content = $decoded[0]['children'][0]['item']['values']['content'];
		self::assertStringContainsString( '<strong>', $content );
		self::assertStringContainsString( 'Bold', $content );
		self::assertStringContainsString( 'https://example.com', $content );
		self::assertStringContainsString( '<a href=', $content );
	}

	public function test_delete_slider_rolls_back_on_mid_sequence_failure(): void {
		// Fail on the SECOND delete call (the xref delete) so we can assert
		// the first delete was rolled back via a ROLLBACK query.
		$this->wpdb->delete_fail_at = array( 1 => true );
		$repo                       = new SliderRepository( $this->wpdb );

		try {
			$repo->delete_slider( 7 );
			self::fail( 'Expected SmartSliderUnavailable to be thrown.' );
		} catch ( SmartSliderUnavailable $e ) {
			self::assertStringContainsString( 'xref', $e->getMessage() );
		}

		// First delete (slides) was attempted, second (xref) failed → only 2 delete() calls.
		self::assertCount( 2, $this->wpdb->deletes );
		// ROLLBACK should be in the queries log — proof the transaction was wound back.
		self::assertContains( 'START TRANSACTION', $this->wpdb->queries );
		self::assertContains( 'ROLLBACK', $this->wpdb->queries );
		self::assertNotContains( 'COMMIT', $this->wpdb->queries );
	}
}
