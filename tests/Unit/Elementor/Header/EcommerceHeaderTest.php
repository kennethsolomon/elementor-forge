<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Header;

use ElementorForge\Elementor\Header\EcommerceHeader;
use ElementorForge\Elementor\ThemeBuilder\Templates as BaseTemplates;
use PHPUnit\Framework\TestCase;

final class EcommerceHeaderTest extends TestCase {

	public function test_spec_returns_header_template_type(): void {
		$spec = EcommerceHeader::spec();
		$this->assertSame( BaseTemplates::TEMPLATE_TYPE_HEADER, $spec->type() );
	}

	public function test_spec_has_header_display_conditions(): void {
		$spec = EcommerceHeader::spec();
		$meta = $spec->meta();

		$this->assertSame( 'header', $meta['_elementor_template_type'] );
		$this->assertContains( 'include/general', $meta['_elementor_conditions'] );
		$this->assertSame( 'ecommerce', $meta['_ef_header_variant'] );
	}

	public function test_document_contains_all_four_layout_slots(): void {
		$encoded = (string) json_encode( EcommerceHeader::spec()->document()->to_array() );

		$this->assertStringContainsString( 'ecommerce_header_desktop_top', $encoded );
		$this->assertStringContainsString( 'ecommerce_header_desktop_nav', $encoded );
		$this->assertStringContainsString( 'ecommerce_header_mobile_top', $encoded );
		$this->assertStringContainsString( 'ecommerce_header_mobile_tab_bar', $encoded );
	}

	public function test_desktop_row_embeds_fibosearch_widget(): void {
		$encoded = (string) json_encode( EcommerceHeader::spec()->document()->to_array() );
		$this->assertStringContainsString( 'fibosearch', $encoded );
	}

	public function test_mobile_bottom_tab_bar_has_five_tabs(): void {
		$encoded = (string) json_encode( EcommerceHeader::spec()->document()->to_array() );
		// Labels live on the button widgets inside the fixed bottom tab bar.
		$this->assertStringContainsString( '"text":"Store"', $encoded );
		$this->assertStringContainsString( '"text":"Search"', $encoded );
		$this->assertStringContainsString( '"text":"Wishlist"', $encoded );
		$this->assertStringContainsString( '"text":"Account"', $encoded );
		$this->assertStringContainsString( '"text":"Categories"', $encoded );
	}

	public function test_mobile_bottom_tab_bar_uses_fixed_positioning_css(): void {
		$encoded = (string) json_encode( EcommerceHeader::spec()->document()->to_array() );
		$this->assertStringContainsString( 'position: fixed', $encoded );
		$this->assertStringContainsString( 'bottom: 0', $encoded );
	}

	public function test_desktop_row_contains_woocommerce_cart_widget(): void {
		$encoded = (string) json_encode( EcommerceHeader::spec()->document()->to_array() );
		$this->assertStringContainsString( 'woocommerce-menu-cart', $encoded );
	}

	public function test_document_version_is_v04(): void {
		$doc = EcommerceHeader::spec()->document()->to_array();
		$this->assertSame( '0.4', $doc['version'] );
	}
}
