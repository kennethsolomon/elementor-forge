<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Onboarding;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\Onboarding\Wizard;
use PHPUnit\Framework\TestCase;
use WP_Error;

require_once __DIR__ . '/StubPluginInstaller.php';

final class WizardTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_boot_registers_admin_post_action(): void {
		$registered_hooks = array();
		Functions\when( 'add_action' )->alias(
			static function ( string $hook, $_callback, int $_priority = 10 ) use ( &$registered_hooks ): void {
				$registered_hooks[] = $hook;
			}
		);

		$wizard = new Wizard();
		$wizard->boot();

		$this->assertContains(
			'admin_post_' . Wizard::ACTION_SLUG,
			$registered_hooks
		);
	}

	public function test_is_complete_returns_false_when_option_missing(): void {
		Functions\when( 'get_option' )->justReturn( false );

		$this->assertFalse( Wizard::is_complete() );
	}

	public function test_is_complete_returns_true_when_option_set(): void {
		Functions\when( 'get_option' )->justReturn( true );

		$this->assertTrue( Wizard::is_complete() );
	}

	public function test_constants_match_expected_values(): void {
		$this->assertSame( 'elementor_forge_onboarding_complete', Wizard::OPTION_COMPLETE );
		$this->assertSame( 'elementor_forge_wizard', Wizard::NONCE_ACTION );
		$this->assertSame( 'elementor_forge_wizard_nonce', Wizard::NONCE_FIELD );
		$this->assertSame( 'elementor_forge_wizard_step', Wizard::ACTION_SLUG );
	}

	public function test_install_dependency_empty_slug_returns_error(): void {
		$stub = new StubPluginInstaller();
		$wizard = new Wizard( $stub );
		$result = $wizard->install_dependency( '', 'some/file.php' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_dep_missing_params', $result->get_error_code() );
		$this->assertCount( 0, $stub->calls );
	}

	public function test_install_dependency_empty_file_returns_error(): void {
		$stub = new StubPluginInstaller();
		$wizard = new Wizard( $stub );
		$result = $wizard->install_dependency( 'elementor', '' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_dep_missing_params', $result->get_error_code() );
		$this->assertCount( 0, $stub->calls );
	}

	public function test_install_dependency_unknown_slug_returns_not_allowlisted(): void {
		$stub = new StubPluginInstaller();
		$wizard = new Wizard( $stub );
		$result = $wizard->install_dependency( 'unknown-plugin', 'unknown-plugin/unknown.php' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_dep_not_allowlisted', $result->get_error_code() );
		$this->assertCount( 0, $stub->calls );
	}

	public function test_install_dependency_manual_only_returns_error(): void {
		$stub = new StubPluginInstaller();
		$wizard = new Wizard( $stub );
		$result = $wizard->install_dependency( 'elementor-pro', 'elementor-pro/elementor-pro.php' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_dep_manual_only', $result->get_error_code() );
		$this->assertCount( 0, $stub->calls );
	}

	public function test_install_dependency_valid_slug_delegates_to_installer(): void {
		$stub = new StubPluginInstaller();
		$wizard = new Wizard( $stub );
		$result = $wizard->install_dependency( 'elementor', 'elementor/elementor.php' );

		$this->assertTrue( $result );
		$this->assertCount( 1, $stub->calls );
		$this->assertSame( 'elementor', $stub->calls[0]['slug'] );
		$this->assertSame( 'elementor/elementor.php', $stub->calls[0]['file'] );
	}

	public function test_install_dependency_propagates_installer_error(): void {
		$expected = new WP_Error( 'download_failed', 'Network timeout.' );
		$stub = new StubPluginInstaller();
		$stub->return_value = $expected;

		$wizard = new Wizard( $stub );
		$result = $wizard->install_dependency( 'elementor', 'elementor/elementor.php' );

		$this->assertSame( $expected, $result );
	}

	public function test_install_dependency_mismatched_file_for_valid_slug_rejected(): void {
		$stub = new StubPluginInstaller();
		$wizard = new Wizard( $stub );
		// Valid slug but wrong file path.
		$result = $wizard->install_dependency( 'elementor', 'wrong-dir/elementor.php' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_dep_not_allowlisted', $result->get_error_code() );
		$this->assertCount( 0, $stub->calls );
	}

	public function test_constructor_accepts_null_installer(): void {
		$wizard = new Wizard( null );
		$this->assertInstanceOf( Wizard::class, $wizard );
	}

	public function test_constructor_accepts_no_arguments(): void {
		$wizard = new Wizard();
		$this->assertInstanceOf( Wizard::class, $wizard );
	}

	public function test_handle_step_rejects_missing_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'esc_html__' )->returnArg();

		// wp_die must halt execution; throw so the test can catch it.
		Functions\when( 'wp_die' )->alias(
			static function ( string $message ): void {
				throw new \RuntimeException( 'wp_die: ' . $message );
			}
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/Insufficient permissions/' );

		$wizard = new Wizard();
		$wizard->handle_step();
	}

	public function test_handle_step_rejects_invalid_nonce(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'wp_verify_nonce' )->justReturn( false );
		Functions\when( 'esc_html__' )->returnArg();

		$_POST[ Wizard::NONCE_FIELD ] = 'bad_nonce';

		// wp_die must halt execution; throw so the test can catch it.
		Functions\when( 'wp_die' )->alias(
			static function ( string $message ): void {
				throw new \RuntimeException( 'wp_die: ' . $message );
			}
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessageMatches( '/Invalid request/' );

		try {
			$wizard = new Wizard();
			$wizard->handle_step();
		} finally {
			unset( $_POST[ Wizard::NONCE_FIELD ] );
		}
	}

	public function test_install_dependency_works_with_acf_slug(): void {
		$stub = new StubPluginInstaller();
		$wizard = new Wizard( $stub );
		$result = $wizard->install_dependency( 'advanced-custom-fields', 'advanced-custom-fields/acf.php' );

		$this->assertTrue( $result );
		$this->assertCount( 1, $stub->calls );
		$this->assertSame( 'advanced-custom-fields', $stub->calls[0]['slug'] );
	}

	public function test_install_dependency_works_with_smart_slider_slug(): void {
		$stub = new StubPluginInstaller();
		$wizard = new Wizard( $stub );
		$result = $wizard->install_dependency( 'smart-slider-3', 'smart-slider-3/smart-slider-3.php' );

		$this->assertTrue( $result );
		$this->assertCount( 1, $stub->calls );
	}

	public function test_install_dependency_works_with_woocommerce_slug(): void {
		$stub = new StubPluginInstaller();
		$wizard = new Wizard( $stub );
		$result = $wizard->install_dependency( 'woocommerce', 'woocommerce/woocommerce.php' );

		$this->assertTrue( $result );
		$this->assertCount( 1, $stub->calls );
	}
}
