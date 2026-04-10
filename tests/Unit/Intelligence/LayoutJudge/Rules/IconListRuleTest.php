<?php
/**
 * IconListRule tests.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Intelligence\LayoutJudge\Rules;

use ElementorForge\Intelligence\LayoutJudge\Decision;
use ElementorForge\Intelligence\LayoutJudge\Rules\IconListRule;
use ElementorForge\Intelligence\LayoutJudge\SectionData;
use PHPUnit\Framework\TestCase;

final class IconListRuleTest extends TestCase {

	private IconListRule $rule;

	protected function setUp(): void {
		parent::setUp();
		$this->rule = new IconListRule();
	}

	public function test_id_returns_stable_identifier(): void {
		self::assertSame( 'bullets.icon_list', $this->rule->id() );
	}

	/** Hard match: bullets type. */
	public function test_bullets_with_short_text_no_images_matches(): void {
		$section  = SectionData::from_array(
			array(
				'section' => 'bullets',
				'items'   => array( 'fast', 'reliable', 'cheap' ),
			)
		);
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
		self::assertSame( Decision::WIDGET_ICON_LIST, $decision->widget() );
		self::assertSame( 0.9, $decision->confidence() );
	}

	public function test_bullets_with_six_items_at_maximum_matches(): void {
		$section  = SectionData::from_array(
			array(
				'section' => 'bullets',
				'items'   => array( 'a', 'b', 'c', 'd', 'e', 'f' ),
			)
		);
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
		self::assertSame( 0.9, $decision->confidence() );
	}

	public function test_bullets_with_seven_items_above_maximum_returns_null(): void {
		$section = SectionData::from_array(
			array(
				'section' => 'bullets',
				'items'   => array( 'a', 'b', 'c', 'd', 'e', 'f', 'g' ),
			)
		);

		self::assertNull( $this->rule->evaluate( $section ) );
	}

	public function test_bullets_with_empty_items_returns_null(): void {
		$section = SectionData::from_array( array( 'section' => 'bullets', 'items' => array() ) );

		self::assertNull( $this->rule->evaluate( $section ) );
	}

	public function test_bullets_with_long_text_returns_null(): void {
		$long = str_repeat( 'x', 100 ); // avg > 80
		$section = SectionData::from_array(
			array(
				'section' => 'bullets',
				'items'   => array(
					array( 'text' => $long ),
					array( 'text' => $long ),
				),
			)
		);

		self::assertNull( $this->rule->evaluate( $section ) );
	}

	public function test_bullets_with_images_returns_null(): void {
		$section = SectionData::from_array(
			array(
				'section' => 'bullets',
				'items'   => array(
					array( 'text' => 'short', 'image' => 'a.jpg' ),
					array( 'text' => 'short' ),
				),
			)
		);

		self::assertNull( $this->rule->evaluate( $section ) );
	}

	public function test_bullets_at_exactly_80_char_avg_matches(): void {
		$text = str_repeat( 'a', 80 );
		$section  = SectionData::from_array(
			array(
				'section' => 'bullets',
				'items'   => array(
					array( 'text' => $text ),
				),
			)
		);
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
	}

	public function test_bullets_at_81_char_avg_returns_null(): void {
		$text = str_repeat( 'a', 81 );
		$section = SectionData::from_array(
			array(
				'section' => 'bullets',
				'items'   => array(
					array( 'text' => $text ),
				),
			)
		);

		self::assertNull( $this->rule->evaluate( $section ) );
	}

	/** Soft match: services/features/process_steps without icons or images. */
	public function test_services_with_short_text_no_icons_no_images_matches(): void {
		$section  = SectionData::from_array(
			array(
				'section' => 'services',
				'items'   => array(
					array( 'text' => 'Short A' ),
					array( 'text' => 'Short B' ),
					array( 'text' => 'Short C' ),
				),
			)
		);
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
		self::assertSame( Decision::WIDGET_ICON_LIST, $decision->widget() );
		self::assertSame( 0.7, $decision->confidence() );
	}

	public function test_features_with_short_text_no_icons_no_images_matches(): void {
		$section  = SectionData::from_array(
			array(
				'section' => 'features',
				'items'   => array(
					array( 'text' => 'A' ),
					array( 'text' => 'B' ),
				),
			)
		);
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
		self::assertSame( 0.7, $decision->confidence() );
	}

	public function test_process_steps_with_short_text_no_icons_no_images_matches(): void {
		$section  = SectionData::from_array(
			array(
				'section' => 'process_steps',
				'items'   => array(
					array( 'text' => 'Step 1' ),
					array( 'text' => 'Step 2' ),
				),
			)
		);
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
	}

	public function test_soft_match_with_icons_present_returns_null(): void {
		$section = SectionData::from_array(
			array(
				'section' => 'services',
				'items'   => array(
					array( 'text' => 'A', 'icon' => 'star' ),
					array( 'text' => 'B' ),
				),
			)
		);

		// items_with_icon > 0, so soft match should not fire.
		self::assertNull( $this->rule->evaluate( $section ) );
	}

	public function test_soft_match_with_images_returns_null(): void {
		$section = SectionData::from_array(
			array(
				'section' => 'services',
				'items'   => array(
					array( 'text' => 'A', 'image' => 'x.jpg' ),
					array( 'text' => 'B' ),
				),
			)
		);

		self::assertNull( $this->rule->evaluate( $section ) );
	}

	public function test_soft_match_with_long_text_returns_null(): void {
		$long = str_repeat( 'x', 100 );
		$section = SectionData::from_array(
			array(
				'section' => 'services',
				'items'   => array(
					array( 'text' => $long ),
					array( 'text' => $long ),
				),
			)
		);

		self::assertNull( $this->rule->evaluate( $section ) );
	}

	public function test_non_applicable_type_for_soft_match_returns_null(): void {
		$section = SectionData::from_array(
			array(
				'section' => 'gallery',
				'items'   => array(
					array( 'text' => 'Short' ),
					array( 'text' => 'Short' ),
				),
			)
		);

		self::assertNull( $this->rule->evaluate( $section ) );
	}

	public function test_decision_rule_id_matches_own_id(): void {
		$section  = SectionData::from_array(
			array(
				'section' => 'bullets',
				'items'   => array( 'a', 'b' ),
			)
		);
		$decision = $this->rule->evaluate( $section );

		self::assertSame( $this->rule->id(), $decision->rule_id() );
	}
}
