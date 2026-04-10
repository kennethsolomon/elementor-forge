<?php
/**
 * NestedCarouselRule tests.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Intelligence\LayoutJudge\Rules;

use ElementorForge\Intelligence\LayoutJudge\Decision;
use ElementorForge\Intelligence\LayoutJudge\Rules\NestedCarouselRule;
use ElementorForge\Intelligence\LayoutJudge\SectionData;
use PHPUnit\Framework\TestCase;

final class NestedCarouselRuleTest extends TestCase {

	private NestedCarouselRule $rule;

	protected function setUp(): void {
		parent::setUp();
		$this->rule = new NestedCarouselRule();
	}

	public function test_id_returns_stable_identifier(): void {
		self::assertSame( 'long_mixed.nested_carousel', $this->rule->id() );
	}

	/** Minimum item count. */
	public function test_eight_items_at_minimum_matches(): void {
		$items = array();
		for ( $i = 0; $i < 8; $i++ ) {
			$items[] = array( 'name' => "Item $i", 'description' => 'Some text here' );
		}
		$section  = SectionData::from_array( array( 'section' => 'services', 'items' => $items ) );
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
		self::assertSame( Decision::WIDGET_NESTED_CAROUSEL, $decision->widget() );
		self::assertSame( 0.8, $decision->confidence() );
	}

	public function test_seven_items_below_minimum_returns_null(): void {
		$items = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$items[] = array( 'name' => "Item $i", 'description' => 'Text' );
		}
		$section = SectionData::from_array( array( 'section' => 'services', 'items' => $items ) );

		self::assertNull( $this->rule->evaluate( $section ) );
	}

	// --- Applicable types ---

	/**
	 * @dataProvider applicable_types_provider
	 */
	public function test_applicable_types_with_enough_items_match( string $type ): void {
		$items = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$items[] = array( 'name' => "Item $i", 'description' => 'Description text' );
		}
		$section  = SectionData::from_array( array( 'section' => $type, 'items' => $items ) );
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
		self::assertSame( Decision::WIDGET_NESTED_CAROUSEL, $decision->widget() );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function applicable_types_provider(): array {
		return array(
			'services'      => array( 'services' ),
			'features'      => array( 'features' ),
			'process_steps' => array( 'process_steps' ),
			'testimonials'  => array( 'testimonials' ),
			'bullets'       => array( 'bullets' ),
		);
	}

	/** Non-applicable types. */
	public function test_non_applicable_types_return_null(): void {
		$items = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$items[] = array( 'name' => "Item $i", 'description' => 'Text' );
		}

		$types = array( 'faq', 'gallery', 'unknown', '' );
		foreach ( $types as $type ) {
			$section = SectionData::from_array( array( 'section' => $type, 'items' => $items ) );
			self::assertNull(
				$this->rule->evaluate( $section ),
				"Type '$type' should not match NestedCarouselRule"
			);
		}
	}

	/** Image-only exclusion. */
	public function test_all_image_items_with_short_text_returns_null(): void {
		$items = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$items[] = array( 'image' => "img_$i.jpg", 'text' => 'Hi' ); // <20 chars avg
		}
		$section = SectionData::from_array( array( 'section' => 'features', 'items' => $items ) );

		self::assertNull( $this->rule->evaluate( $section ) );
	}

	public function test_all_image_items_with_substantial_text_matches(): void {
		$items = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$items[] = array( 'image' => "img_$i.jpg", 'text' => 'This is substantial enough text' ); // >20 chars
		}
		$section  = SectionData::from_array( array( 'section' => 'features', 'items' => $items ) );
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
	}

	public function test_mixed_image_and_text_items_matches(): void {
		$items = array();
		for ( $i = 0; $i < 8; $i++ ) {
			$items[] = $i % 2 === 0
				? array( 'image' => "img_$i.jpg", 'description' => 'Description' )
				: array( 'description' => 'Text only item' );
		}
		$section  = SectionData::from_array( array( 'section' => 'services', 'items' => $items ) );
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
	}

	/** Text-only long list matches. */
	public function test_long_text_only_list_matches(): void {
		$items = array();
		for ( $i = 0; $i < 12; $i++ ) {
			$items[] = array( 'description' => "Step $i details here" );
		}
		$section  = SectionData::from_array( array( 'section' => 'process_steps', 'items' => $items ) );
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
	}

	public function test_decision_rule_id_matches_own_id(): void {
		$items = array();
		for ( $i = 0; $i < 8; $i++ ) {
			$items[] = array( 'description' => 'text' );
		}
		$section  = SectionData::from_array( array( 'section' => 'services', 'items' => $items ) );
		$decision = $this->rule->evaluate( $section );

		self::assertSame( $this->rule->id(), $decision->rule_id() );
	}
}
