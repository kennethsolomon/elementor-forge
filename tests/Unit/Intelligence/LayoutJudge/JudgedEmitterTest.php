<?php
/**
 * Tests for the JudgedEmitter — Phase 1 paths preserved, judged paths added.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Intelligence\LayoutJudge;

use ElementorForge\Elementor\Emitter\ContentDoc;
use ElementorForge\Elementor\Emitter\Emitter;
use ElementorForge\Intelligence\LayoutJudge\JudgedEmitter;
use ElementorForge\Intelligence\LayoutJudge\LayoutJudge;
use PHPUnit\Framework\TestCase;

final class JudgedEmitterTest extends TestCase {

	private JudgedEmitter $judged;

	protected function setUp(): void {
		parent::setUp();
		$this->judged = new JudgedEmitter( new Emitter(), LayoutJudge::with_default_rules() );
	}

	public function test_phase_1_heading_block_takes_base_path_unchanged(): void {
		$container = $this->judged->emit_block( array( 'type' => 'heading', 'text' => 'Hello' ) );

		self::assertNotNull( $container );
		$tree = $container->to_array();
		self::assertSame( 'container', $tree['elType'] );
		self::assertNotEmpty( $tree['elements'] );
		self::assertSame( 'heading', $tree['elements'][0]['widgetType'] );
	}

	public function test_phase_1_faq_block_takes_base_path_unchanged(): void {
		$container = $this->judged->emit_block(
			array(
				'type'  => 'faq',
				'items' => array( array( 'question' => 'q1' ), array( 'question' => 'q2' ) ),
			)
		);

		self::assertNotNull( $container );
		$tree = $container->to_array();
		self::assertSame( 'nested-accordion', $tree['elements'][0]['widgetType'] );
	}

	public function test_unknown_bullets_block_routes_through_judge_to_text_editor(): void {
		$container = $this->judged->emit_block(
			array(
				'type'  => 'bullets',
				'items' => array( 'fast', 'cheap' ),
			)
		);

		self::assertNotNull( $container );
		$tree = $container->to_array();
		self::assertSame( 'text-editor', $tree['elements'][0]['widgetType'] );
	}

	public function test_unknown_services_block_judges_to_icon_box_grid(): void {
		$container = $this->judged->emit_block(
			array(
				'type'  => 'services',
				'items' => array(
					array( 'name' => 'Plumbing', 'icon' => 'wrench', 'description' => 'pipes' ),
					array( 'name' => 'Electric', 'icon' => 'bolt', 'description' => 'wires' ),
					array( 'name' => 'Carpentry', 'icon' => 'hammer', 'description' => 'wood' ),
				),
			)
		);

		self::assertNotNull( $container );
		$tree = $container->to_array();
		// Each item becomes a child container with icon-box.
		self::assertCount( 3, $tree['elements'] );
		self::assertSame( 'container', $tree['elements'][0]['elType'] );
	}

	public function test_unknown_gallery_block_judges_to_image_carousel(): void {
		$container = $this->judged->emit_block(
			array(
				'type'  => 'gallery',
				'items' => array(
					array( 'image' => 'a.jpg' ),
					array( 'image' => 'b.jpg' ),
				),
			)
		);

		self::assertNotNull( $container );
		$tree = $container->to_array();
		self::assertSame( 'image-carousel', $tree['elements'][0]['widgetType'] );
	}

	public function test_emit_doc_appends_one_container_per_block(): void {
		$doc = new ContentDoc(
			'Test page',
			array(
				array( 'type' => 'heading', 'text' => 'A' ),
				array( 'type' => 'bullets', 'items' => array( 'one' ) ),
			)
		);

		$document = $this->judged->emit( $doc );
		$tree     = $document->to_array();

		self::assertSame( 'Test page', $tree['title'] );
		self::assertCount( 2, $tree['content'] );
	}

	public function test_long_faq_extracted_as_accordion_headings(): void {
		$container = $this->judged->emit_block(
			array(
				'type'  => 'faq',
				'items' => array(
					array( 'question' => 'How long?' ),
					array( 'question' => 'How much?' ),
				),
			)
		);

		self::assertNotNull( $container );
		$tree = $container->to_array();
		// Phase 1 base emitter handles faq — verify it produced an accordion.
		self::assertSame( 'nested-accordion', $tree['elements'][0]['widgetType'] );
	}
}
