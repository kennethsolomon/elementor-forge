<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\ThemeBuilder;

use Brain\Monkey;
use ElementorForge\Elementor\Emitter\Container;
use ElementorForge\Elementor\Emitter\Document;
use ElementorForge\Elementor\Emitter\Widgets\Heading;
use ElementorForge\Elementor\ThemeBuilder\TemplateSpec;
use PHPUnit\Framework\TestCase;

final class TemplateSpecTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function make_document( string $title = 'Test Doc' ): Document {
		$doc = new Document( $title, 'page' );
		$c   = new Container( array( 'content_width' => 'boxed' ) );
		$c->add_child( Heading::create( 'Hello' ) );
		$doc->append( $c );
		return $doc;
	}

	public function test_type_returns_constructor_value(): void {
		$spec = new TemplateSpec( 'ef_location_single', 'Title', $this->make_document(), array() );

		$this->assertSame( 'ef_location_single', $spec->type() );
	}

	public function test_title_returns_constructor_value(): void {
		$spec = new TemplateSpec( 'ef_test', 'My Template Title', $this->make_document(), array() );

		$this->assertSame( 'My Template Title', $spec->title() );
	}

	public function test_document_returns_constructor_value(): void {
		$doc  = $this->make_document( 'Doc Title' );
		$spec = new TemplateSpec( 'ef_test', 'Title', $doc, array() );

		$this->assertSame( $doc, $spec->document() );
		$this->assertInstanceOf( Document::class, $spec->document() );
	}

	public function test_meta_returns_constructor_value(): void {
		$meta = array(
			'_elementor_template_type' => 'single-post',
			'_elementor_conditions'    => array( 'include/singular/ef_location' ),
		);
		$spec = new TemplateSpec( 'ef_test', 'Title', $this->make_document(), $meta );

		$this->assertSame( $meta, $spec->meta() );
	}

	public function test_meta_empty_array(): void {
		$spec = new TemplateSpec( 'ef_test', 'Title', $this->make_document(), array() );

		$this->assertSame( array(), $spec->meta() );
	}

	public function test_immutability_document_is_same_instance(): void {
		$doc  = $this->make_document();
		$spec = new TemplateSpec( 'ef_test', 'Title', $doc, array() );

		$this->assertSame( $doc, $spec->document() );
		$this->assertSame( $spec->document(), $spec->document() );
	}

	public function test_type_with_empty_string(): void {
		$spec = new TemplateSpec( '', 'Title', $this->make_document(), array() );

		$this->assertSame( '', $spec->type() );
	}

	public function test_all_accessors_independent(): void {
		$doc  = $this->make_document( 'Independent' );
		$meta = array( 'key' => 'value' );
		$spec = new TemplateSpec( 'ef_type', 'The Title', $doc, $meta );

		$this->assertSame( 'ef_type', $spec->type() );
		$this->assertSame( 'The Title', $spec->title() );
		$this->assertSame( $doc, $spec->document() );
		$this->assertSame( $meta, $spec->meta() );
	}

	public function test_document_content_survives_spec_wrapping(): void {
		$doc  = $this->make_document( 'Content Test' );
		$spec = new TemplateSpec( 'ef_ct', 'Content Test', $doc, array() );

		$arr = $spec->document()->to_array();

		$this->assertSame( 'Content Test', $arr['title'] );
		$this->assertSame( '0.4', $arr['version'] );
		$this->assertCount( 1, $arr['content'] );
	}

	public function test_meta_with_complex_conditions(): void {
		$meta = array(
			'_elementor_template_type' => 'header',
			'_elementor_conditions'    => array( 'include/general' ),
			'custom_key'               => array( 'nested' => true ),
		);
		$spec = new TemplateSpec( 'ef_header', 'Header', $this->make_document(), $meta );

		$this->assertSame( 'header', $spec->meta()['_elementor_template_type'] );
		$this->assertSame( array( 'include/general' ), $spec->meta()['_elementor_conditions'] );
		$this->assertSame( array( 'nested' => true ), $spec->meta()['custom_key'] );
	}
}
