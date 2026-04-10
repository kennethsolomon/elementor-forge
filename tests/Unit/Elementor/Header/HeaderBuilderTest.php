<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Header;

use Brain\Monkey;
use ElementorForge\Elementor\Emitter\Container;
use ElementorForge\Elementor\Emitter\RawNode;
use ElementorForge\Elementor\Emitter\Widgets\Button;
use ElementorForge\Elementor\Emitter\Widgets\Heading;
use ElementorForge\Elementor\Header\HeaderBuilder;
use PHPUnit\Framework\TestCase;

final class HeaderBuilderTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}


	public function test_build_row_creates_container_with_full_width_and_row_direction(): void {
		$row      = HeaderBuilder::build_row( array( 'items' => array() ) );
		$settings = $row->get_settings();

		$this->assertInstanceOf( Container::class, $row );
		$this->assertSame( 'full', $settings['content_width'] );
		$this->assertSame( 'row', $settings['flex_direction'] );
	}


	public function test_build_row_resolves_logo_to_heading_widget(): void {
		$row     = HeaderBuilder::build_row( array( 'items' => array( 'logo' ) ) );
		$encoded = (string) json_encode( $row->to_array() );

		$this->assertStringContainsString( 'heading', $encoded );
		$this->assertStringContainsString( '[site_title]', $encoded );
	}

	public function test_build_row_resolves_nav_to_raw_node_nav_menu_widget(): void {
		$row     = HeaderBuilder::build_row( array( 'items' => array( 'nav' ) ) );
		$encoded = (string) json_encode( $row->to_array() );

		$this->assertStringContainsString( 'nav-menu', $encoded );
	}

	public function test_build_row_resolves_button_with_label_to_button_widget(): void {
		$row     = HeaderBuilder::build_row( array( 'items' => array( 'button:Get a Free Quote' ) ) );
		$encoded = (string) json_encode( $row->to_array() );

		$this->assertStringContainsString( 'button', $encoded );
		$this->assertStringContainsString( 'Get a Free Quote', $encoded );
	}

	public function test_build_row_resolves_search_to_raw_node_search_form(): void {
		$row     = HeaderBuilder::build_row( array( 'items' => array( 'search' ) ) );
		$encoded = (string) json_encode( $row->to_array() );

		$this->assertStringContainsString( 'search-form', $encoded );
	}

	public function test_build_row_resolves_cart_to_raw_node_woocommerce_menu_cart(): void {
		$row     = HeaderBuilder::build_row( array( 'items' => array( 'cart' ) ) );
		$encoded = (string) json_encode( $row->to_array() );

		$this->assertStringContainsString( 'woocommerce-menu-cart', $encoded );
	}


	public function test_build_row_with_hide_mobile_true_sets_hidden_mobile_and_tablet(): void {
		$row      = HeaderBuilder::build_row( array( 'items' => array(), 'hide_mobile' => true ) );
		$settings = $row->get_settings();

		$this->assertSame( 'hidden-mobile', $settings['hide_mobile'] );
		$this->assertSame( 'hidden-tablet', $settings['hide_tablet'] );
	}

	public function test_build_row_with_hide_desktop_true_sets_hidden_desktop_laptop_widescreen(): void {
		$row      = HeaderBuilder::build_row( array( 'items' => array(), 'hide_desktop' => true ) );
		$settings = $row->get_settings();

		$this->assertSame( 'hidden-desktop', $settings['hide_desktop'] );
		$this->assertSame( 'hidden-laptop', $settings['hide_laptop'] );
		$this->assertSame( 'hidden-widescreen', $settings['hide_widescreen'] );
	}

	public function test_build_row_without_hide_flags_does_not_set_visibility_keys(): void {
		$row      = HeaderBuilder::build_row( array( 'items' => array() ) );
		$settings = $row->get_settings();

		$this->assertArrayNotHasKey( 'hide_mobile', $settings );
		$this->assertArrayNotHasKey( 'hide_desktop', $settings );
	}


	public function test_build_row_with_background_sets_background_background_and_color(): void {
		$row      = HeaderBuilder::build_row(
			array( 'items' => array(), 'background' => '#ffffff' )
		);
		$settings = $row->get_settings();

		$this->assertSame( 'classic', $settings['background_background'] );
		$this->assertSame( '#ffffff', $settings['background_color'] );
	}

	public function test_build_row_without_background_does_not_set_background_keys(): void {
		$row      = HeaderBuilder::build_row( array( 'items' => array() ) );
		$settings = $row->get_settings();

		$this->assertArrayNotHasKey( 'background_background', $settings );
		$this->assertArrayNotHasKey( 'background_color', $settings );
	}


	public function test_build_row_with_unknown_item_produces_no_children(): void {
		$row     = HeaderBuilder::build_row( array( 'items' => array( 'unknown_widget_xyz' ) ) );
		$encoded = (string) json_encode( $row->to_array() );

		// The only elements in the row should be empty (null resolves are skipped).
		$data = json_decode( $encoded, true );
		$this->assertSame( array(), $data['elements'] );
	}


	public function test_build_row_with_multiple_items_wraps_each_in_boxed_container(): void {
		$row     = HeaderBuilder::build_row(
			array( 'items' => array( 'logo', 'nav', 'button:CTA' ) )
		);
		$encoded = (string) json_encode( $row->to_array() );

		// With 3 items, each gets a boxed column wrapper.
		$this->assertStringContainsString( 'boxed', $encoded );
	}


	public function test_apply_sticky_returns_same_container_when_not_enabled(): void {
		$header = new Container( array( 'content_width' => 'full' ) );
		$result = HeaderBuilder::apply_sticky( $header, array( 'enabled' => false ) );

		$this->assertSame( $header, $result );
	}

	public function test_apply_sticky_returns_new_container_with_sticky_top_when_enabled(): void {
		$header = new Container( array( 'content_width' => 'full' ) );
		$result = HeaderBuilder::apply_sticky( $header, array( 'enabled' => true ) );

		$settings = $result->get_settings();
		$this->assertSame( 'top', $settings['sticky'] );
		$this->assertContains( 'desktop', $settings['sticky_on'] );
	}
}
