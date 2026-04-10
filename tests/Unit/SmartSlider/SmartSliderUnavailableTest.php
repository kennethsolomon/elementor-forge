<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\SmartSlider;

use ElementorForge\SmartSlider\SmartSliderUnavailable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SmartSliderUnavailableTest extends TestCase {

	public function test_extends_runtime_exception(): void {
		$exception = new SmartSliderUnavailable();
		$this->assertInstanceOf( RuntimeException::class, $exception );
	}

	public function test_default_message_is_empty(): void {
		$exception = new SmartSliderUnavailable();
		$this->assertSame( '', $exception->getMessage() );
	}

	public function test_custom_message_is_preserved(): void {
		$message   = 'Smart Slider 3 is not installed or activated.';
		$exception = new SmartSliderUnavailable( $message );
		$this->assertSame( $message, $exception->getMessage() );
	}

	public function test_custom_code_is_preserved(): void {
		$exception = new SmartSliderUnavailable( 'error', 42 );
		$this->assertSame( 42, $exception->getCode() );
	}

	public function test_previous_exception_is_chained(): void {
		$previous  = new \Exception( 'root cause' );
		$exception = new SmartSliderUnavailable( 'wrapper', 0, $previous );
		$this->assertSame( $previous, $exception->getPrevious() );
	}

	public function test_can_be_thrown_and_caught_as_runtime_exception(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'SS3 table missing' );

		throw new SmartSliderUnavailable( 'SS3 table missing' );
	}

	public function test_can_be_caught_by_own_class(): void {
		$this->expectException( SmartSliderUnavailable::class );

		throw new SmartSliderUnavailable( 'not available' );
	}

	public function test_default_code_is_zero(): void {
		$exception = new SmartSliderUnavailable( 'msg' );
		$this->assertSame( 0, $exception->getCode() );
	}
}
