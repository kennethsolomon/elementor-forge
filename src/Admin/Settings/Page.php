<?php
/**
 * wp-admin settings page for Elementor Forge.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Admin\Settings;

use ElementorForge\Intelligence\LayoutJudge\LayoutJudge;
use ElementorForge\Safety\Gate;
use ElementorForge\Safety\Mode;
use ElementorForge\Settings\Defaults;
use ElementorForge\Settings\OptionKeys;
use ElementorForge\Settings\Store;
use ElementorForge\SmartSlider\SliderRepository;
use ElementorForge\WooCommerce\WooCommerce;

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
		add_action( 'admin_post_elementor_forge_wc_install_templates', array( $this, 'handle_wc_install_templates' ) );
		add_action( 'admin_post_elementor_forge_wc_apply_fibosearch', array( $this, 'handle_wc_apply_fibosearch' ) );
		add_action( 'admin_post_elementor_forge_wc_switch_header', array( $this, 'handle_wc_switch_header' ) );
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

		add_settings_section(
			'elementor_forge_safety',
			'Safety — scope mode',
			static function (): void {
				echo '<p>' . esc_html__( 'Controls the blast radius of every MCP write tool. Default is Full (backwards-compatible). Switch to Page-only when installing on a client site to restrict add_section to specific posts. Read-only disables every write tool for diagnostic runs.', 'elementor-forge' ) . '</p>';
			},
			self::MENU_SLUG
		);
		add_settings_field(
			'safety_mode',
			'Scope mode',
			array( $this, 'render_safety_mode_field' ),
			self::MENU_SLUG,
			'elementor_forge_safety'
		);
		add_settings_field(
			'safety_allowed_post_ids',
			'Allowed post IDs',
			array( $this, 'render_safety_allowlist_field' ),
			self::MENU_SLUG,
			'elementor_forge_safety'
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
			<?php $this->render_woocommerce_section(); ?>

			<hr />
			<?php $this->render_intelligence_section(); ?>

			<hr />
			<?php $this->render_safety_status_panel(); ?>

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

	/**
	 * Render the WooCommerce sub-section — detection panel + three action
	 * buttons (install WC templates, apply Fibosearch defaults, switch to
	 * ecommerce header). Every button is an admin-post form protected by the
	 * shared admin-action nonce.
	 */
	private function render_woocommerce_section(): void {
		$wc     = new WooCommerce();
		$report = $wc->report();
		?>
		<h2><?php echo esc_html__( 'WooCommerce', 'elementor-forge' ); ?></h2>
		<table class="widefat striped" style="max-width:700px">
			<tbody>
				<tr>
					<th><?php echo esc_html__( 'WooCommerce detected', 'elementor-forge' ); ?></th>
					<td><?php echo $report['wc_active'] ? 'Yes (' . esc_html( $report['wc_version'] ) . ')' : 'No'; ?></td>
				</tr>
				<tr>
					<th><?php echo esc_html__( 'WC templates installed', 'elementor-forge' ); ?></th>
					<td><?php echo esc_html( (string) $report['wc_templates_installed'] . ' / ' . (string) $report['wc_templates_total'] ); ?></td>
				</tr>
				<tr>
					<th><?php echo esc_html__( 'Fibosearch detected', 'elementor-forge' ); ?></th>
					<td><?php echo $report['fibosearch']['detected'] ? 'Yes (' . esc_html( $report['fibosearch']['version'] ) . ')' : 'No'; ?></td>
				</tr>
				<tr>
					<th><?php echo esc_html__( 'Fibosearch defaults applied', 'elementor-forge' ); ?></th>
					<td><?php echo $report['fibosearch']['has_been_applied'] ? 'Yes' : 'No'; ?></td>
				</tr>
				<tr>
					<th><?php echo esc_html__( 'Header variant active', 'elementor-forge' ); ?></th>
					<td><?php echo esc_html( $report['header_variant_active'] ); ?></td>
				</tr>
			</tbody>
		</table>
		<p>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:inline">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
				<input type="hidden" name="action" value="elementor_forge_wc_install_templates" />
				<?php submit_button( esc_html__( 'Install WC Theme Builder templates', 'elementor-forge' ), 'secondary', 'submit', false ); ?>
			</form>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:inline; margin-left:0.5em">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
				<input type="hidden" name="action" value="elementor_forge_wc_apply_fibosearch" />
				<?php submit_button( esc_html__( 'Apply Fibosearch defaults', 'elementor-forge' ), 'secondary', 'submit', false ); ?>
			</form>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:inline; margin-left:0.5em">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
				<input type="hidden" name="action" value="elementor_forge_wc_switch_header" />
				<?php submit_button( esc_html__( 'Switch header to ecommerce', 'elementor-forge' ), 'secondary', 'submit', false ); ?>
			</form>
		</p>
		<?php
	}

	/**
	 * Render the Intelligence Layer sub-section — surfaces LayoutJudge rule
	 * count, Smart Slider 3 detection status, and the most recent bulk
	 * generate job status (read from the transient log).
	 */
	private function render_intelligence_section(): void {
		$judge = LayoutJudge::with_default_rules();

		$ss3_present = defined( 'NEXTEND_SMARTSLIDER_3_URL_PATH' );
		$ss3_version = '';
		$ss3_supported = false;
		if ( $ss3_present ) {
			global $wpdb;
			if ( isset( $wpdb ) ) {
				$repo          = new SliderRepository( $wpdb );
				$ss3_version   = $repo->detect_version();
				$ss3_supported = $repo->is_available();
			}
		}

		$cache_dirty = (bool) get_option( OptionKeys::SS3_CACHE_DIRTY, false );
		?>
		<h2><?php echo esc_html__( 'Intelligence Layer', 'elementor-forge' ); ?></h2>
		<table class="widefat striped" style="max-width:700px">
			<tbody>
				<tr>
					<th><?php echo esc_html__( 'Layout judge rules loaded', 'elementor-forge' ); ?></th>
					<td><?php echo esc_html( (string) $judge->rule_count() ); ?></td>
				</tr>
				<tr>
					<th><?php echo esc_html__( 'Smart Slider 3 detected', 'elementor-forge' ); ?></th>
					<td><?php echo $ss3_present ? 'Yes' : 'No'; ?></td>
				</tr>
				<tr>
					<th><?php echo esc_html__( 'Smart Slider 3 version', 'elementor-forge' ); ?></th>
					<td><?php echo esc_html( '' === $ss3_version ? 'unknown' : $ss3_version ); ?></td>
				</tr>
				<tr>
					<th><?php echo esc_html__( 'Smart Slider 3 supported', 'elementor-forge' ); ?></th>
					<td>
						<?php
						if ( $ss3_supported ) {
							echo 'Yes';
						} elseif ( $ss3_present ) {
							echo esc_html( sprintf( 'No — outside %s..<%s', SliderRepository::SUPPORTED_MIN, SliderRepository::SUPPORTED_MAX ) );
						} else {
							echo 'N/A';
						}
						?>
					</td>
				</tr>
				<tr>
					<th><?php echo esc_html__( 'Smart Slider cache flag', 'elementor-forge' ); ?></th>
					<td><?php echo $cache_dirty ? esc_html__( 'Dirty — clear from Smart Slider admin', 'elementor-forge' ) : esc_html__( 'Clean', 'elementor-forge' ); ?></td>
				</tr>
			</tbody>
		</table>
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

	/**
	 * Render the three scope mode radio options with color-coded badges and a
	 * one-sentence description of each mode's blast radius.
	 */
	public function render_safety_mode_field(): void {
		$current = Store::safety_mode();
		$name    = OptionKeys::SETTINGS . '[safety_mode]';
		$descriptions = array(
			Mode::FULL      => __( 'All tools enabled. Wizard runs. Site-wide actions allowed. Default for new installs.', 'elementor-forge' ),
			Mode::PAGE_ONLY => __( 'Wizard disabled. configure_woocommerce rejected. add_section only modifies posts in the allowlist below.', 'elementor-forge' ),
			Mode::READ_ONLY => __( 'All MCP write tools return WP_Error. Diagnostic mode for locked-down client sites.', 'elementor-forge' ),
		);
		echo '<fieldset>';
		foreach ( Mode::all() as $mode_value ) {
			$id = 'elementor_forge_safety_mode_' . $mode_value;
			printf(
				'<label for="%s" style="display:block;margin-bottom:0.5em"><input type="radio" id="%s" name="%s" value="%s" %s /> <strong style="color:%s">%s</strong> — %s</label>',
				esc_attr( $id ),
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $mode_value ),
				checked( $current, $mode_value, false ),
				esc_attr( $this->color_to_css( Mode::color( $mode_value ) ) ),
				esc_html( Mode::label( $mode_value ) ),
				esc_html( $descriptions[ $mode_value ] )
			);
		}
		echo '</fieldset>';
	}

	/**
	 * Render the post ID allowlist CSV input with inline help.
	 */
	public function render_safety_allowlist_field(): void {
		$current = Store::get( 'safety_allowed_post_ids' );
		$name    = OptionKeys::SETTINGS . '[safety_allowed_post_ids]';
		printf(
			'<input type="text" name="%s" value="%s" class="regular-text" placeholder="52, 101, 150" />',
			esc_attr( $name ),
			esc_attr( $current )
		);
		echo '<p class="description">' . esc_html__( 'Comma-separated post IDs. In page_only mode, add_section will only modify these posts. Leave empty to block all add_section calls in page_only mode.', 'elementor-forge' ) . '</p>';
	}

	/**
	 * Render a read-only panel showing the current scope mode + a per-tool
	 * matrix of allowed/rejected status. Lets Kenneth verify at a glance what
	 * the gate is enforcing without opening the code.
	 */
	private function render_safety_status_panel(): void {
		$mode      = Gate::current_mode();
		$allowlist = Store::safety_allowlist();
		$color     = Mode::color( $mode );
		?>
		<h2><?php echo esc_html__( 'Safety — current gate status', 'elementor-forge' ); ?></h2>
		<p>
			<?php echo esc_html__( 'Active mode:', 'elementor-forge' ); ?>
			<strong style="color:<?php echo esc_attr( $this->color_to_css( $color ) ); ?>">
				<?php echo esc_html( Mode::label( $mode ) ); ?>
			</strong>
		</p>
		<p>
			<?php echo esc_html__( 'Allowlist:', 'elementor-forge' ); ?>
			<code><?php echo esc_html( $allowlist->is_empty() ? __( '(empty)', 'elementor-forge' ) : $allowlist->to_string() ); ?></code>
		</p>
		<table class="widefat striped" style="max-width:700px">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Tool', 'elementor-forge' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'elementor-forge' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$matrix = array(
					'create_page'          => Gate::check( 'create_page', Gate::ACTION_CREATE ),
					'add_section'          => Gate::check( 'add_section', Gate::ACTION_MODIFY, $allowlist->is_empty() ? null : $allowlist->to_array()[0] ),
					'apply_template'       => Gate::check( 'apply_template', Gate::ACTION_CREATE ),
					'bulk_generate_pages'  => Gate::check( 'bulk_generate_pages', Gate::ACTION_CREATE ),
					'configure_woocommerce' => Gate::check( 'configure_woocommerce', Gate::ACTION_SITE_WIDE ),
					'manage_slider'        => Gate::check( 'manage_slider', Gate::ACTION_MODIFY ),
				);
				foreach ( $matrix as $tool => $result ) {
					$is_allowed = ( true === $result );
					$label      = $is_allowed ? __( 'ALLOWED', 'elementor-forge' ) : __( 'REJECTED', 'elementor-forge' );
					$style      = $is_allowed ? 'color:green' : 'color:red';
					$reason     = '';
					if ( ! $is_allowed ) {
						// Gate::check returns true|WP_Error, so !$is_allowed means $result is WP_Error.
						$reason = ' — ' . $result->get_error_code();
					}
					printf(
						'<tr><td><code>%s</code></td><td style="%s"><strong>%s</strong>%s</td></tr>',
						esc_html( $tool ),
						esc_attr( $style ),
						esc_html( $label ),
						esc_html( $reason )
					);
				}
				?>
			</tbody>
		</table>
		<p>
			<?php echo esc_html__( 'Wizard enabled:', 'elementor-forge' ); ?>
			<strong><?php echo Gate::is_wizard_enabled() ? esc_html__( 'Yes', 'elementor-forge' ) : esc_html__( 'No (disabled in current mode)', 'elementor-forge' ); ?></strong>
		</p>
		<?php
	}

	/**
	 * Map the {@see Mode::color()} opaque token to an actual hex color for
	 * inline styles. The token → hex mapping lives here so Mode stays free of
	 * UI dependencies.
	 */
	private function color_to_css( string $token ): string {
		switch ( $token ) {
			case 'green':
				return '#2e7d32';
			case 'yellow':
				return '#b58900';
			case 'red':
				return '#b71c1c';
			default:
				return '#555555';
		}
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

	public function handle_wc_install_templates(): void {
		$this->verify_admin_post();
		( new WooCommerce() )->install_templates();
		wp_safe_redirect( admin_url( 'admin.php?page=elementor-forge&wc_templates=1' ) );
		exit;
	}

	public function handle_wc_apply_fibosearch(): void {
		$this->verify_admin_post();
		( new WooCommerce() )->apply_fibosearch_defaults();
		wp_safe_redirect( admin_url( 'admin.php?page=elementor-forge&fibosearch=1' ) );
		exit;
	}

	public function handle_wc_switch_header(): void {
		$this->verify_admin_post();
		( new WooCommerce() )->switch_to_ecommerce_header();
		wp_safe_redirect( admin_url( 'admin.php?page=elementor-forge&header=ecommerce' ) );
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
