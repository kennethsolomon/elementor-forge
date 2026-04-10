<?php
/**
 * Decision value object tests.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Intelligence\LayoutJudge;

use ElementorForge\Intelligence\LayoutJudge\Decision;
use PHPUnit\Framework\TestCase;

final class DecisionTest extends TestCase {

	public function test_constructor_stores_all_fields(): void {
		$decision = new Decision( Decision::WIDGET_ICON_LIST, 'test reason', 0.85, 'rule.test' );

		self::assertSame( Decision::WIDGET_ICON_LIST, $decision->widget() );
		self::assertSame( 'test reason', $decision->reason() );
		self::assertSame( 0.85, $decision->confidence() );
		self::assertSame( 'rule.test', $decision->rule_id() );
	}

	public function test_confidence_clamped_to_max_one(): void {
		$decision = new Decision( Decision::WIDGET_TEXT_EDITOR, 'over', 1.5, 'r' );

		self::assertSame( 1.0, $decision->confidence() );
	}

	public function test_confidence_clamped_to_min_zero(): void {
		$decision = new Decision( Decision::WIDGET_TEXT_EDITOR, 'under', -0.3, 'r' );

		self::assertSame( 0.0, $decision->confidence() );
	}

	public function test_confidence_at_boundary_one_is_kept(): void {
		$decision = new Decision( Decision::WIDGET_TEXT_EDITOR, 'exact', 1.0, 'r' );

		self::assertSame( 1.0, $decision->confidence() );
	}

	public function test_confidence_at_boundary_zero_is_kept(): void {
		$decision = new Decision( Decision::WIDGET_TEXT_EDITOR, 'exact', 0.0, 'r' );

		self::assertSame( 0.0, $decision->confidence() );
	}

	public function test_to_array_returns_all_fields(): void {
		$decision = new Decision( Decision::WIDGET_IMAGE_CAROUSEL, 'gallery', 0.95, 'gallery.rule' );
		$array    = $decision->to_array();

		self::assertSame(
			array(
				'widget'     => Decision::WIDGET_IMAGE_CAROUSEL,
				'reason'     => 'gallery',
				'confidence' => 0.95,
				'rule_id'    => 'gallery.rule',
			),
			$array
		);
	}

	public function test_to_array_reflects_clamped_confidence(): void {
		$decision = new Decision( Decision::WIDGET_TEXT_EDITOR, 'r', 2.0, 'r' );

		self::assertSame( 1.0, $decision->to_array()['confidence'] );
	}

	public function test_widget_constants_are_distinct(): void {
		$constants = array(
			Decision::WIDGET_ICON_LIST,
			Decision::WIDGET_IMAGE_CAROUSEL,
			Decision::WIDGET_ICON_BOX_GRID,
			Decision::WIDGET_NESTED_CAROUSEL,
			Decision::WIDGET_NESTED_ACCORDION,
			Decision::WIDGET_TEXT_EDITOR,
		);

		self::assertCount( 6, array_unique( $constants ) );
	}

	public function test_empty_strings_are_valid(): void {
		$decision = new Decision( '', '', 0.5, '' );

		self::assertSame( '', $decision->widget() );
		self::assertSame( '', $decision->reason() );
		self::assertSame( '', $decision->rule_id() );
	}
}
