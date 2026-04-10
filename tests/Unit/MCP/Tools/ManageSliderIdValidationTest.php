<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\MCP\Tools\ManageSlider;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class ManageSliderIdValidationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( '3.5.1.34' );
		Functions\when( 'current_time' )->justReturn( '2026-04-10 10:00:00' );
		Functions\when( 'wp_json_encode' )->alias( static fn ( $v ) => json_encode( $v ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof WP_Error );
		Functions\when( 'update_option' )->justReturn( true );

		if ( ! defined( 'NEXTEND_SMARTSLIDER_3_URL_PATH' ) ) {
			define( 'NEXTEND_SMARTSLIDER_3_URL_PATH', 'smart-slider3' );
		}

		global $wpdb;
		$wpdb = new \wpdb();
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb = null;
		Monkey\tearDown();
		parent::tearDown();
	}


	public function test_delete_slider_with_slider_id_zero_returns_wp_error_invalid_slider_id(): void {
		$result = ManageSlider::execute(
			array(
				'action'  => 'delete_slider',
				'payload' => array( 'slider_id' => 0 ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_slider_id', $result->get_error_code() );
	}

	public function test_update_slider_with_slider_id_zero_returns_wp_error_invalid_slider_id(): void {
		$result = ManageSlider::execute(
			array(
				'action'  => 'update_slider',
				'payload' => array( 'slider_id' => 0, 'title' => 'Title' ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_slider_id', $result->get_error_code() );
	}

	public function test_get_slider_with_slider_id_zero_returns_wp_error_invalid_slider_id(): void {
		$result = ManageSlider::execute(
			array(
				'action'  => 'get_slider',
				'payload' => array( 'slider_id' => 0 ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_slider_id', $result->get_error_code() );
	}

	public function test_add_slide_with_slider_id_zero_returns_wp_error_invalid_slider_id(): void {
		$result = ManageSlider::execute(
			array(
				'action'  => 'add_slide',
				'payload' => array( 'slider_id' => 0, 'title' => 'Slide' ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_slider_id', $result->get_error_code() );
	}


	public function test_delete_slider_with_negative_slider_id_returns_wp_error_invalid_slider_id(): void {
		$result = ManageSlider::execute(
			array(
				'action'  => 'delete_slider',
				'payload' => array( 'slider_id' => -1 ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_slider_id', $result->get_error_code() );
	}

	public function test_get_slider_with_negative_slider_id_returns_wp_error_invalid_slider_id(): void {
		$result = ManageSlider::execute(
			array(
				'action'  => 'get_slider',
				'payload' => array( 'slider_id' => -5 ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_slider_id', $result->get_error_code() );
	}


	public function test_update_slide_with_slide_id_zero_returns_wp_error_invalid_slider_id(): void {
		$result = ManageSlider::execute(
			array(
				'action'  => 'update_slide',
				'payload' => array( 'slide_id' => 0, 'title' => 'New Title' ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_slider_id', $result->get_error_code() );
	}

	public function test_delete_slide_with_slide_id_zero_returns_wp_error_invalid_slider_id(): void {
		$result = ManageSlider::execute(
			array(
				'action'  => 'delete_slide',
				'payload' => array( 'slide_id' => 0 ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_slider_id', $result->get_error_code() );
	}


	public function test_update_slide_with_negative_slide_id_returns_wp_error_invalid_slider_id(): void {
		$result = ManageSlider::execute(
			array(
				'action'  => 'update_slide',
				'payload' => array( 'slide_id' => -3 ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_slider_id', $result->get_error_code() );
	}

	public function test_delete_slide_with_negative_slide_id_returns_wp_error_invalid_slider_id(): void {
		$result = ManageSlider::execute(
			array(
				'action'  => 'delete_slide',
				'payload' => array( 'slide_id' => -99 ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_slider_id', $result->get_error_code() );
	}


	public function test_delete_slider_with_missing_slider_id_key_returns_wp_error_invalid_slider_id(): void {
		$result = ManageSlider::execute(
			array(
				'action'  => 'delete_slider',
				'payload' => array(),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_slider_id', $result->get_error_code() );
	}

	public function test_update_slide_with_missing_slide_id_key_returns_wp_error_invalid_slider_id(): void {
		$result = ManageSlider::execute(
			array(
				'action'  => 'update_slide',
				'payload' => array( 'title' => 'no id' ),
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_slider_id', $result->get_error_code() );
	}


	public function test_delete_slider_with_positive_id_does_not_return_invalid_slider_id_error(): void {
		global $wpdb;

		$result = ManageSlider::execute(
			array(
				'action'  => 'delete_slider',
				'payload' => array( 'slider_id' => 7 ),
			)
		);

		// Valid slider_id — must NOT return the id-validation error.
		if ( $result instanceof WP_Error ) {
			$this->assertNotSame( 'elementor_forge_invalid_slider_id', $result->get_error_code() );
		} else {
			$this->assertIsArray( $result );
			$this->assertSame( 'delete_slider', $result['action'] );
		}
	}

	public function test_update_slide_with_positive_id_does_not_return_invalid_slider_id_error(): void {
		global $wpdb;
		$wpdb->var_return = 10;

		$result = ManageSlider::execute(
			array(
				'action'  => 'update_slide',
				'payload' => array( 'slide_id' => 5, 'title' => 'OK' ),
			)
		);

		if ( $result instanceof WP_Error ) {
			$this->assertNotSame( 'elementor_forge_invalid_slider_id', $result->get_error_code() );
		} else {
			$this->assertIsArray( $result );
			$this->assertSame( 'update_slide', $result['action'] );
		}
	}
}
