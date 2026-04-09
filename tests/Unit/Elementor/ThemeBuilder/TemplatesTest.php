<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\ThemeBuilder;

use ElementorForge\Elementor\ThemeBuilder\Templates;
use ElementorForge\Elementor\ThemeBuilder\TemplateSpec;
use PHPUnit\Framework\TestCase;

final class TemplatesTest extends TestCase {

	public function test_all_returns_four_theme_builder_templates(): void {
		$templates = Templates::all();
		$this->assertCount( 4, $templates );

		$types = array_map( static fn ( TemplateSpec $t ): string => $t->type(), $templates );
		$this->assertContains( Templates::TEMPLATE_TYPE_LOCATION_SINGLE, $types );
		$this->assertContains( Templates::TEMPLATE_TYPE_SERVICE_SINGLE, $types );
		$this->assertContains( Templates::TEMPLATE_TYPE_HEADER, $types );
		$this->assertContains( Templates::TEMPLATE_TYPE_FOOTER, $types );
	}

	public function test_every_template_produces_a_non_empty_document(): void {
		foreach ( Templates::all() as $template ) {
			$doc = $template->document()->to_array();
			$this->assertIsArray( $doc['content'] );
			$this->assertNotEmpty( $doc['content'] );
			$this->assertSame( '0.4', $doc['version'] );
		}
	}

	public function test_single_templates_carry_display_conditions(): void {
		foreach ( Templates::all() as $template ) {
			if ( in_array( $template->type(), array( Templates::TEMPLATE_TYPE_LOCATION_SINGLE, Templates::TEMPLATE_TYPE_SERVICE_SINGLE ), true ) ) {
				$meta = $template->meta();
				$this->assertArrayHasKey( '_elementor_conditions', $meta );
				$this->assertNotEmpty( $meta['_elementor_conditions'] );
			}
		}
	}
}
