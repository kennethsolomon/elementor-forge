<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Onboarding;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\Onboarding\PluginInstaller;
use ElementorForge\Onboarding\PluginInstallerInterface;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * PluginInstaller wraps WordPress's Plugin_Upgrader API. The class uses PHP
 * internal function_exists / class_exists checks to lazily require WP admin
 * files. Since those internals cannot be stubbed via Brain Monkey, we test
 * only the branches that fire before any require_once, plus branches where
 * Brain Monkey has already defined the WP function (making function_exists
 * return true and skipping the require).
 *
 * Brain Monkey's `when('foo')` defines `foo()` via Patchwork, so subsequent
 * calls to `function_exists('foo')` inside the SUT return true without
 * needing a real WP load.
 */
final class PluginInstallerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/tmp/wp/' );
		}

		// Define WP upgrader classes so PluginInstaller's class_exists() guards
		// pass without requiring real WP admin files. These must be defined
		// before test methods call install_from_wporg().
		if ( ! class_exists( \Plugin_Upgrader::class, false ) ) {
			eval( 'class Plugin_Upgrader { public function __construct($skin = null) {} public function install(string $url) { return true; } }' ); // phpcs:ignore
		}
		if ( ! class_exists( \WP_Ajax_Upgrader_Skin::class, false ) ) {
			eval( 'class WP_Ajax_Upgrader_Skin { public $result = null; }' ); // phpcs:ignore
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_implements_plugin_installer_interface(): void {
		$this->assertTrue(
			( new \ReflectionClass( PluginInstaller::class ) )
				->implementsInterface( PluginInstallerInterface::class )
		);
	}

	public function test_install_from_wporg_rejects_user_without_capability(): void {
		// current_user_can check fires before any require_once.
		Functions\when( 'current_user_can' )->justReturn( false );

		$installer = new PluginInstaller();
		$result = $installer->install_from_wporg( 'elementor' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_install_denied', $result->get_error_code() );
	}

	public function test_install_from_wporg_returns_error_when_plugins_api_fails(): void {
		Functions\when( 'current_user_can' )->justReturn( true );

		// Define plugins_api + class stubs so function_exists/class_exists pass.
		Functions\when( 'plugins_api' )->justReturn(
			new WP_Error( 'plugins_api_failed', 'Could not reach wp.org.' )
		);
		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ): bool {
				return $thing instanceof WP_Error;
			}
		);

		$installer = new PluginInstaller();
		$result = $installer->install_from_wporg( 'elementor' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'plugins_api_failed', $result->get_error_code() );
	}

	public function test_install_from_wporg_returns_error_when_no_download_link(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'plugins_api' )->justReturn( (object) array( 'name' => 'Elementor' ) );
		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ): bool {
				return $thing instanceof WP_Error;
			}
		);

		$installer = new PluginInstaller();
		$result = $installer->install_from_wporg( 'elementor' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_install_no_link', $result->get_error_code() );
	}

	public function test_is_installed_returns_true_when_plugin_in_list(): void {
		Functions\when( 'get_plugins' )->justReturn(
			array(
				'elementor/elementor.php' => array( 'Name' => 'Elementor' ),
			)
		);

		$installer = new PluginInstaller();
		$this->assertTrue( $installer->is_installed( 'elementor/elementor.php' ) );
	}

	public function test_is_installed_returns_false_when_plugin_missing(): void {
		Functions\when( 'get_plugins' )->justReturn( array() );

		$installer = new PluginInstaller();
		$this->assertFalse( $installer->is_installed( 'elementor/elementor.php' ) );
	}

	public function test_is_active_returns_true_when_active(): void {
		Functions\when( 'is_plugin_active' )->justReturn( true );

		$installer = new PluginInstaller();
		$this->assertTrue( $installer->is_active( 'elementor/elementor.php' ) );
	}

	public function test_is_active_returns_false_when_inactive(): void {
		Functions\when( 'is_plugin_active' )->justReturn( false );

		$installer = new PluginInstaller();
		$this->assertFalse( $installer->is_active( 'elementor/elementor.php' ) );
	}

	public function test_activate_returns_true_on_success(): void {
		Functions\when( 'activate_plugin' )->justReturn( null );
		Functions\when( 'is_wp_error' )->justReturn( false );

		$installer = new PluginInstaller();
		$result = $installer->activate( 'elementor/elementor.php' );

		$this->assertTrue( $result );
	}

	public function test_activate_returns_wp_error_on_failure(): void {
		$error = new WP_Error( 'plugin_invalid', 'Plugin file does not exist.' );
		Functions\when( 'activate_plugin' )->justReturn( $error );
		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ): bool {
				return $thing instanceof WP_Error;
			}
		);

		$installer = new PluginInstaller();
		$result = $installer->activate( 'nonexistent/plugin.php' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'plugin_invalid', $result->get_error_code() );
	}

	public function test_install_and_activate_returns_true_when_already_active(): void {
		Functions\when( 'is_plugin_active' )->justReturn( true );

		$installer = new PluginInstaller();
		$result = $installer->install_and_activate( 'elementor', 'elementor/elementor.php' );

		$this->assertTrue( $result );
	}

	public function test_install_and_activate_activates_when_installed_but_inactive(): void {
		Functions\when( 'is_plugin_active' )->justReturn( false );
		Functions\when( 'get_plugins' )->justReturn( array( 'elementor/elementor.php' => array() ) );
		Functions\when( 'activate_plugin' )->justReturn( null );
		Functions\when( 'is_wp_error' )->justReturn( false );

		$installer = new PluginInstaller();
		$result = $installer->install_and_activate( 'elementor', 'elementor/elementor.php' );

		$this->assertTrue( $result );
	}

	public function test_install_and_activate_propagates_activation_error(): void {
		Functions\when( 'is_plugin_active' )->justReturn( false );
		Functions\when( 'get_plugins' )->justReturn( array( 'elementor/elementor.php' => array() ) );

		$error = new WP_Error( 'activation_failed', 'Fatal error during activation.' );
		Functions\when( 'activate_plugin' )->justReturn( $error );
		Functions\when( 'is_wp_error' )->alias(
			static function ( $thing ): bool {
				return $thing instanceof WP_Error;
			}
		);

		$installer = new PluginInstaller();
		$result = $installer->install_and_activate( 'elementor', 'elementor/elementor.php' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'activation_failed', $result->get_error_code() );
	}
}
