<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Schema;

use Brain\Monkey;
use ElementorForge\Elementor\Schema\Breakpoints;
use PHPUnit\Framework\TestCase;

final class BreakpointsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_desktop_constant_is_empty_string(): void {
		$this->assertSame( '', Breakpoints::DESKTOP );
	}

	public function test_laptop_constant_value(): void {
		$this->assertSame( '_laptop', Breakpoints::LAPTOP );
	}

	public function test_tablet_constant_value(): void {
		$this->assertSame( '_tablet', Breakpoints::TABLET );
	}

	public function test_tablet_extra_constant_value(): void {
		$this->assertSame( '_tablet_extra', Breakpoints::TABLET_EXT );
	}

	public function test_mobile_constant_value(): void {
		$this->assertSame( '_mobile', Breakpoints::MOBILE );
	}

	public function test_mobile_extra_constant_value(): void {
		$this->assertSame( '_mobile_extra', Breakpoints::MOBILE_EXT );
	}

	public function test_widescreen_constant_value(): void {
		$this->assertSame( '_widescreen', Breakpoints::WIDESCREEN );
	}

	public function test_all_returns_seven_breakpoints(): void {
		$all = Breakpoints::all();

		$this->assertCount( 7, $all );
	}

	public function test_all_contains_every_constant(): void {
		$all = Breakpoints::all();

		$this->assertContains( Breakpoints::DESKTOP, $all );
		$this->assertContains( Breakpoints::LAPTOP, $all );
		$this->assertContains( Breakpoints::TABLET, $all );
		$this->assertContains( Breakpoints::TABLET_EXT, $all );
		$this->assertContains( Breakpoints::MOBILE, $all );
		$this->assertContains( Breakpoints::MOBILE_EXT, $all );
		$this->assertContains( Breakpoints::WIDESCREEN, $all );
	}

	public function test_all_starts_with_desktop(): void {
		$all = Breakpoints::all();

		$this->assertSame( '', $all[0], 'Desktop (empty string) should be first in the list' );
	}

	public function test_all_returns_unique_values(): void {
		$all = Breakpoints::all();

		$this->assertCount( count( $all ), array_unique( $all ) );
	}

	public function test_all_values_are_strings(): void {
		foreach ( Breakpoints::all() as $bp ) {
			$this->assertIsString( $bp );
		}
	}

	public function test_non_desktop_suffixes_start_with_underscore(): void {
		$non_desktop = array_filter( Breakpoints::all(), fn( string $bp ) => '' !== $bp );

		foreach ( $non_desktop as $bp ) {
			$this->assertStringStartsWith( '_', $bp, "Breakpoint suffix '$bp' should start with underscore" );
		}
	}

	public function test_suffixes_can_build_responsive_keys(): void {
		$base_key = 'padding';
		$keys     = array();

		foreach ( Breakpoints::all() as $suffix ) {
			$keys[] = $base_key . $suffix;
		}

		$this->assertContains( 'padding', $keys );
		$this->assertContains( 'padding_tablet', $keys );
		$this->assertContains( 'padding_mobile', $keys );
		$this->assertContains( 'padding_laptop', $keys );
		$this->assertContains( 'padding_widescreen', $keys );
		$this->assertContains( 'padding_tablet_extra', $keys );
		$this->assertContains( 'padding_mobile_extra', $keys );
	}
}
