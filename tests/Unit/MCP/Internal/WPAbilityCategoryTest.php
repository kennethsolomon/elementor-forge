<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Internal;

require_once dirname( __DIR__, 3 ) . '/../src/MCP/Internal/class-wp-ability-category.php';

use Brain\Monkey;
use PHPUnit\Framework\TestCase;
use WP_Ability_Category;

final class WPAbilityCategoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_construct_stores_slug_and_properties(): void {
		$category = new WP_Ability_Category(
			'test-cat',
			array( 'label' => 'Test Category', 'description' => 'A test.' )
		);

		$this->assertSame( 'test-cat', $category->get_slug() );
		$this->assertSame( 'Test Category', $category->get_label() );
		$this->assertSame( 'A test.', $category->get_description() );
	}

	public function test_construct_throws_when_slug_empty(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'slug cannot be empty' );
		new WP_Ability_Category( '', array( 'label' => 'X', 'description' => 'X' ) );
	}

	public function test_construct_throws_when_label_missing(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'label' );
		new WP_Ability_Category( 'slug', array( 'description' => 'X' ) );
	}

	public function test_construct_throws_when_label_not_string(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'label' );
		new WP_Ability_Category( 'slug', array( 'label' => 123, 'description' => 'X' ) );
	}

	public function test_construct_throws_when_description_missing(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'description' );
		new WP_Ability_Category( 'slug', array( 'label' => 'X' ) );
	}

	public function test_construct_throws_when_description_not_string(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'description' );
		new WP_Ability_Category( 'slug', array( 'label' => 'X', 'description' => false ) );
	}

	public function test_construct_throws_when_meta_not_array(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'meta' );
		new WP_Ability_Category( 'slug', array( 'label' => 'X', 'description' => 'X', 'meta' => 'bad' ) );
	}

	public function test_get_meta_returns_empty_by_default(): void {
		$category = new WP_Ability_Category(
			'slug',
			array( 'label' => 'X', 'description' => 'X' )
		);
		$this->assertSame( array(), $category->get_meta() );
	}

	public function test_get_meta_returns_provided_values(): void {
		$category = new WP_Ability_Category(
			'slug',
			array( 'label' => 'X', 'description' => 'X', 'meta' => array( 'key' => 'val' ) )
		);
		$this->assertSame( array( 'key' => 'val' ), $category->get_meta() );
	}

	public function test_unknown_properties_are_ignored(): void {
		$category = new WP_Ability_Category(
			'slug',
			array( 'label' => 'Cat', 'description' => 'Desc', 'bogus' => true )
		);
		$this->assertSame( 'Cat', $category->get_label() );
	}

	public function test_wakeup_throws_logic_exception(): void {
		$category = new WP_Ability_Category(
			'slug',
			array( 'label' => 'X', 'description' => 'X' )
		);
		$this->expectException( \LogicException::class );
		$category->__wakeup();
	}

	public function test_sleep_throws_logic_exception(): void {
		$category = new WP_Ability_Category(
			'slug',
			array( 'label' => 'X', 'description' => 'X' )
		);
		$this->expectException( \LogicException::class );
		$category->__sleep();
	}
}
