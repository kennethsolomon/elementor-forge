<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Schema;

use Brain\Monkey;
use ElementorForge\Elementor\Schema\Units;
use PHPUnit\Framework\TestCase;

final class UnitsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_px_constant(): void {
		$this->assertSame( 'px', Units::PX );
	}

	public function test_em_constant(): void {
		$this->assertSame( 'em', Units::EM );
	}

	public function test_rem_constant(): void {
		$this->assertSame( 'rem', Units::REM );
	}

	public function test_pct_constant(): void {
		$this->assertSame( '%', Units::PCT );
	}

	public function test_vh_constant(): void {
		$this->assertSame( 'vh', Units::VH );
	}

	public function test_vw_constant(): void {
		$this->assertSame( 'vw', Units::VW );
	}

	public function test_fr_constant(): void {
		$this->assertSame( 'fr', Units::FR );
	}

	public function test_deg_constant(): void {
		$this->assertSame( 'deg', Units::DEG );
	}

	public function test_s_constant(): void {
		$this->assertSame( 's', Units::S );
	}

	public function test_ms_constant(): void {
		$this->assertSame( 'ms', Units::MS );
	}

	public function test_spacing_returns_four_units(): void {
		$spacing = Units::spacing();

		$this->assertCount( 4, $spacing );
	}

	public function test_spacing_contains_px_em_rem_pct(): void {
		$spacing = Units::spacing();

		$this->assertContains( 'px', $spacing );
		$this->assertContains( 'em', $spacing );
		$this->assertContains( 'rem', $spacing );
		$this->assertContains( '%', $spacing );
	}

	public function test_spacing_does_not_contain_viewport_units(): void {
		$spacing = Units::spacing();

		$this->assertNotContains( 'vh', $spacing );
		$this->assertNotContains( 'vw', $spacing );
	}

	public function test_spacing_does_not_contain_time_units(): void {
		$spacing = Units::spacing();

		$this->assertNotContains( 's', $spacing );
		$this->assertNotContains( 'ms', $spacing );
	}

	public function test_size_returns_eight_units(): void {
		$size = Units::size();

		$this->assertCount( 8, $size );
	}

	public function test_size_contains_all_expected_units(): void {
		$size = Units::size();

		$this->assertContains( 'px', $size );
		$this->assertContains( 'em', $size );
		$this->assertContains( 'rem', $size );
		$this->assertContains( '%', $size );
		$this->assertContains( 'vh', $size );
		$this->assertContains( 'vw', $size );
		$this->assertContains( 'fr', $size );
		$this->assertContains( 'deg', $size );
	}

	public function test_size_does_not_contain_time_units(): void {
		$size = Units::size();

		$this->assertNotContains( 's', $size );
		$this->assertNotContains( 'ms', $size );
	}

	public function test_spacing_is_subset_of_size(): void {
		$spacing = Units::spacing();
		$size    = Units::size();

		foreach ( $spacing as $unit ) {
			$this->assertContains( $unit, $size, "Spacing unit '$unit' should also be a valid size unit" );
		}
	}

	public function test_spacing_values_are_all_strings(): void {
		foreach ( Units::spacing() as $unit ) {
			$this->assertIsString( $unit );
		}
	}

	public function test_size_values_are_all_strings(): void {
		foreach ( Units::size() as $unit ) {
			$this->assertIsString( $unit );
		}
	}

	public function test_spacing_values_are_unique(): void {
		$spacing = Units::spacing();
		$this->assertCount( count( $spacing ), array_unique( $spacing ) );
	}

	public function test_size_values_are_unique(): void {
		$size = Units::size();
		$this->assertCount( count( $size ), array_unique( $size ) );
	}
}
