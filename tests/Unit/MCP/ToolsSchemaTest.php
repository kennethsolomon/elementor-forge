<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP;

use ElementorForge\MCP\Tools\AddSection;
use ElementorForge\MCP\Tools\ApplyTemplate;
use ElementorForge\MCP\Tools\BulkGenerate;
use ElementorForge\MCP\Tools\ConfigureWooCommerce;
use ElementorForge\MCP\Tools\CreatePage;
use ElementorForge\MCP\Tools\ManageSlider;
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

	public function test_configure_woocommerce_schema_has_three_boolean_flags(): void {
		$schema = ConfigureWooCommerce::input_schema();
		$this->assertSame( 'object', $schema['type'] );
		$this->assertSame( 'boolean', $schema['properties']['install_templates']['type'] );
		$this->assertSame( 'boolean', $schema['properties']['apply_fibosearch']['type'] );
		$this->assertSame( 'boolean', $schema['properties']['switch_header']['type'] );
	}

	public function test_manage_slider_schema_enumerates_actions(): void {
		$schema = ManageSlider::input_schema();
		$this->assertSame( 'object', $schema['type'] );
		$this->assertContains( 'action', $schema['required'] );
		$this->assertContains( 'create_slider', $schema['properties']['action']['enum'] );
		$this->assertContains( 'list_sliders', $schema['properties']['action']['enum'] );
	}

	public function test_bulk_generate_schema_supports_matrix_and_dry_run(): void {
		$schema = BulkGenerate::input_schema();
		$this->assertArrayHasKey( 'multiply_by', $schema['properties'] );
		$this->assertArrayHasKey( 'dry_run', $schema['properties'] );
		$this->assertArrayHasKey( 'transactional', $schema['properties'] );
	}
}
