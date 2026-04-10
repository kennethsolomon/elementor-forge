<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Internal;

require_once dirname( __DIR__, 3 ) . '/../src/MCP/Internal/class-wp-ability-category.php';
require_once dirname( __DIR__, 3 ) . '/../src/MCP/Internal/class-wp-ability-categories-registry.php';

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WP_Ability_Categories_Registry;
use WP_Ability_Category;

final class WPAbilityCategoriesRegistryTest extends TestCase {

	private WP_Ability_Categories_Registry $registry;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'apply_filters' )->alias( static fn ( string $tag, ...$args ) => $args[0] );
		Functions\when( 'do_action' )->justReturn( null );

		// Use reflection to create a fresh instance without the singleton gate.
		$ref            = new \ReflectionClass( WP_Ability_Categories_Registry::class );
		$this->registry = $ref->newInstanceWithoutConstructor();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_register_returns_category_on_valid_input(): void {
		$category = $this->registry->register(
			'my-category',
			array( 'label' => 'My Category', 'description' => 'A category.' )
		);

		$this->assertInstanceOf( WP_Ability_Category::class, $category );
		$this->assertSame( 'my-category', $category->get_slug() );
	}

	public function test_register_rejects_duplicate_slug(): void {
		$this->registry->register( 'my-cat', array( 'label' => 'X', 'description' => 'X' ) );
		$duplicate = $this->registry->register( 'my-cat', array( 'label' => 'Y', 'description' => 'Y' ) );
		$this->assertNull( $duplicate );
	}

	public function test_register_rejects_invalid_slug_format_uppercase(): void {
		$result = $this->registry->register( 'My-Cat', array( 'label' => 'X', 'description' => 'X' ) );
		$this->assertNull( $result );
	}

	public function test_register_rejects_slug_with_spaces(): void {
		$result = $this->registry->register( 'my cat', array( 'label' => 'X', 'description' => 'X' ) );
		$this->assertNull( $result );
	}

	public function test_register_rejects_slug_with_special_chars(): void {
		$result = $this->registry->register( 'my_cat!', array( 'label' => 'X', 'description' => 'X' ) );
		$this->assertNull( $result );
	}

	public function test_register_accepts_hyphenated_slug(): void {
		$category = $this->registry->register(
			'multi-word-category',
			array( 'label' => 'Multi', 'description' => 'Multi word.' )
		);
		$this->assertInstanceOf( WP_Ability_Category::class, $category );
	}

	public function test_register_accepts_numeric_slug(): void {
		$category = $this->registry->register(
			'cat123',
			array( 'label' => 'Nums', 'description' => 'Numbers.' )
		);
		$this->assertInstanceOf( WP_Ability_Category::class, $category );
	}

	public function test_register_returns_null_on_construct_exception(): void {
		// Missing label triggers InvalidArgumentException inside WP_Ability_Category
		$result = $this->registry->register( 'bad-cat', array( 'description' => 'only desc' ) );
		$this->assertNull( $result );
	}

	public function test_unregister_removes_and_returns_category(): void {
		$this->registry->register( 'to-remove', array( 'label' => 'X', 'description' => 'X' ) );
		$removed = $this->registry->unregister( 'to-remove' );

		$this->assertInstanceOf( WP_Ability_Category::class, $removed );
		$this->assertFalse( $this->registry->is_registered( 'to-remove' ) );
	}

	public function test_unregister_returns_null_for_unknown(): void {
		$this->assertNull( $this->registry->unregister( 'nonexistent' ) );
	}

	public function test_get_all_registered_returns_empty_initially(): void {
		$this->assertSame( array(), $this->registry->get_all_registered() );
	}

	public function test_get_all_registered_returns_all_categories(): void {
		$this->registry->register( 'cat-a', array( 'label' => 'A', 'description' => 'A' ) );
		$this->registry->register( 'cat-b', array( 'label' => 'B', 'description' => 'B' ) );

		$all = $this->registry->get_all_registered();
		$this->assertCount( 2, $all );
		$this->assertArrayHasKey( 'cat-a', $all );
		$this->assertArrayHasKey( 'cat-b', $all );
	}

	public function test_is_registered_returns_true_for_existing(): void {
		$this->registry->register( 'exists', array( 'label' => 'E', 'description' => 'E' ) );
		$this->assertTrue( $this->registry->is_registered( 'exists' ) );
	}

	public function test_is_registered_returns_false_for_unknown(): void {
		$this->assertFalse( $this->registry->is_registered( 'nope' ) );
	}

	public function test_get_registered_returns_category_for_existing(): void {
		$this->registry->register( 'found', array( 'label' => 'F', 'description' => 'F' ) );
		$cat = $this->registry->get_registered( 'found' );
		$this->assertInstanceOf( WP_Ability_Category::class, $cat );
		$this->assertSame( 'found', $cat->get_slug() );
	}

	public function test_get_registered_returns_null_for_unknown(): void {
		$this->assertNull( $this->registry->get_registered( 'nope' ) );
	}

	public function test_get_instance_returns_null_before_init(): void {
		Functions\when( 'did_action' )->justReturn( 0 );
		$this->assertNull( WP_Ability_Categories_Registry::get_instance() );
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
			static function ( string $tag, $args, $_slug ) use ( &$filter_called ) {
				if ( 'wp_register_ability_category_args' === $tag ) {
					$filter_called = true;
				}
				return $args;
			}
		);

		$this->registry->register( 'filtered', array( 'label' => 'F', 'description' => 'F' ) );
		$this->assertTrue( $filter_called );
	}

	public function test_register_rejects_slug_with_underscores(): void {
		$result = $this->registry->register( 'my_cat', array( 'label' => 'X', 'description' => 'X' ) );
		$this->assertNull( $result );
	}
}
