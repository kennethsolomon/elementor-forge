<?php
/**
 * Tests for SlideTemplate::minimal().
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\SmartSlider;

use ElementorForge\SmartSlider\SlideTemplate;
use PHPUnit\Framework\TestCase;

final class SlideTemplateTest extends TestCase {

	public function test_minimal_with_heading_only_emits_one_layer(): void {
		$json = SlideTemplate::minimal( 'My Heading' );
		$decoded = json_decode( $json, true );

		self::assertIsArray( $decoded );
		self::assertCount( 1, $decoded );
		self::assertSame( 'content', $decoded[0]['type'] );
		self::assertCount( 1, $decoded[0]['children'] );
		self::assertSame( 'heading', $decoded[0]['children'][0]['item']['type'] );
		self::assertSame( 'My Heading', $decoded[0]['children'][0]['item']['values']['heading'] );
	}

	public function test_minimal_with_heading_and_body_emits_two_layers(): void {
		$json = SlideTemplate::minimal( 'Title', 'Body text' );
		$decoded = json_decode( $json, true );

		self::assertCount( 2, $decoded[0]['children'] );
		self::assertSame( 'heading', $decoded[0]['children'][0]['item']['type'] );
		self::assertSame( 'text', $decoded[0]['children'][1]['item']['type'] );
		self::assertSame( 'Body text', $decoded[0]['children'][1]['item']['values']['content'] );
	}

	public function test_minimal_with_empty_body_skips_text_layer(): void {
		$json = SlideTemplate::minimal( 'Title', '' );
		$decoded = json_decode( $json, true );

		self::assertCount( 1, $decoded[0]['children'] );
	}

	public function test_minimal_includes_responsive_visibility_keys(): void {
		$json = SlideTemplate::minimal( 'Title' );
		$decoded = json_decode( $json, true );

		// Required keys for the front-end renderer.
		self::assertSame( 1, $decoded[0]['desktopportrait'] );
		self::assertSame( 1, $decoded[0]['tabletportrait'] );
		self::assertSame( 1, $decoded[0]['mobileportrait'] );
	}
}
