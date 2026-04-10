<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Onboarding;

use ElementorForge\Onboarding\PluginInstallerInterface;
use WP_Error;

/**
 * Stub installer for tests that need to verify delegation without triggering
 * PHPUnit's createMock (which loads Doctrine\Instantiator — PHP 8.3 syntax on
 * the installed version). Tracks calls so tests can assert invocation counts
 * and arguments.
 */
final class StubPluginInstaller implements PluginInstallerInterface {

	/** @var list<array{slug: string, file: string}> */
	public array $calls = array();

	/** @var true|WP_Error */
	public $return_value = true;

	public function install_and_activate( string $slug, string $file ) {
		$this->calls[] = array( 'slug' => $slug, 'file' => $file );
		return $this->return_value;
	}
}
