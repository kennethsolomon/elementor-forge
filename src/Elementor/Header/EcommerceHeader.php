<?php
/**
 * Ecommerce header variant (Theme Builder Header template).
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Header;

use ElementorForge\Elementor\Emitter\Container;
use ElementorForge\Elementor\Emitter\Document;
use ElementorForge\Elementor\Emitter\RawNode;
use ElementorForge\Elementor\Emitter\Widgets\Button;
use ElementorForge\Elementor\Emitter\Widgets\Heading;
use ElementorForge\Elementor\Emitter\Widgets\TextEditor;
use ElementorForge\Elementor\ThemeBuilder\TemplateSpec;

/**
 * Builds the ecommerce header variant that replaces the default service-business
 * header when the `header_pattern` plugin setting is flipped to `ecommerce`.
 *
 * Mobile layout (Atlas Packaging Hub pattern):
 *
 *   ┌────────────────────────────────┐
 *   │  [cart] [account]       logo   │   top row
 *   ├────────────────────────────────┤
 *   │       hero carousel            │
 *   └────────────────────────────────┘
 *   ...page content...
 *   ┌────────────────────────────────┐
 *   │ Store │ Search │ ❤ │ 👤 │ ≡    │   fixed bottom tab bar
 *   └────────────────────────────────┘
 *
 * The bottom tab bar is an Elementor Container with `position: fixed` CSS
 * applied via the container's own `custom_css` setting — Elementor Pro's
 * sticky-scroll API pins to the top of the viewport on scroll, it does not
 * provide fixed positioning, so we inject the CSS directly rather than
 * fighting the sticky module. Verified by reading the
 * `Elementor\Modules\ThemeBuilder\Documents\Header` class and the
 * `elementor-pro/modules/sticky` module — the `sticky` setting supports
 * `top` and `bottom` anchor points for sticky-on-scroll, not permanent fixed
 * positioning.
 *
 * Desktop layout:
 *
 *   ┌──────────────────────────────────────────────────────────────┐
 *   │ logo   [Fibosearch bar]     welcome | ♥ 0 | 🛒 0            │   top row
 *   ├──────────────────────────────────────────────────────────────┤
 *   │ Home | Shop | Categories ▾ | Best Discounts ▾ | Contact     │   nav row
 *   └──────────────────────────────────────────────────────────────┘
 *
 * The Fibosearch widget is embedded as the primary search input — that's why
 * Phase 2's Fibosearch configuration step is a sibling of this header. If
 * Fibosearch is absent at install time, Elementor renders the widget as an
 * empty placeholder which is still acceptable as the template is allowed to
 * encode the intent even when the plugin is missing.
 */
final class EcommerceHeader {

	/**
	 * Build the ecommerce header {@see TemplateSpec}. The caller is the
	 * WooCommerce Theme Builder installer when the setting flips to
	 * `ecommerce`, or the settings page "Switch header pattern to ecommerce"
	 * action. Pure — no WordPress or WC dependencies.
	 */
	public static function spec(): TemplateSpec {
		$doc = new Document( 'Elementor Forge — Ecommerce Header', 'header' );

		$doc->append( self::desktop_row() );
		$doc->append( self::desktop_nav_row() );
		$doc->append( self::mobile_top_row() );
		$doc->append( self::mobile_bottom_tab_bar() );

		return new TemplateSpec(
			\ElementorForge\Elementor\ThemeBuilder\Templates::TEMPLATE_TYPE_HEADER,
			'Elementor Forge — Ecommerce Header',
			$doc,
			array(
				'_elementor_template_type' => 'header',
				'_elementor_conditions'    => array( 'include/general' ),
				'_ef_header_variant'       => 'ecommerce',
			)
		);
	}

