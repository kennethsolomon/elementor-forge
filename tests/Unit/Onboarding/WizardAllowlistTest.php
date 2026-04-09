<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Onboarding;

use ElementorForge\Onboarding\PluginInstallerInterface;
use ElementorForge\Onboarding\Wizard;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class WizardAllowlistTest extends TestCase {

	public function test_valid_slug_and_matching_file_delegates_to_installer(): void {
		$mock = $this->createMock( PluginInstallerInterface::class );
		$mock->expects( $this->once() )
			->method( 'install_and_activate' )
			->with( 'elementor', 'elementor/elementor.php' )
			->willReturn( true );

		$wizard = new Wizard( $mock );
		$result = $wizard->install_dependency( 'elementor', 'elementor/elementor.php' );

		$this->assertTrue( $result );
	}

	public function test_invalid_slug_rejected_without_touching_installer(): void {
		$mock = $this->createMock( PluginInstallerInterface::class );
		$mock->expects( $this->never() )->method( 'install_and_activate' );

		$wizard = new Wizard( $mock );
		$result = $wizard->install_dependency( 'evil-plugin', 'evil-plugin/evil-plugin.php' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_dep_not_allowlisted', $result->get_error_code() );
	}

	public function test_valid_slug_but_mismatched_file_rejected(): void {
		$mock = $this->createMock( PluginInstallerInterface::class );
		$mock->expects( $this->never() )->method( 'install_and_activate' );

		$wizard = new Wizard( $mock );
		// Legitimate slug but attacker-supplied file path — allowlist pins both.
		$result = $wizard->install_dependency( 'elementor', '../../../../wp-config.php' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_dep_not_allowlisted', $result->get_error_code() );
	}

	public function test_manual_only_dependency_rejected(): void {
		$mock = $this->createMock( PluginInstallerInterface::class );
		$mock->expects( $this->never() )->method( 'install_and_activate' );

		$wizard = new Wizard( $mock );
		// Elementor Pro is allowlisted but flagged auto_install = false.
		$result = $wizard->install_dependency( 'elementor-pro', 'elementor-pro/elementor-pro.php' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'elementor_forge_dep_manual_only', $result->get_error_code() );
	}

	public function test_empty_params_rejected(): void {
		$mock = $this->createMock( PluginInstallerInterface::class );
		$mock->expects( $this->never() )->method( 'install_and_activate' );

		$wizard = new Wizard( $mock );

		$empty_slug = $wizard->install_dependency( '', 'elementor/elementor.php' );
		$this->assertInstanceOf( WP_Error::class, $empty_slug );
		$this->assertSame( 'elementor_forge_dep_missing_params', $empty_slug->get_error_code() );

		$empty_file = $wizard->install_dependency( 'elementor', '' );
		$this->assertInstanceOf( WP_Error::class, $empty_file );
		$this->assertSame( 'elementor_forge_dep_missing_params', $empty_file->get_error_code() );
	}

	public function test_installer_error_propagates(): void {
		$expected = new WP_Error( 'elementor_forge_install_failed', 'Download blew up.' );

		$mock = $this->createMock( PluginInstallerInterface::class );
		$mock->expects( $this->once() )
			->method( 'install_and_activate' )
			->willReturn( $expected );

		$wizard = new Wizard( $mock );
		$result = $wizard->install_dependency( 'advanced-custom-fields', 'advanced-custom-fields/acf.php' );

		$this->assertSame( $expected, $result );
	}
}
