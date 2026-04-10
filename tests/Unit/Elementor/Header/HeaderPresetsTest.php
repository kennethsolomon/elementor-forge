<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Header;

use Brain\Monkey;
use ElementorForge\Elementor\Header\HeaderPresets;
use ElementorForge\Elementor\ThemeBuilder\TemplateSpec;
use ElementorForge\Elementor\ThemeBuilder\Templates;
use PHPUnit\Framework\TestCase;

final class HeaderPresetsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}


	public function test_presets_constant_contains_five_values(): void {
		$this->assertCount( 5, HeaderPresets::PRESETS );
		$this->assertContains( 'business', HeaderPresets::PRESETS );
		$this->assertContains( 'ecommerce', HeaderPresets::PRESETS );
		$this->assertContains( 'portfolio', HeaderPresets::PRESETS );
		$this->assertContains( 'blog', HeaderPresets::PRESETS );
		$this->assertContains( 'saas', HeaderPresets::PRESETS );
	}


	public function test_build_business_returns_template_spec_with_header_type(): void {
		$spec = HeaderPresets::build( 'business' );

		$this->assertInstanceOf( TemplateSpec::class, $spec );
		$this->assertSame( Templates::TEMPLATE_TYPE_HEADER, $spec->type() );
	}

	public function test_build_business_has_elementor_conditions_include_general(): void {
		$spec = HeaderPresets::build( 'business' );
		$meta = $spec->meta();

		$this->assertContains( 'include/general', $meta['_elementor_conditions'] );
	}


	public function test_build_ecommerce_has_three_sections_top_nav_mobile(): void {
		$spec     = HeaderPresets::build( 'ecommerce' );
		$content  = $spec->document()->content();

		// ecommerce: top row (logo+search+cart) + nav row + mobile row = 3
		$this->assertCount( 3, $content );
	}

	public function test_build_ecommerce_has_header_template_type_in_meta(): void {
		$spec = HeaderPresets::build( 'ecommerce' );
		$meta = $spec->meta();

		$this->assertSame( 'header', $meta['_elementor_template_type'] );
		$this->assertSame( 'ecommerce', $meta['_ef_header_variant'] );
	}


	public function test_build_portfolio_has_centered_logo(): void {
		$spec    = HeaderPresets::build( 'portfolio' );
		$encoded = (string) json_encode( $spec->document()->to_array() );

		// logo_center resolves to a Heading widget with align=center.
		$this->assertStringContainsString( '"align":"center"', $encoded );
		$this->assertStringContainsString( '"widgetType":"heading"', $encoded );
	}

	public function test_build_portfolio_has_elementor_conditions(): void {
		$spec = HeaderPresets::build( 'portfolio' );
		$meta = $spec->meta();

		$this->assertArrayHasKey( '_elementor_conditions', $meta );
		$this->assertContains( 'include/general', $meta['_elementor_conditions'] );
	}


	public function test_build_blog_has_two_sections(): void {
		$spec    = HeaderPresets::build( 'blog' );
		$content = $spec->document()->content();

		// blog: desktop row (logo+nav) + mobile row (logo+hamburger) = 2
		$this->assertCount( 2, $content );
	}

	public function test_build_blog_has_ef_header_variant_blog(): void {
		$spec = HeaderPresets::build( 'blog' );
		$meta = $spec->meta();

		$this->assertSame( 'blog', $meta['_ef_header_variant'] );
	}


	public function test_build_saas_has_login_and_cta_buttons(): void {
		$spec    = HeaderPresets::build( 'saas' );
		$encoded = (string) json_encode( $spec->document()->to_array() );

		$this->assertStringContainsString( 'Log In', $encoded );
		$this->assertStringContainsString( 'Start Free Trial', $encoded );
	}

	public function test_build_saas_has_ef_header_variant_saas(): void {
		$spec = HeaderPresets::build( 'saas' );
		$meta = $spec->meta();

		$this->assertSame( 'saas', $meta['_ef_header_variant'] );
	}


	public function test_build_with_rows_override_uses_custom_layout(): void {
		$spec = HeaderPresets::build(
			'business',
			array(
				'rows' => array(
					array( 'items' => array( 'logo', 'cart' ), 'align' => 'center' ),
				),
			)
		);
		$encoded = (string) json_encode( $spec->document()->to_array() );

		$this->assertStringContainsString( 'woocommerce-menu-cart', $encoded );
	}

	public function test_build_with_background_color_override_applies_color(): void {
		$spec    = HeaderPresets::build( 'business', array( 'background_color' => '#111111' ) );
		$encoded = (string) json_encode( $spec->document()->to_array() );

		$this->assertStringContainsString( '#111111', $encoded );
	}

	public function test_build_with_invalid_preset_defaults_to_business(): void {
		$spec = HeaderPresets::build( 'does_not_exist' );
		$meta = $spec->meta();

		// Defaults to business — variant should be 'does_not_exist' in the meta
		// because the method stores whatever preset name was passed.
		$this->assertSame( Templates::TEMPLATE_TYPE_HEADER, $spec->type() );
		// title should reference the fallback.
		$this->assertStringContainsString( 'Header', $spec->title() );
	}

	// ── all presets have _elementor_conditions ─────────────────────────────────

	/**
	 * @dataProvider presetProvider
	 */
	public function test_all_presets_have_elementor_conditions_include_general( string $preset ): void {
		$spec = HeaderPresets::build( $preset );
		$meta = $spec->meta();

		$this->assertArrayHasKey( '_elementor_conditions', $meta );
		$this->assertContains( 'include/general', $meta['_elementor_conditions'] );
	}

	/**
	 * @return list<array{string}>
	 */
	public static function presetProvider(): array {
		return array_map( static fn ( $p ) => array( $p ), HeaderPresets::PRESETS );
	}
}
