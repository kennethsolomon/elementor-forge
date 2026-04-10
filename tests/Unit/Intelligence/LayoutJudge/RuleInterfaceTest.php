<?php
/**
 * Rule interface contract tests.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Intelligence\LayoutJudge;

use ElementorForge\Intelligence\LayoutJudge\Decision;
use ElementorForge\Intelligence\LayoutJudge\Rule;
use ElementorForge\Intelligence\LayoutJudge\SectionData;
use PHPUnit\Framework\TestCase;

final class RuleInterfaceTest extends TestCase {

	public function test_rule_is_an_interface(): void {
		$reflection = new \ReflectionClass( Rule::class );

		self::assertTrue( $reflection->isInterface() );
	}

	public function test_rule_declares_id_method(): void {
		$reflection = new \ReflectionClass( Rule::class );

		self::assertTrue( $reflection->hasMethod( 'id' ) );
		$method = $reflection->getMethod( 'id' );
		self::assertSame( 'string', $method->getReturnType()->getName() );
	}

	public function test_rule_declares_evaluate_method(): void {
		$reflection = new \ReflectionClass( Rule::class );

		self::assertTrue( $reflection->hasMethod( 'evaluate' ) );
		$method = $reflection->getMethod( 'evaluate' );
		self::assertTrue( $method->getReturnType()->allowsNull() );
	}

	public function test_anonymous_rule_implementation_returning_null(): void {
		$rule = new class() implements Rule {
			public function id(): string {
				return 'test.null_rule';
			}

			public function evaluate( SectionData $section ): ?Decision {
				return null;
			}
		};

		$section = SectionData::from_array( array( 'section' => 'bullets', 'items' => array() ) );

		self::assertSame( 'test.null_rule', $rule->id() );
		self::assertNull( $rule->evaluate( $section ) );
	}

	public function test_anonymous_rule_implementation_returning_decision(): void {
		$rule = new class() implements Rule {
			public function id(): string {
				return 'test.match_rule';
			}

			public function evaluate( SectionData $section ): ?Decision {
				return new Decision( Decision::WIDGET_TEXT_EDITOR, 'match', 0.5, $this->id() );
			}
		};

		$section  = SectionData::from_array( array( 'section' => 'any', 'items' => array() ) );
		$decision = $rule->evaluate( $section );

		self::assertInstanceOf( Decision::class, $decision );
		self::assertSame( 'test.match_rule', $decision->rule_id() );
	}

	public function test_all_concrete_rules_implement_rule_interface(): void {
		$rule_classes = array(
			\ElementorForge\Intelligence\LayoutJudge\Rules\FaqRule::class,
			\ElementorForge\Intelligence\LayoutJudge\Rules\GalleryRule::class,
			\ElementorForge\Intelligence\LayoutJudge\Rules\IconBoxGridRule::class,
			\ElementorForge\Intelligence\LayoutJudge\Rules\IconListRule::class,
			\ElementorForge\Intelligence\LayoutJudge\Rules\NestedCarouselRule::class,
			\ElementorForge\Intelligence\LayoutJudge\Rules\TextHeavyAccordionRule::class,
		);

		foreach ( $rule_classes as $class ) {
			$reflection = new \ReflectionClass( $class );
			self::assertTrue(
				$reflection->implementsInterface( Rule::class ),
				"$class must implement Rule interface"
			);
		}
	}

	public function test_all_concrete_rules_have_unique_ids(): void {
		$rules = array(
			new \ElementorForge\Intelligence\LayoutJudge\Rules\FaqRule(),
			new \ElementorForge\Intelligence\LayoutJudge\Rules\GalleryRule(),
			new \ElementorForge\Intelligence\LayoutJudge\Rules\IconBoxGridRule(),
			new \ElementorForge\Intelligence\LayoutJudge\Rules\IconListRule(),
			new \ElementorForge\Intelligence\LayoutJudge\Rules\NestedCarouselRule(),
			new \ElementorForge\Intelligence\LayoutJudge\Rules\TextHeavyAccordionRule(),
		);

		$ids = array_map( fn( Rule $r ) => $r->id(), $rules );
		self::assertCount( count( $ids ), array_unique( $ids ), 'All rule IDs must be unique' );
	}
}
