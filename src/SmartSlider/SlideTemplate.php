<?php
/**
 * Minimal slide layer JSON template.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\SmartSlider;

/**
 * Produces the minimal layer-tree JSON Smart Slider 3 expects in
 * `slides.slide`. The shape is verified against the official 3.5.1.34 sample
 * slider INSERT in `Install.php`. Anything more complex is left to caller-
 * supplied layer arrays.
 */
final class SlideTemplate {

	/**
	 * Build a single-slide layer tree containing one heading + one text block.
	 * Returns the JSON-encoded string ready to be written to `slides.slide`.
	 *
	 * Layer keys reflect the 3.5.1.34 sample. Smart Slider tolerates missing
	 * non-essential keys; the keys here are the minimum required for the
	 * front-end renderer to draw the slide without warnings.
	 *
	 * @param string $heading Slide heading text.
	 * @param string $body    Slide body text. Optional.
	 */
	public static function minimal( string $heading, string $body = '' ): string {
		$layers = array(
			array(
				'type'                     => 'content',
				'pm'                       => 'default',
				'desktopportraitfontsize'  => 100,
				'desktopportraitmaxwidth'  => 1120,
				'desktopportraitselfalign' => 'center',
				'opened'                   => 1,
				'id'                       => '',
				'desktopportrait'          => 1,
				'desktoplandscape'         => 1,
				'tabletportrait'           => 1,
				'tabletlandscape'          => 1,
				'mobileportrait'           => 1,
				'mobilelandscape'          => 1,
				'children'                 => array(
					self::heading_layer( $heading ),
					'' === $body ? array() : self::text_layer( $body ),
				),
			),
		);

		// Filter out any empty children that came from optional layers.
		$layers[0]['children'] = array_values(
			array_filter(
				$layers[0]['children'],
				static fn ( $layer ): bool => is_array( $layer ) && array() !== $layer
			)
		);

		return self::encode_json( $layers );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function heading_layer( string $text ): array {
		return array(
			'type'                    => 'layer',
			'pm'                      => 'normal',
			'desktopportraitfontsize' => 100,
			'desktopportrait'         => 1,
			'desktoplandscape'        => 1,
			'tabletportrait'          => 1,
			'tabletlandscape'         => 1,
			'mobileportrait'          => 1,
			'mobilelandscape'         => 1,
			'item'                    => array(
				'type'   => 'heading',
				'values' => array( 'heading' => $text ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function text_layer( string $text ): array {
		return array(
			'type'                    => 'layer',
			'pm'                      => 'normal',
			'desktopportraitfontsize' => 100,
			'desktopportrait'         => 1,
			'desktoplandscape'        => 1,
			'tabletportrait'          => 1,
			'tabletlandscape'         => 1,
			'mobileportrait'          => 1,
			'mobilelandscape'         => 1,
			'item'                    => array(
				'type'   => 'text',
				'values' => array( 'content' => $text ),
			),
		);
	}

	/**
	 * Encode a value as JSON. Prefers wp_json_encode when WP is loaded so the
	 * encoding rules match Smart Slider's own writes.
	 *
	 * @param mixed $value
	 */
	private static function encode_json( $value ): string {
		if ( function_exists( 'wp_json_encode' ) ) {
			$encoded = wp_json_encode( $value );
			if ( is_string( $encoded ) ) {
				return $encoded;
			}
		}
		$encoded = json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		return is_string( $encoded ) ? $encoded : '[]';
	}
}
