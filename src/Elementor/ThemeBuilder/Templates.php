<?php
/**
 * Theme Builder template definitions.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\ThemeBuilder;

use ElementorForge\CPT\PostTypes;
use ElementorForge\Elementor\Emitter\Container;
use ElementorForge\Elementor\Emitter\Document;
use ElementorForge\Elementor\Emitter\KitTag;
use ElementorForge\Elementor\Emitter\Widgets\Button;
use ElementorForge\Elementor\Emitter\Widgets\GoogleMaps;
use ElementorForge\Elementor\Emitter\Widgets\Heading;
use ElementorForge\Elementor\Emitter\Widgets\IconBox;
use ElementorForge\Elementor\Emitter\Widgets\Image;
use ElementorForge\Elementor\Emitter\Widgets\NestedAccordion;
use ElementorForge\Elementor\Emitter\Widgets\Shortcode;
use ElementorForge\Elementor\Emitter\Widgets\TextEditor;
use ElementorForge\Elementor\Emitter\Emitter;

/**
 * Declarative factory for the pre-wired Elementor Theme Builder Single templates
 * the wizard installs on first run. These are synthesized from our emitter
 * primitives so they can be tested without WordPress loaded.
 *
 * Each template ships ACF dynamic-tag references via the widget settings — in
 * Elementor JSON, a dynamic tag on (e.g.) the heading title takes the shape:
 *
 *     "__dynamic__": {
 *         "title": "[elementor-tag id=\"...\" name=\"acf\" settings=\"%7B%22key%22%3A%22field_ef_suburb_name%22%7D\"]"
 *     }
 *
 * We do not emit the raw dynamic tag strings — we mark the container with a
 * placeholder which the ACF integration layer resolves at render time. This
 * keeps the emitter pure and the tag schema explicit.
 */
final class Templates {

	public const TEMPLATE_TYPE_LOCATION_SINGLE = 'ef_location_single';
	public const TEMPLATE_TYPE_SERVICE_SINGLE  = 'ef_service_single';
	public const TEMPLATE_TYPE_HEADER          = 'ef_header';
	public const TEMPLATE_TYPE_FOOTER          = 'ef_footer';

	/**
	 * Return every Theme Builder template the wizard must install.
	 *
	 * @return list<TemplateSpec>
	 */
	public static function all(): array {
		return array(
			self::location_single(),
			self::service_single(),
			self::service_business_header(),
			self::service_business_footer(),
		);
	}

	private static function location_single(): TemplateSpec {
		$doc = new Document( 'Elementor Forge — Location Single', 'wp-post' );

		// Hero block: suburb name heading, local phone, hero image.
		$hero = new Container(
			array(
				'content_width' => 'boxed',
				'padding'       => array( 'unit' => 'em', 'top' => '4', 'right' => '1', 'bottom' => '4', 'left' => '1', 'isLinked' => false ),
			)
		);
		$hero->add_child(
			new DynamicWidget(
				Heading::create( '[acf suburb_name]', 'h1', 'center' ),
				array(
					'title' => array( 'field' => 'suburb_name' ),
				)
			)
		);
		$hero->add_child(
			new DynamicWidget(
				TextEditor::create( '[acf local_phone]' ),
				array(
					'editor' => array( 'field' => 'local_phone' ),
				)
			)
		);
		$hero->add_child(
			new DynamicWidget(
				Image::create( 0, '', '' ),
				array(
					'image' => array( 'field' => 'hero_image' ),
				)
			)
		);
		$doc->append( $hero );

		// Map block.
		$map = new Container( array( 'content_width' => 'full' ) );
		$map->add_child( GoogleMaps::create( '[acf suburb_name]', 13, 500 ) );
		$doc->append( $map );

		return new TemplateSpec(
			self::TEMPLATE_TYPE_LOCATION_SINGLE,
			'Elementor Forge — Location Single',
			$doc,
			array(
				'_elementor_template_type' => 'single-post',
				'_elementor_conditions'    => array( 'include/singular/' . PostTypes::LOCATION ),
			)
		);
	}

