<?php
/**
 * First-activation onboarding wizard.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Onboarding;

use ElementorForge\ACF\Registrar as AcfRegistrar;
use ElementorForge\Admin\Settings\Page as SettingsPage;
use ElementorForge\Elementor\SectionLibrary;
use ElementorForge\Elementor\ThemeBuilder\Installer as TemplatesInstaller;
use WP_Error;

/**
 * Vanilla-PHP wizard UI at `Elementor Forge > Setup`. Drives the first-run
 * setup flow:
 *
 *   1. Dependency auto-install from wp.org (Elementor, ACF, CF7, Smart Slider 3,
 *      optional WooCommerce + FiboSearch). Elementor Pro shown as manual upload.
 *   2. Section template library install (~12 reusable section templates).
 *   3. Theme Builder Single + Header + Footer templates installed.
 *   4. ACF field groups registered for the current `acf_mode`.
 *   5. Onboarding-complete option written so this screen hides itself afterwards.
 *
 * Each step is idempotent so users can safely rerun the wizard at any time
 * from the settings page. Steps that fail mid-way surface a `WP_Error`
 * message and let the user retry the step without rolling back the others.
 */
final class Wizard {

	public const OPTION_COMPLETE = 'elementor_forge_onboarding_complete';
	public const NONCE_ACTION    = 'elementor_forge_wizard';
	public const NONCE_FIELD     = 'elementor_forge_wizard_nonce';
	public const ACTION_SLUG     = 'elementor_forge_wizard_step';

	/** @var PluginInstallerInterface|null */
	private ?PluginInstallerInterface $installer;

	public function __construct( ?PluginInstallerInterface $installer = null ) {
		$this->installer = $installer;
	}

	public function boot(): void {
		add_action( 'admin_post_' . self::ACTION_SLUG, array( $this, 'handle_step' ) );
	}

	private function installer(): PluginInstallerInterface {
		if ( null === $this->installer ) {
			$this->installer = new PluginInstaller();
		}
		return $this->installer;
	}

	public static function is_complete(): bool {
		return (bool) get_option( self::OPTION_COMPLETE, false );
	}

