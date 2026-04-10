<?php
/**
 * Composable header row builder.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Header;

use ElementorForge\Elementor\Emitter\Container;
use ElementorForge\Elementor\Emitter\Emitter;
use ElementorForge\Elementor\Emitter\KitTag;
use ElementorForge\Elementor\Emitter\RawNode;
use ElementorForge\Elementor\Emitter\Widgets\Button;
use ElementorForge\Elementor\Emitter\Widgets\Heading;
use ElementorForge\Elementor\Emitter\Widgets\TextEditor;

/**
 * Builds header rows from a declarative item list. Each "item" is a string
 * keyword that maps to a widget or widget group:
 *
 *   - logo         → Heading with [site_title]
 *   - nav          → nav-menu widget (WP registered menu)
 *   - button:text  → CTA Button with label "text"
 *   - search       → search-form widget
 *   - cart         → woocommerce-menu-cart widget
 *   - account      → TextEditor "My Account" link
 *   - text:content → TextEditor with arbitrary content
 *
 * The builder handles responsive visibility and sticky settings.
 */
final class HeaderBuilder {

	/**
	 * Build a header row from a row spec.
	 *
	 * @param array<string, mixed> $row_spec Keys: items (list<string>), align, background, height, hide_mobile, hide_desktop.
	 * @return Container
	 */
	public static function build_row( array $row_spec ): Container {
		$items     = isset( $row_spec['items'] ) && is_array( $row_spec['items'] ) ? $row_spec['items'] : array();
		$align     = isset( $row_spec['align'] ) && is_string( $row_spec['align'] ) ? $row_spec['align'] : 'space-between';
		$bg        = isset( $row_spec['background'] ) && is_string( $row_spec['background'] ) ? $row_spec['background'] : '';
		$hide_mob  = ! empty( $row_spec['hide_mobile'] );
		$hide_desk = ! empty( $row_spec['hide_desktop'] );

		$settings = array(
			'content_width'        => 'full',
			'flex_direction'       => 'row',
			'flex_align_items'     => 'center',
			'flex_justify_content' => $align,
			'padding'              => array( 'unit' => 'em', 'top' => '0.75', 'right' => '1.5', 'bottom' => '0.75', 'left' => '1.5', 'isLinked' => false ),
		);

		if ( $hide_mob ) {
			$settings['hide_mobile'] = 'hidden-mobile';
			$settings['hide_tablet'] = 'hidden-tablet';
		}
		if ( $hide_desk ) {
			$settings['hide_desktop']    = 'hidden-desktop';
			$settings['hide_laptop']     = 'hidden-laptop';
			$settings['hide_widescreen'] = 'hidden-widescreen';
		}
		if ( '' !== $bg ) {
			$settings['background_background'] = 'classic';
			$settings['background_color']      = $bg;
		}

		$row = new Container( $settings );

		$count = count( $items );
		$width = $count > 1 ? Emitter::column_width( $count ) : null;

		foreach ( $items as $item ) {
			if ( ! is_string( $item ) ) {
				continue;
			}
			$widget = self::resolve_item( $item );
			if ( null === $widget ) {
				continue;
			}

			if ( null !== $width && $count > 1 ) {
				$col = new Container( array( 'content_width' => 'boxed', 'width' => $width ) );
				$col->add_child( $widget );
				$row->add_child( $col );
			} else {
				$row->add_child( $widget );
			}
		}

		return $row;
	}

	/**
	 * Apply sticky settings to a header container.
	 *
	 * @param Container            $header  The outermost header container.
	 * @param array<string, mixed> $sticky  Keys: enabled (bool), shrink (bool), background (string).
	 */
	public static function apply_sticky( Container $header, array $sticky ): Container {
		if ( empty( $sticky['enabled'] ) ) {
			return $header;
		}

		$settings = $header->get_settings();
		$settings['sticky']                = 'top';
		$settings['sticky_on']             = array( 'desktop', 'tablet' );
		$settings['sticky_offset']         = 0;
		$settings['sticky_effects_offset'] = 0;

		if ( ! empty( $sticky['shrink'] ) ) {
			$settings['motion_fx_motion_fx_scrolling'] = 'yes';
		}

		// Rebuild the container with new settings.
		$new_header = new Container( $settings, $header->get_id() );
		foreach ( $header->get_children() as $child ) {
			$new_header->add_child( $child );
		}

		return $new_header;
	}

	/**
	 * Resolve an item keyword into an Elementor Node.
	 *
	 * @return \ElementorForge\Elementor\Emitter\Node|null
	 */
	private static function resolve_item( string $item ): ?\ElementorForge\Elementor\Emitter\Node {
		// Parameterized items: "button:Get a Quote", "text:Welcome"
		if ( str_contains( $item, ':' ) ) {
			$parts = explode( ':', $item, 2 );
			$type  = $parts[0];
			$param = $parts[1] ?? '';

			switch ( $type ) {
				case 'button':
					return Button::create( $param, '' );
				case 'text':
					return TextEditor::create( $param );
				default:
					return null;
			}
		}

		switch ( $item ) {
			case 'logo':
				return Heading::create( '[site_title]', 'h3', 'left' );

			case 'logo_center':
				return Heading::create( '[site_title]', 'h3', 'center' );

			case 'nav':
				return new RawNode(
					RawNode::raw_widget(
						'nav-menu',
						array(
							'menu'   => 'primary',
							'layout' => 'horizontal',
							'toggle' => 'burger',
						),
						'ef_header_nav'
					)
				);

			case 'nav_mobile':
				return new RawNode(
					RawNode::raw_widget(
						'nav-menu',
						array(
							'menu'   => 'primary',
							'layout' => 'dropdown',
							'toggle' => 'burger',
						),
						'ef_header_nav_mobile'
					)
				);

			case 'search':
				return new RawNode(
					RawNode::raw_widget(
						'search-form',
						array( 'placeholder' => 'Search...' ),
						'ef_header_search'
					)
				);

			case 'cart':
				return new RawNode(
					RawNode::raw_widget(
						'woocommerce-menu-cart',
						array( 'show_subtotal' => 'yes' ),
						'ef_header_cart'
					)
				);

			case 'account':
				return TextEditor::create( '<a href="/my-account/">My Account</a>' );

			case 'hamburger':
				return new RawNode(
					RawNode::raw_widget(
						'nav-menu',
						array(
							'menu'   => 'primary',
							'layout' => 'dropdown',
							'toggle' => 'burger',
						),
						'ef_header_hamburger'
					)
				);

			default:
				return null;
		}
	}
}
