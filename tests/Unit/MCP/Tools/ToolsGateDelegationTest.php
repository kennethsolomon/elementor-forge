<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\MCP\Tools\AddSection;
use ElementorForge\MCP\Tools\ApplyTemplate;
use ElementorForge\MCP\Tools\BulkGenerate;
use ElementorForge\MCP\Tools\ConfigureWooCommerce;
use ElementorForge\MCP\Tools\CreatePage;
use ElementorForge\MCP\Tools\ManageSlider;
use ElementorForge\Safety\Gate;
use ElementorForge\Safety\Mode;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Regression: every MCP write tool must delegate to {@see Gate::check()}
 * FIRST thing in execute() and return the resulting WP_Error unchanged when
 * the gate rejects. Failure to call the gate = a safety bypass, so this is
 * the critical defence-in-depth layer that stops future tool authors from
 * accidentally wiring a new tool that skips the safety mode.
 *
 * For each tool we assert two properties under page_only + read_only modes:
 *
 *   1. The tool returns a WP_Error whose code matches a Gate::ERR_* constant.
 *   2. The Gate-returned error surfaces as the FIRST failure — i.e. the tool
 *      does not leak through to its own input validation, which would indicate
 *      the gate call is in the wrong place.
 */
final class ToolsGateDelegationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'absint' )->alias( static fn ( $v ): int => abs( (int) $v ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof \WP_Error );
		Functions\when( 'wp_suspend_cache_addition' )->justReturn( false );
		Functions\when( 'wp_defer_term_counting' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'current_user_can' )->justReturn( true );
		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function stubMode( string $mode, string $allowlist_csv = '' ): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'acf_mode'                => 'free',
				'ucaddon_shim'            => 'preserve',
				'mcp_server'              => 'enabled',
				'header_pattern'          => 'service_business',
				'safety_mode'             => $mode,
				'safety_allowed_post_ids' => $allowlist_csv,
			)
		);
	}

	public function test_create_page_rejected_in_read_only(): void {
		$this->stubMode( Mode::READ_ONLY );
		$result = CreatePage::execute( array( 'title' => 'X', 'content_doc' => array( 'title' => 'X', 'blocks' => array() ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}

	public function test_add_section_rejected_in_read_only(): void {
		$this->stubMode( Mode::READ_ONLY );
		$result = AddSection::execute( array( 'page_id' => 52, 'block' => array( 'type' => 'heading' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}

	public function test_apply_template_rejected_in_read_only(): void {
		$this->stubMode( Mode::READ_ONLY );
		$result = ApplyTemplate::execute( array( 'cpt' => 'ef_location', 'post_data' => array( 'title' => 'X' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}

	public function test_bulk_generate_rejected_in_read_only(): void {
		$this->stubMode( Mode::READ_ONLY );
		$result = BulkGenerate::execute( array( 'cpt' => 'ef_location', 'items' => array( array( 'title' => 'X' ) ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}

	public function test_configure_woocommerce_rejected_in_read_only(): void {
		$this->stubMode( Mode::READ_ONLY );
		$result = ConfigureWooCommerce::execute( array() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}

	public function test_manage_slider_rejected_in_read_only(): void {
		$this->stubMode( Mode::READ_ONLY );
		$result = ManageSlider::execute( array( 'action' => 'list_sliders' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}

	public function test_configure_woocommerce_rejected_in_page_only(): void {
		$this->stubMode( Mode::PAGE_ONLY, '52' );
		$result = ConfigureWooCommerce::execute( array() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_SITE_WIDE_IN_PAGE_ONLY, $result->get_error_code() );
	}

	public function test_add_section_rejected_in_page_only_with_empty_allowlist(): void {
		$this->stubMode( Mode::PAGE_ONLY, '' );
		$result = AddSection::execute( array( 'page_id' => 52, 'block' => array( 'type' => 'heading' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_ALLOWLIST_EMPTY, $result->get_error_code() );
	}

	public function test_add_section_rejected_in_page_only_when_post_not_in_allowlist(): void {
		$this->stubMode( Mode::PAGE_ONLY, '52' );
		$result = AddSection::execute( array( 'page_id' => 99, 'block' => array( 'type' => 'heading' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_POST_NOT_IN_ALLOWLIST, $result->get_error_code() );
	}

	public function test_create_page_still_allowed_in_page_only(): void {
		$this->stubMode( Mode::PAGE_ONLY, '' );
		// Passes the gate; then hits our own missing-title validation which is
		// fine — proves the gate returned true and did not short-circuit.
		$result = CreatePage::execute( array( 'title' => '', 'content_doc' => array( 'title' => 'X', 'blocks' => array() ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_missing_title', $result->get_error_code() );
	}

	public function test_apply_template_still_allowed_in_page_only(): void {
		$this->stubMode( Mode::PAGE_ONLY, '' );
		$result = ApplyTemplate::execute( array( 'cpt' => 'bogus', 'post_data' => array( 'title' => 'X' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_cpt', $result->get_error_code() );
	}

	public function test_bulk_generate_still_allowed_in_page_only(): void {
		$this->stubMode( Mode::PAGE_ONLY, '' );
		$result = BulkGenerate::execute( array( 'cpt' => 'bogus', 'items' => array() ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_invalid_cpt', $result->get_error_code() );
	}

	public function test_full_mode_lets_tools_reach_own_validation(): void {
		$this->stubMode( Mode::FULL );
		// Own validation error path — proves gate allowed through.
		$result = CreatePage::execute( array( 'title' => '', 'content_doc' => array( 'title' => 'X', 'blocks' => array() ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_missing_title', $result->get_error_code() );
	}
}