	/**
	 * Render the wizard screen. Called by the Settings menu registration.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$step = isset( $_GET['step'] ) ? sanitize_key( (string) $_GET['step'] ) : 'welcome'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap"><h1>Elementor Forge Setup</h1>';

		if ( 'success' === $status ) {
			echo '<div class="notice notice-success"><p>' . esc_html( $message ) . '</p></div>';
		} elseif ( 'error' === $status ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		}

		if ( self::is_complete() && 'welcome' === $step ) {
			echo '<div class="notice notice-info"><p>Onboarding is already complete. You can rerun any step manually below.</p></div>';
		}

		self::render_step_nav( $step );

		switch ( $step ) {
			case 'dependencies':
				self::render_dependencies_step();
				break;
			case 'templates':
				self::render_templates_step();
				break;
			case 'done':
				self::render_done_step();
				break;
			case 'welcome':
			default:
				self::render_welcome_step();
				break;
		}

		echo '</div>';
	}

	private static function render_step_nav( string $current ): void {
		$steps = array(
			'welcome'      => '1. Welcome',
			'dependencies' => '2. Dependencies',
			'templates'    => '3. Templates',
			'done'         => '4. Done',
		);
		echo '<ol class="elementor-forge-steps">';
		foreach ( $steps as $slug => $label ) {
			$active = $slug === $current ? ' style="font-weight:bold"' : '';
			echo '<li' . $active . '>' . esc_html( $label ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</ol>';
	}

	private static function render_welcome_step(): void {
		$next_url = add_query_arg( array( 'page' => 'elementor-forge-setup', 'step' => 'dependencies' ), admin_url( 'admin.php' ) );
		echo '<p>Elementor Forge will install required dependencies and build your Theme Builder templates. This takes about two minutes.</p>';
		echo '<a class="button button-primary" href="' . esc_url( $next_url ) . '">Start</a>';
	}

	private static function render_dependencies_step(): void {
		$installer = new PluginInstaller();
		echo '<h2>Dependencies</h2><table class="widefat striped"><thead><tr><th>Plugin</th><th>Status</th><th>Action</th></tr></thead><tbody>';
		foreach ( Dependencies::all() as $dep ) {
			$installed = $installer->is_installed( $dep['file'] );
			$active    = $installer->is_active( $dep['file'] );
			$status    = $active ? 'Active' : ( $installed ? 'Installed (inactive)' : 'Missing' );
			$action    = '';
			if ( ! $active ) {
				if ( $dep['auto_install'] ) {
					$action = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
					$action .= wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false );
					$action .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SLUG ) . '" />';
					$action .= '<input type="hidden" name="wizard_step" value="install_dependency" />';
					$action .= '<input type="hidden" name="dependency_slug" value="' . esc_attr( $dep['slug'] ) . '" />';
					$action .= '<input type="hidden" name="dependency_file" value="' . esc_attr( $dep['file'] ) . '" />';
					$action .= '<button type="submit" class="button button-secondary">Install &amp; Activate</button>';
					$action .= '</form>';
				} else {
					$action = '<em>Upload ZIP or paste license key — paid plugin, not on wp.org.</em>';
				}
			}
			echo '<tr><td>' . esc_html( $dep['label'] ) . '</td><td>' . esc_html( $status ) . '</td><td>' . $action . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</tbody></table>';

		$next_url = add_query_arg( array( 'page' => 'elementor-forge-setup', 'step' => 'templates' ), admin_url( 'admin.php' ) );
		echo '<p><a class="button button-primary" href="' . esc_url( $next_url ) . '">Next: install templates</a></p>';
	}

	private static function render_templates_step(): void {
		echo '<h2>Templates</h2>';
		echo '<p>Installs the Theme Builder Single templates for Locations and Services, the service-business Header and Footer, and the reusable section library.</p>';

		$form_url = admin_url( 'admin-post.php' );
		echo '<form method="post" action="' . esc_url( $form_url ) . '">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SLUG ) . '" />';
		echo '<input type="hidden" name="wizard_step" value="install_templates" />';
		submit_button( 'Install templates + ACF field groups' );
		echo '</form>';
	}

	private static function render_done_step(): void {
		echo '<h2>Setup complete</h2>';
		echo '<p>Elementor Forge is ready. You can now use:</p><ul>';
		echo '<li>wp-admin > Locations — manage location CPTs</li>';
		echo '<li>wp-admin > Services — manage service CPTs</li>';
		echo '<li>Elementor > Templates > Theme Builder — edit the installed templates</li>';
		echo '<li>Elementor Forge > Settings — adjust toggles or rerun onboarding</li>';
		echo '</ul>';
		echo '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=' . SettingsPage::MENU_SLUG ) ) . '">Back to settings</a>';
	}

	/**
	 * admin-post.php router. One handler, multiple wizard steps, distinguished
	 * by the `wizard_step` hidden field.
	 */
	public function handle_step(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'elementor-forge' ), '', array( 'response' => 403 ) );
		}
		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'Invalid request.', 'elementor-forge' ), '', array( 'response' => 403 ) );
		}

		$step = isset( $_POST['wizard_step'] ) ? sanitize_key( (string) $_POST['wizard_step'] ) : '';

		switch ( $step ) {
			case 'install_dependency':
				$slug   = isset( $_POST['dependency_slug'] ) ? sanitize_key( (string) $_POST['dependency_slug'] ) : '';
				$file   = isset( $_POST['dependency_file'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['dependency_file'] ) ) : '';
				$result = $this->install_dependency( $slug, $file );
				$this->redirect_with_result( 'dependencies', $result, 'Installed ' . $slug );
				return;

			case 'install_templates':
				try {
					( new TemplatesInstaller() )->install_all();
					// Install reusable section library alongside Theme Builder templates.
					$sections = SectionLibrary::all();
					foreach ( $sections as $spec ) {
						( new TemplatesInstaller() )->install_one( $spec );
					}
					// Trigger ACF registration so the field groups are registered immediately.
					( new AcfRegistrar() )->register_all();
					update_option( self::OPTION_COMPLETE, true, false );
					$this->redirect_with_result( 'done', true, 'Templates installed and onboarding complete.' );
				} catch ( \Throwable $e ) {
					$this->redirect_with_result( 'templates', new WP_Error( 'elementor_forge_templates', $e->getMessage() ), 'Templates failed.' );
				}
				return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=elementor-forge-setup' ) );
		exit;
	}

	/**
	 * Install a wp.org dependency after gating it through the curated allowlist.
	 *
	 * Pure enough to unit-test: the only side effects it performs come from the
	 * injected {@see PluginInstaller}, which tests replace with a mock. Every
	 * path returns either `true` or a {@see WP_Error} so the caller can
	 * surface the outcome to the wizard UI.
	 *
	 * @return true|WP_Error
	 */
	public function install_dependency( string $slug, string $file ) {
		if ( '' === $slug || '' === $file ) {
			return new WP_Error(
				'elementor_forge_dep_missing_params',
				'Dependency slug and file are required.'
			);
		}

		$entry = Dependencies::find( $slug, $file );
		if ( null === $entry ) {
			return new WP_Error(
				'elementor_forge_dep_not_allowlisted',
				sprintf( 'Plugin "%s" is not in the Elementor Forge dependency allowlist.', $slug )
			);
		}
		if ( ! $entry['auto_install'] ) {
			return new WP_Error(
				'elementor_forge_dep_manual_only',
				sprintf( 'Plugin "%s" cannot be auto-installed — upload required.', $slug )
			);
		}

		return $this->installer()->install_and_activate( $entry['slug'], $entry['file'] );
	}

	/**
	 * @param true|WP_Error $result
	 */
	private function redirect_with_result( string $step, $result, string $success_message ): void {
		$args = array( 'page' => 'elementor-forge-setup', 'step' => $step );
		if ( is_wp_error( $result ) ) {
			$args['status']  = 'error';
			$args['message'] = rawurlencode( $result->get_error_message() );
		} else {
			$args['status']  = 'success';
			$args['message'] = rawurlencode( $success_message );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
