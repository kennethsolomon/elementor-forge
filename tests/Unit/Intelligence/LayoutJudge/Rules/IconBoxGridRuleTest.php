<?php
/**
 * IconBoxGridRule tests.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Intelligence\LayoutJudge\Rules;

use ElementorForge\Intelligence\LayoutJudge\Decision;
use ElementorForge\Intelligence\LayoutJudge\Rules\IconBoxGridRule;
use ElementorForge\Intelligence\LayoutJudge\SectionData;
use PHPUnit\Framework\TestCase;

final class IconBoxGridRuleTest extends TestCase {

	private IconBoxGridRule $rule;

	protected function setUp(): void {
		parent::setUp();
		$this->rule = new IconBoxGridRule();
	}

	public function test_id_returns_stable_identifier(): void {
		self::assertSame( 'services.icon_box_grid', $this->rule->id() );
	}

	// --- Applicable types ---

	/**
	 * @dataProvider applicable_types_provider
	 */
	public function test_applicable_types_with_three_icon_items_match( string $type ): void {
		$section  = SectionData::from_array(
			array(
				'section' => $type,
				'items'   => array(
					array( 'name' => 'A', 'icon' => 'star', 'description' => 'Short' ),
					array( 'name' => 'B', 'icon' => 'bolt', 'description' => 'Short' ),
					array( 'name' => 'C', 'icon' => 'gear', 'description' => 'Short' ),
				),
			)
		);
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
		self::assertSame( Decision::WIDGET_ICON_BOX_GRID, $decision->widget() );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function applicable_types_provider(): array {
		return array(
			'services'      => array( 'services' ),
			'features'      => array( 'features' ),
			'process_steps' => array( 'process_steps' ),
		);
	}

	/** Non-applicable types. */
	public function test_non_applicable_type_returns_null(): void {
		$types = array( 'faq', 'gallery', 'bullets', 'testimonials', 'unknown' );

		foreach ( $types as $type ) {
			$section = SectionData::from_array(
				array(
					'section' => $type,
					'items'   => array(
						array( 'icon' => 'a' ),
						array( 'icon' => 'b' ),
						array( 'icon' => 'c' ),
					),
				)
			);
			self::assertNull(
				$this->rule->evaluate( $section ),
				"Type '$type' should not match IconBoxGridRule"
			);
		}
	}

	/** Item count boundaries. */
	public function test_two_items_below_minimum_returns_null(): void {
		$section = SectionData::from_array(
			array(
				'section' => 'services',
				'items'   => array(
					array( 'icon' => 'a', 'description' => 'x' ),
					array( 'icon' => 'b', 'description' => 'y' ),
				),
			)
		);

		self::assertNull( $this->rule->evaluate( $section ) );
	}

	public function test_three_items_at_minimum_matches(): void {
		$section = SectionData::from_array(
			array(
				'section' => 'services',
				'items'   => array(
					array( 'icon' => 'a', 'description' => 'x' ),
					array( 'icon' => 'b', 'description' => 'y' ),
					array( 'icon' => 'c', 'description' => 'z' ),
				),
			)
		);

		self::assertInstanceOf( Decision::class, $this->rule->evaluate( $section ) );
	}

	public function test_six_items_at_maximum_matches(): void {
		$items = array();
		for ( $i = 0; $i < 6; $i++ ) {
			$items[] = array( 'icon' => "icon_$i", 'description' => 'Short' );
		}
		$section = SectionData::from_array( array( 'section' => 'features', 'items' => $items ) );

		self::assertInstanceOf( Decision::class, $this->rule->evaluate( $section ) );
	}

	public function test_seven_items_above_maximum_returns_null(): void {
		$items = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$items[] = array( 'icon' => "icon_$i", 'description' => 'Short' );
		}
		$section = SectionData::from_array( array( 'section' => 'services', 'items' => $items ) );

		self::assertNull( $this->rule->evaluate( $section ) );
	}

	/** Text-heavy exclusion. */
	public function test_text_heavy_section_returns_null(): void {
		$long = str_repeat( 'word ', 30 ); // >120 chars avg
		$section = SectionData::from_array(
			array(
				'section' => 'services',
				'items'   => array(
					array( 'icon' => 'a', 'description' => $long ),
					array( 'icon' => 'b', 'description' => $long ),
					array( 'icon' => 'c', 'description' => $long ),
				),
			)
		);

		self::assertNull( $this->rule->evaluate( $section ) );
	}

	/** Confidence scoring. */
	public function test_all_items_with_icons_get_high_confidence(): void {
		$section  = SectionData::from_array(
			array(
				'section' => 'services',
				'items'   => array(
					array( 'icon' => 'a', 'description' => 'Short' ),
					array( 'icon' => 'b', 'description' => 'Short' ),
					array( 'icon' => 'c', 'description' => 'Short' ),
				),
			)
		);
		$decision = $this->rule->evaluate( $section );

		self::assertSame( 0.9, $decision->confidence() );
	}

	public function test_partial_icons_get_lower_confidence(): void {
		$section  = SectionData::from_array(
			array(
				'section' => 'services',
				'items'   => array(
					array( 'icon' => 'a', 'description' => 'Short' ),
					array( 'description' => 'No icon' ),
					array( 'icon' => 'c', 'description' => 'Short' ),
				),
			)
		);
		$decision = $this->rule->evaluate( $section );

		self::assertSame( 0.75, $decision->confidence() );
	}

	public function test_no_icons_get_lower_confidence(): void {
		$section  = SectionData::from_array(
			array(
				'section' => 'services',
				'items'   => array(
					array( 'description' => 'No icon' ),
					array( 'description' => 'No icon' ),
					array( 'description' => 'No icon' ),
				),
			)
		);
		$decision = $this->rule->evaluate( $section );

		self::assertSame( 0.75, $decision->confidence() );
	}

	public function test_decision_rule_id_matches_own_id(): void {
		$section  = SectionData::from_array(
			array(
				'section' => 'services',
				'items'   => array(
					array( 'icon' => 'a' ),
					array( 'icon' => 'b' ),
					array( 'icon' => 'c' ),
				),
			)
		);
		$decision = $this->rule->evaluate( $section );

		self::assertSame( $this->rule->id(), $decision->rule_id() );
	}
}
