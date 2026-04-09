<?php
/**
 * Text-heavy long list → nested accordion rule.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Intelligence\LayoutJudge\Rules;

use ElementorForge\Intelligence\LayoutJudge\Decision;
use ElementorForge\Intelligence\LayoutJudge\Rule;
use ElementorForge\Intelligence\LayoutJudge\SectionData;

/**
 * Long text-heavy lists (>=5 items, average text >120 chars) collapse into a
 * nested accordion. Use case: detailed feature breakdowns, multi-step process
 * with substantive descriptions, "what's included" sections.
 */
final class TextHeavyAccordionRule implements Rule {

	private const MIN_ITEMS = 5;

	public function id(): string {
		return 'text_heavy.nested_accordion';
	}

	public function evaluate( SectionData $section ): ?Decision {
		if ( $section->item_count() < self::MIN_ITEMS ) {
			return null;
		}
		if ( ! $section->is_text_heavy() ) {
			return null;
		}

		// FAQ has its own dedicated rule with higher confidence.
		if ( 'faq' === $section->type() ) {
			return null;
		}

		return new Decision(
			Decision::WIDGET_NESTED_ACCORDION,
			sprintf(
				'%d items, avg text %d chars — text-heavy list, accordion keeps the page scannable.',
				$section->item_count(),
				$section->avg_text_length()
			),
			0.78,
			$this->id()
		);
	}
}
