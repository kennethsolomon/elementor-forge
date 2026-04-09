<?php
/**
 * Services / features → icon box grid rule.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Intelligence\LayoutJudge\Rules;

use ElementorForge\Intelligence\LayoutJudge\Decision;
use ElementorForge\Intelligence\LayoutJudge\Rule;
use ElementorForge\Intelligence\LayoutJudge\SectionData;

/**
 * 3-6 items with an icon + short text body — the canonical service-business
 * "feature card grid" pattern. Maps to a Flex Container with an IconBox per
 * item. Lower-bound 3 items keeps single/double-item sections out of the grid
 * (those should land on a different layout).
 */
final class IconBoxGridRule implements Rule {

	private const MIN_ITEMS = 3;
	private const MAX_ITEMS = 6;

	public function id(): string {
		return 'services.icon_box_grid';
	}

	public function evaluate( SectionData $section ): ?Decision {
		$applicable_types = array( 'services', 'features', 'process_steps' );
		if ( ! in_array( $section->type(), $applicable_types, true ) ) {
			return null;
		}

		$count = $section->item_count();
		if ( $count < self::MIN_ITEMS || $count > self::MAX_ITEMS ) {
			return null;
		}

		// Section is text-heavy → fall through to a different rule.
		if ( $section->is_text_heavy() ) {
			return null;
		}

		$confidence = $section->items_with_icon() === $count ? 0.9 : 0.75;

		return new Decision(
			Decision::WIDGET_ICON_BOX_GRID,
			sprintf(
				'%d items in a %s section, %d/%d carry icons, average text %d chars — icon-box grid.',
				$count,
				$section->type(),
				$section->items_with_icon(),
				$count,
				$section->avg_text_length()
			),
			$confidence,
			$this->id()
		);
	}
}
