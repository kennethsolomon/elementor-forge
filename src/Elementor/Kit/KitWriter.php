<?php
/**
 * Kit globals writer.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Kit;

use ElementorForge\Elementor\CacheClearer;

/**
 * Writes brand colors, typography, and button styles to the active Elementor
 * Kit post. The Kit stores these in `_elementor_page_settings` postmeta as
 * a serialized array.
 *
 * Elementor's Default Kit is an `elementor_library` post with
 * `_elementor_template_type = 'kit'`. The active Kit ID is resolved via
 * `get_option('elementor_active_kit')`.
 */
final class KitWriter {

	/**
	 * System color IDs that map to Elementor's 4 built-in color slots.
	 */
	public const SYSTEM_COLORS = array( 'primary', 'secondary', 'text', 'accent' );

	/**
	 * Get the active Kit post ID.
	 */
	public static function active_kit_id(): int {
		$kit_id = get_option( 'elementor_active_kit', 0 );
		return is_numeric( $kit_id ) ? absint( $kit_id ) : 0;
	}

	/**
	 * Write global settings to the active Kit.
	 *
	 * @param array<string, mixed> $settings Keys: 'colors', 'typography', 'button'.
	 * @return array{kit_id: int, updated: array<string, bool>}
	 */
	public static function write( array $settings ): array {
		$kit_id = self::active_kit_id();
		if ( $kit_id <= 0 ) {
			return array( 'kit_id' => 0, 'updated' => array() );
		}

		$page_settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		if ( ! is_array( $page_settings ) ) {
			$page_settings = array();
		}

		$updated = array();

		if ( isset( $settings['colors'] ) && is_array( $settings['colors'] ) ) {
			$page_settings = self::apply_colors( $page_settings, $settings['colors'] );
			$updated['colors'] = true;
		}

		if ( isset( $settings['typography'] ) && is_array( $settings['typography'] ) ) {
			$page_settings = self::apply_typography( $page_settings, $settings['typography'] );
			$updated['typography'] = true;
		}

		if ( isset( $settings['button'] ) && is_array( $settings['button'] ) ) {
			$page_settings = self::apply_button_styles( $page_settings, $settings['button'] );
			$updated['button'] = true;
		}

		update_post_meta( $kit_id, '_elementor_page_settings', $page_settings );
		CacheClearer::clear( $kit_id );

		return array( 'kit_id' => $kit_id, 'updated' => $updated );
	}

	/**
	 * Apply system colors to Kit page settings.
	 *
	 * @param array<string, mixed>  $page_settings
	 * @param array<string, string> $colors Map of color slot → hex value (e.g. 'primary' => '#1a73e8').
	 * @return array<string, mixed>
	 */
	private static function apply_colors( array $page_settings, array $colors ): array {
		$system_colors = isset( $page_settings['system_colors'] ) && is_array( $page_settings['system_colors'] )
			? $page_settings['system_colors']
			: array();

		// Index existing system colors by _id.
		$indexed = array();
		foreach ( $system_colors as $entry ) {
			if ( is_array( $entry ) && isset( $entry['_id'] ) ) {
				$indexed[ $entry['_id'] ] = $entry;
			}
		}

		// Apply provided colors.
		foreach ( $colors as $slot => $hex ) {
			if ( ! is_string( $slot ) || ! is_string( $hex ) ) {
				continue;
			}
			$hex = sanitize_hex_color( $hex );
			if ( '' === $hex || null === $hex ) {
				continue;
			}

			if ( in_array( $slot, self::SYSTEM_COLORS, true ) ) {
				$indexed[ $slot ] = array(
					'_id'   => $slot,
					'title' => ucfirst( $slot ),
					'color' => $hex,
				);
			} else {
				// Custom color slot.
				$indexed[ $slot ] = array(
					'_id'   => $slot,
					'title' => ucfirst( str_replace( array( '_', '-' ), ' ', $slot ) ),
					'color' => $hex,
				);
			}
		}

		$page_settings['system_colors'] = array_values( $indexed );
		return $page_settings;
	}

