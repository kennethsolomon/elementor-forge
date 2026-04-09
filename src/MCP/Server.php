<?php
/**
 * MCP server bootstrap and tool registration.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\MCP;

use ElementorForge\Settings\Store;
use WP\MCP\Core\McpAdapter;
use WP\MCP\Transport\HttpTransport;

/**
 * Wires Elementor Forge's MCP tools into the WordPress MCP Adapter.
 *
 * Registration flow:
 *   1. On `wp_abilities_api_categories_init` — register the `elementor-forge`
 *      ability category.
 *   2. On `wp_abilities_api_init` — register each tool as a WP_Ability.
 *   3. On `mcp_adapter_init` — spin up an McpServer and hand it the ability
 *      names so the adapter exposes them as MCP tools over HTTP.
 *
 * All four steps are gated on the `mcp_server` plugin setting (`enabled` vs
 * `disabled`). When disabled no abilities register and no server is created,
 * keeping the runtime footprint at zero.
 */
final class Server {

	public const CATEGORY           = 'elementor-forge';
	public const ABILITY_CREATE_PAGE            = 'elementor-forge/create-page';
	public const ABILITY_ADD_SECTION            = 'elementor-forge/add-section';
	public const ABILITY_APPLY_TEMPLATE         = 'elementor-forge/apply-template';
	public const ABILITY_BULK_GENERATE          = 'elementor-forge/bulk-generate-pages';
	public const ABILITY_CONFIGURE_WOOCOMMERCE  = 'elementor-forge/configure-woocommerce';

	public const SERVER_ID        = 'elementor-forge';
	public const REST_NAMESPACE   = 'elementor-forge/v1';
	public const REST_ROUTE       = 'mcp';

	public function boot(): void {
		if ( ! Store::is_mcp_enabled() ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		add_action( 'mcp_adapter_init', array( $this, 'register_server' ) );
	}

	public function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => 'Elementor Forge',
				'description' => 'Tools for generating Elementor pages and managing Theme Builder templates.',
			)
		);
	}

	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::ABILITY_CREATE_PAGE,
			array(
				'label'               => 'Create Elementor Page',
				'description'         => 'Build a one-off Elementor page from a structured content document.',
				'category'            => self::CATEGORY,
				'input_schema'        => Tools\CreatePage::input_schema(),
				'output_schema'       => Tools\CreatePage::output_schema(),
				'execute_callback'    => array( Tools\CreatePage::class, 'execute' ),
				'permission_callback' => array( Tools\CreatePage::class, 'permission' ),
				'meta'                => array(
					'annotations'  => array( 'destructive' => true, 'idempotent' => false ),
					'show_in_rest' => false,
				),
			)
		);

		wp_register_ability(
			self::ABILITY_ADD_SECTION,
			array(
				'label'               => 'Add Section',
				'description'         => 'Append a saved section template to an existing Elementor page.',
				'category'            => self::CATEGORY,
				'input_schema'        => Tools\AddSection::input_schema(),
				'output_schema'       => Tools\AddSection::output_schema(),
				'execute_callback'    => array( Tools\AddSection::class, 'execute' ),
				'permission_callback' => array( Tools\AddSection::class, 'permission' ),
				'meta'                => array(
					'annotations'  => array( 'destructive' => true, 'idempotent' => false ),
					'show_in_rest' => false,
				),
			)
		);

		wp_register_ability(
			self::ABILITY_APPLY_TEMPLATE,
			array(
				'label'               => 'Apply Template to CPT',
				'description'         => 'Create a CPT post, populate its ACF fields, and assign the matching Theme Builder single template.',
				'category'            => self::CATEGORY,
				'input_schema'        => Tools\ApplyTemplate::input_schema(),
				'output_schema'       => Tools\ApplyTemplate::output_schema(),
				'execute_callback'    => array( Tools\ApplyTemplate::class, 'execute' ),
				'permission_callback' => array( Tools\ApplyTemplate::class, 'permission' ),
				'meta'                => array(
					'annotations'  => array( 'destructive' => true, 'idempotent' => false ),
					'show_in_rest' => false,
				),
			)
		);

		wp_register_ability(
			self::ABILITY_BULK_GENERATE,
			array(
				'label'               => 'Bulk Generate Pages',
				'description'         => 'Batch-create multiple pages for a CPT from a list of content documents. Basic Phase 1 implementation — the full matrix build (suburbs × services) lands in Phase 3.',
				'category'            => self::CATEGORY,
				'input_schema'        => Tools\BulkGenerate::input_schema(),
				'output_schema'       => Tools\BulkGenerate::output_schema(),
				'execute_callback'    => array( Tools\BulkGenerate::class, 'execute' ),
				'permission_callback' => array( Tools\BulkGenerate::class, 'permission' ),
				'meta'                => array(
					'annotations'  => array( 'destructive' => true, 'idempotent' => false ),
					'show_in_rest' => false,
				),
			)
		);

		wp_register_ability(
			self::ABILITY_CONFIGURE_WOOCOMMERCE,
			array(
				'label'               => 'Configure WooCommerce',
				'description'         => 'Install the four WooCommerce Theme Builder templates, apply Fibosearch defaults, and switch the header pattern to ecommerce. Idempotent — safe to rerun.',
				'category'            => self::CATEGORY,
				'input_schema'        => Tools\ConfigureWooCommerce::input_schema(),
				'output_schema'       => Tools\ConfigureWooCommerce::output_schema(),
				'execute_callback'    => array( Tools\ConfigureWooCommerce::class, 'execute' ),
				'permission_callback' => array( Tools\ConfigureWooCommerce::class, 'permission' ),
				'meta'                => array(
					'annotations'  => array( 'destructive' => true, 'idempotent' => true ),
					'show_in_rest' => false,
				),
			)
		);
	}

	public function register_server( McpAdapter $adapter ): void {
		$adapter->create_server(
			self::SERVER_ID,
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			'Elementor Forge',
			'MCP server for content-doc driven Elementor page generation.',
			'0.1.0',
			array( HttpTransport::class ),
			null,
			null,
			array(
				self::ABILITY_CREATE_PAGE,
				self::ABILITY_ADD_SECTION,
				self::ABILITY_APPLY_TEMPLATE,
				self::ABILITY_BULK_GENERATE,
				self::ABILITY_CONFIGURE_WOOCOMMERCE,
			),
			array(),
			array(),
			static function (): bool {
				return current_user_can( 'manage_options' );
			}
		);
	}
}
