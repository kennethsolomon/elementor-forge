<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Internal;

require_once dirname( __DIR__, 3 ) . '/../src/MCP/Internal/class-wp-ability.php';
require_once dirname( __DIR__, 3 ) . '/../src/MCP/Internal/class-wp-ability-category.php';
require_once dirname( __DIR__, 3 ) . '/../src/MCP/Internal/class-wp-ability-categories-registry.php';
require_once dirname( __DIR__, 3 ) . '/../src/MCP/Internal/class-wp-abilities-registry.php';

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WP_Abilities_Registry;
use WP_Ability;
use WP_Error;

final class WPAbilitiesRegistryTest extends TestCase {

	private WP_Abilities_Registry $registry;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_parse_args' )->alias(
			static function ( $args, array $defaults = array() ): array {
				if ( is_object( $args ) ) {
					$args = get_object_vars( $args );
				}
				return array_merge( $defaults, is_array( $args ) ? $args : array() );
			}
		);
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof WP_Error );
		Functions\when( 'apply_filters' )->alias( static fn ( string $tag, ...$args ) => $args[0] );
		Functions\when( 'wp_has_ability_category' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( null );

		// Use reflection to create a fresh instance without the singleton gate.
		$ref            = new \ReflectionClass( WP_Abilities_Registry::class );
		$this->registry = $ref->newInstanceWithoutConstructor();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function validArgs( array $overrides = array() ): array {
		return array_merge(
			array(
				'label'               => 'Test',
				'description'         => 'A test.',
				'category'            => 'test-cat',
				'execute_callback'    => static fn () => true,
				'permission_callback' => static fn () => true,
			),
			$overrides
		);
	}

	public function test_register_returns_ability_on_valid_input(): void {
		$ability = $this->registry->register( 'test/my-ability', $this->validArgs() );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		$this->assertSame( 'test/my-ability', $ability->get_name() );
	}

	public function test_register_rejects_invalid_name_format(): void {
		$result = $this->registry->register( 'invalid name!', $this->validArgs() );
		$this->assertNull( $result );
	}

	public function test_register_rejects_name_without_namespace(): void {
		$result = $this->registry->register( 'nonamespace', $this->validArgs() );
		$this->assertNull( $result );
	}

	public function test_register_rejects_uppercase_name(): void {
		$result = $this->registry->register( 'Test/Ability', $this->validArgs() );
		$this->assertNull( $result );
	}

	public function test_register_rejects_duplicate_name(): void {
		$this->registry->register( 'test/ability', $this->validArgs() );
		$duplicate = $this->registry->register( 'test/ability', $this->validArgs() );
		$this->assertNull( $duplicate );
	}

	public function test_register_rejects_unknown_category(): void {
		Functions\when( 'wp_has_ability_category' )->justReturn( false );

		$result = $this->registry->register( 'test/ability', $this->validArgs() );
		$this->assertNull( $result );
	}

	public function test_register_rejects_invalid_ability_class(): void {
		$result = $this->registry->register(
			'test/ability',
			$this->validArgs( array( 'ability_class' => \stdClass::class ) )
		);
		$this->assertNull( $result );
	}

	public function test_register_returns_null_on_construct_exception(): void {
		// Missing required label triggers InvalidArgumentException
		$result = $this->registry->register(
			'test/ability',
			array(
				'description'         => 'x',
				'category'            => 'c',
				'execute_callback'    => static fn () => true,
				'permission_callback' => static fn () => true,
			)
		);
		$this->assertNull( $result );
	}

	public function test_unregister_removes_and_returns_ability(): void {
		$this->registry->register( 'test/ability', $this->validArgs() );
		$removed = $this->registry->unregister( 'test/ability' );

		$this->assertInstanceOf( WP_Ability::class, $removed );
		$this->assertFalse( $this->registry->is_registered( 'test/ability' ) );
	}

	public function test_unregister_returns_null_for_unknown(): void {
		$result = $this->registry->unregister( 'test/nonexistent' );
		$this->assertNull( $result );
	}

	public function test_get_all_registered_returns_empty_initially(): void {
		$this->assertSame( array(), $this->registry->get_all_registered() );
	}

	public function test_get_all_registered_returns_all_abilities(): void {
		$this->registry->register( 'test/one', $this->validArgs( array( 'label' => 'One' ) ) );
		$this->registry->register( 'test/two', $this->validArgs( array( 'label' => 'Two' ) ) );

		$all = $this->registry->get_all_registered();
		$this->assertCount( 2, $all );
		$this->assertArrayHasKey( 'test/one', $all );
		$this->assertArrayHasKey( 'test/two', $all );
	}

	public function test_is_registered_returns_true_for_existing(): void {
		$this->registry->register( 'test/ability', $this->validArgs() );
		$this->assertTrue( $this->registry->is_registered( 'test/ability' ) );
	}

	public function test_is_registered_returns_false_for_unknown(): void {
		$this->assertFalse( $this->registry->is_registered( 'test/nope' ) );
	}

	public function test_get_registered_returns_ability_for_existing(): void {
		$this->registry->register( 'test/ability', $this->validArgs() );
		$ability = $this->registry->get_registered( 'test/ability' );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		$this->assertSame( 'test/ability', $ability->get_name() );
	}

	public function test_get_registered_returns_null_for_unknown(): void {
		$this->assertNull( $this->registry->get_registered( 'test/nope' ) );
	}

	public function test_get_instance_returns_null_before_init(): void {
		Functions\when( 'did_action' )->justReturn( 0 );
		$this->assertNull( WP_Abilities_Registry::get_instance() );
	}

	public function test_wakeup_throws_logic_exception(): void {
		$this->expectException( \LogicException::class );
		$this->registry->__wakeup();
	}

	public function test_sleep_throws_logic_exception(): void {
		$this->expectException( \LogicException::class );
		$this->registry->__sleep();
	}

	public function test_register_applies_filter_on_args(): void {
		$filter_called = false;
		Functions\when( 'apply_filters' )->alias(
			static function ( string $tag, $args, $_name ) use ( &$filter_called ) {
				if ( 'wp_register_ability_args' === $tag ) {
					$filter_called = true;
				}
				return $args;
			}
		);

		$this->registry->register( 'test/filtered', $this->validArgs() );
		$this->assertTrue( $filter_called );
	}
}
