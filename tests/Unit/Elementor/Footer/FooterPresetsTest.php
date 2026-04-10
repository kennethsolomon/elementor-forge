<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Footer;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\Elementor\Footer\FooterPresets;
use ElementorForge\Elementor\ThemeBuilder\TemplateSpec;
use ElementorForge\Elementor\ThemeBuilder\Templates;
use PHPUnit\Framework\TestCase;

final class FooterPresetsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		// FooterPresets::copyright_text calls wp_kses_post when overriding.
		Functions\when( 'wp_kses_post' )->returnArg();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}


	public function test_presets_constant_contains_four_values(): void {
		$this->assertCount( 4, FooterPresets::PRESETS );
		$this->assertContains( 'simple', FooterPresets::PRESETS );
		$this->assertContains( 'multi_column', FooterPresets::PRESETS );
		$this->assertContains( 'minimal', FooterPresets::PRESETS );
		$this->assertContains( 'newsletter', FooterPresets::PRESETS );
	}


	public function test_build_simple_returns_template_spec_with_footer_type(): void {
		$spec = FooterPresets::build( 'simple' );

		$this->assertInstanceOf( TemplateSpec::class, $spec );
		$this->assertSame( Templates::TEMPLATE_TYPE_FOOTER, $spec->type() );
	}

	public function test_build_simple_has_elementor_conditions_include_general(): void {
		$spec = FooterPresets::build( 'simple' );
		$meta = $spec->meta();

		$this->assertContains( 'include/general', $meta['_elementor_conditions'] );
	}

	public function test_build_simple_has_ef_footer_variant_simple(): void {
		$spec = FooterPresets::build( 'simple' );
		$meta = $spec->meta();

		$this->assertSame( 'simple', $meta['_ef_footer_variant'] );
	}


	public function test_build_multi_column_has_three_column_row_and_copyright(): void {
		$spec    = FooterPresets::build( 'multi_column' );
		$content = $spec->document()->content();

		// multi_column: columns row + copyright row = 2 top-level containers.
		$this->assertCount( 2, $content );
	}

	public function test_build_multi_column_contains_about_links_contact_columns(): void {
		$spec    = FooterPresets::build( 'multi_column' );
		$encoded = (string) json_encode( $spec->document()->to_array() );

		$this->assertStringContainsString( 'About', $encoded );
		$this->assertStringContainsString( 'Quick Links', $encoded );
		$this->assertStringContainsString( 'Contact', $encoded );
	}


	public function test_build_minimal_has_single_centered_row(): void {
		$spec    = FooterPresets::build( 'minimal' );
		$content = $spec->document()->content();

		$this->assertCount( 1, $content );
	}

	public function test_build_minimal_has_centered_justify_content(): void {
		$spec    = FooterPresets::build( 'minimal' );
		$encoded = (string) json_encode( $spec->document()->to_array() );

		$this->assertStringContainsString( 'center', $encoded );
	}


	public function test_build_newsletter_has_cta_columns_and_copyright(): void {
		$spec    = FooterPresets::build( 'newsletter' );
		$content = $spec->document()->content();

		// newsletter: CTA + links row + copyright = 3 top-level containers.
		$this->assertCount( 3, $content );
	}

	public function test_build_newsletter_contains_subscribe_cta(): void {
		$spec    = FooterPresets::build( 'newsletter' );
		$encoded = (string) json_encode( $spec->document()->to_array() );

		$this->assertStringContainsString( 'Stay Updated', $encoded );
		$this->assertStringContainsString( 'Subscribe', $encoded );
	}


	public function test_build_with_copyright_text_override_replaces_default(): void {
		$spec    = FooterPresets::build( 'simple', array( 'copyright_text' => 'Custom Copyright 2026' ) );
		$encoded = (string) json_encode( $spec->document()->to_array() );

		$this->assertStringContainsString( 'Custom Copyright 2026', $encoded );
	}

	public function test_build_with_background_color_override_applies_color(): void {
		$spec    = FooterPresets::build( 'simple', array( 'background_color' => '#222222' ) );
		$encoded = (string) json_encode( $spec->document()->to_array() );

		$this->assertStringContainsString( '#222222', $encoded );
	}


	public function test_build_with_invalid_preset_falls_through_to_simple(): void {
		$spec = FooterPresets::build( 'nonexistent' );

		$this->assertInstanceOf( TemplateSpec::class, $spec );
		$this->assertSame( Templates::TEMPLATE_TYPE_FOOTER, $spec->type() );
	}

	// ── all presets have _elementor_conditions ─────────────────────────────────

	/**
	 * @dataProvider presetProvider
	 */
	public function test_all_presets_have_elementor_conditions_include_general( string $preset ): void {
		$spec = FooterPresets::build( $preset );
		$meta = $spec->meta();

		$this->assertArrayHasKey( '_elementor_conditions', $meta );
		$this->assertContains( 'include/general', $meta['_elementor_conditions'] );
	}

	/**
	 * @return list<array{string}>
	 */
	public static function presetProvider(): array {
		return array_map( static fn ( $p ) => array( $p ), FooterPresets::PRESETS );
	}
}