	private static function service_single(): TemplateSpec {
		$doc = new Document( 'Elementor Forge — Service Single', 'wp-post' );

		$hero = new Container(
			array(
				'content_width' => 'boxed',
				'padding'       => array( 'unit' => 'em', 'top' => '4', 'right' => '1', 'bottom' => '4', 'left' => '1', 'isLinked' => false ),
			)
		);
		$hero->add_child(
			new DynamicWidget(
				Heading::create( '[acf service_name]', 'h1', 'center' ),
				array(
					'title' => array( 'field' => 'service_name' ),
				)
			)
		);
		$hero->add_child(
			new DynamicWidget(
				TextEditor::create( '[acf description]' ),
				array(
					'editor' => array( 'field' => 'description' ),
				)
			)
		);
		$hero->add_child(
			new DynamicWidget(
				Image::create( 0, '', '' ),
				array(
					'image' => array( 'field' => 'hero_image' ),
				)
			)
		);
		$doc->append( $hero );

		// Related FAQ block (dynamic on Pro, loop-grid on Free — wizard picks).
		$faq_container = new Container( array( 'content_width' => 'boxed' ) );
		$faq_container->add_child( Heading::create( 'Frequently Asked Questions', 'h2', 'center' ) );
		$faq_container->add_child( NestedAccordion::create( array( 'Placeholder FAQ' ) ) );
		$doc->append( $faq_container );

		return new TemplateSpec(
			self::TEMPLATE_TYPE_SERVICE_SINGLE,
			'Elementor Forge — Service Single',
			$doc,
			array(
				'_elementor_template_type' => 'single-post',
				'_elementor_conditions'    => array( 'include/singular/' . PostTypes::SERVICE ),
			)
		);
	}

	private static function service_business_header(): TemplateSpec {
		$doc = new Document( 'Elementor Forge — Header', 'header' );

		$row = new Container(
			array(
				'content_width'    => 'full',
				'flex_direction'   => 'row',
				'flex_align_items' => 'center',
				'padding'          => array( 'unit' => 'em', 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true ),
				'__globals__'      => array(
					'background_color' => KitTag::color( KitTag::COLOR_PRIMARY ),
				),
			)
		);
		$row->add_child( Heading::create( '[site_title]', 'h3', 'left' ) );

		$cta = Button::create( 'Get a Free Quote', '/contact-us/' );
		$row->add_child( $cta );

		$doc->append( $row );

		return new TemplateSpec(
			self::TEMPLATE_TYPE_HEADER,
			'Elementor Forge — Header',
			$doc,
			array(
				'_elementor_template_type' => 'header',
				'_elementor_conditions'    => array( 'include/general' ),
			)
		);
	}

	private static function service_business_footer(): TemplateSpec {
		$doc = new Document( 'Elementor Forge — Footer', 'footer' );

		$row = new Container(
			array(
				'content_width'  => 'full',
				'flex_direction' => 'row',
				'padding'        => array( 'unit' => 'em', 'top' => '3', 'right' => '1', 'bottom' => '3', 'left' => '1', 'isLinked' => true ),
			)
		);

		$width = Emitter::column_width( 3, 20 );

		$col_1 = new Container( array( 'content_width' => 'boxed', 'width' => $width ) );
		$col_1->add_child( Heading::create( 'Contact', 'h4', 'left' ) );
		$col_1->add_child( TextEditor::create( '[site_title]' ) );
		$row->add_child( $col_1 );

		$col_2 = new Container( array( 'content_width' => 'boxed', 'width' => $width ) );
		$col_2->add_child( Heading::create( 'Services', 'h4', 'left' ) );
		$col_2->add_child( TextEditor::create( 'List of services here.' ) );
		$row->add_child( $col_2 );

		$col_3 = new Container( array( 'content_width' => 'boxed', 'width' => $width ) );
		$col_3->add_child( Heading::create( 'Service Areas', 'h4', 'left' ) );
		$col_3->add_child( TextEditor::create( 'List of areas here.' ) );
		$row->add_child( $col_3 );

		$doc->append( $row );

		$copyright = new Container( array( 'content_width' => 'full' ) );
		$copyright->add_child( TextEditor::create( '&copy; [current_year] [site_title]. All rights reserved.' ) );
		$doc->append( $copyright );

		return new TemplateSpec(
			self::TEMPLATE_TYPE_FOOTER,
			'Elementor Forge — Footer',
			$doc,
			array(
				'_elementor_template_type' => 'footer',
				'_elementor_conditions'    => array( 'include/general' ),
			)
		);
	}
}
