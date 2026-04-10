<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Emitter;

use ElementorForge\Elementor\Emitter\EncoderException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EncoderExceptionTest extends TestCase {

	public function test_encoder_exception_is_runtime_exception_subclass(): void {
		$e = new EncoderException( 'test message' );
		$this->assertInstanceOf( RuntimeException::class, $e );
	}

	public function test_encoder_exception_can_be_thrown_and_caught(): void {
		$caught = null;

		try {
			throw new EncoderException( 'encoding failed' );
		} catch ( EncoderException $e ) {
			$caught = $e;
		}

		$this->assertNotNull( $caught );
		$this->assertSame( 'encoding failed', $caught->getMessage() );
	}

	public function test_encoder_exception_is_catchable_as_runtime_exception(): void {
		$caught = null;

		try {
			throw new EncoderException( 'caught as parent' );
		} catch ( RuntimeException $e ) {
			$caught = $e;
		}

		$this->assertNotNull( $caught );
		$this->assertInstanceOf( EncoderException::class, $caught );
	}

	public function test_encoder_exception_preserves_message(): void {
		$message = 'Failed to JSON-encode Elementor content tree. Possible cause: non-UTF-8 data in widget settings.';
		$e       = new EncoderException( $message );
		$this->assertSame( $message, $e->getMessage() );
	}

	public function test_encoder_exception_supports_chained_cause(): void {
		$cause    = new \RuntimeException( 'original' );
		$e        = new EncoderException( 'wrapper', 0, $cause );
		$this->assertSame( $cause, $e->getPrevious() );
	}

	public function test_encoder_exception_default_code_is_zero(): void {
		$e = new EncoderException( 'msg' );
		$this->assertSame( 0, $e->getCode() );
	}

	public function test_encoder_exception_accepts_custom_code(): void {
		$e = new EncoderException( 'msg', 42 );
		$this->assertSame( 42, $e->getCode() );
	}
}
