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
		$delete_meta_calls   = array();
		$delete_option_calls = array();

		Functions\when( 'delete_post_meta' )->alias(
			static function ( $post_id, $key ) use ( &$delete_meta_calls ): bool {
				$delete_meta_calls[] = array( 'post_id' => $post_id, 'key' => $key );
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			static function ( $option ) use ( &$delete_option_calls ): bool {
				$delete_option_calls[] = $option;
				return true;
			}
		);

		CacheClearer::clear( 99 );

		$this->assertCount( 1, $delete_meta_calls );
		$this->assertSame( 99, $delete_meta_calls[0]['post_id'] );
		$this->assertSame( '_elementor_css', $delete_meta_calls[0]['key'] );
		$this->assertContains( 'elementor_pro_theme_builder_conditions_cache', $delete_option_calls );
	}

	public function test_clear_with_zero_post_id_skips_delete_post_meta(): void {
		$delete_meta_calls   = array();
		$delete_option_calls = array();

		Functions\when( 'delete_post_meta' )->alias(
			static function ( $post_id, $key ) use ( &$delete_meta_calls ): bool {
				$delete_meta_calls[] = array( 'post_id' => $post_id, 'key' => $key );
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			static function ( $option ) use ( &$delete_option_calls ): bool {
				$delete_option_calls[] = $option;
				return true;
			}
		);

		CacheClearer::clear( 0 );

		$this->assertCount( 0, $delete_meta_calls );
		$this->assertContains( 'elementor_pro_theme_builder_conditions_cache', $delete_option_calls );
	}

	public function test_clear_default_argument_is_zero(): void {
		$delete_meta_calls = array();

		Functions\when( 'delete_post_meta' )->alias(
			static function () use ( &$delete_meta_calls ): bool {
				$delete_meta_calls[] = true;
				return true;
			}
		);
		Functions\when( 'delete_option' )->justReturn( true );

		CacheClearer::clear();

		$this->assertCount( 0, $delete_meta_calls );
	}

	public function test_clear_always_deletes_theme_builder_conditions_cache(): void {
		$delete_option_calls = array();

		Functions\when( 'delete_post_meta' )->justReturn( true );
		Functions\when( 'delete_option' )->alias(
			static function ( $option ) use ( &$delete_option_calls ): bool {
				$delete_option_calls[] = $option;
				return true;
			}
		);

		CacheClearer::clear( 1 );

		$this->assertContains( 'elementor_pro_theme_builder_conditions_cache', $delete_option_calls );
	}

	public function test_clear_skips_elementor_plugin_files_manager_when_class_does_not_exist(): void {
		Functions\when( 'delete_post_meta' )->justReturn( true );
		Functions\when( 'delete_option' )->justReturn( true );

		CacheClearer::clear( 5 );

		$this->assertTrue( true );
	}

	public function test_clear_with_large_post_id_calls_delete_post_meta_with_correct_id(): void {
		$delete_meta_calls = array();

		Functions\when( 'delete_post_meta' )->alias(
			static function ( $post_id, $key ) use ( &$delete_meta_calls ): bool {
				$delete_meta_calls[] = array( 'post_id' => $post_id, 'key' => $key );
				return true;
			}
		);
		Functions\when( 'delete_option' )->justReturn( true );

		CacheClearer::clear( 99999 );

		$this->assertCount( 1, $delete_meta_calls );
		$this->assertSame( 99999, $delete_meta_calls[0]['post_id'] );
	}
}
