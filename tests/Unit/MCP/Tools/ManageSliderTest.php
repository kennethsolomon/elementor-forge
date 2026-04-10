<?php
/**
 * ManageSlider MCP tool tests.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\MCP\Tools\ManageSlider;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class ManageSliderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( '3.5.1.34' );
		Functions\when( 'current_time' )->justReturn( '2026-04-08 12:00:00' );
		Functions\when( 'wp_json_encode' )->alias( static fn ( $v ) => json_encode( $v ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof WP_Error );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'wp_kses_post' )->returnArg();

		if ( ! defined( 'NEXTEND_SMARTSLIDER_3_URL_PATH' ) ) {
			define( 'NEXTEND_SMARTSLIDER_3_URL_PATH', 'smart-slider3' );
		}

		// Provide a fake $wpdb global so the dispatch path can resolve it.
		global $wpdb;
		$wpdb = new \wpdb();
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb = null;
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_invalid_action_returns_wp_error(): void {
		$result = ManageSlider::execute( array( 'action' => 'lol_unknown' ) );
		self::assertInstanceOf( WP_Error::class, $result );
	}

	public function test_create_slider_returns_inserted_id(): void {
		global $wpdb;
		$wpdb->next_insert_id = 42;

		$result = ManageSlider::execute(
			array(
				'action'  => 'create_slider',
				'payload' => array( 'title' => 'Hero' ),
			)
		);

		self::assertIsArray( $result );
		self::assertSame( 'create_slider', $result['action'] );
		self::assertArrayHasKey( 'slider_id', $result['result'] );
		self::assertSame( 42, $result['result']['slider_id'] );
	}

	public function test_list_sliders_returns_normalized_rows(): void {
		global $wpdb;
		$wpdb->results_return = array(
			array( 'id' => 1, 'title' => 'A', 'slider_status' => 'published', 'type' => 'simple' ),
		);

		$result = ManageSlider::execute( array( 'action' => 'list_sliders' ) );

		self::assertIsArray( $result );
		self::assertCount( 1, $result['result']['sliders'] );
		self::assertSame( 'A', $result['result']['sliders'][0]['title'] );
	}

	public function test_get_slider_returns_decoded_row(): void {
		global $wpdb;
		$wpdb->row_return = array(
			'id'            => 5,
			'title'         => 'Hero',
			'type'          => 'simple',
			'params'        => '{"aria-label":"Hero"}',
			'slider_status' => 'published',
		);

		$result = ManageSlider::execute(
			array( 'action' => 'get_slider', 'payload' => array( 'slider_id' => 5 ) )
		);

		self::assertIsArray( $result );
		self::assertSame( 'Hero', $result['result']['slider']['title'] );
	}

	public function test_smart_slider_unavailable_is_translated_to_wp_error(): void {
		Functions\when( 'get_option' )->justReturn( '' );

		$result = ManageSlider::execute(
			array( 'action' => 'create_slider', 'payload' => array( 'title' => 'x' ) )
		);

		self::assertInstanceOf( WP_Error::class, $result );
	}

	public function test_input_schema_lists_all_actions(): void {
		$schema = ManageSlider::input_schema();
		self::assertSame( ManageSlider::ACTIONS, $schema['properties']['action']['enum'] );
	}

	public function test_update_slider_updates_existing_row(): void {
		$result = ManageSlider::execute(
			array(
				'action'  => 'update_slider',
				'payload' => array(
					'slider_id' => 42,
					'title'     => 'Renamed',
					'params'    => array( 'aria-label' => 'Renamed' ),
				),
			)
		);

		self::assertIsArray( $result );
		self::assertSame( 'update_slider', $result['action'] );
		self::assertArrayHasKey( 'updated', $result['result'] );
		self::assertTrue( $result['result']['updated'] );

		global $wpdb;
		self::assertCount( 1, $wpdb->updates );
		self::assertSame( 'wp_nextend2_smartslider3_sliders', $wpdb->updates[0]['table'] );
		self::assertSame( array( 'id' => 42 ), $wpdb->updates[0]['where'] );
	}

	public function test_delete_slider_removes_row_and_related(): void {
		$result = ManageSlider::execute(
			array(
				'action'  => 'delete_slider',
				'payload' => array( 'slider_id' => 7 ),
			)
		);

		self::assertIsArray( $result );
		self::assertSame( 'delete_slider', $result['action'] );
		self::assertArrayHasKey( 'deleted', $result['result'] );
		self::assertTrue( $result['result']['deleted'] );

		global $wpdb;
		self::assertCount( 3, $wpdb->deletes );
		self::assertSame( 'wp_nextend2_smartslider3_slides', $wpdb->deletes[0]['table'] );
		self::assertSame( array( 'slider' => 7 ), $wpdb->deletes[0]['where'] );
		self::assertSame( 'wp_nextend2_smartslider3_sliders_xref', $wpdb->deletes[1]['table'] );
		self::assertSame( 'wp_nextend2_smartslider3_sliders', $wpdb->deletes[2]['table'] );
	}

	public function test_add_slide_returns_new_slide_id(): void {
		global $wpdb;
		$wpdb->next_insert_id = 123;
		$wpdb->var_return     = 0;

		$result = ManageSlider::execute(
			array(
				'action'  => 'add_slide',
				'payload' => array(
					'slider_id' => 10,
					'title'     => 'Slide A',
					'body'      => 'body text',
				),
			)
		);

		self::assertIsArray( $result );
		self::assertSame( 'add_slide', $result['action'] );
		self::assertArrayHasKey( 'slide_id', $result['result'] );
		self::assertSame( 123, $result['result']['slide_id'] );
		self::assertCount( 1, $wpdb->inserts );
		self::assertSame( 'wp_nextend2_smartslider3_slides', $wpdb->inserts[0]['table'] );
	}

	public function test_update_slide_updates_existing_slide(): void {
		global $wpdb;
		$wpdb->var_return = 10; // parent slider id lookup after update

		$result = ManageSlider::execute(
			array(
				'action'  => 'update_slide',
				'payload' => array(
					'slide_id' => 99,
					'title'    => 'Renamed',
				),
			)
		);

		self::assertIsArray( $result );
		self::assertSame( 'update_slide', $result['action'] );
		self::assertTrue( $result['result']['updated'] );
		self::assertCount( 1, $wpdb->updates );
		self::assertSame( array( 'id' => 99 ), $wpdb->updates[0]['where'] );
	}

	public function test_delete_slide_removes_single_slide(): void {
		global $wpdb;
		$wpdb->var_return = 10; // parent slider id lookup for cache invalidation

		$result = ManageSlider::execute(
			array(
				'action'  => 'delete_slide',
				'payload' => array( 'slide_id' => 99 ),
			)
		);

		self::assertIsArray( $result );
		self::assertSame( 'delete_slide', $result['action'] );
		self::assertTrue( $result['result']['deleted'] );
		self::assertCount( 1, $wpdb->deletes );
		self::assertSame( 'wp_nextend2_smartslider3_slides', $wpdb->deletes[0]['table'] );
		self::assertSame( array( 'id' => 99 ), $wpdb->deletes[0]['where'] );
	}

	public function test_execute_rejects_unknown_action(): void {
		$result = ManageSlider::execute( array( 'action' => 'delete_universe' ) );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'elementor_forge_invalid_action', $result->get_error_code() );
	}
}
