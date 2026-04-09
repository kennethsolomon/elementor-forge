<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Emitter;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\Elementor\Emitter\Encoder;
use PHPUnit\Framework\TestCase;

final class EncoderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
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
		Functions\when( 'wp_unslash' )->alias(
			static function ( $value ) {
				if ( is_array( $value ) ) {
					return array_map( 'wp_unslash', $value );
				}
				return is_string( $value ) ? stripslashes( $value ) : $value;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_encode_round_trips_simple_payload(): void {
		$payload = array(
			array(
				'id'       => 'abc12345',
				'elType'   => 'widget',
				'settings' => array( 'title' => 'Hello "world"' ),
				'elements' => array(),
				'isInner'  => false,
			),
		);

		$slashed = Encoder::encode_for_meta( $payload );
		$this->assertIsString( $slashed );
		$decoded = Encoder::decode_from_meta( $slashed );

		$this->assertSame( $payload, $decoded );
	}

	public function test_slashing_survives_quoted_text(): void {
		$payload = array(
			array(
				'id'       => 'aa11bb22',
				'elType'   => 'widget',
				'settings' => array( 'text' => 'She said "It\'s working"' ),
				'elements' => array(),
				'isInner'  => false,
			),
		);

		$slashed = Encoder::encode_for_meta( $payload );
		$decoded = Encoder::decode_from_meta( $slashed );

		$this->assertSame( 'She said "It\'s working"', $decoded[0]['settings']['text'] );
	}

	public function test_decode_empty_string_returns_empty_array(): void {
		$this->assertSame( array(), Encoder::decode_from_meta( '' ) );
	}

	public function test_decode_invalid_json_returns_empty_array(): void {
		$this->assertSame( array(), Encoder::decode_from_meta( 'not json at all' ) );
	}
}
