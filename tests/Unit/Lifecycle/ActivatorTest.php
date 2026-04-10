<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Lifecycle;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\Lifecycle\Activator;
use PHPUnit\Framework\TestCase;

final class ActivatorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Define constants the activator depends on.
		if ( ! defined( 'ELEMENTOR_FORGE_MIN_PHP' ) ) {
			define( 'ELEMENTOR_FORGE_MIN_PHP', '8.0' );
		}
		if ( ! defined( 'ELEMENTOR_FORGE_VERSION' ) ) {
			define( 'ELEMENTOR_FORGE_VERSION', '0.5.0' );
		}
		if ( ! defined( 'ELEMENTOR_FORGE_PLUGIN_FILE' ) ) {
			define( 'ELEMENTOR_FORGE_PLUGIN_FILE', '/wp-content/plugins/elementor-forge/elementor-forge.php' );
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_activate_records_version_and_timestamp_on_fresh_install(): void {
		// PHP 8.0 >= 8.0 — the real version_compare passes the gate naturally.
		$stored_values = array();
		Functions\when( 'update_option' )->alias(
			static function ( string $key, $value, $_autoload ) use ( &$stored_values ): bool {
				$stored_values[ $key ] = $value;
				return true;
			}
		);

		// First activation — no existing timestamp.
		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );

		Activator::activate();

		$this->assertArrayHasKey( Activator::OPTION_VERSION, $stored_values );
		$this->assertSame( ELEMENTOR_FORGE_VERSION, $stored_values[ Activator::OPTION_VERSION ] );
		$this->assertArrayHasKey( Activator::OPTION_ACTIVATED_AT, $stored_values );
		// Timestamp should be an ISO 8601 date.
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T/', $stored_values[ Activator::OPTION_ACTIVATED_AT ] );
	}

	public function test_activate_skips_activated_at_when_already_set(): void {
		$updated_keys = array();
		Functions\when( 'update_option' )->alias(
			static function ( string $key ) use ( &$updated_keys ): bool {
				$updated_keys[] = $key;
				return true;
			}
		);

		// Existing activation timestamp — should not overwrite.
		Functions\when( 'get_option' )->justReturn( '2025-01-01T00:00:00+00:00' );
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );

		Activator::activate();

		$this->assertContains( Activator::OPTION_VERSION, $updated_keys );
		$this->assertNotContains( Activator::OPTION_ACTIVATED_AT, $updated_keys );
	}

	public function test_activate_fires_activated_action(): void {
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( '2025-01-01T00:00:00+00:00' );
		Functions\when( 'flush_rewrite_rules' )->justReturn( null );

		$fired_actions = array();
		Functions\when( 'do_action' )->alias(
			static function ( string $hook ) use ( &$fired_actions ): void {
				$fired_actions[] = $hook;
			}
		);

		Activator::activate();

		$this->assertContains( 'elementor_forge/activated', $fired_actions );
	}

	public function test_activate_flushes_rewrite_rules(): void {
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( '2025-01-01T00:00:00+00:00' );
		Functions\when( 'do_action' )->justReturn( null );

		$flushed = false;
		$hard_value = null;
		Functions\when( 'flush_rewrite_rules' )->alias(
			static function ( bool $hard ) use ( &$flushed, &$hard_value ): void {
				$flushed = true;
				$hard_value = $hard;
			}
		);

		Activator::activate();

		$this->assertTrue( $flushed );
		$this->assertFalse( $hard_value, 'flush_rewrite_rules should be called with false (soft flush)' );
	}

	public function test_option_constants_match_expected_values(): void {
		$this->assertSame( 'elementor_forge_version', Activator::OPTION_VERSION );
		$this->assertSame( 'elementor_forge_activated_at', Activator::OPTION_ACTIVATED_AT );
	}
}
