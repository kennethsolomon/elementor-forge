<?php
/**
 * Reusable section template library.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor;

use ElementorForge\Elementor\Emitter\Container;
use ElementorForge\Elementor\Emitter\Document;
use ElementorForge\Elementor\Emitter\Widgets\Button;
use ElementorForge\Elementor\Emitter\Widgets\GoogleMaps;
use ElementorForge\Elementor\Emitter\Widgets\Heading;
use ElementorForge\Elementor\Emitter\Widgets\IconBox;
use ElementorForge\Elementor\Emitter\Widgets\Image;
use ElementorForge\Elementor\Emitter\Widgets\NestedAccordion;
use ElementorForge\Elementor\Emitter\Widgets\Shortcode;
use ElementorForge\Elementor\Emitter\Widgets\TextEditor;
use ElementorForge\Elementor\ThemeBuilder\Installer;
use ElementorForge\Elementor\ThemeBuilder\TemplateSpec;

/**
 * Twelve reusable Elementor section templates the wizard installs as
 * `elementor_library` posts so the user can drop them into any page.
 *
 * These are the service-business layout building blocks Kenneth uses on every
 * SDM page:
 *
 *   hero, trust strip, service cards, FAQ, CTA, testimonials, process steps,
 *   service area list, location cards, contact form, map+hours, footer CTA.
 *
 * Every section is built from the same emitter primitives the MCP tools use,
 * so edits to the underlying widget schemas automatically propagate here too.
 */
final class SectionLibrary {

	public const META_SECTION_SLUG = '_ef_section_slug';

	/**
	 * @return list<TemplateSpec>
	 */
	public static function all(): array {
		return array(
			self::spec( 'hero', 'Hero', self::hero() ),
			self::spec( 'trust_strip', 'Trust Strip', self::trust_strip() ),
			self::spec( 'service_cards', 'Service Cards', self::service_cards() ),
			self::spec( 'faq', 'FAQ', self::faq() ),
			self::spec( 'cta', 'CTA', self::cta() ),
			self::spec( 'testimonials', 'Testimonials', self::testimonials() ),
			self::spec( 'process_steps', 'Process Steps', self::process_steps() ),
			self::spec( 'service_area_list', 'Service Area List', self::service_area_list() ),
			self::spec( 'location_cards', 'Location Cards', self::location_cards() ),
			self::spec( 'contact_form', 'Contact Form', self::contact_form() ),
			self::spec( 'map_hours', 'Map and Hours', self::map_hours() ),
			self::spec( 'footer_cta', 'Footer CTA', self::footer_cta() ),
		);
	}

	private static function spec( string $slug, string $title, Document $document ): TemplateSpec {
		return new TemplateSpec(
			'ef_section_' . $slug,
			'Elementor Forge — ' . $title,
			$document,
			array(
				'_elementor_template_type'    => 'section',
				Installer::META_TEMPLATE_TYPE => 'ef_section_' . $slug,
				self::META_SECTION_SLUG       => $slug,
			)
		);
	}

	private static function hero(): Document {
		$doc = new Document( 'Hero', 'section' );
		$c   = new Container(
			array(
				'content_width'        => 'boxed',
				'padding'              => array( 'unit' => 'em', 'top' => '5', 'right' => '1', 'bottom' => '5', 'left' => '1', 'isLinked' => false ),
				'flex_direction'       => 'column',
				'flex_justify_content' => 'center',
			)
		);
		$c->add_child( Heading::create( 'Trusted Local Experts', 'h1', 'center' ) );
		$c->add_child( TextEditor::create( 'Professional service backed by decades of experience.' ) );
		$c->add_child( Button::create( 'Get a Free Quote', '/contact-us/' ) );
		$doc->append( $c );
		return $doc;
	}

	private static function trust_strip(): Document {
		$doc = new Document( 'Trust Strip', 'section' );
		$c   = new Container( array( 'content_width' => 'full', 'flex_direction' => 'row', 'flex_justify_content' => 'space-around' ) );
		$width = Emitter\Emitter::column_width( 4 );
		foreach ( array( '20+ Years Experience', 'Fully Insured', 'Satisfaction Guaranteed', '5-Star Rated' ) as $text ) {
			$col = new Container( array( 'content_width' => 'boxed', 'width' => $width ) );
			$col->add_child( Heading::create( $text, 'h4', 'center' ) );
			$c->add_child( $col );
		}
		$doc->append( $c );
		return $doc;
	}

	private static function service_cards(): Document {
		$doc = new Document( 'Service Cards', 'section' );
		$c   = new Container( array( 'content_width' => 'boxed', 'flex_direction' => 'row', 'flex_wrap' => 'wrap' ) );
		$width = Emitter\Emitter::column_width( 4, 20 );
		for ( $i = 1; $i <= 4; $i++ ) {
			$card = new Container( array( 'content_width' => 'boxed', 'width' => $width ) );
			$card->add_child( IconBox::create( 'Service ' . $i, 'Describe the service here.' ) );
			$c->add_child( $card );
		}
		$doc->append( $c );
		return $doc;
	}

