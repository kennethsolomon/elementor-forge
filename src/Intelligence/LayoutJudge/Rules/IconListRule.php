<?php
/**
 * Bullets / short list → icon list rule.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Intelligence\LayoutJudge\Rules;

use ElementorForge\Intelligence\LayoutJudge\Decision;
use ElementorForge\Intelligence\LayoutJudge\Rule;
use ElementorForge\Intelligence\LayoutJudge\SectionData;

/**
 * Short text-only lists with at most 6 items map to the Elementor icon list
 * widget. Triggered for `bullets` sections directly, or any section type with
 * the same shape signature: short text per item, no images, low overall item
 * count.
 */
final class IconListRule implements Rule {

	private const MAX_ITEMS = 6;
	private const MAX_AVG_TEXT_LENGTH = 80;

	public function id(): string {
		return 'bullets.icon_list';
	}

	public function evaluate( SectionData $section ): ?Decision {
		$type = $section->type();

		// Hard match — `bullets` section type.
		if ( 'bullets' === $type
			&& $section->item_count() > 0
			&& $section->item_count() <= self::MAX_ITEMS
			&& $section->avg_text_length() <= self::MAX_AVG_TEXT_LENGTH
			&& 0 === $section->images_present()
		) {
			return new Decision(
				Decision::WIDGET_ICON_LIST,
				sprintf(
					'%d short text items, no images — icon list (count<=%d, avg<=%d).',
					$section->item_count(),
					self::MAX_ITEMS,
					self::MAX_AVG_TEXT_LENGTH
				),
				0.9,
				$this->id()
			);
		}

		// Soft match — any other section type with the same shape.
		if ( in_array( $type, array( 'services', 'features', 'process_steps' ), true )
			&& $section->item_count() > 0
			&& $section->item_count() <= self::MAX_ITEMS
			&& $section->avg_text_length() <= self::MAX_AVG_TEXT_LENGTH
			&& 0 === $section->images_present()
			&& 0 === $section->items_with_icon()
		) {
			return new Decision(
				Decision::WIDGET_ICON_LIST,
				sprintf(
					'%d-item %s with no icons or images and short text — icon list.',
					$section->item_count(),
					$type
				),
				0.7,
				$this->id()
			);
		}

		return null;
	}
}
