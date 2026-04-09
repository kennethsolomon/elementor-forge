<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Onboarding;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\Onboarding\ThemeInstaller;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Unit coverage for {@see ThemeInstaller}. Brain Monkey stubs the WP core
 * functions the installer touches — `current_user_can`, `wp_get_theme`,
 * `switch_theme`. We exercise the pure branches (capability missing, already
 * installed, already active, not installed) without loading WordPress. The
 * actual `Theme_Upgrader` call path is reached on wp-env in the integration
 * path — verifying that here would require booting WP and is left to the
 * wp-env smoke check.
 */
final class ThemeInstallerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_install_rejects_user_without_install_themes_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$installer = new ThemeInstaller();
		$result    = $installer->install_hello_elementor();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_theme_install_denied', $result->get_error_code() );
	}

	public function test_install_is_noop_when_already_installed(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_get_theme' )->justReturn(
			new FakeWpTheme( 'hello-elementor', true )
		);

		$installer = new ThemeInstaller();
		$result    = $installer->install_hello_elementor();

		$this->assertIsArray( $result );
		$this->assertTrue( $result['installed'] );
		$this->assertTrue( $result['already_installed'] );
	}

	public function test_activate_rejects_user_without_switch_themes_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$installer = new ThemeInstaller();
		$result    = $installer->activate_hello_elementor();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_theme_switch_denied', $result->get_error_code() );
	}

	public function test_activate_errors_when_theme_not_installed(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'wp_get_theme' )->justReturn(
			new FakeWpTheme( 'hello-elementor', false )
		);

		$installer = new ThemeInstaller();
		$result    = $installer->activate_hello_elementor();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_theme_not_installed', $result->get_error_code() );
	}

	public function test_activate_is_noop_when_already_active(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		// Both wp_get_theme(slug) and wp_get_theme() (no args → active theme)
		// return a FakeWpTheme that reports itself as installed AND the
		// active stylesheet, so the installer takes the already-active path.
		Functions\when( 'wp_get_theme' )->alias(
			static function (): FakeWpTheme {
				return new FakeWpTheme( 'hello-elementor', true );
			}
		);

		$installer = new ThemeInstaller();
		$result    = $installer->activate_hello_elementor();

		$this->assertIsArray( $result );
		$this->assertTrue( $result['activated'] );
		$this->assertTrue( $result['already_active'] );
	}

	public function test_is_active_returns_false_when_another_theme_is_stylesheet(): void {
		Functions\when( 'wp_get_theme' )->alias(
			static function ( string $slug = '' ): FakeWpTheme {
				if ( '' === $slug ) {
					return new FakeWpTheme( 'twentytwentyfive', true );
				}
				return new FakeWpTheme( $slug, true );
			}
		);

		$installer = new ThemeInstaller();
		$this->assertFalse( $installer->is_active() );
	}

	public function test_install_and_activate_propagates_wp_error_from_install_step(): void {
		Functions\when( 'current_user_can' )->justReturn( false );

		$installer = new ThemeInstaller();
		$result    = $installer->install_and_activate();

		$this->assertInstanceOf( WP_Error::class, $result );
	}
}
