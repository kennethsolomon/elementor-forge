<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Emitter;

use ElementorForge\Elementor\Emitter\Widgets\Button;
use ElementorForge\Elementor\Emitter\Widgets\Divider;
use ElementorForge\Elementor\Emitter\Widgets\GoogleMaps;
use ElementorForge\Elementor\Emitter\Widgets\Heading;
use ElementorForge\Elementor\Emitter\Widgets\Icon;
use ElementorForge\Elementor\Emitter\Widgets\IconBox;
use ElementorForge\Elementor\Emitter\Widgets\Image;
use ElementorForge\Elementor\Emitter\Widgets\ImageCarousel;
use ElementorForge\Elementor\Emitter\Widgets\NestedAccordion;
use ElementorForge\Elementor\Emitter\Widgets\NestedCarousel;
use ElementorForge\Elementor\Emitter\Widgets\Shortcode;
use ElementorForge\Elementor\Emitter\Widgets\Spacer;
use ElementorForge\Elementor\Emitter\Widgets\TemplateRef;
use ElementorForge\Elementor\Emitter\Widgets\TextEditor;
use PHPUnit\Framework\TestCase;

final class WidgetsTest extends TestCase {

	public function test_every_widget_type_serializes_with_elType_widget(): void {
		$widgets = array(
			Heading::create( 'H' ),
			TextEditor::create( 'T' ),
			Button::create( 'B', '/url' ),
			Divider::create(),
			Spacer::create( 20 ),
			IconBox::create( 'ic', 'desc' ),
			Icon::create(),
			Image::create( 42, 'https://example.test/a.jpg', 'alt' ),
			ImageCarousel::create( array( array( 'id' => 1, 'url' => 'x' ) ) ),
			NestedCarousel::create( 2 ),
			NestedAccordion::create( array( 'a', 'b' ) ),
			GoogleMaps::create( 'Melbourne VIC' ),
			TemplateRef::create( 3220 ),
			Shortcode::create( '[contact-form-7 id="1"]' ),
		);

		foreach ( $widgets as $widget ) {
			$arr = $widget->to_array();
			$this->assertSame( 'widget', $arr['elType'] );
			$this->assertIsString( $arr['widgetType'] );
			$this->assertNotEmpty( $arr['widgetType'] );
			$this->assertIsArray( $arr['elements'] );
			$this->assertArrayHasKey( 'id', $arr );
			$this->assertArrayHasKey( 'settings', $arr );
		}
	}

	public function test_widget_types_are_distinct(): void {
		$types = array(
			Heading::create( 'x' )->widget_type(),
			TextEditor::create( 'x' )->widget_type(),
			Button::create( 'x' )->widget_type(),
			Divider::create()->widget_type(),
			Spacer::create()->widget_type(),
			IconBox::create( 'x' )->widget_type(),
			Icon::create()->widget_type(),
			Image::create( 1, 'x' )->widget_type(),
			ImageCarousel::create( array() )->widget_type(),
			NestedCarousel::create()->widget_type(),
			NestedAccordion::create()->widget_type(),
			GoogleMaps::create( 'x' )->widget_type(),
			TemplateRef::create( 1 )->widget_type(),
			Shortcode::create( 'x' )->widget_type(),
		);
		$this->assertCount( count( $types ), array_unique( $types ), 'Duplicate widget types found: ' . implode( ',', $types ) );
	}
}
