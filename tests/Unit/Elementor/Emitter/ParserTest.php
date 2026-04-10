<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Emitter;

use ElementorForge\Elementor\Emitter\Parser;
use ElementorForge\Elementor\Emitter\RawNode;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase {

	public function test_parses_sample_home_page_json(): void {
		$data   = $this->load_sample( 'home-page.json' );
		$parser = new Parser( false );
		$doc    = $parser->parse_document( $data );

		$this->assertSame( 'Home', $doc->title() );
		$this->assertSame( 'page', $doc->type() );
		$this->assertCount( 10, $doc->content() );
	}

	public function test_parses_sample_service_page_json(): void {
		$data   = $this->load_sample( 'service-page.json' );
		$parser = new Parser( false );
		$doc    = $parser->parse_document( $data );

		$this->assertSame( 'Service Page Sample', $doc->title() );
		$this->assertCount( 10, $doc->content() );
	}

	public function test_parses_sample_location_page_json(): void {
		$data   = $this->load_sample( 'location-page.json' );
		$parser = new Parser( false );
		$doc    = $parser->parse_document( $data );

		$this->assertSame( 'Location Sample Page', $doc->title() );
		$this->assertCount( 10, $doc->content() );
	}

	public function test_preserves_ucaddon_widgets_when_preserve_mode(): void {
		$data   = $this->load_sample( 'home-page.json' );
		$parser = new Parser( false );
		$doc    = $parser->parse_document( $data );

		$this->assertGreaterThan( 0, $this->count_ucaddon( $doc->content() ) );
	}

	public function test_strips_ucaddon_widgets_when_strip_mode(): void {
		$data        = $this->load_sample( 'home-page.json' );
		$parser_keep = new Parser( false );
		$keep        = $parser_keep->parse_document( $data );
		$keep_count  = $this->count_ucaddon( $keep->content() );

		$parser_strip = new Parser( true );
		$stripped     = $parser_strip->parse_document( $data );
		$strip_count  = $this->count_ucaddon( $stripped->content() );

		$this->assertGreaterThan( 0, $keep_count );
		$this->assertSame( 0, $strip_count );
	}

	public function test_rejects_document_without_content_key(): void {
		$this->expectException( \InvalidArgumentException::class );
		( new Parser( false ) )->parse_document( array( 'title' => 'broken' ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function load_sample( string $filename ): array {
		$path = __DIR__ . '/../../../fixtures/' . $filename;
		if ( ! is_file( $path ) ) {
			// Fall back to the vault fixture copy.
			$path = dirname( __DIR__, 4 ) . '/../../Team Inbox/SDM/' . $filename;
		}
		$this->assertFileExists( $path, 'Sample JSON not found at ' . $path );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local test fixture, not a remote URL.
		$contents = file_get_contents( $path );
		$this->assertIsString( $contents );
		$decoded = json_decode( $contents, true );
		$this->assertIsArray( $decoded );
		/** @var array<string, mixed> */
		return $decoded;
	}

	/**
	 * @param list<\ElementorForge\Elementor\Emitter\Node> $nodes
	 */
	private function count_ucaddon( array $nodes ): int {
		$count = 0;
		foreach ( $nodes as $node ) {
			$arr = $node->to_array();
			if ( isset( $arr['widgetType'] ) && is_string( $arr['widgetType'] ) && 0 === strpos( $arr['widgetType'], 'ucaddon_' ) ) {
				++$count;
			}
			if ( isset( $arr['elements'] ) && is_array( $arr['elements'] ) ) {
				$count += $this->count_ucaddon_raw( $arr['elements'] );
			}
		}
		return $count;
	}

	/**
	 * @param array<int, array<string, mixed>> $elements
	 */
	private function count_ucaddon_raw( array $elements ): int {
		$count = 0;
		foreach ( $elements as $el ) {
			if ( isset( $el['widgetType'] ) && is_string( $el['widgetType'] ) && 0 === strpos( $el['widgetType'], 'ucaddon_' ) ) {
				++$count;
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$count += $this->count_ucaddon_raw( $el['elements'] );
			}
		}
		return $count;
	}
}
