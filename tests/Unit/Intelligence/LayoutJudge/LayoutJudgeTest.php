<?php
/**
 * Tests for the deterministic LayoutJudge across all rules.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Intelligence\LayoutJudge;

use ElementorForge\Intelligence\LayoutJudge\Decision;
use ElementorForge\Intelligence\LayoutJudge\LayoutJudge;
use PHPUnit\Framework\TestCase;

final class LayoutJudgeTest extends TestCase {

	private LayoutJudge $judge;

	protected function setUp(): void {
		parent::setUp();
		$this->judge = LayoutJudge::with_default_rules();
	}

	public function test_default_judge_loads_six_rules(): void {
		self::assertSame( 6, $this->judge->rule_count() );
	}

	public function test_faq_section_always_routes_to_nested_accordion(): void {
		$decision = $this->judge->decide(
			array(
				'section' => 'faq',
				'items'   => array(
					array( 'question' => 'q1', 'answer' => 'a1' ),
					array( 'question' => 'q2', 'answer' => 'a2' ),
				),
			)
		);

		self::assertSame( Decision::WIDGET_NESTED_ACCORDION, $decision->widget() );
		self::assertGreaterThan( 0.9, $decision->confidence() );
	}

	public function test_short_bullets_route_to_icon_list(): void {
		$decision = $this->judge->decide(
			array(
				'section' => 'bullets',
				'items'   => array( 'fast', 'reliable', 'affordable', 'local' ),
			)
		);

		self::assertSame( Decision::WIDGET_ICON_LIST, $decision->widget() );
	}

	public function test_seven_bullets_does_not_match_icon_list(): void {
		$decision = $this->judge->decide(
			array(
				'section' => 'bullets',
				'items'   => array( 'a', 'b', 'c', 'd', 'e', 'f', 'g' ),
			)
		);

		self::assertNotSame( Decision::WIDGET_ICON_LIST, $decision->widget() );
	}

	public function test_long_bullets_text_does_not_match_icon_list(): void {
		$long = str_repeat( 'word ', 25 );
		$decision = $this->judge->decide(
			array(
				'section' => 'bullets',
				'items'   => array(
					array( 'text' => $long ),
					array( 'text' => $long ),
				),
			)
		);

		self::assertNotSame( Decision::WIDGET_ICON_LIST, $decision->widget() );
	}

	public function test_three_to_six_services_with_icons_route_to_icon_box_grid(): void {
		$decision = $this->judge->decide(
			array(
				'section' => 'services',
				'items'   => array(
					array( 'name' => 'Plumbing', 'icon' => 'wrench', 'description' => 'Pipes & drains' ),
					array( 'name' => 'Electrical', 'icon' => 'bolt', 'description' => 'Sparkies' ),
					array( 'name' => 'Carpentry', 'icon' => 'hammer', 'description' => 'Wood work' ),
				),
			)
		);

		self::assertSame( Decision::WIDGET_ICON_BOX_GRID, $decision->widget() );
	}

	public function test_two_services_does_not_route_to_icon_box_grid(): void {
		$decision = $this->judge->decide(
			array(
				'section' => 'services',
				'items'   => array(
					array( 'name' => 'A', 'icon' => 'star' ),
					array( 'name' => 'B', 'icon' => 'star' ),
				),
			)
		);

		self::assertNotSame( Decision::WIDGET_ICON_BOX_GRID, $decision->widget() );
	}

	public function test_seven_services_skips_icon_box_grid_and_routes_to_carousel(): void {
		$items = array();
		for ( $i = 0; $i < 9; $i++ ) {
			$items[] = array( 'name' => 'Service ' . $i, 'icon' => 'tool', 'description' => 'desc' );
		}

		$decision = $this->judge->decide(
			array(
				'section' => 'services',
				'items'   => $items,
			)
		);

		self::assertSame( Decision::WIDGET_NESTED_CAROUSEL, $decision->widget() );
	}

	public function test_gallery_with_one_image_routes_to_image_carousel(): void {
		$decision = $this->judge->decide(
			array(
				'section' => 'gallery',
				'items'   => array( array( 'image' => 'a.jpg' ) ),
			)
		);

		self::assertSame( Decision::WIDGET_IMAGE_CAROUSEL, $decision->widget() );
	}

	public function test_four_image_only_items_route_to_image_carousel(): void {
		$decision = $this->judge->decide(
			array(
				'section' => 'features',
				'items'   => array(
					array( 'image' => 'a.jpg' ),
					array( 'image' => 'b.jpg' ),
					array( 'image' => 'c.jpg' ),
					array( 'image' => 'd.jpg' ),
				),
			)
		);

		self::assertSame( Decision::WIDGET_IMAGE_CAROUSEL, $decision->widget() );
	}

	public function test_text_heavy_long_list_routes_to_accordion(): void {
		$long_text = str_repeat( 'detailed paragraph ', 20 );
		$items = array();
		for ( $i = 0; $i < 6; $i++ ) {
			$items[] = array( 'name' => 'Step ' . $i, 'description' => $long_text );
		}

		$decision = $this->judge->decide(
			array(
				'section' => 'process_steps',
				'items'   => $items,
			)
		);

		self::assertSame( Decision::WIDGET_NESTED_ACCORDION, $decision->widget() );
	}

	public function test_unknown_section_type_falls_back_to_text_editor(): void {
		$decision = $this->judge->decide(
			array(
				'section' => 'mystery_section',
				'items'   => array( array( 'text' => 'one' ) ),
			)
		);

		self::assertSame( Decision::WIDGET_TEXT_EDITOR, $decision->widget() );
		self::assertTrue( LayoutJudge::is_low_confidence( $decision ) );
	}

	public function test_audit_returns_every_matched_rule_sorted_by_confidence(): void {
		$audit = $this->judge->decide_with_audit(
			array(
				'section' => 'faq',
				'items'   => array(
					array( 'question' => 'q1' ),
					array( 'question' => 'q2' ),
				),
			)
		);

		self::assertSame( Decision::WIDGET_NESTED_ACCORDION, $audit['decision']->widget() );
		self::assertNotEmpty( $audit['matches'] );
		self::assertSame( 'faq.always_accordion', $audit['matches'][0]->rule_id() );
	}

	public function test_decision_confidence_is_clamped_to_zero_one_range(): void {
		$decision = new Decision( Decision::WIDGET_TEXT_EDITOR, 'reason', 1.5, 'test.rule' );
		self::assertSame( 1.0, $decision->confidence() );

		$decision = new Decision( Decision::WIDGET_TEXT_EDITOR, 'reason', -0.5, 'test.rule' );
		self::assertSame( 0.0, $decision->confidence() );
	}

	public function test_low_confidence_threshold_check(): void {
		$low  = new Decision( Decision::WIDGET_TEXT_EDITOR, 'r', 0.4, 'r' );
		$high = new Decision( Decision::WIDGET_ICON_LIST, 'r', 0.8, 'r' );

		self::assertTrue( LayoutJudge::is_low_confidence( $low ) );
		self::assertFalse( LayoutJudge::is_low_confidence( $high ) );
	}

	public function test_decision_to_array_serializes_all_fields(): void {
		$decision = new Decision( Decision::WIDGET_ICON_LIST, 'because', 0.85, 'rule.x' );
		$array    = $decision->to_array();

		self::assertSame( Decision::WIDGET_ICON_LIST, $array['widget'] );
		self::assertSame( 'because', $array['reason'] );
		self::assertSame( 0.85, $array['confidence'] );
		self::assertSame( 'rule.x', $array['rule_id'] );
	}

	public function test_custom_rule_set_replaces_defaults(): void {
		$judge = new LayoutJudge( array() );
		self::assertSame( 0, $judge->rule_count() );
		$decision = $judge->decide( array( 'section' => 'faq', 'items' => array() ) );
		self::assertSame( Decision::WIDGET_TEXT_EDITOR, $decision->widget() );
	}
}
