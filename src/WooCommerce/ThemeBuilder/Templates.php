<?php
/**
 * WooCommerce Theme Builder template definitions.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\WooCommerce\ThemeBuilder;

use ElementorForge\Elementor\Emitter\Container;
use ElementorForge\Elementor\Emitter\Document;
use ElementorForge\Elementor\Emitter\RawNode;
use ElementorForge\Elementor\Emitter\Widgets\Button;
use ElementorForge\Elementor\Emitter\Widgets\Heading;
use ElementorForge\Elementor\Emitter\Widgets\TextEditor;
use ElementorForge\Elementor\ThemeBuilder\TemplateSpec;

/**
 * Declarative factory for the four WooCommerce Theme Builder templates Forge
 * installs when the `header_pattern` toggle is flipped to `ecommerce` (or when
 * the `configure_woocommerce` MCP tool is executed):
 *
 *   - Shop Archive       — product listing layout
 *   - Single Product     — product detail layout
 *   - Cart               — cart layout
 *   - Checkout           — checkout layout
 *
 * Every spec ships a {@see Document} built from the same emitter primitives as
 * the Phase 1 Theme Builder templates. WooCommerce-specific widgets
 * (woocommerce-products, woocommerce-product-images, etc.) are injected via
 * {@see RawNode} so the emitter itself does not need to know about every WC Pro
 * widget — Forge only needs Elementor to render them at runtime, the source of
 * truth for the widget schemas is Elementor Pro itself.
 *
 * Display conditions are written as strings matching Elementor's display
 * conditions engine (`include/product_archive`, `include/singular/product`,
 * `include/cart_page`, `include/checkout_page`). These condition strings were
 * verified against Elementor Pro's WooCommerce module source — they are the
 * values the module registers on the Conditions_Manager when WooCommerce is
 * detected at boot time.
 */
final class Templates {

	public const TEMPLATE_TYPE_SHOP_ARCHIVE   = 'ef_wc_shop_archive';
	public const TEMPLATE_TYPE_SINGLE_PRODUCT = 'ef_wc_single_product';
	public const TEMPLATE_TYPE_CART           = 'ef_wc_cart';
	public const TEMPLATE_TYPE_CHECKOUT       = 'ef_wc_checkout';

	/**
	 * Return every WooCommerce Theme Builder template. Pure — no WC required.
	 *
	 * @return list<TemplateSpec>
	 */
	public static function all(): array {
		return array(
			self::shop_archive(),
			self::single_product(),
			self::cart(),
			self::checkout(),
		);
	}

	/**
	 * Shop Archive template — hero heading + product grid + pagination.
	 *
	 * The `woocommerce-products` widget slug is the one Elementor Pro's
	 * WooCommerce module registers via `Elementor\Modules\ThemeBuilder\
	 * Classes\Widgets_Manager` on WC boot. Confirmed by reading the module's
	 * `Products_Renderer` class — the widget type string is exactly
	 * `woocommerce-products`, not `wc-products` or `product-grid`.
	 */
	private static function shop_archive(): TemplateSpec {
		$doc = new Document( 'Elementor Forge — Shop Archive', 'wp-post' );

		// Hero band.
		$hero = new Container(
			array(
				'content_width' => 'boxed',
				'padding'       => array( 'unit' => 'em', 'top' => '3', 'right' => '1', 'bottom' => '3', 'left' => '1', 'isLinked' => false ),
			)
		);
		$hero->add_child( Heading::create( 'Shop', 'h1', 'center' ) );
		$hero->add_child( TextEditor::create( 'Browse our full product catalogue.' ) );
		$doc->append( $hero );

		// Product grid row: sidebar + grid.
		$row = new Container(
			array(
				'content_width'  => 'boxed',
				'flex_direction' => 'row',
				'flex_wrap'      => 'nowrap',
			)
		);

		$sidebar = new Container( array( 'content_width' => 'boxed', '_flex_size' => '0 0 280px' ) );
		$sidebar->add_child( Heading::create( 'Filter', 'h4', 'left' ) );
		$sidebar->add_child( new RawNode( self::raw_widget( 'fibosearch', array(), 'wc_sidebar_search' ) ) );
		$sidebar->add_child( new RawNode( self::raw_widget( 'woocommerce-product-categories', array(), 'wc_sidebar_cats' ) ) );
		$sidebar->add_child( new RawNode( self::raw_widget( 'woocommerce-price-filter', array(), 'wc_sidebar_price' ) ) );
		$row->add_child( $sidebar );

		$grid_col = new Container( array( 'content_width' => 'boxed', '_flex_size' => '1 1 auto' ) );
		$grid_col->add_child(
			new RawNode(
				self::raw_widget(
					'woocommerce-products',
					array(
						'columns'           => 3,
						'rows'              => 4,
						'paginate'          => 'yes',
						'allow_order'       => 'yes',
						'show_result_count' => 'yes',
					),
					'wc_products_grid'
				)
			)
		);
		$row->add_child( $grid_col );
		$doc->append( $row );

		// Pagination row (Elementor Pro woocommerce-archive-products widget
		// paints its own pagination, but we include an explicit one so custom
		// themes that disable it still get the control).
		$pagination_row = new Container( array( 'content_width' => 'boxed' ) );
		$pagination_row->add_child( new RawNode( self::raw_widget( 'woocommerce-archive-products', array(), 'wc_archive_pagination' ) ) );
		$doc->append( $pagination_row );

		return new TemplateSpec(
			self::TEMPLATE_TYPE_SHOP_ARCHIVE,
			'Elementor Forge — Shop Archive',
			$doc,
			array(
				'_elementor_template_type' => 'product-archive',
				'_elementor_conditions'    => array( 'include/product_archive' ),
			)
		);
	}

