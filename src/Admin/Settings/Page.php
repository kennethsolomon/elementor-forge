<?php
/**
 * wp-admin settings page for Elementor Forge.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Admin\Settings;

use ElementorForge\Settings\Defaults;
use ElementorForge\Settings\OptionKeys;
use ElementorForge\Settings\Store;

/**
 * Single wp-admin settings page with four toggles, a "rerun onboarding" action,
 * a "rebuild templates" action, and a read-only debug panel. Uses the WP
 * Settings API for the toggles so WordPress handles nonce validation, capability
 * checks, and sanitization on the option update path.
 */
final class Page {

	public const MENU_SLUG  = 'elementor-forge';
	public const PAGE_HOOK  = 'toplevel_page_elementor-forge';
	public const SECTION_ID = 'elementor_forge_main';
	public const NONCE_ACTION = 'elementor_forge_admin_action';
	public const NONCE_FIELD  = 'elementor_forge_admin_nonce';

	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_elementor_forge_rerun_onboarding', array( $this, 'handle_rerun_onboarding' ) );
		add_action( 'admin_post_elementor_forge_rebuild_templates', array( $this, 'handle_rebuild_templates' ) );
	}

	public function register_menu(): void {
		add_menu_page(
			'Elementor Forge',
			'Elementor Forge',
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-hammer',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Settings',
			'Settings',
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			'Setup Wizard',
			'Setup',
			'manage_options',
			'elementor-forge-setup',
			array( \ElementorForge\Onboarding\Wizard::class, 'render' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'elementor_forge_settings_group',
			OptionKeys::SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Store::class, 'sanitize' ),
				'default'           => Defaults::all(),
			)
		);

		add_settings_section(
			self::SECTION_ID,
			'Core behavior',
			static function (): void {
				echo '<p>Four locked toggles. Change at your own risk.</p>';
			},
			self::MENU_SLUG
		);

		add_settings_field(
			'acf_mode',
			'ACF mode',
			array( $this, 'render_acf_mode_field' ),
			self::MENU_SLUG,
			self::SECTION_ID
		);
		add_settings_field(
			'ucaddon_shim',
			'ucaddon compat shim',
			array( $this, 'render_ucaddon_shim_field' ),
			self::MENU_SLUG,
			self::SECTION_ID
		);
		add_settings_field(
			'mcp_server',
			'MCP server',
			array( $this, 'render_mcp_server_field' ),
			self::MENU_SLUG,
			self::SECTION_ID
		);
		add_settings_field(
			'header_pattern',
			'Header pattern',
			array( $this, 'render_header_pattern_field' ),
			self::MENU_SLUG,
			self::SECTION_ID
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = Store::all();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Elementor Forge', 'elementor-forge' ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'elementor_forge_settings_group' );
				do_settings_sections( self::MENU_SLUG );
				submit_button( esc_html__( 'Save settings', 'elementor-forge' ) );
				?>
			</form>

			<hr />
			<h2><?php echo esc_html__( 'Actions', 'elementor-forge' ); ?></h2>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:inline">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
				<input type="hidden" name="action" value="elementor_forge_rerun_onboarding" />
				<?php submit_button( esc_html__( 'Rerun onboarding wizard', 'elementor-forge' ), 'secondary', 'submit', false ); ?>
			</form>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:inline; margin-left:0.5em">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
				<input type="hidden" name="action" value="elementor_forge_rebuild_templates" />
				<?php submit_button( esc_html__( 'Rebuild Theme Builder templates', 'elementor-forge' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr />
			<h2><?php echo esc_html__( 'Debug', 'elementor-forge' ); ?></h2>
			<?php
			$debug_json = wp_json_encode( $settings, JSON_PRETTY_PRINT );
			if ( false === $debug_json ) {
				$debug_json = '';
			}
			?>
			<pre><?php echo esc_html( $debug_json ); ?></pre>
		</div>
		<?php
	}

	public function render_acf_mode_field(): void {
		$current = Store::get( 'acf_mode' );
		$name    = OptionKeys::SETTINGS . '[acf_mode]';
		printf(
			'<select name="%s"><option value="free" %s>Free (related CPT + Loop Grid)</option><option value="pro" %s>Pro (repeaters)</option></select>',
			esc_attr( $name ),
			selected( $current, Defaults::ACF_MODE_FREE, false ),
			selected( $current, Defaults::ACF_MODE_PRO, false )
		);
	}

	public function render_ucaddon_shim_field(): void {
		$current = Store::get( 'ucaddon_shim' );
		$name    = OptionKeys::SETTINGS . '[ucaddon_shim]';
		printf(
			'<select name="%s"><option value="preserve" %s>Preserve on update</option><option value="strip" %s>Strip on update</option></select>',
			esc_attr( $name ),
			selected( $current, Defaults::UCADDON_SHIM_PRESERVE, false ),
			selected( $current, Defaults::UCADDON_SHIM_STRIP, false )
		);
	}

	public function render_mcp_server_field(): void {
		$current = Store::get( 'mcp_server' );
		$name    = OptionKeys::SETTINGS . '[mcp_server]';
		printf(
			'<select name="%s"><option value="enabled" %s>Enabled</option><option value="disabled" %s>Disabled</option></select>',
			esc_attr( $name ),
			selected( $current, Defaults::MCP_SERVER_ENABLED, false ),
			selected( $current, Defaults::MCP_SERVER_DISABLED, false )
		);
	}

	public function render_header_pattern_field(): void {
		$current = Store::get( 'header_pattern' );
		$name    = OptionKeys::SETTINGS . '[header_pattern]';
		printf(
			'<select name="%s"><option value="service_business" %s>Service Business</option><option value="ecommerce" %s>Ecommerce (Phase 2)</option></select>',
			esc_attr( $name ),
			selected( $current, Defaults::HEADER_PATTERN_SERVICE_BUSINESS, false ),
			selected( $current, Defaults::HEADER_PATTERN_ECOMMERCE, false )
		);
	}

	public function handle_rerun_onboarding(): void {
		$this->verify_admin_post();
		delete_option( \ElementorForge\Onboarding\Wizard::OPTION_COMPLETE );
		wp_safe_redirect( admin_url( 'admin.php?page=elementor-forge-setup' ) );
		exit;
	}

	public function handle_rebuild_templates(): void {
		$this->verify_admin_post();
		( new \ElementorForge\Elementor\ThemeBuilder\Installer() )->install_all();
		wp_safe_redirect( admin_url( 'admin.php?page=elementor-forge&rebuilt=1' ) );
		exit;
	}

	private function verify_admin_post(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'elementor-forge' ), '', array( 'response' => 403 ) );
		}
		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Invalid request.', 'elementor-forge' ), '', array( 'response' => 403 ) );
		}
	}
}