	/**
	 * Desktop top row: logo + Fibosearch input + account/wishlist/cart icons.
	 *
	 * Hidden on mobile breakpoints via the `hide_mobile` container flag, which
	 * Elementor honors on Flexbox Containers.
	 */
	private static function desktop_row(): Container {
		$row = new Container(
			array(
				'content_width'    => 'full',
				'flex_direction'   => 'row',
				'flex_align_items' => 'center',
				'flex_gap'         => array( 'unit' => 'px', 'size' => 24 ),
				'padding'          => array( 'unit' => 'em', 'top' => '0.75', 'right' => '1.5', 'bottom' => '0.75', 'left' => '1.5', 'isLinked' => false ),
				'hide_mobile'      => 'hidden-mobile',
				'hide_tablet'      => 'hidden-tablet',
				'_ef_slot'         => 'ecommerce_header_desktop_top',
			)
		);

		// Logo column.
		$logo_col = new Container( array( 'content_width' => 'boxed', '_flex_size' => '0 0 220px' ) );
		$logo_col->add_child( Heading::create( '[site_title]', 'h3', 'left' ) );
		$row->add_child( $logo_col );

		// Primary Fibosearch search column (center, grows).
		$search_col = new Container( array( 'content_width' => 'boxed', '_flex_size' => '1 1 auto' ) );
		$search_col->add_child( new RawNode( RawNode::raw_widget( 'fibosearch', array( 'placeholder' => 'Search products...' ), 'ef_header_fibosearch' ) ) );
		$row->add_child( $search_col );

		// Account / wishlist / cart column (right aligned).
		$actions_col = new Container(
			array(
				'content_width'        => 'boxed',
				'flex_direction'       => 'row',
				'flex_justify_content' => 'flex-end',
				'flex_align_items'     => 'center',
				'flex_gap'             => array( 'unit' => 'px', 'size' => 16 ),
				'_flex_size'           => '0 0 auto',
			)
		);
		$actions_col->add_child( TextEditor::create( 'Welcome, Guest' ) );
		$actions_col->add_child( new RawNode( RawNode::raw_widget( 'woocommerce-menu-cart', array( 'show_subtotal' => 'yes', 'icon' => 'cart' ), 'ef_header_wishlist' ) ) );
		$actions_col->add_child( new RawNode( RawNode::raw_widget( 'woocommerce-menu-cart', array( 'show_subtotal' => 'yes' ), 'ef_header_cart' ) ) );
		$row->add_child( $actions_col );

		return $row;
	}

	/**
	 * Desktop secondary nav row: primary menu including "Best Discounts"
	 * dropdown. Elementor Pro's `nav-menu` widget is used — it reads the WP
	 * menu assigned to the `primary` menu location by default.
	 */
	private static function desktop_nav_row(): Container {
		$row = new Container(
			array(
				'content_width'    => 'full',
				'flex_direction'   => 'row',
				'flex_align_items' => 'center',
				'padding'          => array( 'unit' => 'em', 'top' => '0.5', 'right' => '1.5', 'bottom' => '0.5', 'left' => '1.5', 'isLinked' => false ),
				'hide_mobile'      => 'hidden-mobile',
				'hide_tablet'      => 'hidden-tablet',
				'_ef_slot'         => 'ecommerce_header_desktop_nav',
			)
		);
		$row->add_child( new RawNode( RawNode::raw_widget( 'nav-menu', array( 'menu' => 'primary', 'layout' => 'horizontal' ), 'ef_header_primary_nav' ) ) );
		return $row;
	}

