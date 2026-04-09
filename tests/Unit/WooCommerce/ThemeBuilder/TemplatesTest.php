<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\WooCommerce\ThemeBuilder;

use ElementorForge\Elementor\ThemeBuilder\TemplateSpec;
use ElementorForge\WooCommerce\ThemeBuilder\Templates;
use PHPUnit\Framework\TestCase;

final class TemplatesTest extends TestCase {

	public function test_all_returns_four_wc_templates(): void {
		$templates = Templates::all();
		$this->assertCount( 4, $templates );

		$types = array_map( static fn ( TemplateSpec $t ): string => $t->type(), $templates );
		$this->assertContains( Templates::TEMPLATE_TYPE_SHOP_ARCHIVE, $types );
		$this->assertContains( Templates::TEMPLATE_TYPE_SINGLE_PRODUCT, $types );
		$this->assertContains( Templates::TEMPLATE_TYPE_CART, $types );
		$this->assertContains( Templates::TEMPLATE_TYPE_CHECKOUT, $types );
	}

	public function test_every_template_produces_a_non_empty_document(): void {
		foreach ( Templates::all() as $template ) {
			$doc = $template->document()->to_array();
			$this->assertIsArray( $doc['content'] );
			$this->assertNotEmpty( $doc['content'] );
			$this->assertSame( '0.4', $doc['version'] );
		}
	}

	public function test_every_template_carries_display_conditions(): void {
		$expected = array(
			Templates::TEMPLATE_TYPE_SHOP_ARCHIVE   => 'include/product_archive',
			Templates::TEMPLATE_TYPE_SINGLE_PRODUCT => 'include/singular/product',
			Templates::TEMPLATE_TYPE_CART           => 'include/cart_page',
			Templates::TEMPLATE_TYPE_CHECKOUT       => 'include/checkout_page',
		);

		foreach ( Templates::all() as $template ) {
			$meta = $template->meta();
			$this->assertArrayHasKey( '_elementor_conditions', $meta );
			$this->assertArrayHasKey( '_elementor_template_type', $meta );
			$this->assertIsArray( $meta['_elementor_conditions'] );
			$this->assertNotEmpty( $meta['_elementor_conditions'] );
			$this->assertContains( $expected[ $template->type() ], $meta['_elementor_conditions'] );
		}
	}

	public function test_template_types_are_wc_prefixed(): void {
		foreach ( Templates::all() as $template ) {
			$this->assertStringStartsWith( 'ef_wc_', $template->type() );
		}
	}

	public function test_single_product_contains_product_images_widget(): void {
		$templates = Templates::all();
		$single    = null;
		foreach ( $templates as $template ) {
			if ( Templates::TEMPLATE_TYPE_SINGLE_PRODUCT === $template->type() ) {
				$single = $template;
				break;
			}
		}
		$this->assertNotNull( $single );

		$json = $single->document()->to_array();
		$encoded = (string) json_encode( $json );
		$this->assertStringContainsString( 'woocommerce-product-images', $encoded );
		$this->assertStringContainsString( 'woocommerce-product-title', $encoded );
		$this->assertStringContainsString( 'woocommerce-product-add-to-cart', $encoded );
	}

	public function test_shop_archive_contains_products_widget_and_fibosearch(): void {
		$templates = Templates::all();
		$shop      = null;
		foreach ( $templates as $template ) {
			if ( Templates::TEMPLATE_TYPE_SHOP_ARCHIVE === $template->type() ) {
				$shop = $template;
				break;
			}
		}
		$this->assertNotNull( $shop );

		$encoded = (string) json_encode( $shop->document()->to_array() );
		$this->assertStringContainsString( 'woocommerce-products', $encoded );
		$this->assertStringContainsString( 'fibosearch', $encoded );
	}
}