	/**
	 * Apply typography settings to Kit page settings.
	 *
	 * @param array<string, mixed>                $page_settings
	 * @param array<string, array<string, mixed>> $typography Map of slot → typography settings.
	 * @return array<string, mixed>
	 */
	private static function apply_typography( array $page_settings, array $typography ): array {
		$system_typography = isset( $page_settings['system_typography'] ) && is_array( $page_settings['system_typography'] )
			? $page_settings['system_typography']
			: array();

		$indexed = array();
		foreach ( $system_typography as $entry ) {
			if ( is_array( $entry ) && isset( $entry['_id'] ) ) {
				$indexed[ $entry['_id'] ] = $entry;
			}
		}

		foreach ( $typography as $slot => $settings ) {
			if ( ! is_string( $slot ) || ! is_array( $settings ) ) {
				continue;
			}
			$typo_value = array();
			if ( isset( $settings['font_family'] ) && is_string( $settings['font_family'] ) ) {
				$typo_value['typography_font_family'] = sanitize_text_field( $settings['font_family'] );
			}
			if ( isset( $settings['font_size'] ) ) {
				$typo_value['typography_font_size'] = self::dimension( $settings['font_size'] );
			}
			if ( isset( $settings['font_weight'] ) && is_string( $settings['font_weight'] ) ) {
				$typo_value['typography_font_weight'] = sanitize_text_field( $settings['font_weight'] );
			}
			if ( isset( $settings['line_height'] ) ) {
				$typo_value['typography_line_height'] = self::dimension( $settings['line_height'] );
			}

			$indexed[ $slot ] = array(
				'_id'                    => $slot,
				'title'                  => ucfirst( str_replace( array( '_', '-' ), ' ', $slot ) ),
				'typography_typography'  => 'custom',
				'typography_font_family' => $typo_value['typography_font_family'] ?? '',
				'typography_font_size'   => $typo_value['typography_font_size'] ?? array(),
				'typography_font_weight' => $typo_value['typography_font_weight'] ?? '',
				'typography_line_height' => $typo_value['typography_line_height'] ?? array(),
			);
		}

		$page_settings['system_typography'] = array_values( $indexed );
		return $page_settings;
	}

	/**
	 * Apply button styles to Kit page settings.
	 *
	 * @param array<string, mixed> $page_settings
	 * @param array<string, mixed> $button Button style overrides.
	 * @return array<string, mixed>
	 */
	private static function apply_button_styles( array $page_settings, array $button ): array {
		$mappings = array(
			'text_color'       => 'button_text_color',
			'background_color' => 'button_background_color',
			'border_color'     => 'button_border_color',
			'border_radius'    => 'button_border_radius',
			'padding'          => 'button_padding',
			'font_family'      => 'button_typography_font_family',
			'font_size'        => 'button_typography_font_size',
			'font_weight'      => 'button_typography_font_weight',
		);

		foreach ( $mappings as $input_key => $kit_key ) {
			if ( ! isset( $button[ $input_key ] ) ) {
				continue;
			}
			$value = $button[ $input_key ];
			if ( is_string( $value ) ) {
				$page_settings[ $kit_key ] = sanitize_text_field( $value );
			} elseif ( is_array( $value ) ) {
				$page_settings[ $kit_key ] = $value;
			}
		}

		return $page_settings;
	}

	/**
	 * Normalize a dimension value into Elementor's unit shape.
	 *
	 * @param mixed $value Int (px assumed) or array{unit, size}.
	 * @return array{unit: string, size: int|float, sizes: array<empty>}
	 */
	private static function dimension( $value ): array {
		if ( is_array( $value ) && isset( $value['size'] ) ) {
			return array(
				'unit'  => isset( $value['unit'] ) && is_string( $value['unit'] ) ? $value['unit'] : 'px',
				'size'  => is_numeric( $value['size'] ) ? (float) $value['size'] : 16,
				'sizes' => array(),
			);
		}
		if ( is_numeric( $value ) ) {
			return array( 'unit' => 'px', 'size' => (float) $value, 'sizes' => array() );
		}
		return array( 'unit' => 'px', 'size' => 16, 'sizes' => array() );
	}
}
