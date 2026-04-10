<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Onboarding;

use ElementorForge\Onboarding\Wizard;
use PHPUnit\Framework\TestCase;
use WP_Error;

require_once __DIR__ . '/StubAllowlistInstaller.php';

final class WizardAllowlistTest extends TestCase {

	public function test_valid_slug_and_matching_file_delegates_to_installer(): void {
		$stub = new StubAllowlistInstaller();

		$wizard = new Wizard( $stub );
		$result = $wizard->install_dependency( 'elementor', 'elementor/elementor.php' );

		$this->assertTrue( $result );
		$this->assertCount( 1, $stub->calls );
		$this->assertSame( array( 'elementor', 'elementor/elementor.php' ), $stub->calls[0] );
	}

	public function test_invalid_slug_rejected_without_touching_installer(): void {
		$stub = new StubAllowlistInstaller();

		$wizard = new Wizard( $stub );
		$result = $wizard->install_dependency( 'evil-plugin', 'evil-plugin/evil-plugin.php' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_dep_not_allowlisted', $result->get_error_code() );
		$this->assertCount( 0, $stub->calls );
	}

	public function test_valid_slug_but_mismatched_file_rejected(): void {
		$stub = new StubAllowlistInstaller();

		$wizard = new Wizard( $stub );
		$result = $wizard->install_dependency( 'elementor', '../../../../wp-config.php' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_dep_not_allowlisted', $result->get_error_code() );
		$this->assertCount( 0, $stub->calls );
	}

	public function test_manual_only_dependency_rejected(): void {
		$stub = new StubAllowlistInstaller();

		$wizard = new Wizard( $stub );
		$result = $wizard->install_dependency( 'elementor-pro', 'elementor-pro/elementor-pro.php' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_dep_manual_only', $result->get_error_code() );
		$this->assertCount( 0, $stub->calls );
	}

	public function test_empty_params_rejected(): void {
		$stub = new StubAllowlistInstaller();

		$wizard = new Wizard( $stub );

		$empty_slug = $wizard->install_dependency( '', 'elementor/elementor.php' );
		$this->assertInstanceOf( WP_Error::class, $empty_slug );
		$this->assertSame( 'elementor_forge_dep_missing_params', $empty_slug->get_error_code() );

		$empty_file = $wizard->install_dependency( 'elementor', '' );
		$this->assertInstanceOf( WP_Error::class, $empty_file );
		$this->assertSame( 'elementor_forge_dep_missing_params', $empty_file->get_error_code() );

		$this->assertCount( 0, $stub->calls );
	}

	public function test_installer_error_propagates(): void {
		$expected = new WP_Error( 'elementor_forge_install_failed', 'Download blew up.' );

		$stub = new StubAllowlistInstaller();
		$stub->return_value = $expected;

		$wizard = new Wizard( $stub );
		$result = $wizard->install_dependency( 'advanced-custom-fields', 'advanced-custom-fields/acf.php' );

		$this->assertSame( $expected, $result );
		$this->assertCount( 1, $stub->calls );
	}
}
