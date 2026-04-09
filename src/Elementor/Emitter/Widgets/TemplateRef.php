<?php
/**
 * Elementor template reference widget.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter\Widgets;

use ElementorForge\Elementor\Emitter\Widget;

/**
 * The `template` widget is Elementor's way of embedding a saved template
 * (CPT `elementor_library`) by id. Kenneth's SDM workflow uses this heavily —
 * see `location-page.json` template refs 3220/3230.
 *
 * Named `TemplateRef` (not `Template`) to avoid shadowing the "Template" word
 * elsewhere in the namespace. {@see widget_type()} still returns the canonical
 * `template` string Elementor expects.
 */
final class TemplateRef extends Widget {

	public function widget_type(): string {
		return 'template';
	}

	/**
	 * @param array<string, mixed> $extra
	 */
	public static function create( int $template_id, array $extra = array() ): self {
		$settings = array(
			'template_id' => $template_id,
		);
		return new self( array_merge( $settings, $extra ) );
	}
}
