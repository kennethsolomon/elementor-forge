<?php
/**
 * Footer preset factory.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Footer;

use ElementorForge\Elementor\Emitter\Container;
use ElementorForge\Elementor\Emitter\Document;
use ElementorForge\Elementor\Emitter\Emitter;
use ElementorForge\Elementor\Emitter\Widgets\Button;
use ElementorForge\Elementor\Emitter\Widgets\Heading;
use ElementorForge\Elementor\Emitter\Widgets\TextEditor;
use ElementorForge\Elementor\Header\HeaderBuilder;
use ElementorForge\Elementor\ThemeBuilder\TemplateSpec;
use ElementorForge\Elementor\ThemeBuilder\Templates;

/**
 * Factory for footer presets. Each builds a TemplateSpec for the Theme Builder.
 *
 * Available presets:
 *   - simple:       Single row with copyright text
 *   - multi_column: 3-4 column layout with sections + copyright
 *   - minimal:      Centered text-only footer
 *   - newsletter:   CTA section + multi-column links + copyright
 */
final class FooterPresets {

	public const PRESETS = array( 'simple', 'multi_column', 'minimal', 'newsletter' );

	/**
	 * @param string               $preset    One of self::PRESETS.
	 * @param array<string, mixed> $overrides Override keys: columns, background_color, copyright_text.
	 */
	public static function build( string $preset, array $overrides = array() ): TemplateSpec {
		switch ( $preset ) {
			case 'multi_column':
				$doc = self::multi_column( $overrides );
				break;
			case 'minimal':
				$doc = self::minimal( $overrides );
				break;
			case 'newsletter':
				$doc = self::newsletter( $overrides );
				break;
			case 'simple':
			default:
				$doc = self::simple( $overrides );
				break;
		}

		return new TemplateSpec(
			Templates::TEMPLATE_TYPE_FOOTER,
			'Elementor Forge — Footer (' . ucfirst( str_replace( '_', ' ', $preset ) ) . ')',
			$doc,
			array(
				'_elementor_template_type' => 'footer',
				'_elementor_conditions'    => array( 'include/general' ),
				'_ef_footer_variant'       => $preset,
			)
		);
	}

	/**
	 * Simple footer: single-row copyright.
	 *
	 * @param array<string, mixed> $overrides
	 */
	private static function simple( array $overrides ): Document {
		$doc       = new Document( 'Elementor Forge — Simple Footer', 'footer' );
		$copyright = self::copyright_text( $overrides );
		$bg        = self::resolve_bg( $overrides );

		$settings = array(
			'content_width'        => 'full',
			'flex_justify_content' => 'center',
			'padding'              => array( 'unit' => 'em', 'top' => '2', 'right' => '1', 'bottom' => '2', 'left' => '1', 'isLinked' => false ),
		);
		if ( '' !== $bg ) {
			$settings['background_background'] = 'classic';
			$settings['background_color']      = $bg;
		}

		$row = new Container( $settings );
		$row->add_child( TextEditor::create( $copyright ) );
		$doc->append( $row );

		return $doc;
	}

	/**
	 * Multi-column footer: 3 columns (About/Links/Contact) + copyright row.
	 *
	 * @param array<string, mixed> $overrides
	 */
	private static function multi_column( array $overrides ): Document {
		$doc       = new Document( 'Elementor Forge — Multi-Column Footer', 'footer' );
		$copyright = self::copyright_text( $overrides );
		$bg        = self::resolve_bg( $overrides );

		$row_settings = array(
			'content_width'  => 'full',
			'flex_direction' => 'row',
			'padding'        => array( 'unit' => 'em', 'top' => '3', 'right' => '1.5', 'bottom' => '3', 'left' => '1.5', 'isLinked' => false ),
		);
		if ( '' !== $bg ) {
			$row_settings['background_background'] = 'classic';
			$row_settings['background_color']      = $bg;
		}

		$row   = new Container( $row_settings );
		$width = Emitter::column_width( 3, 20 );

		// Column 1: About.
		$col1 = new Container( array( 'content_width' => 'boxed', 'width' => $width ) );
		$col1->add_child( Heading::create( 'About', 'h4', 'left' ) );
		$col1->add_child( TextEditor::create( '[site_title] — Your trusted partner.' ) );
		$row->add_child( $col1 );

		// Column 2: Quick Links.
		$col2 = new Container( array( 'content_width' => 'boxed', 'width' => $width ) );
		$col2->add_child( Heading::create( 'Quick Links', 'h4', 'left' ) );
		$col2->add_child( TextEditor::create( '<ul><li><a href="/">Home</a></li><li><a href="/about/">About</a></li><li><a href="/services/">Services</a></li><li><a href="/contact/">Contact</a></li></ul>' ) );
		$row->add_child( $col2 );

		// Column 3: Contact.
		$col3 = new Container( array( 'content_width' => 'boxed', 'width' => $width ) );
		$col3->add_child( Heading::create( 'Contact', 'h4', 'left' ) );
		$col3->add_child( TextEditor::create( 'Email: info@example.com<br>Phone: (03) 1234 5678' ) );
		$row->add_child( $col3 );

		$doc->append( $row );

		// Copyright row.
		$cr = new Container(
			array(
				'content_width'        => 'full',
				'flex_justify_content' => 'center',
				'padding'              => array( 'unit' => 'em', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ),
			)
		);
		$cr->add_child( TextEditor::create( $copyright ) );
		$doc->append( $cr );

		return $doc;
	}

