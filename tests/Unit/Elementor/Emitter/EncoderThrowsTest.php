<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Emitter;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\Elementor\Emitter\Encoder;
use ElementorForge\Elementor\Emitter\EncoderException;
use PHPUnit\Framework\TestCase;

final class EncoderThrowsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_encode_for_meta_throws_encoder_exception_when_wp_json_encode_returns_false(): void {
		Functions\when( 'wp_json_encode' )->justReturn( false );

		$this->expectException( EncoderException::class );

		Encoder::encode_for_meta( array( array( 'bad' => "\xB1\x31" ) ) );
	}

	public function test_encode_for_meta_exception_message_describes_failure(): void {
		Functions\when( 'wp_json_encode' )->justReturn( false );

		$caught = null;
		try {
			Encoder::encode_for_meta( array() );
		} catch ( EncoderException $e ) {
			$caught = $e;
		}

		$this->assertNotNull( $caught );
		$this->assertStringContainsString( 'JSON-encode', $caught->getMessage() );
	}

	public function test_encode_for_meta_throws_before_calling_wp_slash(): void {
		Functions\when( 'wp_json_encode' )->justReturn( false );
		// wp_slash must never be reached — if it were called without a stub
		// Brain\Monkey would throw a different error than EncoderException.
		Functions\expect( 'wp_slash' )->never();

		$this->expectException( EncoderException::class );
		Encoder::encode_for_meta( array() );
	}

	public function test_encode_for_meta_does_not_throw_when_wp_json_encode_returns_valid_string(): void {
		Functions\when( 'wp_json_encode' )->justReturn( '[]' );
		Functions\when( 'wp_slash' )->alias(
			static fn ( $v ) => is_string( $v ) ? addslashes( $v ) : $v
		);

		// Must not throw.
		$result = Encoder::encode_for_meta( array() );
		$this->assertIsString( $result );
	}

	public function test_encode_for_meta_exception_is_runtime_exception(): void {
		Functions\when( 'wp_json_encode' )->justReturn( false );

		$caught = null;
		try {
			Encoder::encode_for_meta( array() );
		} catch ( \RuntimeException $e ) {
			$caught = $e;
		}

		$this->assertInstanceOf( EncoderException::class, $caught );
	}
}
