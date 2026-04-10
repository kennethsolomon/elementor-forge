<?php
/**
 * FaqRule tests.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Intelligence\LayoutJudge\Rules;

use ElementorForge\Intelligence\LayoutJudge\Decision;
use ElementorForge\Intelligence\LayoutJudge\Rules\FaqRule;
use ElementorForge\Intelligence\LayoutJudge\SectionData;
use PHPUnit\Framework\TestCase;

final class FaqRuleTest extends TestCase {

	private FaqRule $rule;

	protected function setUp(): void {
		parent::setUp();
		$this->rule = new FaqRule();
	}

	public function test_id_returns_stable_identifier(): void {
		self::assertSame( 'faq.always_accordion', $this->rule->id() );
	}

	public function test_faq_section_matches_with_nested_accordion(): void {
		$section  = SectionData::from_array(
			array(
				'section' => 'faq',
				'items'   => array(
					array( 'question' => 'What is this?', 'answer' => 'A test.' ),
				),
			)
		);
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
		self::assertSame( Decision::WIDGET_NESTED_ACCORDION, $decision->widget() );
	}

	public function test_faq_section_has_high_confidence(): void {
		$section  = SectionData::from_array(
			array(
				'section' => 'faq',
				'items'   => array( array( 'question' => 'q', 'answer' => 'a' ) ),
			)
		);
		$decision = $this->rule->evaluate( $section );

		self::assertSame( 0.99, $decision->confidence() );
	}

	public function test_faq_section_with_empty_items_still_matches(): void {
		$section  = SectionData::from_array( array( 'section' => 'faq', 'items' => array() ) );
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
		self::assertSame( Decision::WIDGET_NESTED_ACCORDION, $decision->widget() );
	}

	public function test_faq_section_with_many_items_still_matches(): void {
		$items = array();
		for ( $i = 0; $i < 20; $i++ ) {
			$items[] = array( 'question' => "Q$i", 'answer' => "A$i" );
		}
		$section  = SectionData::from_array( array( 'section' => 'faq', 'items' => $items ) );
		$decision = $this->rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
	}

	public function test_decision_rule_id_matches_own_id(): void {
		$section  = SectionData::from_array( array( 'section' => 'faq', 'items' => array() ) );
		$decision = $this->rule->evaluate( $section );

		self::assertSame( $this->rule->id(), $decision->rule_id() );
	}

	public function test_non_faq_section_returns_null(): void {
		$types = array( 'bullets', 'services', 'features', 'process_steps', 'gallery', 'testimonials', '' );

		foreach ( $types as $type ) {
			$section = SectionData::from_array( array( 'section' => $type, 'items' => array() ) );
			self::assertNull(
				$this->rule->evaluate( $section ),
				"Section type '$type' should not match FaqRule"
			);
		}
	}
}