	/**
	 * Single Product template — images, title, price, add-to-cart, tabs, related.
	 */
	private static function single_product(): TemplateSpec {
		$doc = new Document( 'Elementor Forge — Single Product', 'wp-post' );

		// Breadcrumbs.
		$breadcrumbs = new Container( array( 'content_width' => 'boxed' ) );
		$breadcrumbs->add_child( new RawNode( self::raw_widget( 'woocommerce-breadcrumb', array(), 'wc_breadcrumbs' ) ) );
		$doc->append( $breadcrumbs );

		// Top row: images (left) + title/price/add-to-cart (right).
		$row = new Container(
			array(
				'content_width'  => 'boxed',
				'flex_direction' => 'row',
				'flex_wrap'      => 'wrap',
			)
		);

		$images_col = new Container( array( 'content_width' => 'boxed', '_flex_size' => '0 0 50%' ) );
		$images_col->add_child( new RawNode( self::raw_widget( 'woocommerce-product-images', array(), 'wc_product_images' ) ) );
		$row->add_child( $images_col );

		$info_col = new Container( array( 'content_width' => 'boxed', '_flex_size' => '0 0 50%' ) );
		$info_col->add_child( new RawNode( self::raw_widget( 'woocommerce-product-title', array(), 'wc_product_title' ) ) );
		$info_col->add_child( new RawNode( self::raw_widget( 'woocommerce-product-price', array(), 'wc_product_price' ) ) );
		$info_col->add_child( new RawNode( self::raw_widget( 'woocommerce-product-rating', array(), 'wc_product_rating' ) ) );
		$info_col->add_child( new RawNode( self::raw_widget( 'woocommerce-product-short-description', array(), 'wc_product_short_desc' ) ) );
		$info_col->add_child( new RawNode( self::raw_widget( 'woocommerce-product-add-to-cart', array(), 'wc_product_add_to_cart' ) ) );
		$info_col->add_child( new RawNode( self::raw_widget( 'woocommerce-product-meta', array(), 'wc_product_meta' ) ) );
		$row->add_child( $info_col );
		$doc->append( $row );

		// Tabs row.
		$tabs = new Container( array( 'content_width' => 'boxed' ) );
		$tabs->add_child( new RawNode( self::raw_widget( 'woocommerce-product-data-tabs', array(), 'wc_product_tabs' ) ) );
		$doc->append( $tabs );

		// Related products.
		$related = new Container( array( 'content_width' => 'boxed' ) );
		$related->add_child( Heading::create( 'Related products', 'h3', 'left' ) );
		$related->add_child( new RawNode( self::raw_widget( 'woocommerce-product-related', array( 'columns' => 4 ), 'wc_product_related' ) ) );
		$doc->append( $related );

		return new TemplateSpec(
			self::TEMPLATE_TYPE_SINGLE_PRODUCT,
			'Elementor Forge — Single Product',
			$doc,
			array(
				'_elementor_template_type' => 'single-product',
				'_elementor_conditions'    => array( 'include/singular/product' ),
			)
		);
	}

