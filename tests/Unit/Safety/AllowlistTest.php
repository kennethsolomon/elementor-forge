<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Safety;

use ElementorForge\Safety\Allowlist;
use PHPUnit\Framework\TestCase;

final class AllowlistTest extends TestCase {

	public function test_from_string_parses_comma_separated_integers(): void {
		$allowlist = Allowlist::from_string( '52,101,150' );
		$this->assertSame( array( 52, 101, 150 ), $allowlist->to_array() );
	}

	public function test_from_string_strips_whitespace(): void {
		$allowlist = Allowlist::from_string( ' 52 ,  101 , 150  ' );
		$this->assertSame( array( 52, 101, 150 ), $allowlist->to_array() );
	}

	public function test_from_string_rejects_non_positive_integers(): void {
		$allowlist = Allowlist::from_string( '0, -1, 42, abc, 7' );
		$this->assertSame( array( 7, 42 ), $allowlist->to_array() );
	}

	public function test_from_string_rejects_duplicates(): void {
		$allowlist = Allowlist::from_string( '42,7,42,7,100' );
		$this->assertSame( array( 7, 42, 100 ), $allowlist->to_array() );
	}

	public function test_from_string_empty_returns_empty_allowlist(): void {
		$allowlist = Allowlist::from_string( '' );
		$this->assertTrue( $allowlist->is_empty() );
		$this->assertSame( array(), $allowlist->to_array() );
	}

	public function test_from_string_whitespace_only_returns_empty_allowlist(): void {
		$allowlist = Allowlist::from_string( '   ' );
		$this->assertTrue( $allowlist->is_empty() );
	}

	public function test_contains_returns_true_for_matching_id(): void {
		$allowlist = Allowlist::from_string( '52,101' );
		$this->assertTrue( $allowlist->contains( 52 ) );
		$this->assertTrue( $allowlist->contains( 101 ) );
	}

	public function test_contains_returns_false_for_non_matching_id(): void {
		$allowlist = Allowlist::from_string( '52,101' );
		$this->assertFalse( $allowlist->contains( 99 ) );
		$this->assertFalse( $allowlist->contains( 0 ) );
	}

	public function test_is_empty_true_when_no_ids(): void {
		$this->assertTrue( ( new Allowlist( array() ) )->is_empty() );
	}

	public function test_is_empty_false_when_ids_present(): void {
		$this->assertFalse( ( new Allowlist( array( 1 ) ) )->is_empty() );
	}

	public function test_to_string_round_trips_from_string(): void {
		$allowlist = Allowlist::from_string( '7, 42, 100' );
		$this->assertSame( '7,42,100', $allowlist->to_string() );
	}

	public function test_to_array_returns_sorted_unique_ints(): void {
		$allowlist = new Allowlist( array( 100, 7, 42, 7 ) );
		$this->assertSame( array( 7, 42, 100 ), $allowlist->to_array() );
	}

	public function test_constructor_rejects_non_positive_ints(): void {
		$allowlist = new Allowlist( array( 0, -5, 42, '100', 'bogus' ) );
		// 'bogus' cast to int = 0, stripped. '100' cast to int = 100, kept.
		$this->assertSame( array( 42, 100 ), $allowlist->to_array() );
	}
}
