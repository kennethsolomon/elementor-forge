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
				'values' => array( 'heading' => self::kses_post( $text ) ),
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
				'values' => array( 'content' => self::kses_post( $text ) ),
			),
		);
	}

	/**
	 * Sanitize a string with `wp_kses_post()` when WP is loaded, otherwise a
	 * deterministic fallback that strips `<script>` / `<iframe>` / event
	 * handlers / `javascript:` URLs so unit tests (which run without WP) still
	 * get the XSS guarantees the front-end renderer needs.
	 */
	private static function kses_post( string $text ): string {
		if ( function_exists( 'wp_kses_post' ) ) {
			return wp_kses_post( $text );
		}
		// Fallback path for pure-PHP unit tests — never reached in WP runtime.
		$without_scripts = preg_replace( '#<(script|iframe|object|embed|style)\b[^>]*>.*?</\1>#is', '', $text );
		$without_scripts = null === $without_scripts ? $text : $without_scripts;
		$without_events  = preg_replace( '#\son\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $without_scripts );
		$without_events  = null === $without_events ? $without_scripts : $without_events;
		$without_js_uri  = preg_replace( '#(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2#i', '$1=$2$2', $without_events );
		return null === $without_js_uri ? $without_events : $without_js_uri;
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
