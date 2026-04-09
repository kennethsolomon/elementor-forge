<?php
/**
 * FAQ → nested accordion rule.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Intelligence\LayoutJudge\Rules;

use ElementorForge\Intelligence\LayoutJudge\Decision;
use ElementorForge\Intelligence\LayoutJudge\Rule;
use ElementorForge\Intelligence\LayoutJudge\SectionData;

/**
 * Any section labeled `faq` is unconditionally a nested accordion. FAQ is the
 * one section type with a hard, content-independent mapping — accordions are
 * the only correct affordance for collapsible Q&A.
 */
final class FaqRule implements Rule {

	public function id(): string {
		return 'faq.always_accordion';
	}

	public function evaluate( SectionData $section ): ?Decision {
		if ( 'faq' !== $section->type() ) {
			return null;
		}
		return new Decision(
			Decision::WIDGET_NESTED_ACCORDION,
			'FAQ section — accordion is the canonical affordance for collapsible Q&A.',
			0.99,
			$this->id()
		);
	}
}
