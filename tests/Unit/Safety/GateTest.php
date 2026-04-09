<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Safety;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\Safety\Gate;
use ElementorForge\Safety\Mode;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Every cell of the tool × mode decision matrix is exercised here. Every row
 * of {@see Gate}'s inline matrix has at least one test covering allow and one
 * covering reject. The error code assertions lock the public contract callers
 * (and the settings UI) rely on.
 */
final class GateTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Stub get_option so Store::safety_mode() and Store::safety_allowlist()
	 * return the requested mode + allowlist CSV.
	 */
	private function stubSettings( string $mode, string $allowlist_csv = '' ): void {
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

	public function test_full_mode_allows_create_page(): void {
		$this->stubSettings( Mode::FULL );
		$this->assertTrue( Gate::check( 'create_page', Gate::ACTION_CREATE ) );
	}

	public function test_full_mode_allows_add_section_even_with_empty_allowlist(): void {
		$this->stubSettings( Mode::FULL );
		$this->assertTrue( Gate::check( 'add_section', Gate::ACTION_MODIFY, 999 ) );
	}

	public function test_full_mode_allows_apply_template(): void {
		$this->stubSettings( Mode::FULL );
		$this->assertTrue( Gate::check( 'apply_template', Gate::ACTION_CREATE ) );
	}

	public function test_full_mode_allows_bulk_generate(): void {
		$this->stubSettings( Mode::FULL );
		$this->assertTrue( Gate::check( 'bulk_generate_pages', Gate::ACTION_CREATE ) );
	}

	public function test_full_mode_allows_configure_woocommerce(): void {
		$this->stubSettings( Mode::FULL );
		$this->assertTrue( Gate::check( 'configure_woocommerce', Gate::ACTION_SITE_WIDE ) );
	}

	public function test_full_mode_allows_manage_slider(): void {
		$this->stubSettings( Mode::FULL );
		$this->assertTrue( Gate::check( 'manage_slider', Gate::ACTION_MODIFY ) );
	}

	public function test_page_only_mode_allows_create_page(): void {
		$this->stubSettings( Mode::PAGE_ONLY );
		$this->assertTrue( Gate::check( 'create_page', Gate::ACTION_CREATE ) );
	}

	public function test_page_only_mode_allows_apply_template(): void {
		$this->stubSettings( Mode::PAGE_ONLY );
		$this->assertTrue( Gate::check( 'apply_template', Gate::ACTION_CREATE ) );
	}

	public function test_page_only_mode_allows_bulk_generate(): void {
		$this->stubSettings( Mode::PAGE_ONLY );
		$this->assertTrue( Gate::check( 'bulk_generate_pages', Gate::ACTION_CREATE ) );
	}

	public function test_page_only_mode_rejects_add_section_with_empty_allowlist(): void {
		$this->stubSettings( Mode::PAGE_ONLY, '' );
		$result = Gate::check( 'add_section', Gate::ACTION_MODIFY, 52 );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_ALLOWLIST_EMPTY, $result->get_error_code() );
	}

	public function test_page_only_mode_allows_add_section_when_post_id_in_allowlist(): void {
		$this->stubSettings( Mode::PAGE_ONLY, '52,101' );
		$this->assertTrue( Gate::check( 'add_section', Gate::ACTION_MODIFY, 52 ) );
		$this->assertTrue( Gate::check( 'add_section', Gate::ACTION_MODIFY, 101 ) );
	}

	public function test_page_only_mode_rejects_add_section_when_post_id_not_in_allowlist(): void {
		$this->stubSettings( Mode::PAGE_ONLY, '52,101' );
		$result = Gate::check( 'add_section', Gate::ACTION_MODIFY, 99 );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_POST_NOT_IN_ALLOWLIST, $result->get_error_code() );
	}

	public function test_page_only_mode_rejects_add_section_with_null_post_id(): void {
		$this->stubSettings( Mode::PAGE_ONLY, '52' );
		$result = Gate::check( 'add_section', Gate::ACTION_MODIFY, null );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_POST_NOT_IN_ALLOWLIST, $result->get_error_code() );
	}

	public function test_page_only_mode_rejects_configure_woocommerce(): void {
		$this->stubSettings( Mode::PAGE_ONLY, '52' );
		$result = Gate::check( 'configure_woocommerce', Gate::ACTION_SITE_WIDE );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_SITE_WIDE_IN_PAGE_ONLY, $result->get_error_code() );
	}

	public function test_page_only_mode_allows_manage_slider_special_case(): void {
		// Sliders are not posts — the allowlist does not apply and the tool
		// is allowed in page_only mode even with an empty allowlist.
		$this->stubSettings( Mode::PAGE_ONLY, '' );
		$this->assertTrue( Gate::check( 'manage_slider', Gate::ACTION_MODIFY ) );
	}

	public function test_read_only_mode_rejects_create_page(): void {
		$this->stubSettings( Mode::READ_ONLY );
		$result = Gate::check( 'create_page', Gate::ACTION_CREATE );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}

	public function test_read_only_mode_rejects_add_section(): void {
		$this->stubSettings( Mode::READ_ONLY );
		$result = Gate::check( 'add_section', Gate::ACTION_MODIFY, 52 );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}

	public function test_read_only_mode_rejects_apply_template(): void {
		$this->stubSettings( Mode::READ_ONLY );
		$result = Gate::check( 'apply_template', Gate::ACTION_CREATE );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}

	public function test_read_only_mode_rejects_bulk_generate(): void {
		$this->stubSettings( Mode::READ_ONLY );
		$result = Gate::check( 'bulk_generate_pages', Gate::ACTION_CREATE );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}

	public function test_read_only_mode_rejects_configure_woocommerce(): void {
		$this->stubSettings( Mode::READ_ONLY );
		$result = Gate::check( 'configure_woocommerce', Gate::ACTION_SITE_WIDE );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}

	public function test_read_only_mode_rejects_manage_slider(): void {
		$this->stubSettings( Mode::READ_ONLY );
		$result = Gate::check( 'manage_slider', Gate::ACTION_MODIFY );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}

	public function test_is_wizard_enabled_true_in_full_mode(): void {
		$this->stubSettings( Mode::FULL );
		$this->assertTrue( Gate::is_wizard_enabled() );
	}

	public function test_is_wizard_enabled_false_in_page_only_mode(): void {
		$this->stubSettings( Mode::PAGE_ONLY );
		$this->assertFalse( Gate::is_wizard_enabled() );
	}

	public function test_is_wizard_enabled_false_in_read_only_mode(): void {
		$this->stubSettings( Mode::READ_ONLY );
		$this->assertFalse( Gate::is_wizard_enabled() );
	}

	public function test_current_mode_falls_back_to_full_on_invalid_stored_value(): void {
		$this->stubSettings( 'garbage_mode_value' );
		$this->assertSame( Mode::FULL, Gate::current_mode() );
	}

	public function test_full_mode_allows_theme_install(): void {
		$this->stubSettings( Mode::FULL );
		$this->assertTrue(
			Gate::check( 'install_hello_elementor', Gate::ACTION_THEME_INSTALL )
		);
	}

	public function test_page_only_mode_rejects_theme_install(): void {
		$this->stubSettings( Mode::PAGE_ONLY, '52,101' );
		$result = Gate::check( 'install_hello_elementor', Gate::ACTION_THEME_INSTALL );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame(
			Gate::ERR_THEME_INSTALL_IN_PAGE_ONLY,
			$result->get_error_code()
		);
	}

	public function test_read_only_mode_rejects_theme_install(): void {
		$this->stubSettings( Mode::READ_ONLY );
		$result = Gate::check( 'install_hello_elementor', Gate::ACTION_THEME_INSTALL );
		$this->assertInstanceOf( WP_Error::class, $result );
		// read_only takes precedence over the theme_install-specific error.
		$this->assertSame( Gate::ERR_READ_ONLY, $result->get_error_code() );
	}
}
