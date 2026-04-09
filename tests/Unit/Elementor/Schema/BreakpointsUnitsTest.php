<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Schema;

use ElementorForge\Elementor\Schema\Breakpoints;
use ElementorForge\Elementor\Schema\Units;
use PHPUnit\Framework\TestCase;

final class BreakpointsUnitsTest extends TestCase {

	public function test_breakpoints_include_all_canonical_suffixes(): void {
		$all = Breakpoints::all();
		$this->assertContains( '', $all );
		$this->assertContains( '_tablet', $all );
		$this->assertContains( '_mobile', $all );
		$this->assertContains( '_widescreen', $all );
		$this->assertContains( '_laptop', $all );
	}

	public function test_spacing_units_include_px_em_pct(): void {
		$units = Units::spacing();
		$this->assertContains( 'px', $units );
		$this->assertContains( 'em', $units );
		$this->assertContains( '%', $units );
	}

	public function test_size_units_include_fr_and_deg(): void {
		$units = Units::size();
		$this->assertContains( 'fr', $units );
		$this->assertContains( 'deg', $units );
	}
}
