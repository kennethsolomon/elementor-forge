<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\Elementor\CacheClearer;
use PHPUnit\Framework\TestCase;

final class CacheClearerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_clear_with_positive_post_id_calls_delete_post_meta_and_delete_option(): void {
		Functions\expect( 'delete_post_meta' )
			->once()
			->with( 99, '_elementor_css' );

		Functions\expect( 'delete_option' )
			->once()
			->with( 'elementor_pro_theme_builder_conditions_cache' );

		CacheClearer::clear( 99 );
	}

	public function test_clear_with_zero_post_id_skips_delete_post_meta(): void {
		Functions\expect( 'delete_post_meta' )->never();

		Functions\expect( 'delete_option' )
			->once()
			->with( 'elementor_pro_theme_builder_conditions_cache' );

		CacheClearer::clear( 0 );
	}

	public function test_clear_default_argument_is_zero(): void {
		Functions\expect( 'delete_post_meta' )->never();

		Functions\expect( 'delete_option' )
			->once()
			->with( 'elementor_pro_theme_builder_conditions_cache' );

		CacheClearer::clear();
	}

	public function test_clear_always_deletes_theme_builder_conditions_cache(): void {
		Functions\when( 'delete_post_meta' )->justReturn( true );

		Functions\expect( 'delete_option' )
			->once()
			->with( 'elementor_pro_theme_builder_conditions_cache' );

		CacheClearer::clear( 1 );
	}

	public function test_clear_skips_elementor_plugin_files_manager_when_class_does_not_exist(): void {
		// \Elementor\Plugin is not loaded in unit tests — class_exists() returns
		// false so the files_manager branch must silently skip without errors.
		Functions\when( 'delete_post_meta' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );

		CacheClearer::clear( 5 );

		// No exception means the class_exists guard worked correctly.
		$this->assertTrue( true );
	}

	public function test_clear_with_large_post_id_calls_delete_post_meta_with_correct_id(): void {
		Functions\expect( 'delete_post_meta' )
			->once()
			->with( 99999, '_elementor_css' );

		Functions\when( 'delete_option' )->justReturn( true );

		CacheClearer::clear( 99999 );
	}
}
