<?php
/**
 * WooCommerce integration entry point.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\WooCommerce;

use ElementorForge\Elementor\Header\EcommerceHeader;
use ElementorForge\Elementor\ThemeBuilder\Installer as BaseInstaller;
use ElementorForge\Settings\Defaults;
use ElementorForge\Settings\Store;
use ElementorForge\WooCommerce\Fibosearch\Configurator as FibosearchConfigurator;
use ElementorForge\WooCommerce\ThemeBuilder\Installer as WcInstaller;
use ElementorForge\WooCommerce\ThemeBuilder\Templates as WcTemplates;

/**
 * Top-level orchestrator for every Phase 2 feature that requires WooCommerce.
 * Aggregates:
 *
 *   - The four WC Theme Builder templates (shop archive, single product,
 *     cart, checkout) via {@see WcInstaller}.
 *   - The Fibosearch defaults applier via {@see FibosearchConfigurator}.
 *   - The ecommerce header variant via {@see EcommerceHeader::spec()} → base
 *     {@see BaseInstaller::install_one()}.
 *
 * Feature gates:
 *
 *   - The class itself is safe to instantiate without WooCommerce present.
 *   - Every method that touches WC state checks {@see self::is_wc_active()}
 *     first and returns a structured "skipped" result when WC is missing.
 *   - Fibosearch is an independent feature — WC can be present while
 *     Fibosearch is not, and vice versa. Each method reports which
 *     sub-features were applied vs skipped so the caller (the MCP tool or
 *     the settings page) can surface a granular status.
 *
 * This class is pure enough to unit-test: its only WordPress dependencies are
 * `class_exists('WooCommerce')` and the base installer / configurator
 * dependencies, which both accept Brain Monkey shims.
 */
final class WooCommerce {

	/** @var BaseInstaller */
	private BaseInstaller $base_installer;

	/** @var WcInstaller */
	private WcInstaller $wc_installer;

	/** @var FibosearchConfigurator */
	private FibosearchConfigurator $fibosearch;

	public function __construct(
		?BaseInstaller $base_installer = null,
		?WcInstaller $wc_installer = null,
		?FibosearchConfigurator $fibosearch = null
	) {
		$this->base_installer = $base_installer ?? new BaseInstaller();
		$this->wc_installer   = $wc_installer ?? new WcInstaller( $this->base_installer );
		$this->fibosearch     = $fibosearch ?? new FibosearchConfigurator();
	}

	/**
	 * Whether WooCommerce is loaded. Canonical feature-detect: WC ships the
	 * `WooCommerce` class as its main plugin class and loads it on
	 * `plugins_loaded` priority 0 — if the class is missing at `init` time,
	 * the plugin is absent.
	 */
	public static function is_wc_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Apply every Phase 2 configuration step. Each step is guarded — steps
	 * that cannot run (WC missing, Fibosearch missing, etc.) return a
	 * `skipped` entry with a reason so the caller can surface it.
	 *
	 * @return array{
	 *     wc_active: bool,
	 *     templates: array{status:string, installed:array<string,int>, reason?:string},
	 *     fibosearch: array{status:string, applied?:bool, reason?:string, keys_updated?:list<string>, keys_preserved?:list<string>},
	 *     header: array{status:string, post_id?:int, reason?:string}
	 * }
	 */
	public function configure_all(): array {
		if ( ! self::is_wc_active() ) {
			return array(
				'wc_active'  => false,
				'templates'  => array(
					'status'    => 'skipped',
					'installed' => array(),
					'reason'    => 'WooCommerce is not active.',
				),
				'fibosearch' => array(
					'status' => 'skipped',
					'reason' => 'WooCommerce is not active; Fibosearch config skipped.',
				),
				'header'     => array(
					'status' => 'skipped',
					'reason' => 'WooCommerce is not active; header variant skipped.',
				),
			);
		}

		$templates_result = array(
			'status'    => 'installed',
			'installed' => $this->wc_installer->install_all(),
		);

		$fibosearch_raw = $this->fibosearch->apply_defaults();
		if ( $fibosearch_raw['applied'] ) {
			$fibosearch_result = array(
				'status'         => 'applied',
				'applied'        => true,
				'reason'         => $fibosearch_raw['reason'],
				'keys_updated'   => $fibosearch_raw['keys_updated'],
				'keys_preserved' => $fibosearch_raw['keys_preserved'],
			);
		} else {
			$fibosearch_result = array(
				'status'  => 'skipped',
				'applied' => false,
				'reason'  => $fibosearch_raw['reason'],
			);
		}

		// Switch header variant + install the ecommerce header TemplateSpec.
		Store::update( array( 'header_pattern' => Defaults::HEADER_PATTERN_ECOMMERCE ) );
		$header_spec = EcommerceHeader::spec();
		$post_id     = $this->base_installer->install_one( $header_spec );

		$header_result = $post_id > 0
			? array( 'status' => 'installed', 'post_id' => $post_id )
			: array( 'status' => 'failed', 'reason' => 'base installer returned 0 for ecommerce header spec' );

		return array(
			'wc_active'  => true,
			'templates'  => $templates_result,
			'fibosearch' => $fibosearch_result,
			'header'     => $header_result,
		);
	}

	/**
	 * Produce a read-only detection report for the settings page. Does not
	 * mutate any state; safe to call on every admin page load.
	 *
	 * @return array{
	 *     wc_active: bool,
	 *     wc_version: string,
	 *     wc_templates_installed: int,
	 *     wc_templates_total: int,
	 *     wc_templates_fully_installed: bool,
	 *     fibosearch: array{detected:bool, version:string, option_exists:bool, in_sync:bool, keys_present:int},
	 *     header_variant_active: string
	 * }
	 */
	public function report(): array {
		$wc_version = '';
		if ( self::is_wc_active() && defined( 'WC_VERSION' ) ) {
			$wc_version = (string) constant( 'WC_VERSION' );
		}

		$wc_existing = self::is_wc_active() ? $this->wc_installer->existing() : array();

		return array(
			'wc_active'                    => self::is_wc_active(),
			'wc_version'                   => $wc_version,
			'wc_templates_installed'       => count( $wc_existing ),
			'wc_templates_total'           => count( WcTemplates::all() ),
			'wc_templates_fully_installed' => self::is_wc_active() && $this->wc_installer->is_fully_installed(),
			'fibosearch'                   => $this->fibosearch->report(),
			'header_variant_active'        => Store::get( 'header_pattern' ),
		);
	}

	/**
	 * Install only the WC templates — called from the "Install WC templates"
	 * settings page action.
	 *
	 * @return array<string, int>
	 */
	public function install_templates(): array {
		if ( ! self::is_wc_active() ) {
			return array();
		}
		return $this->wc_installer->install_all();
	}

	/**
	 * Apply only the Fibosearch defaults — called from the "Apply Fibosearch
	 * defaults" settings page action.
	 *
	 * @return array{applied:bool, reason:string, keys_updated:list<string>, keys_preserved:list<string>}
	 */
	public function apply_fibosearch_defaults(): array {
		return $this->fibosearch->apply_defaults();
	}

	/**
	 * Switch the header pattern to ecommerce and install the ecommerce header
	 * variant. Returns the installed header post ID or 0 on failure.
	 */
	public function switch_to_ecommerce_header(): int {
		if ( ! self::is_wc_active() ) {
			return 0;
		}
		Store::update( array( 'header_pattern' => Defaults::HEADER_PATTERN_ECOMMERCE ) );
		return $this->base_installer->install_one( EcommerceHeader::spec() );
	}
}
