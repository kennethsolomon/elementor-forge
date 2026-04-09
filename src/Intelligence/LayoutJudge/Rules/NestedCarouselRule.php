<?php
/**
 * Long mixed-content list → nested carousel rule.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Intelligence\LayoutJudge\Rules;

use ElementorForge\Intelligence\LayoutJudge\Decision;
use ElementorForge\Intelligence\LayoutJudge\Rule;
use ElementorForge\Intelligence\LayoutJudge\SectionData;

/**
 * 8+ items, mixed content (text + optional image), with text bodies. Maps to
 * the nested carousel — each slide is a full container with its own widgets,
 * which is the right tool for "carousel of complex cards." Examples: project
 * showcase carousel, testimonial carousel with photos, case study slider.
 */
final class NestedCarouselRule implements Rule {

	private const MIN_ITEMS = 8;

	public function id(): string {
		return 'long_mixed.nested_carousel';
	}

	public function evaluate( SectionData $section ): ?Decision {
		if ( $section->item_count() < self::MIN_ITEMS ) {
			return null;
		}

		$applicable_types = array( 'services', 'features', 'process_steps', 'testimonials', 'bullets' );
		if ( ! in_array( $section->type(), $applicable_types, true ) ) {
			return null;
		}

		// Skip if every item is just an image — gallery rule should win.
		if ( $section->images_present() === $section->item_count() && $section->avg_text_length() < 20 ) {
			return null;
		}

		return new Decision(
			Decision::WIDGET_NESTED_CAROUSEL,
			sprintf(
				'%d items in a %s section — too long for a static grid; nested carousel handles mixed content per slide.',
				$section->item_count(),
				$section->type()
			),
			0.8,
			$this->id()
		);
	}
}
