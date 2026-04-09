<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Safety;

use ElementorForge\Safety\Mode;
use PHPUnit\Framework\TestCase;

final class ModeTest extends TestCase {

	public function test_all_returns_three_values(): void {
		$all = Mode::all();
		$this->assertCount( 3, $all );
		$this->assertContains( Mode::FULL, $all );
		$this->assertContains( Mode::PAGE_ONLY, $all );
		$this->assertContains( Mode::READ_ONLY, $all );
	}

	public function test_is_valid_accepts_full_page_only_read_only(): void {
		$this->assertTrue( Mode::is_valid( 'full' ) );
		$this->assertTrue( Mode::is_valid( 'page_only' ) );
		$this->assertTrue( Mode::is_valid( 'read_only' ) );
	}

	public function test_is_valid_rejects_arbitrary_string(): void {
		$this->assertFalse( Mode::is_valid( 'bogus' ) );
		$this->assertFalse( Mode::is_valid( '' ) );
		$this->assertFalse( Mode::is_valid( 'FULL' ) );
	}

	public function test_label_returns_human_readable_string(): void {
		$this->assertSame( 'Full (site-wide)', Mode::label( Mode::FULL ) );
		$this->assertSame( 'Page-only (allowlisted)', Mode::label( Mode::PAGE_ONLY ) );
		$this->assertSame( 'Read-only (diagnostic)', Mode::label( Mode::READ_ONLY ) );
		$this->assertSame( 'Unknown', Mode::label( 'garbage' ) );
	}

	public function test_color_returns_expected_token(): void {
		$this->assertSame( 'green', Mode::color( Mode::FULL ) );
		$this->assertSame( 'yellow', Mode::color( Mode::PAGE_ONLY ) );
		$this->assertSame( 'red', Mode::color( Mode::READ_ONLY ) );
		$this->assertSame( 'gray', Mode::color( 'garbage' ) );
	}
}
