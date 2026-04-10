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
	public const ABILITY_MANAGE_SLIDER          = 'elementor-forge/manage-slider';
	public const ABILITY_SET_KIT_GLOBALS        = 'elementor-forge/set-kit-globals';
	public const ABILITY_CREATE_HEADER          = 'elementor-forge/create-header';
	public const ABILITY_CREATE_FOOTER          = 'elementor-forge/create-footer';
	public const ABILITY_GET_PAGE_STRUCTURE    = 'elementor-forge/get-page-structure';
	public const ABILITY_EDIT_SECTION          = 'elementor-forge/edit-section';
	public const ABILITY_DELETE_SECTION        = 'elementor-forge/delete-section';
	public const ABILITY_REORDER_SECTIONS      = 'elementor-forge/reorder-sections';
	public const ABILITY_UPDATE_WIDGET         = 'elementor-forge/update-widget';
	public const ABILITY_DUPLICATE_SECTION     = 'elementor-forge/duplicate-section';

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

		wp_register_ability(
			self::ABILITY_MANAGE_SLIDER,
			array(
				'label'               => 'Manage Smart Slider',
				'description'         => 'CRUD against Smart Slider 3 Free. Single tool, multi-action: create_slider, update_slider, get_slider, delete_slider, add_slide, update_slide, delete_slide, list_sliders. Throws if Smart Slider 3 is missing or out of supported version range.',
				'category'            => self::CATEGORY,
				'input_schema'        => Tools\ManageSlider::input_schema(),
				'output_schema'       => Tools\ManageSlider::output_schema(),
				'execute_callback'    => array( Tools\ManageSlider::class, 'execute' ),
				'permission_callback' => array( Tools\ManageSlider::class, 'permission' ),
				'meta'                => array(
					'annotations'  => array( 'destructive' => true, 'idempotent' => false ),
					'show_in_rest' => false,
				),
			)
		);

		wp_register_ability(
			self::ABILITY_SET_KIT_GLOBALS,
			array(
				'label'               => 'Set Kit Globals',
				'description'         => 'Set the Default Kit brand palette: colors (primary, secondary, text, accent), typography (headings, body), and button styles. Call this FIRST when setting up a new site.',
				'category'            => self::CATEGORY,
				'input_schema'        => Tools\SetKitGlobals::input_schema(),
				'output_schema'       => Tools\SetKitGlobals::output_schema(),
				'execute_callback'    => array( Tools\SetKitGlobals::class, 'execute' ),
				'permission_callback' => array( Tools\SetKitGlobals::class, 'permission' ),
				'meta'                => array(
					'annotations'  => array( 'destructive' => true, 'idempotent' => true ),
					'show_in_rest' => false,
				),
			)
		);

		wp_register_ability(
			self::ABILITY_CREATE_HEADER,
			array(
				'label'               => 'Create Header',
				'description'         => 'Create a Theme Builder header from a preset (business, ecommerce, portfolio, blog, saas) with optional overrides. Supports custom row layouts, sticky behavior, and transparent mode.',
				'category'            => self::CATEGORY,
				'input_schema'        => Tools\CreateHeader::input_schema(),
				'output_schema'       => Tools\CreateHeader::output_schema(),
				'execute_callback'    => array( Tools\CreateHeader::class, 'execute' ),
				'permission_callback' => array( Tools\CreateHeader::class, 'permission' ),
				'meta'                => array(
					'annotations'  => array( 'destructive' => true, 'idempotent' => false ),
					'show_in_rest' => false,
				),
			)
		);

		wp_register_ability(
			self::ABILITY_CREATE_FOOTER,
			array(
				'label'               => 'Create Footer',
				'description'         => 'Create a Theme Builder footer from a preset (simple, multi_column, minimal, newsletter) with optional overrides. Supports custom background and copyright text.',
				'category'            => self::CATEGORY,
				'input_schema'        => Tools\CreateFooter::input_schema(),
				'output_schema'       => Tools\CreateFooter::output_schema(),
				'execute_callback'    => array( Tools\CreateFooter::class, 'execute' ),
				'permission_callback' => array( Tools\CreateFooter::class, 'permission' ),
				'meta'                => array(
					'annotations'  => array( 'destructive' => true, 'idempotent' => false ),
					'show_in_rest' => false,
				),
			)
		);

		wp_register_ability(
			self::ABILITY_GET_PAGE_STRUCTURE,
			array(
				'label'               => 'Get Page Structure',
				'description'         => 'Read-only: returns the annotated element tree of any Elementor page, template, or post. Use to inspect content before editing.',
				'category'            => self::CATEGORY,
				'input_schema'        => Tools\GetPageStructure::input_schema(),
				'output_schema'       => Tools\GetPageStructure::output_schema(),
				'execute_callback'    => array( Tools\GetPageStructure::class, 'execute' ),
				'permission_callback' => array( Tools\GetPageStructure::class, 'permission' ),
				'meta'                => array(
					'annotations'  => array( 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => false,
				),
			)
		);

		wp_register_ability(
			self::ABILITY_EDIT_SECTION,
			array(
				'label'               => 'Edit Section',
				'description'         => 'Replace a top-level section on an Elementor page by index or element ID. Use get_page_structure first to find the target.',
				'category'            => self::CATEGORY,
				'input_schema'        => Tools\EditSection::input_schema(),
				'output_schema'       => Tools\EditSection::output_schema(),
				'execute_callback'    => array( Tools\EditSection::class, 'execute' ),
				'permission_callback' => array( Tools\EditSection::class, 'permission' ),
				'meta'                => array(
					'annotations'  => array( 'destructive' => true, 'idempotent' => false ),
					'show_in_rest' => false,
				),
			)
		);

		wp_register_ability(
			self::ABILITY_DELETE_SECTION,
			array(
				'label'               => 'Delete Section',
				'description'         => 'Remove a top-level section from an Elementor page by index or element ID.',
				'category'            => self::CATEGORY,
				'input_schema'        => Tools\DeleteSection::input_schema(),
				'output_schema'       => Tools\DeleteSection::output_schema(),
				'execute_callback'    => array( Tools\DeleteSection::class, 'execute' ),
				'permission_callback' => array( Tools\DeleteSection::class, 'permission' ),
				'meta'                => array(
					'annotations'  => array( 'destructive' => true, 'idempotent' => false ),
					'show_in_rest' => false,
				),
			)
		);

		wp_register_ability(
			self::ABILITY_REORDER_SECTIONS,
			array(
				'label'               => 'Reorder Sections',
				'description'         => 'Change the order of top-level sections on an Elementor page. Provide the desired order as an array of current section indices.',
				'category'            => self::CATEGORY,
				'input_schema'        => Tools\ReorderSections::input_schema(),
				'output_schema'       => Tools\ReorderSections::output_schema(),
				'execute_callback'    => array( Tools\ReorderSections::class, 'execute' ),
				'permission_callback' => array( Tools\ReorderSections::class, 'permission' ),
				'meta'                => array(
					'annotations'  => array( 'destructive' => true, 'idempotent' => false ),
					'show_in_rest' => false,
				),
			)
		);

		wp_register_ability(
			self::ABILITY_UPDATE_WIDGET,
			array(
				'label'               => 'Update Widget',
				'description'         => 'Update a widget\'s settings by element ID. Merges new settings into existing. Use get_page_structure to find widget IDs.',
				'category'            => self::CATEGORY,
				'input_schema'        => Tools\UpdateWidget::input_schema(),
				'output_schema'       => Tools\UpdateWidget::output_schema(),
				'execute_callback'    => array( Tools\UpdateWidget::class, 'execute' ),
				'permission_callback' => array( Tools\UpdateWidget::class, 'permission' ),
				'meta'                => array(
					'annotations'  => array( 'destructive' => true, 'idempotent' => false ),
					'show_in_rest' => false,
				),
			)
		);

		wp_register_ability(
			self::ABILITY_DUPLICATE_SECTION,
			array(
				'label'               => 'Duplicate Section',
				'description'         => 'Deep-clone a top-level section with new IDs and insert it after the original or at a specified position.',
				'category'            => self::CATEGORY,
				'input_schema'        => Tools\DuplicateSection::input_schema(),
				'output_schema'       => Tools\DuplicateSection::output_schema(),
				'execute_callback'    => array( Tools\DuplicateSection::class, 'execute' ),
				'permission_callback' => array( Tools\DuplicateSection::class, 'permission' ),
				'meta'                => array(
					'annotations'  => array( 'destructive' => true, 'idempotent' => false ),
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
				self::ABILITY_MANAGE_SLIDER,
				self::ABILITY_SET_KIT_GLOBALS,
				self::ABILITY_CREATE_HEADER,
				self::ABILITY_CREATE_FOOTER,
				self::ABILITY_GET_PAGE_STRUCTURE,
				self::ABILITY_EDIT_SECTION,
				self::ABILITY_DELETE_SECTION,
				self::ABILITY_REORDER_SECTIONS,
				self::ABILITY_UPDATE_WIDGET,
				self::ABILITY_DUPLICATE_SECTION,
			),
			array(),
			array(),
			static function (): bool {
				return current_user_can( 'manage_options' );
			}
		);
	}
}
