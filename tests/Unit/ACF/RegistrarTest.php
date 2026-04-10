<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\ACF;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\ACF\Registrar;
use PHPUnit\Framework\TestCase;

final class RegistrarTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_boot_registers_acf_init_hook(): void {
		$registered_hooks = array();
		Functions\when( 'add_action' )->alias(
			static function ( string $hook, $callback, int $priority = 10 ) use ( &$registered_hooks ): void {
				$registered_hooks[] = array(
					'hook'     => $hook,
					'callback' => $callback,
					'priority' => $priority,
				);
			}
		);

		$registrar = new Registrar();
		$registrar->boot();

		$this->assertCount( 1, $registered_hooks );
		$this->assertSame( 'acf/init', $registered_hooks[0]['hook'] );
		$this->assertSame( array( $registrar, 'register_all' ), $registered_hooks[0]['callback'] );
	}

	/**
	 * When acf_add_local_field_group is not defined (no Brain\Monkey stub),
	 * function_exists returns false naturally and register_all exits early.
	 */
	public function test_register_all_exits_early_when_acf_missing(): void {
		// Do NOT define acf_add_local_field_group — function_exists() returns false.
		$registrar = new Registrar();
		$registrar->register_all();

		// No exception = early return worked.
		$this->assertTrue( true );
	}

	/**
	 * When acf_add_local_field_group IS defined via Brain\Monkey, function_exists
	 * returns true and register_all iterates the field groups.
	 */
	public function test_register_all_calls_acf_add_local_field_group_in_free_mode(): void {
		// Store::is_acf_pro_mode() reads get_option — empty array = free mode default.
		Functions\when( 'get_option' )->justReturn( array() );

		// Declare acf_add_local_field_group via Brain\Monkey so function_exists
		// returns true naturally — never stub function_exists itself.
		$called_groups = array();
		Functions\when( 'acf_add_local_field_group' )->alias(
			static function ( array $group ) use ( &$called_groups ): void {
				$called_groups[] = $group;
			}
		);

		$registrar = new Registrar();
		$registrar->register_all();

		$this->assertNotEmpty( $called_groups, 'acf_add_local_field_group should be called at least once' );
	}

	/**
	 * When Store says pro mode but no ACF Pro classes exist, the Registrar
	 * falls back to free mode (acf_pro_active returns false).
	 */
	public function test_register_all_falls_back_to_free_when_pro_classes_absent(): void {
		Functions\when( 'get_option' )->justReturn( array( 'acf_mode' => 'pro' ) );

		$called_groups = array();
		Functions\when( 'acf_add_local_field_group' )->alias(
			static function ( array $group ) use ( &$called_groups ): void {
				$called_groups[] = $group;
			}
		);

		$registrar = new Registrar();
		$registrar->register_all();

		// Falls back to free mode — still registers groups.
		$this->assertNotEmpty( $called_groups );
	}

	public function test_acf_pro_active_returns_false_when_no_pro_classes_exist(): void {
		$this->assertFalse( Registrar::acf_pro_active() );
	}
}
