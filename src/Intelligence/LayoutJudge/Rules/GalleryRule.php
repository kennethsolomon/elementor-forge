<?php
/**
 * Gallery → image carousel rule.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Intelligence\LayoutJudge\Rules;

use ElementorForge\Intelligence\LayoutJudge\Decision;
use ElementorForge\Intelligence\LayoutJudge\Rule;
use ElementorForge\Intelligence\LayoutJudge\SectionData;

/**
 * Image-heavy sections (>=4 items, all carrying an image reference) map to the
 * Elementor image carousel widget. Section type `gallery` is matched directly;
 * other types match if every item has an image and the count meets the
 * threshold.
 */
final class GalleryRule implements Rule {

	private const MIN_IMAGES_FOR_CAROUSEL = 4;

	public function id(): string {
		return 'gallery.image_carousel';
	}

	public function evaluate( SectionData $section ): ?Decision {
		if ( 'gallery' === $section->type() && $section->item_count() >= 1 ) {
			return new Decision(
				Decision::WIDGET_IMAGE_CAROUSEL,
				'Gallery section — image carousel is the default for image-only collections.',
				0.95,
				$this->id()
			);
		}

		if (
			$section->item_count() >= self::MIN_IMAGES_FOR_CAROUSEL
			&& $section->images_present() === $section->item_count()
		) {
			return new Decision(
				Decision::WIDGET_IMAGE_CAROUSEL,
				sprintf(
					'%d items, every item has an image — image carousel.',
					$section->item_count()
				),
				0.85,
				$this->id()
			);
		}

		return null;
	}
}
