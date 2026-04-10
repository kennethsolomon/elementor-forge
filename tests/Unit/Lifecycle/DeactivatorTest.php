<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Lifecycle;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\Lifecycle\Deactivator;
use PHPUnit\Framework\TestCase;

final class DeactivatorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_deactivate_fires_deactivated_action(): void {
		$fired_actions = array();
		Functions\when( 'do_action' )->alias(
			static function ( string $hook ) use ( &$fired_actions ): void {
				$fired_actions[] = $hook;
			}
		);
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );

		Deactivator::deactivate();

		$this->assertContains( 'elementor_forge/deactivated', $fired_actions );
	}

	public function test_deactivate_flushes_rewrite_rules(): void {
		Functions\when( 'do_action' )->justReturn( null );

		$flushed = false;
		$hard_value = null;
		Functions\when( 'flush_rewrite_rules' )->alias(
			static function ( bool $hard ) use ( &$flushed, &$hard_value ): void {
				$flushed = true;
				$hard_value = $hard;
			}
		);

		Deactivator::deactivate();

		$this->assertTrue( $flushed );
		$this->assertFalse( $hard_value, 'flush_rewrite_rules should be called with false (soft flush)' );
	}

	public function test_deactivate_does_not_remove_data(): void {
		// Deactivator must never call delete_option or any data-removal function.
		// If delete_option is called, Brain Monkey will throw because it was
		// never stubbed. We only stub the functions Deactivator SHOULD call.
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );

		Deactivator::deactivate();

		// If we reached here without error, delete_option was never invoked.
		$this->assertTrue( true );
	}
}
