<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP;

use ElementorForge\MCP\Tools\AddSection;
use ElementorForge\MCP\Tools\ApplyTemplate;
use ElementorForge\MCP\Tools\BulkGenerate;
use ElementorForge\MCP\Tools\CreatePage;
use PHPUnit\Framework\TestCase;

final class ToolsSchemaTest extends TestCase {

	public function test_create_page_schema_has_required_properties(): void {
		$schema = CreatePage::input_schema();
		$this->assertSame( 'object', $schema['type'] );
		$this->assertContains( 'title', $schema['required'] );
		$this->assertContains( 'content_doc', $schema['required'] );
	}

	public function test_add_section_schema_requires_page_id(): void {
		$schema = AddSection::input_schema();
		$this->assertContains( 'page_id', $schema['required'] );
	}

	public function test_apply_template_schema_enum_restricts_cpt(): void {
		$schema = ApplyTemplate::input_schema();
		$enum   = $schema['properties']['cpt']['enum'];
		$this->assertContains( 'ef_location', $enum );
		$this->assertContains( 'ef_service', $enum );
	}

	public function test_bulk_generate_requires_at_least_one_item(): void {
		$schema = BulkGenerate::input_schema();
		$this->assertSame( 1, $schema['properties']['items']['minItems'] );
	}
}
