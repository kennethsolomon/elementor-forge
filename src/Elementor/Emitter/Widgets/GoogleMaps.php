<?php
/**
 * Elementor google_maps widget.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter\Widgets;

use ElementorForge\Elementor\Emitter\Widget;

final class GoogleMaps extends Widget {

	public function widget_type(): string {
		return 'google_maps';
	}

	/**
	 * @param array<string, mixed> $extra
	 */
	public static function create( string $address, int $zoom = 13, int $height_px = 500, array $extra = array() ): self {
		$settings = array(
			'address' => $address,
			'zoom'    => array(
				'unit'  => 'px',
				'size'  => $zoom,
				'sizes' => array(),
			),
			'height'  => array(
				'unit'  => 'px',
				'size'  => $height_px,
				'sizes' => array(),
			),
		);
		return new self( array_merge( $settings, $extra ) );
	}
}
