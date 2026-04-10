<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\CPT;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\CPT\PostTypes;
use ElementorForge\CPT\Registrar;
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

	public function test_boot_registers_init_hook_at_priority_zero(): void {
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
		$this->assertSame( 'init', $registered_hooks[0]['hook'] );
		$this->assertSame( array( $registrar, 'register_all' ), $registered_hooks[0]['callback'] );
		$this->assertSame( 0, $registered_hooks[0]['priority'] );
	}

	public function test_register_all_calls_register_post_type_for_each_cpt(): void {
		$registered = array();
		Functions\when( 'register_post_type' )->alias(
			static function ( string $slug, array $args ) use ( &$registered ): void {
				$registered[ $slug ] = $args;
			}
		);

		$registrar = new Registrar();
		$registrar->register_all();

		$expected_slugs = array_keys( PostTypes::all() );
		foreach ( $expected_slugs as $slug ) {
			$this->assertArrayHasKey( $slug, $registered, "Missing CPT registration: {$slug}" );
		}
	}

	public function test_register_all_passes_correct_args_for_each_cpt(): void {
		$registered = array();
		Functions\when( 'register_post_type' )->alias(
			static function ( string $slug, array $args ) use ( &$registered ): void {
				$registered[ $slug ] = $args;
			}
		);

		$registrar = new Registrar();
		$registrar->register_all();

		foreach ( PostTypes::all() as $slug => $expected_args ) {
			$this->assertSame(
				$expected_args,
				$registered[ $slug ],
				"Args mismatch for CPT '{$slug}'"
			);
		}
	}

	public function test_register_all_call_count_matches_post_types_count(): void {
		$call_count = 0;
		Functions\when( 'register_post_type' )->alias(
			static function () use ( &$call_count ): void {
				++$call_count;
			}
		);

		$registrar = new Registrar();
		$registrar->register_all();

		$this->assertSame( count( PostTypes::all() ), $call_count );
	}
}
