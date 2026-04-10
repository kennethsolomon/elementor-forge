<?php
/**
 * TextHeavyAccordionRule tests.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Intelligence\LayoutJudge\Rules;

use ElementorForge\Intelligence\LayoutJudge\Decision;
use ElementorForge\Intelligence\LayoutJudge\Rules\TextHeavyAccordionRule;
use ElementorForge\Intelligence\LayoutJudge\SectionData;
use PHPUnit\Framework\TestCase;

final class TextHeavyAccordionRuleTest extends TestCase {

	private TextHeavyAccordionRule $rule;

	protected function setUp(): void {
		parent::setUp();
		$this->rule = new TextHeavyAccordionRule();
	}

	public function test_id_returns_stable_identifier(): void {
		self::assertSame( 'text_heavy.nested_accordion', $this->rule->id() );
	}

	/** Matching conditions. */
	public function test_five_items_with_long_text_matches(): void {
		$long = str_repeat( 'word ', 30 ); // >120 chars avg
		$items = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$items[] = array( 'name' => "Item $i", 'description' => $long );
		}
		$section  = SectionData::from_array( array( 'section' => 'services', 'items' => $items ) );
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
		self::assertSame( Decision::WIDGET_NESTED_ACCORDION, $decision->widget() );
		self::assertSame( 0.78, $decision->confidence() );
	}

	public function test_many_items_with_long_text_matches(): void {
		$long = str_repeat( 'a', 200 );
		$items = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$items[] = array( 'description' => $long );
		}
		$section  = SectionData::from_array( array( 'section' => 'features', 'items' => $items ) );
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
	}

	public function test_text_heavy_via_max_length_above_240_matches(): void {
		$short = 'short';
		$huge  = str_repeat( 'b', 250 );
		$items = array(
			array( 'text' => $short ),
			array( 'text' => $short ),
			array( 'text' => $short ),
			array( 'text' => $short ),
			array( 'text' => $huge ),
		);
		$section  = SectionData::from_array( array( 'section' => 'process_steps', 'items' => $items ) );
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
		self::assertSame( Decision::WIDGET_NESTED_ACCORDION, $decision->widget() );
	}

	/** Minimum item count. */
	public function test_four_items_below_minimum_returns_null(): void {
		$long = str_repeat( 'a', 200 );
		$items = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$items[] = array( 'description' => $long );
		}
		$section = SectionData::from_array( array( 'section' => 'services', 'items' => $items ) );

		self::assertNull( $this->rule->evaluate( $section ) );
	}

	public function test_five_items_at_minimum_matches(): void {
		$long = str_repeat( 'a', 150 );
		$items = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$items[] = array( 'description' => $long );
		}
		$section  = SectionData::from_array( array( 'section' => 'services', 'items' => $items ) );
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
	}

	/** Not text-heavy. */
	public function test_short_text_items_returns_null(): void {
		$items = array();
		for ( $i = 0; $i < 6; $i++ ) {
			$items[] = array( 'description' => 'Short' );
		}
		$section = SectionData::from_array( array( 'section' => 'services', 'items' => $items ) );

		self::assertNull( $this->rule->evaluate( $section ) );
	}

	/** FAQ exclusion. */
	public function test_faq_type_returns_null_even_when_text_heavy(): void {
		$long = str_repeat( 'a', 200 );
		$items = array();
		for ( $i = 0; $i < 6; $i++ ) {
			$items[] = array( 'question' => "Q$i", 'answer' => $long );
		}
		$section = SectionData::from_array( array( 'section' => 'faq', 'items' => $items ) );

		self::assertNull( $this->rule->evaluate( $section ) );
	}

	/** Any non-faq section type is accepted. */
	public function test_any_non_faq_type_with_text_heavy_items_matches(): void {
		$long = str_repeat( 'x', 150 );
		$types = array( 'services', 'features', 'process_steps', 'bullets', 'testimonials', 'unknown_type' );

		foreach ( $types as $type ) {
			$items = array();
			for ( $i = 0; $i < 5; $i++ ) {
				$items[] = array( 'description' => $long );
			}
			$section  = SectionData::from_array( array( 'section' => $type, 'items' => $items ) );
			$decision = $this->rule->evaluate( $section );

			self::assertInstanceOf(
				Decision::class,
				$decision,
				"Type '$type' with text-heavy items should match"
			);
		}
	}

	public function test_decision_rule_id_matches_own_id(): void {
		$long = str_repeat( 'a', 150 );
		$items = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$items[] = array( 'description' => $long );
		}
		$section  = SectionData::from_array( array( 'section' => 'services', 'items' => $items ) );
		$decision = $this->rule->evaluate( $section );

		self::assertSame( $this->rule->id(), $decision->rule_id() );
	}

	public function test_empty_items_returns_null(): void {
		$section = SectionData::from_array( array( 'section' => 'services', 'items' => array() ) );

		self::assertNull( $this->rule->evaluate( $section ) );
	}
}
