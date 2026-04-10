<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\MCP\Server;
use ElementorForge\Settings\Store;
use PHPUnit\Framework\TestCase;

final class ServerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Store::flush_cache();
		Functions\when( 'get_option' )->justReturn(
			array(
				'acf_mode'                => 'free',
				'ucaddon_shim'            => 'preserve',
				'mcp_server'              => 'enabled',
				'header_pattern'          => 'service_business',
				'safety_mode'             => 'full',
				'safety_allowed_post_ids' => '',
			)
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_constants_are_namespaced(): void {
		$this->assertSame( 'elementor-forge', Server::CATEGORY );
		$this->assertSame( 'elementor-forge/create-page', Server::ABILITY_CREATE_PAGE );
		$this->assertSame( 'elementor-forge/add-section', Server::ABILITY_ADD_SECTION );
		$this->assertSame( 'elementor-forge/apply-template', Server::ABILITY_APPLY_TEMPLATE );
		$this->assertSame( 'elementor-forge/bulk-generate-pages', Server::ABILITY_BULK_GENERATE );
		$this->assertSame( 'elementor-forge/configure-woocommerce', Server::ABILITY_CONFIGURE_WOOCOMMERCE );
		$this->assertSame( 'elementor-forge/manage-slider', Server::ABILITY_MANAGE_SLIDER );
	}

	public function test_server_id_and_rest_route(): void {
		$this->assertSame( 'elementor-forge', Server::SERVER_ID );
		$this->assertSame( 'elementor-forge/v1', Server::REST_NAMESPACE );
		$this->assertSame( 'mcp', Server::REST_ROUTE );
	}

	public function test_boot_registers_three_action_hooks_when_mcp_enabled(): void {
		$hooks = array();
		Functions\when( 'add_action' )->alias(
			static function ( string $tag, $_callback ) use ( &$hooks ): void {
				$hooks[] = $tag;
			}
		);

		$server = new Server();
		$server->boot();

		$this->assertContains( 'wp_abilities_api_categories_init', $hooks );
		$this->assertContains( 'wp_abilities_api_init', $hooks );
		$this->assertContains( 'mcp_adapter_init', $hooks );
		$this->assertCount( 3, $hooks );
	}

	public function test_boot_skips_hooks_when_mcp_disabled(): void {
		Store::flush_cache();
		Functions\when( 'get_option' )->justReturn(
			array(
				'acf_mode'                => 'free',
				'ucaddon_shim'            => 'preserve',
				'mcp_server'              => 'disabled',
				'header_pattern'          => 'service_business',
				'safety_mode'             => 'full',
				'safety_allowed_post_ids' => '',
			)
		);

		$hooks = array();
		Functions\when( 'add_action' )->alias(
			static function ( string $tag, $_callback ) use ( &$hooks ): void {
				$hooks[] = $tag;
			}
		);

		$server = new Server();
		$server->boot();

		$this->assertEmpty( $hooks );
	}

	public function test_register_category_returns_early_when_wp_function_missing(): void {
		// wp_register_ability_category is not defined in unit tests
		// (the shim guards on ABSPATH), so register_category() must
		// return early without errors.
		$server = new Server();
		$server->register_category();
		// No exception or error = passes the function_exists guard.
		$this->assertTrue( true );
	}

	public function test_register_abilities_returns_early_when_wp_function_missing(): void {
		// wp_register_ability is not defined in unit tests, so
		// register_abilities() must return early without errors.
		$server = new Server();
		$server->register_abilities();
		$this->assertTrue( true );
	}

	public function test_ability_count_matches_constant_count(): void {
		// Verify the constant list lines up — six tools registered.
		$constants = array(
			Server::ABILITY_CREATE_PAGE,
			Server::ABILITY_ADD_SECTION,
			Server::ABILITY_APPLY_TEMPLATE,
			Server::ABILITY_BULK_GENERATE,
			Server::ABILITY_CONFIGURE_WOOCOMMERCE,
			Server::ABILITY_MANAGE_SLIDER,
		);
		$this->assertCount( 6, $constants );
		// All must follow the namespace/slug format.
		foreach ( $constants as $name ) {
			$this->assertMatchesRegularExpression( '/^[a-z0-9-]+\/[a-z0-9-]+$/', $name );
		}
	}

	public function test_all_ability_constants_share_category_prefix(): void {
		$prefix = Server::CATEGORY . '/';
		$this->assertStringStartsWith( $prefix, Server::ABILITY_CREATE_PAGE );
		$this->assertStringStartsWith( $prefix, Server::ABILITY_ADD_SECTION );
		$this->assertStringStartsWith( $prefix, Server::ABILITY_APPLY_TEMPLATE );
		$this->assertStringStartsWith( $prefix, Server::ABILITY_BULK_GENERATE );
		$this->assertStringStartsWith( $prefix, Server::ABILITY_CONFIGURE_WOOCOMMERCE );
		$this->assertStringStartsWith( $prefix, Server::ABILITY_MANAGE_SLIDER );
	}

	public function test_boot_hooks_use_correct_method_callbacks(): void {
		$callbacks = array();
		Functions\when( 'add_action' )->alias(
			static function ( string $tag, $callback ) use ( &$callbacks ): void {
				$callbacks[ $tag ] = $callback;
			}
		);

		$server = new Server();
		$server->boot();

		$this->assertSame( array( $server, 'register_category' ), $callbacks['wp_abilities_api_categories_init'] );
		$this->assertSame( array( $server, 'register_abilities' ), $callbacks['wp_abilities_api_init'] );
		$this->assertSame( array( $server, 'register_server' ), $callbacks['mcp_adapter_init'] );
	}
}
