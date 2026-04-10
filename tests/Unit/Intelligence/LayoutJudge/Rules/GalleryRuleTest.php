<?php
/**
 * GalleryRule tests.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Intelligence\LayoutJudge\Rules;

use ElementorForge\Intelligence\LayoutJudge\Decision;
use ElementorForge\Intelligence\LayoutJudge\Rules\GalleryRule;
use ElementorForge\Intelligence\LayoutJudge\SectionData;
use PHPUnit\Framework\TestCase;

final class GalleryRuleTest extends TestCase {

	private GalleryRule $rule;

	protected function setUp(): void {
		parent::setUp();
		$this->rule = new GalleryRule();
	}

	public function test_id_returns_stable_identifier(): void {
		self::assertSame( 'gallery.image_carousel', $this->rule->id() );
	}

	/** Gallery type hard match. */
	public function test_gallery_section_with_one_item_matches(): void {
		$section  = SectionData::from_array(
			array(
				'section' => 'gallery',
				'items'   => array( array( 'image' => 'a.jpg' ) ),
			)
		);
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
		self::assertSame( Decision::WIDGET_IMAGE_CAROUSEL, $decision->widget() );
		self::assertSame( 0.95, $decision->confidence() );
	}

	public function test_gallery_section_with_many_items_matches(): void {
		$items = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$items[] = array( 'image' => "img_$i.jpg" );
		}
		$section  = SectionData::from_array( array( 'section' => 'gallery', 'items' => $items ) );
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
		self::assertSame( 0.95, $decision->confidence() );
	}

	public function test_gallery_section_with_zero_items_returns_null(): void {
		$section = SectionData::from_array( array( 'section' => 'gallery', 'items' => array() ) );

		self::assertNull( $this->rule->evaluate( $section ) );
	}

	/** All-image soft match (non-gallery type). */
	public function test_four_image_only_items_match_with_lower_confidence(): void {
		$section  = SectionData::from_array(
			array(
				'section' => 'features',
				'items'   => array(
					array( 'image' => 'a.jpg' ),
					array( 'image' => 'b.jpg' ),
					array( 'url' => 'c.jpg' ),
					array( 'src' => 'd.jpg' ),
				),
			)
		);
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
		self::assertSame( Decision::WIDGET_IMAGE_CAROUSEL, $decision->widget() );
		self::assertSame( 0.85, $decision->confidence() );
	}

	public function test_three_image_only_items_below_threshold_returns_null(): void {
		$section = SectionData::from_array(
			array(
				'section' => 'features',
				'items'   => array(
					array( 'image' => 'a.jpg' ),
					array( 'image' => 'b.jpg' ),
					array( 'image' => 'c.jpg' ),
				),
			)
		);

		self::assertNull( $this->rule->evaluate( $section ) );
	}

	public function test_four_items_with_mixed_content_returns_null(): void {
		$section = SectionData::from_array(
			array(
				'section' => 'features',
				'items'   => array(
					array( 'image' => 'a.jpg' ),
					array( 'image' => 'b.jpg' ),
					array( 'image' => 'c.jpg' ),
					array( 'text' => 'no image here' ),
				),
			)
		);

		// images_present (3) !== item_count (4), so soft match fails.
		self::assertNull( $this->rule->evaluate( $section ) );
	}

	public function test_decision_rule_id_matches_own_id(): void {
		$section  = SectionData::from_array(
			array(
				'section' => 'gallery',
				'items'   => array( array( 'image' => 'x.jpg' ) ),
			)
		);
		$decision = $this->rule->evaluate( $section );

		self::assertSame( $this->rule->id(), $decision->rule_id() );
	}

	public function test_non_gallery_non_image_section_returns_null(): void {
		$section = SectionData::from_array(
			array(
				'section' => 'bullets',
				'items'   => array( 'one', 'two', 'three' ),
			)
		);

		self::assertNull( $this->rule->evaluate( $section ) );
	}
}
