<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\WooCommerce\Fibosearch;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\WooCommerce\Fibosearch\Configurator;
use PHPUnit\Framework\TestCase;

final class ConfiguratorTest extends TestCase {

	/** @var array<string, mixed> */
	private array $option_store = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->option_store = array();

		Functions\when( 'get_option' )->alias(
			function ( string $key, $fallback = false ) {
				return array_key_exists( $key, $this->option_store ) ? $this->option_store[ $key ] : $fallback;
			}
		);

		Functions\when( 'update_option' )->alias(
			function ( string $key, $value ) {
				$this->option_store[ $key ] = $value;
				return true;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_apply_defaults_skips_when_fibosearch_missing(): void {
		// Intentionally do not define dgwt_wcas — represents plugin absence.
		$configurator = new Configurator();
		$result       = $configurator->apply_defaults();

		$this->assertFalse( $result['applied'] );
		$this->assertStringContainsString( 'Fibosearch not detected', $result['reason'] );
		$this->assertSame( array(), $result['keys_updated'] );
	}

	public function test_apply_defaults_writes_every_key_when_option_missing(): void {
		$this->fake_fibosearch();

		$result = ( new Configurator() )->apply_defaults();

		$this->assertTrue( $result['applied'] );
		$this->assertNotEmpty( $result['keys_updated'] );
		$this->assertSame( array(), $result['keys_preserved'] );

		// Every default key lives in the stored option.
		foreach ( array_keys( Configurator::DEFAULTS ) as $key ) {
			$this->assertArrayHasKey( $key, $this->option_store[ Configurator::OPTION_KEY ] );
		}
	}

	public function test_apply_defaults_is_idempotent(): void {
		$this->fake_fibosearch();

		$first  = ( new Configurator() )->apply_defaults();
		$second = ( new Configurator() )->apply_defaults();

		$this->assertTrue( $first['applied'] );
		$this->assertTrue( $second['applied'] );
		$this->assertNotEmpty( $first['keys_updated'] );
		$this->assertSame( array(), $second['keys_updated'], 'Second run must not re-apply keys.' );
		$this->assertStringContainsString( 'already set', $second['reason'] );
	}

	public function test_apply_defaults_preserves_user_customizations(): void {
		$this->fake_fibosearch();
		$this->option_store[ Configurator::OPTION_KEY ] = array(
			'is_fuzzy_matching' => 0, // user turned it off
			'min_chars'         => 3, // user raised the threshold
		);

		$result = ( new Configurator() )->apply_defaults();

		$this->assertTrue( $result['applied'] );
		$this->assertContains( 'is_fuzzy_matching', $result['keys_preserved'] );
		$this->assertContains( 'min_chars', $result['keys_preserved'] );
		$this->assertSame( 0, $this->option_store[ Configurator::OPTION_KEY ]['is_fuzzy_matching'] );
		$this->assertSame( 3, $this->option_store[ Configurator::OPTION_KEY ]['min_chars'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_is_in_sync_returns_false_when_fibosearch_missing(): void {
		$configurator = new Configurator();
		$this->assertFalse( $configurator->is_in_sync() );
	}

	public function test_is_in_sync_returns_true_after_apply(): void {
		$this->fake_fibosearch();
		$configurator = new Configurator();

		$this->assertFalse( $configurator->is_in_sync() );
		$configurator->apply_defaults();
		$this->assertTrue( $configurator->is_in_sync() );
	}

	public function test_report_returns_structured_shape(): void {
		$this->fake_fibosearch();

		$report = ( new Configurator() )->report();
		$this->assertArrayHasKey( 'detected', $report );
		$this->assertArrayHasKey( 'version', $report );
		$this->assertArrayHasKey( 'option_exists', $report );
		$this->assertArrayHasKey( 'in_sync', $report );
		$this->assertArrayHasKey( 'keys_present', $report );
		$this->assertTrue( $report['detected'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_report_when_fibosearch_absent(): void {
		$report = ( new Configurator() )->report();
		$this->assertFalse( $report['detected'] );
		$this->assertFalse( $report['option_exists'] );
		$this->assertFalse( $report['in_sync'] );
		$this->assertSame( 0, $report['keys_present'] );
	}

	private function fake_fibosearch(): void {
		if ( ! function_exists( 'dgwt_wcas' ) ) {
			Functions\when( 'dgwt_wcas' )->justReturn( null );
		}
	}
}
