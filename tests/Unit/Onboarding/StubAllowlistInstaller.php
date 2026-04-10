<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Onboarding;

use ElementorForge\Onboarding\PluginInstallerInterface;
use WP_Error;

/**
 * Manual stub to avoid Doctrine Instantiator PHP 8.3 syntax errors on PHP 8.0.
 */
class StubAllowlistInstaller implements PluginInstallerInterface {

	/** @var list<array{string, string}> */
	public array $calls = array();

	/** @var true|WP_Error */
	public $return_value = true;

	public bool $should_be_called = true;

	public function install_and_activate( string $slug, string $file ) {
		$this->calls[] = array( $slug, $file );
		return $this->return_value;
	}
}
