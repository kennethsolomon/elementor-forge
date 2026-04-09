<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor;

use ElementorForge\Elementor\SectionLibrary;
use PHPUnit\Framework\TestCase;

final class SectionLibraryTest extends TestCase {

	public function test_library_has_twelve_sections(): void {
		$all = SectionLibrary::all();
		$this->assertCount( 12, $all );
	}

	public function test_every_section_is_tagged_as_section(): void {
		foreach ( SectionLibrary::all() as $spec ) {
			$meta = $spec->meta();
			$this->assertSame( 'section', $meta['_elementor_template_type'] );
			$this->assertArrayHasKey( SectionLibrary::META_SECTION_SLUG, $meta );
		}
	}

	public function test_every_section_document_has_content(): void {
		foreach ( SectionLibrary::all() as $spec ) {
			$out = $spec->document()->to_array();
			$this->assertNotEmpty( $out['content'], $spec->type() . ' produced empty content' );
		}
	}
}
