<?php
/**
 * Contract for the PluginInstaller seam used by the Wizard.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Onboarding;

use WP_Error;

/**
 * Narrow seam the wizard depends on for dependency installs. Keeps
 * {@see PluginInstaller} `final` (no subclassing in production) while still
 * allowing unit tests to mock the install path without touching WordPress
 * core's upgrader API.
 */
interface PluginInstallerInterface {

	/**
	 * Install a plugin from wp.org and activate it.
	 *
	 * @return true|WP_Error
	 */
	public function install_and_activate( string $slug, string $file );
}