	/**
	 * Cart template — cart items table + totals sidebar.
	 */
	private static function cart(): TemplateSpec {
		$doc = new Document( 'Elementor Forge — Cart', 'wp-post' );

		$hero = new Container( array( 'content_width' => 'boxed' ) );
		$hero->add_child( Heading::create( 'Your Cart', 'h1', 'center' ) );
		$doc->append( $hero );

		$row = new Container(
			array(
				'content_width'  => 'boxed',
				'flex_direction' => 'row',
				'flex_wrap'      => 'wrap',
			)
		);

		$items_col = new Container( array( 'content_width' => 'boxed', '_flex_size' => '1 1 65%' ) );
		$items_col->add_child( new RawNode( self::raw_widget( 'woocommerce-cart', array( 'section' => 'cart_table' ), 'wc_cart_items' ) ) );
		$items_col->add_child( new RawNode( self::raw_widget( 'woocommerce-cart', array( 'section' => 'coupon' ), 'wc_cart_coupon' ) ) );
		$row->add_child( $items_col );

		$totals_col = new Container( array( 'content_width' => 'boxed', '_flex_size' => '1 1 35%' ) );
		$totals_col->add_child( Heading::create( 'Order summary', 'h3', 'left' ) );
		$totals_col->add_child( new RawNode( self::raw_widget( 'woocommerce-cart', array( 'section' => 'cart_totals' ), 'wc_cart_totals' ) ) );
		$totals_col->add_child( Button::create( 'Proceed to checkout', '/checkout/' ) );
		$row->add_child( $totals_col );
		$doc->append( $row );

		return new TemplateSpec(
			self::TEMPLATE_TYPE_CART,
			'Elementor Forge — Cart',
			$doc,
			array(
				'_elementor_template_type' => 'cart',
				'_elementor_conditions'    => array( 'include/cart_page' ),
			)
		);
	}

	/**
	 * Checkout template — billing/shipping form + order review sidebar.
	 */
	private static function checkout(): TemplateSpec {
		$doc = new Document( 'Elementor Forge — Checkout', 'wp-post' );

		$hero = new Container( array( 'content_width' => 'boxed' ) );
		$hero->add_child( Heading::create( 'Checkout', 'h1', 'center' ) );
		$doc->append( $hero );

		$row = new Container(
			array(
				'content_width'  => 'boxed',
				'flex_direction' => 'row',
				'flex_wrap'      => 'wrap',
			)
		);

		$form_col = new Container( array( 'content_width' => 'boxed', '_flex_size' => '1 1 60%' ) );
		$form_col->add_child( new RawNode( self::raw_widget( 'woocommerce-checkout-page', array( 'section' => 'billing' ), 'wc_checkout_billing' ) ) );
		$form_col->add_child( new RawNode( self::raw_widget( 'woocommerce-checkout-page', array( 'section' => 'shipping' ), 'wc_checkout_shipping' ) ) );
		$row->add_child( $form_col );

		$review_col = new Container( array( 'content_width' => 'boxed', '_flex_size' => '1 1 40%' ) );
		$review_col->add_child( Heading::create( 'Review your order', 'h3', 'left' ) );
		$review_col->add_child( new RawNode( self::raw_widget( 'woocommerce-checkout-page', array( 'section' => 'order_review' ), 'wc_checkout_review' ) ) );
		$review_col->add_child( new RawNode( self::raw_widget( 'woocommerce-checkout-page', array( 'section' => 'payment' ), 'wc_checkout_payment' ) ) );
		$review_col->add_child( new RawNode( self::raw_widget( 'woocommerce-checkout-page', array( 'section' => 'place_order' ), 'wc_checkout_place_order' ) ) );
		$row->add_child( $review_col );
		$doc->append( $row );

		return new TemplateSpec(
			self::TEMPLATE_TYPE_CHECKOUT,
			'Elementor Forge — Checkout',
			$doc,
			array(
				'_elementor_template_type' => 'checkout',
				'_elementor_conditions'    => array( 'include/checkout_page' ),
			)
		);
	}

	/**
	 * Build a minimal Elementor widget element array for a widget type the
	 * Forge emitter does not natively model. The widget is wrapped in a
	 * {@see RawNode} so the installer round-trips it byte-identical into
	 * `_elementor_data`. Elementor Pro's WC widgets read their own settings at
	 * render time so we only need to pass the minimum shape and any overrides
	 * we want to ship.
	 *
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private static function raw_widget( string $widget_type, array $settings, string $id ): array {
		return array(
			'id'         => $id,
			'settings'   => (object) $settings,
			'elements'   => array(),
			'isInner'    => false,
			'widgetType' => $widget_type,
			'elType'     => 'widget',
		);
	}
}
