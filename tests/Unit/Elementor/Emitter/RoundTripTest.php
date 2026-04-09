<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Emitter;

use ElementorForge\Elementor\Emitter\Document;
use ElementorForge\Elementor\Emitter\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Byte-level round-trip invariants for the Elementor v0.4 Parser + Emitter.
 *
 * This test parses each of the three canonical SDM sample exports into a
 * {@see Document}, re-serialises via {@see Document::to_array()}, and then
 * runs a structural fingerprint comparison against the original decoded
 * payload.
 *
 * ### Normalisation rules
 *
 * True byte-identity is NOT achievable because:
 *
 *   1. Elementor's exports wrap the document in PHP-order keys that happen
 *      to match Forge's emitter order, but the nested `settings` objects
 *      sometimes ship with trailing `null` values that the parser keeps but
 *      which `json_encode` could reorder relative to a direct `(object)` cast.
 *   2. The `page_settings` field is serialised as a JSON object (`{}`) when
 *      empty, and as an associative object when populated — both shapes the
 *      emitter preserves via `(object)` cast, but empty-array vs empty-object
 *      comparisons must tolerate either shape.
 *   3. Elementor occasionally emits `"isInner": false` via a plain boolean
 *      and PHP's `json_decode` preserves it, so this field must compare as a
 *      bool not a string.
 *
 * The normaliser runs both sides through the same transform (decode → walk
 * → canonicalise → re-encode) so any difference left after normalisation is
 * a real drop or mutation, not a cosmetic variance.
 *
 * ### What a failure means
 *
 *   - A missing widget type → Parser lost an element on recursion
 *   - A container nesting level collapsed → Container::add_child miscounted
 *   - A responsive breakpoint key disappeared → settings array was mutated
 *   - A spacing unit changed → to_array cast broke the object shape
 *
 * Any of those are load-bearing Phase 1 regressions and must fail this test.
 */
final class RoundTripTest extends TestCase {

	/**
	 * @return array<string, array{0:string, 1:int}>
	 */
	public static function sampleProvider(): array {
		return array(
			'home-page'     => array( 'home-page.json', 10 ),
			'service-page'  => array( 'service-page.json', 10 ),
			'location-page' => array( 'location-page.json', 10 ),
		);
	}

	/**
	 * @dataProvider sampleProvider
	 */
	public function test_parser_emitter_preserves_document_structure( string $filename, int $expected_sections ): void {
		$original = $this->load_sample( $filename );

		$parser   = new Parser( false );
		$doc      = $parser->parse_document( $original );
		$emitted  = $doc->to_array();

		$this->assertCount( $expected_sections, $doc->content() );

		// Re-run both payloads through a canonical JSON encode+decode so that
		// `(object)` casts and associative arrays normalise to the same shape.
		$left  = self::canonicalise( $original );
		$right = self::canonicalise( $emitted );

		$this->assertSame(
			$left['version'] ?? null,
			$right['version'] ?? null,
			'Schema version must survive round trip.'
		);
		$this->assertSame(
			$left['title'] ?? null,
			$right['title'] ?? null,
			'Document title must survive round trip.'
		);
		$this->assertSame(
			$left['type'] ?? null,
			$right['type'] ?? null,
			'Document type must survive round trip.'
		);
		$this->assertEquals(
			$left['page_settings'] ?? array(),
			$right['page_settings'] ?? array(),
			'page_settings must survive round trip.'
		);

		$this->assertSame(
			count( $left['content'] ?? array() ),
			count( $right['content'] ?? array() ),
			'Top-level section count must match.'
		);

		foreach ( ( $left['content'] ?? array() ) as $index => $left_section ) {
			$right_section = ( $right['content'] ?? array() )[ $index ] ?? null;
			$this->assertNotNull( $right_section, "Section $index missing after round trip." );
			$this->assertEquals(
				$left_section,
				$right_section,
				"Section $index structure mismatch (widgets, nesting, settings, or isInner differ)."
			);
		}
	}

	/**
	 * Strip-mode round trip: every ucaddon_* widget must drop from the output
	 * AND the remaining tree must re-emit without orphan containers.
	 */
	public function test_strip_mode_drops_ucaddon_and_still_round_trips(): void {
		foreach ( array( 'home-page.json', 'service-page.json', 'location-page.json' ) as $filename ) {
			$original = $this->load_sample( $filename );

			$parser  = new Parser( true );
			$doc     = $parser->parse_document( $original );
			$emitted = $doc->to_array();

			$this->assertSame(
				0,
				$this->count_ucaddon_recursive( $emitted['content'] ?? array() ),
				"ucaddon_* widget leaked through strip mode for $filename."
			);

			// Re-parse emitted output to verify tree is still structurally valid
			// (no orphaned elements, no NULL nodes, no broken container children).
			$reparsed = $parser->parse_document(
				array(
					'content'       => $emitted['content'] ?? array(),
					'title'         => $emitted['title'] ?? '',
					'type'          => $emitted['type'] ?? 'page',
					'page_settings' => (array) ( $emitted['page_settings'] ?? array() ),
				)
			);
			$this->assertInstanceOf( Document::class, $reparsed );
			$this->assertSame(
				count( $doc->content() ),
				count( $reparsed->content() ),
				"Strip-mode re-parse lost a top-level section in $filename."
			);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function load_sample( string $filename ): array {
		$path = __DIR__ . '/../../../fixtures/' . $filename;
		if ( ! is_file( $path ) ) {
			$path = dirname( __DIR__, 4 ) . '/../../Team Inbox/SDM/' . $filename;
		}
		$this->assertFileExists( $path, 'Sample JSON not found at ' . $path );
		$contents = file_get_contents( $path );
		$this->assertIsString( $contents );
		$decoded = json_decode( $contents, true );
		$this->assertIsArray( $decoded );
		/** @var array<string, mixed> */
		return $decoded;
	}

	/**
	 * Canonicalise a payload by round-tripping through json_encode/json_decode.
	 * This forces `(object)` casts to land as associative arrays and empty
	 * objects to become empty arrays on both sides, so the subsequent
	 * assertEquals compares apples to apples.
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private static function canonicalise( array $data ): array {
		$json = json_encode( $data );
		if ( false === $json ) {
			return $data;
		}
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : $data;
	}

	/**
	 * @param array<int, mixed> $elements
	 */
	private function count_ucaddon_recursive( array $elements ): int {
		$count = 0;
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			$widget_type = isset( $el['widgetType'] ) && is_string( $el['widgetType'] ) ? $el['widgetType'] : '';
			if ( 0 === strpos( $widget_type, 'ucaddon_' ) ) {
				++$count;
			}
			if ( isset( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$count += $this->count_ucaddon_recursive( $el['elements'] );
			}
		}
		return $count;
	}
}
