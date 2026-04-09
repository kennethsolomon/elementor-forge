<?php
/**
 * SectionData computed-signal tests.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Intelligence\LayoutJudge;

use ElementorForge\Intelligence\LayoutJudge\SectionData;
use PHPUnit\Framework\TestCase;

final class SectionDataTest extends TestCase {

	public function test_empty_section_returns_zero_signals(): void {
		$section = SectionData::from_array( array( 'section' => 'bullets', 'items' => array() ) );

		self::assertSame( 'bullets', $section->type() );
		self::assertSame( 0, $section->item_count() );
		self::assertSame( 0, $section->avg_text_length() );
		self::assertSame( 0, $section->images_present() );
		self::assertFalse( $section->is_text_heavy() );
	}

	public function test_string_items_are_normalized_to_text_arrays(): void {
		$section = SectionData::from_array(
			array(
				'section' => 'bullets',
				'items'   => array( 'fast', 'reliable', 'cheap' ),
			)
		);

		self::assertSame( 3, $section->item_count() );
		self::assertGreaterThan( 0, $section->avg_text_length() );
	}

	public function test_text_heavy_threshold_uses_average_above_120(): void {
		$long = str_repeat( 'a', 150 );
		$section = SectionData::from_array(
			array(
				'section' => 'features',
				'items'   => array(
					array( 'text' => $long ),
					array( 'text' => $long ),
				),
			)
		);

		self::assertTrue( $section->is_text_heavy() );
	}

	public function test_text_heavy_threshold_uses_max_above_240(): void {
		$short = 'short';
		$huge  = str_repeat( 'b', 250 );
		$section = SectionData::from_array(
			array(
				'section' => 'features',
				'items'   => array(
					array( 'text' => $short ),
					array( 'text' => $short ),
					array( 'text' => $huge ),
				),
			)
		);

		self::assertTrue( $section->is_text_heavy() );
	}

	public function test_image_count_matches_only_items_with_image_keys(): void {
		$section = SectionData::from_array(
			array(
				'section' => 'gallery',
				'items'   => array(
					array( 'image' => 'a.jpg' ),
					array( 'url' => 'b.jpg' ),
					array( 'src' => 'c.jpg' ),
					array( 'text' => 'no image here' ),
				),
			)
		);

		self::assertSame( 4, $section->item_count() );
		self::assertSame( 3, $section->images_present() );
	}

	public function test_icon_signal_only_counts_explicit_icon_keys(): void {
		$section = SectionData::from_array(
			array(
				'section' => 'services',
				'items'   => array(
					array( 'icon' => 'star' ),
					array( 'icon' => 'check' ),
					array( 'name' => 'no icon' ),
				),
			)
		);

		self::assertSame( 2, $section->items_with_icon() );
	}

	public function test_section_type_defaults_to_empty_string(): void {
		$section = SectionData::from_array( array( 'items' => array() ) );

		self::assertSame( '', $section->type() );
	}
}
