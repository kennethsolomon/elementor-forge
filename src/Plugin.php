<?php
/**
 * Elementor Forge main plugin bootstrap.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge;

use ElementorForge\ACF\Registrar as AcfRegistrar;
use ElementorForge\Admin\Settings\Page as SettingsPage;
use ElementorForge\CPT\Registrar as CptRegistrar;
use ElementorForge\MCP\Server as McpServer;
use ElementorForge\Onboarding\Wizard;

/**
 * Singleton bootstrap. Orchestrates feature-detect gates, loads modules, and wires hooks.
 *
 * Thin by design — all business logic lives in namespaced service classes that can be
 * instantiated and tested without WordPress loaded.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Whether boot() has run.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Returns the singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot the plugin. Safe to call multiple times — the second call is a no-op.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		// WordPress version gate.
		if ( ! $this->meets_wp_version() ) {
			$this->notice( __( 'Elementor Forge requires WordPress 6.4 or higher.', 'elementor-forge' ) );
			return;
		}

		// CPTs — always registered so uninstall and the ACF/Theme Builder code can reference them.
		( new CptRegistrar() )->boot();

		// ACF field groups — feature-detected inside the registrar.
		( new AcfRegistrar() )->boot();

		// Elementor feature-detect gate. Deferred to elementor/loaded so we don't race activation order.
		add_action( 'elementor/loaded', array( $this, 'on_elementor_loaded' ) );

		// Admin-only wiring.
		if ( is_admin() ) {
			( new SettingsPage() )->boot();
			( new Wizard() )->boot();
			do_action( 'elementor_forge/admin/register' );
		}

		// MCP server gate — internally checks the `mcp_server` setting.
		( new McpServer() )->boot();

		do_action( 'elementor_forge/mcp/register' );

		$this->booted = true;

		/**
		 * Fires once the plugin has finished booting. Public extension point.
		 */
		do_action( 'elementor_forge/booted', $this );
	}

	/**
	 * Called after elementor/loaded fires. Safe to touch the Elementor APIs here.
	 */
	public function on_elementor_loaded(): void {
		if ( ! $this->meets_elementor_version() ) {
			$this->notice( __( 'Elementor Forge requires Elementor 3.20 or higher.', 'elementor-forge' ) );
			return;
		}

		/**
		 * Fires once Elementor has loaded and version-gated. Extension point for modules that
		 * depend on Elementor (widgets, Theme Builder helpers, emitter hooks).
		 */
		do_action( 'elementor_forge/elementor_ready', $this );
	}

	/**
	 * Check whether the running WordPress version meets the plugin minimum.
	 *
	 * @psalm-suppress MixedArgument
	 */
	private function meets_wp_version(): bool {
		global $wp_version;
		return is_string( $wp_version ) && version_compare( $wp_version, ELEMENTOR_FORGE_MIN_WP, '>=' );
	}

	/**
	 * Check whether the loaded Elementor version meets the plugin minimum.
	 */
	private function meets_elementor_version(): bool {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return false;
		}
		return version_compare( (string) ELEMENTOR_VERSION, ELEMENTOR_FORGE_MIN_ELEMENTOR, '>=' );
	}

	/**
	 * Queue a dismissible admin notice.
	 *
	 * @param string $message Human-readable notice text.
	 */
	private function notice( string $message ): void {
		add_action(
			'admin_notices',
			static function () use ( $message ): void {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html( $message )
				);
			}
		);
	}
}
