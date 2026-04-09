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
	 * Regression guard for the int-0 stomp bug. Fibosearch stores checkbox
	 * state as int 0/1. A user who ticks a box off stores int 0. The old
	 * sentinel `'' !== $current[$key]` treated int 0 as "unset" and stomped
	 * it back to the shipped default on re-apply. The fix uses
	 * `array_key_exists()` — if the key is on the option, the user has
	 * visited that setting and the value is preserved as-is.
	 */
	public function test_apply_defaults_preserves_explicit_int_zero(): void {
		$this->fake_fibosearch();
		// Seed keys with ints matching what Fibosearch actually writes on
		// checkbox uncheck — the user explicitly disabled every search-in
		// toggle. Default for all of these is 1.
		$this->option_store[ Configurator::OPTION_KEY ] = array(
			'search_in_product_title' => 0,
			'search_in_product_sku'   => 0,
			'show_product_image'      => 0,
		);

		$result = ( new Configurator() )->apply_defaults();

		$this->assertTrue( $result['applied'] );
		$this->assertContains( 'search_in_product_title', $result['keys_preserved'] );
		$this->assertContains( 'search_in_product_sku', $result['keys_preserved'] );
		$this->assertContains( 'show_product_image', $result['keys_preserved'] );

		// The int 0 values must survive — NOT be rewritten to the default 1.
		$stored = $this->option_store[ Configurator::OPTION_KEY ];
		$this->assertSame( 0, $stored['search_in_product_title'] );
		$this->assertSame( 0, $stored['search_in_product_sku'] );
		$this->assertSame( 0, $stored['show_product_image'] );

		// And a missing key gets populated with its default on the same run.
		$this->assertArrayHasKey( 'is_fuzzy_matching', $stored );
		$this->assertSame( 1, $stored['is_fuzzy_matching'] );
		$this->assertContains( 'is_fuzzy_matching', $result['keys_updated'] );
	}

	/**
	 * An explicit empty string (user cleared a text field) is a visited
	 * setting and must be preserved. This documents the contract that the
	 * sentinel is key-existence, not value-truthiness.
	 */
	public function test_apply_defaults_preserves_explicit_empty_string(): void {
		$this->fake_fibosearch();
		$this->option_store[ Configurator::OPTION_KEY ] = array(
			'show_matching_fuzziness_level' => '',
		);

		$result = ( new Configurator() )->apply_defaults();

		$this->assertTrue( $result['applied'] );
		$this->assertContains( 'show_matching_fuzziness_level', $result['keys_preserved'] );
		$this->assertSame(
			'',
			$this->option_store[ Configurator::OPTION_KEY ]['show_matching_fuzziness_level']
		);
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_has_been_applied_returns_false_when_fibosearch_missing(): void {
		$configurator = new Configurator();
		$this->assertFalse( $configurator->has_been_applied() );
	}

	public function test_has_been_applied_returns_true_after_apply(): void {
		$this->fake_fibosearch();
		$configurator = new Configurator();

		$this->assertFalse( $configurator->has_been_applied() );
		$configurator->apply_defaults();
		$this->assertTrue( $configurator->has_been_applied() );
	}

	public function test_has_been_applied_stays_true_after_user_tweaks_value(): void {
		$this->fake_fibosearch();
		$configurator = new Configurator();

		$configurator->apply_defaults();
		$this->assertTrue( $configurator->has_been_applied() );

		// Simulate the user flipping a checkbox off after Forge ran.
		$this->option_store[ Configurator::OPTION_KEY ]['is_fuzzy_matching'] = 0;
		$this->assertTrue(
			$configurator->has_been_applied(),
			'User-tweaked 0 must not flip has_been_applied() back to false.'
		);
	}

	public function test_report_returns_structured_shape(): void {
		$this->fake_fibosearch();

		$report = ( new Configurator() )->report();
		$this->assertArrayHasKey( 'detected', $report );
		$this->assertArrayHasKey( 'version', $report );
		$this->assertArrayHasKey( 'option_exists', $report );
		$this->assertArrayHasKey( 'has_been_applied', $report );
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
		$this->assertFalse( $report['has_been_applied'] );
		$this->assertSame( 0, $report['keys_present'] );
	}

	private function fake_fibosearch(): void {
		if ( ! function_exists( 'dgwt_wcas' ) ) {
			Functions\when( 'dgwt_wcas' )->justReturn( null );
		}
	}
}