	private static function faq(): Document {
		$doc = new Document( 'FAQ', 'section' );
		$c   = new Container( array( 'content_width' => 'boxed' ) );
		$c->add_child( Heading::create( 'Frequently Asked Questions', 'h2', 'center' ) );
		$c->add_child(
			NestedAccordion::create(
				array(
					'How quickly can you start?',
					'Are you fully insured?',
					'Do you offer free quotes?',
					'What areas do you service?',
				)
			)
		);
		$doc->append( $c );
		return $doc;
	}

	private static function cta(): Document {
		$doc = new Document( 'CTA', 'section' );
		$c   = new Container(
			array(
				'content_width'        => 'boxed',
				'padding'              => array( 'unit' => 'em', 'top' => '4', 'right' => '1', 'bottom' => '4', 'left' => '1', 'isLinked' => false ),
				'flex_justify_content' => 'center',
			)
		);
		$c->add_child( Heading::create( 'Ready to Get Started?', 'h2', 'center' ) );
		$c->add_child( Button::create( 'Request a Free Quote', '/contact-us/' ) );
		$doc->append( $c );
		return $doc;
	}

	private static function testimonials(): Document {
		$doc = new Document( 'Testimonials', 'section' );
		$c   = new Container( array( 'content_width' => 'boxed', 'flex_direction' => 'row' ) );
		$width = Emitter\Emitter::column_width( 3, 20 );
		for ( $i = 1; $i <= 3; $i++ ) {
			$col = new Container( array( 'content_width' => 'boxed', 'width' => $width ) );
			$col->add_child( TextEditor::create( '"Excellent work and great communication throughout." — Customer ' . $i ) );
			$c->add_child( $col );
		}
		$doc->append( $c );
		return $doc;
	}

	private static function process_steps(): Document {
		$doc = new Document( 'Process Steps', 'section' );
		$c   = new Container( array( 'content_width' => 'boxed', 'flex_direction' => 'row' ) );
		$width = Emitter\Emitter::column_width( 4, 20 );
		foreach ( array( '1. Request Quote', '2. On-Site Survey', '3. Approve Scope', '4. We Deliver' ) as $step ) {
			$col = new Container( array( 'content_width' => 'boxed', 'width' => $width ) );
			$col->add_child( IconBox::create( $step, '' ) );
			$c->add_child( $col );
		}
		$doc->append( $c );
		return $doc;
	}

	private static function service_area_list(): Document {
		$doc = new Document( 'Service Area List', 'section' );
		$c   = new Container( array( 'content_width' => 'boxed' ) );
		$c->add_child( Heading::create( 'Service Areas', 'h2', 'center' ) );
		$c->add_child( TextEditor::create( 'We proudly serve the following suburbs.' ) );
		$doc->append( $c );
		return $doc;
	}

	private static function location_cards(): Document {
		$doc = new Document( 'Location Cards', 'section' );
		$c   = new Container( array( 'content_width' => 'boxed', 'flex_direction' => 'row', 'flex_wrap' => 'wrap' ) );
		$width = Emitter\Emitter::column_width( 4, 20 );
		for ( $i = 1; $i <= 4; $i++ ) {
			$col = new Container( array( 'content_width' => 'boxed', 'width' => $width ) );
			$col->add_child( Image::create( 0, '', 'Location ' . $i ) );
			$col->add_child( Heading::create( 'Location ' . $i, 'h4', 'center' ) );
			$c->add_child( $col );
		}
		$doc->append( $c );
		return $doc;
	}

	private static function contact_form(): Document {
		$doc = new Document( 'Contact Form', 'section' );
		$c   = new Container( array( 'content_width' => 'boxed' ) );
		$c->add_child( Heading::create( 'Contact Us', 'h2', 'center' ) );
		$c->add_child( Shortcode::create( '[contact-form-7 id="123"]' ) );
		$doc->append( $c );
		return $doc;
	}

	private static function map_hours(): Document {
		$doc = new Document( 'Map and Hours', 'section' );
		$c   = new Container( array( 'content_width' => 'boxed', 'flex_direction' => 'row' ) );
		$width = Emitter\Emitter::column_width( 2, 20 );
		$col_1 = new Container( array( 'content_width' => 'boxed', 'width' => $width ) );
		$col_1->add_child( GoogleMaps::create( 'Melbourne VIC', 13, 400 ) );
		$c->add_child( $col_1 );
		$col_2 = new Container( array( 'content_width' => 'boxed', 'width' => $width ) );
		$col_2->add_child( Heading::create( 'Hours of Operation', 'h3', 'left' ) );
		$col_2->add_child( TextEditor::create( 'Mon-Fri: 8am - 6pm<br>Sat: 9am - 4pm<br>Sun: Closed' ) );
		$c->add_child( $col_2 );
		$doc->append( $c );
		return $doc;
	}

	private static function footer_cta(): Document {
		$doc = new Document( 'Footer CTA', 'section' );
		$c   = new Container(
			array(
				'content_width'        => 'full',
				'padding'              => array( 'unit' => 'em', 'top' => '3', 'right' => '1', 'bottom' => '3', 'left' => '1', 'isLinked' => false ),
				'flex_justify_content' => 'center',
			)
		);
		$c->add_child( Heading::create( 'Book Your Free Quote Today', 'h3', 'center' ) );
		$c->add_child( Button::create( 'Contact Us', '/contact-us/' ) );
		$doc->append( $c );
		return $doc;
	}
}