	/**
	 * Mobile top row: centered logo + cart + account icons. Hidden on
	 * desktop. Includes a carousel slot below the top row so the mobile hero
	 * stays close to the logo visually.
	 */
	private static function mobile_top_row(): Container {
		$wrapper = new Container(
			array(
				'content_width'   => 'full',
				'flex_direction'  => 'column',
				'hide_desktop'    => 'hidden-desktop',
				'hide_laptop'     => 'hidden-laptop',
				'hide_widescreen' => 'hidden-widescreen',
				'_ef_slot'        => 'ecommerce_header_mobile_top',
			)
		);

		$top_row = new Container(
			array(
				'content_width'        => 'full',
				'flex_direction'       => 'row',
				'flex_align_items'     => 'center',
				'flex_justify_content' => 'space-between',
				'padding'              => array( 'unit' => 'em', 'top' => '0.75', 'right' => '1', 'bottom' => '0.75', 'left' => '1', 'isLinked' => false ),
			)
		);
		$top_row->add_child( Heading::create( '[site_title]', 'h4', 'center' ) );

		$icons_col = new Container(
			array(
				'content_width'    => 'boxed',
				'flex_direction'   => 'row',
				'flex_align_items' => 'center',
				'flex_gap'         => array( 'unit' => 'px', 'size' => 12 ),
				'_flex_size'       => '0 0 auto',
			)
		);
		$icons_col->add_child( new RawNode( RawNode::raw_widget( 'woocommerce-menu-cart', array(), 'ef_header_mobile_cart' ) ) );
		$top_row->add_child( $icons_col );
		$wrapper->add_child( $top_row );

		// Hero carousel slot (below logo, above fold).
		$carousel_row = new Container( array( 'content_width' => 'full', '_ef_slot' => 'ecommerce_header_mobile_carousel' ) );
		$carousel_row->add_child( new RawNode( RawNode::raw_widget( 'nested-carousel', array( 'auto_play' => 'yes', 'autoplay_speed' => 5000 ), 'ef_header_mobile_carousel' ) ) );
		$wrapper->add_child( $carousel_row );

		return $wrapper;
	}

	/**
	 * Mobile bottom tab bar: fixed-position 5-tab nav (Store, Search, Wishlist,
	 * Account, Categories). Hidden on desktop. Fixed positioning is injected
	 * via the container `custom_css` setting because Elementor Pro's sticky
	 * module supports sticky-on-scroll but not permanent fixed positioning.
	 */
	private static function mobile_bottom_tab_bar(): Container {
		$bar = new Container(
			array(
				'content_width'        => 'full',
				'flex_direction'       => 'row',
				'flex_justify_content' => 'space-around',
				'flex_align_items'     => 'center',
				'padding'              => array( 'unit' => 'em', 'top' => '0.5', 'right' => '0', 'bottom' => '0.5', 'left' => '0', 'isLinked' => false ),
				'hide_desktop'         => 'hidden-desktop',
				'hide_laptop'          => 'hidden-laptop',
				'hide_widescreen'      => 'hidden-widescreen',
				'_ef_slot'             => 'ecommerce_header_mobile_tab_bar',
				'custom_css'           => 'selector { position: fixed; bottom: 0; left: 0; right: 0; z-index: 9999; background: #ffffff; box-shadow: 0 -2px 8px rgba(0,0,0,0.08); }',
			)
		);

		foreach (
			array(
				array( 'label' => 'Store', 'url' => '/shop/', 'id' => 'ef_tab_store' ),
				array( 'label' => 'Search', 'url' => '#search', 'id' => 'ef_tab_search' ),
				array( 'label' => 'Wishlist', 'url' => '/wishlist/', 'id' => 'ef_tab_wishlist' ),
				array( 'label' => 'Account', 'url' => '/my-account/', 'id' => 'ef_tab_account' ),
				array( 'label' => 'Categories', 'url' => '/categories/', 'id' => 'ef_tab_categories' ),
			) as $tab
		) {
			$cell = new Container(
				array(
					'content_width'        => 'boxed',
					'flex_direction'       => 'column',
					'flex_align_items'     => 'center',
					'flex_justify_content' => 'center',
					'_flex_size'           => '1 1 0',
				)
			);
			$cell->add_child( Button::create( $tab['label'], $tab['url'] ) );
			$bar->add_child( $cell );
		}

		return $bar;
	}

}