	/**
	 * Minimal footer: centered text only.
	 *
	 * @param array<string, mixed> $overrides
	 */
	private static function minimal( array $overrides ): Document {
		$doc       = new Document( 'Elementor Forge — Minimal Footer', 'footer' );
		$copyright = self::copyright_text( $overrides );

		$row = new Container(
			array(
				'content_width'        => 'full',
				'flex_justify_content' => 'center',
				'flex_align_items'     => 'center',
				'padding'              => array( 'unit' => 'em', 'top' => '1.5', 'right' => '1', 'bottom' => '1.5', 'left' => '1', 'isLinked' => false ),
			)
		);
		$row->add_child( TextEditor::create( $copyright ) );
		$doc->append( $row );

		return $doc;
	}

	/**
	 * Newsletter footer: CTA row + multi-column links + copyright.
	 *
	 * @param array<string, mixed> $overrides
	 */
	private static function newsletter( array $overrides ): Document {
		$doc       = new Document( 'Elementor Forge — Newsletter Footer', 'footer' );
		$copyright = self::copyright_text( $overrides );
		$bg        = self::resolve_bg( $overrides );

		// CTA row.
		$cta_settings = array(
			'content_width'        => 'full',
			'flex_direction'       => 'column',
			'flex_align_items'     => 'center',
			'flex_justify_content' => 'center',
			'padding'              => array( 'unit' => 'em', 'top' => '3', 'right' => '1', 'bottom' => '3', 'left' => '1', 'isLinked' => false ),
		);
		if ( '' !== $bg ) {
			$cta_settings['background_background'] = 'classic';
			$cta_settings['background_color']      = $bg;
		}

		$cta = new Container( $cta_settings );
		$cta->add_child( Heading::create( 'Stay Updated', 'h3', 'center' ) );
		$cta->add_child( TextEditor::create( 'Subscribe to our newsletter for the latest updates.' ) );
		$cta->add_child( Button::create( 'Subscribe', '#newsletter' ) );
		$doc->append( $cta );

		// Links row — same as multi_column.
		$row   = new Container( array( 'content_width' => 'full', 'flex_direction' => 'row', 'padding' => array( 'unit' => 'em', 'top' => '2', 'right' => '1.5', 'bottom' => '2', 'left' => '1.5', 'isLinked' => false ) ) );
		$width = Emitter::column_width( 3, 20 );

		$col1 = new Container( array( 'content_width' => 'boxed', 'width' => $width ) );
		$col1->add_child( Heading::create( 'Company', 'h4', 'left' ) );
		$col1->add_child( TextEditor::create( '<ul><li><a href="/about/">About</a></li><li><a href="/careers/">Careers</a></li><li><a href="/blog/">Blog</a></li></ul>' ) );
		$row->add_child( $col1 );

		$col2 = new Container( array( 'content_width' => 'boxed', 'width' => $width ) );
		$col2->add_child( Heading::create( 'Support', 'h4', 'left' ) );
		$col2->add_child( TextEditor::create( '<ul><li><a href="/help/">Help Center</a></li><li><a href="/contact/">Contact</a></li><li><a href="/faq/">FAQ</a></li></ul>' ) );
		$row->add_child( $col2 );

		$col3 = new Container( array( 'content_width' => 'boxed', 'width' => $width ) );
		$col3->add_child( Heading::create( 'Legal', 'h4', 'left' ) );
		$col3->add_child( TextEditor::create( '<ul><li><a href="/privacy/">Privacy</a></li><li><a href="/terms/">Terms</a></li></ul>' ) );
		$row->add_child( $col3 );

		$doc->append( $row );

		// Copyright.
		$cr = new Container( array( 'content_width' => 'full', 'flex_justify_content' => 'center', 'padding' => array( 'unit' => 'em', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ) ) );
		$cr->add_child( TextEditor::create( $copyright ) );
		$doc->append( $cr );

		return $doc;
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private static function copyright_text( array $overrides ): string {
		if ( isset( $overrides['copyright_text'] ) && is_string( $overrides['copyright_text'] ) ) {
			return wp_kses_post( $overrides['copyright_text'] );
		}
		return '&copy; [current_year] [site_title]. All rights reserved.';
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private static function resolve_bg( array $overrides ): string {
		if ( isset( $overrides['background_color'] ) && is_string( $overrides['background_color'] ) ) {
			return $overrides['background_color'];
		}
		return '';
	}
}
