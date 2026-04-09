<?php
/**
 * Hello Elementor theme installer — wraps WP core's Theme_Upgrader.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Onboarding;

use WP_Error;

/**
 * Installs and activates the Hello Elementor theme from wp.org.
 *
 * Hello Elementor is the only theme Elementor Pro's Theme Builder supports
 * fully — display conditions, header/footer templates, and the Kit Global
 * cascade all assume Hello Elementor's minimal wrapper. New SDM client sites
 * often arrive with some other theme active; this class exposes a one-shot
 * install + activate path used by both the onboarding wizard and the Settings
 * page "Install Hello Elementor" button.
 *
 * Pure enough to unit-test: every WP core function is accessed through
 * `function_exists()` / `class_exists()` guards so Brain Monkey can stub the
 * surface the installer touches.
 *
 * Capabilities required:
 *
 *   - `install_themes` to download + unpack the zip
 *   - `switch_themes`  to call `switch_theme()` after install
 *
 * Feature-detected:
 *
 *   - `Theme_Upgrader` class must be available — it lives in core's
 *     `wp-admin/includes/class-theme-upgrader.php`. We require_once the file
 *     before instantiating to work from admin-post and CLI contexts alike.
 *   - `themes_api()` function must exist — it lives in `theme.php` in
 *     `wp-admin/includes/`. Same lazy-load dance.
 *
 * Return shape (stable — callers branch on it):
 *
 *   array{installed: bool, activated: bool, already_installed: bool, reason: string}
 */
final class ThemeInstaller {

	public const THEME_SLUG = 'hello-elementor';

	/**
	 * Ensure Hello Elementor is installed. Idempotent — returns a structured
	 * "already installed" result if the theme directory already exists.
	 *
	 * @return array{installed:bool, already_installed:bool, reason:string}|WP_Error
	 */
	public function install_hello_elementor() {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'install_themes' ) ) {
			return new WP_Error(
				'elementor_forge_theme_install_denied',
				'Current user cannot install themes. The install_themes capability is required.'
			);
		}

		// Idempotency gate — if Hello Elementor is already present on disk,
		// skip the download and return a structured no-op. Activation is a
		// separate call so the caller can choose whether to swap themes.
		if ( $this->is_installed() ) {
			return array(
				'installed'         => true,
				'already_installed' => true,
				'reason'            => 'Hello Elementor is already installed.',
			);
		}

		// Lazy-load the upgrader + themes API. Both live in wp-admin/includes
		// and are only autoloaded on admin requests — CLI and admin-post
		// handlers need to pull them explicitly.
		if ( ! function_exists( 'themes_api' ) ) {
			if ( defined( 'ABSPATH' ) ) {
				require_once ABSPATH . 'wp-admin/includes/theme.php';
			}
		}
		if ( ! class_exists( '\Theme_Upgrader' ) ) {
			if ( defined( 'ABSPATH' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
				require_once ABSPATH . 'wp-admin/includes/class-theme-upgrader.php';
			}
		}
		if ( ! class_exists( '\WP_Ajax_Upgrader_Skin' ) ) {
			if ( defined( 'ABSPATH' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
			}
		}

		// If even after the require_once the upgrader class is missing, WP is
		// too old or the admin includes are somehow unavailable. Fail loud.
		if ( ! class_exists( '\Theme_Upgrader' ) || ! function_exists( 'themes_api' ) ) {
			return new WP_Error(
				'elementor_forge_theme_upgrader_missing',
				'WP core Theme_Upgrader or themes_api() is unavailable. WordPress 6.4+ is required.'
			);
		}

		$api = themes_api(
			'theme_information',
			array(
				'slug'   => self::THEME_SLUG,
				'fields' => array( 'sections' => false ),
			)
		);

		if ( $api instanceof WP_Error ) {
			return $api;
		}
		if ( ! is_object( $api ) || ! isset( $api->download_link ) ) {
			return new WP_Error(
				'elementor_forge_theme_install_no_link',
				sprintf( 'Theme "%s" has no download link on wp.org.', self::THEME_SLUG )
			);
		}

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );
		$result   = $upgrader->install( (string) $api->download_link );

		if ( $result instanceof WP_Error ) {
			return $result;
		}
		// WP_Upgrader_Skin::$result holds the result of the upgrader operation
		// and may be a WP_Error if the skin captured the failure before
		// Theme_Upgrader::install() returned. We access it via property_exists
		// so PHPStan does not trip over the fact that the declared property
		// type is non-nullable in recent WP stubs.
		if ( property_exists( $skin, 'result' ) && $skin->result instanceof WP_Error ) {
			return $skin->result;
		}
		if ( false === $result || null === $result ) {
			return new WP_Error(
				'elementor_forge_theme_install_failed',
				sprintf( 'Installation of theme "%s" failed.', self::THEME_SLUG )
			);
		}

		return array(
			'installed'         => true,
			'already_installed' => false,
			'reason'            => 'Hello Elementor downloaded and installed from wp.org.',
		);
	}

	/**
	 * Activate Hello Elementor. Returns WP_Error if the theme is not on disk
	 * or the current user lacks `switch_themes`. Idempotent — if it's already
	 * the active theme, returns a structured no-op.
	 *
	 * @return array{activated:bool, already_active:bool, reason:string}|WP_Error
	 */
	public function activate_hello_elementor() {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'switch_themes' ) ) {
			return new WP_Error(
				'elementor_forge_theme_switch_denied',
				'Current user cannot switch themes. The switch_themes capability is required.'
			);
		}

		if ( ! $this->is_installed() ) {
			return new WP_Error(
				'elementor_forge_theme_not_installed',
				'Hello Elementor is not installed. Call install_hello_elementor() first.'
			);
		}

		if ( $this->is_active() ) {
			return array(
				'activated'      => true,
				'already_active' => true,
				'reason'         => 'Hello Elementor is already the active theme.',
			);
		}

		if ( ! function_exists( 'switch_theme' ) ) {
			return new WP_Error(
				'elementor_forge_switch_theme_missing',
				'WP core switch_theme() is unavailable.'
			);
		}

		switch_theme( self::THEME_SLUG );

		// Verify the swap landed. switch_theme() returns void so the only way
		// to confirm is to re-read the active stylesheet afterwards. We query
		// wp_get_theme() directly here rather than calling $this->is_active()
		// because PHPStan memoizes pure-method calls on $this and would flag
		// the post-switch re-check as "always false".
		$post_switch       = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
		$post_stylesheet   = '';
		if ( is_object( $post_switch ) && method_exists( $post_switch, 'get_stylesheet' ) ) {
			$post_stylesheet = (string) $post_switch->get_stylesheet();
		}
		if ( self::THEME_SLUG !== $post_stylesheet ) {
			return new WP_Error(
				'elementor_forge_theme_switch_failed',
				'switch_theme() did not activate Hello Elementor. Check for a broken theme install or an active theme-switch filter.'
			);
		}

		return array(
			'activated'      => true,
			'already_active' => false,
			'reason'         => 'Hello Elementor activated via switch_theme().',
		);
	}

	/**
	 * One-shot install + activate. Runs install_hello_elementor(), and if that
	 * succeeds (including the "already installed" path), runs
	 * activate_hello_elementor(). Returns a flattened result shape the
	 * settings page + wizard both render identically.
	 *
	 * @return array{installed:bool, activated:bool, already_installed:bool, already_active:bool, reason:string}|WP_Error
	 */
	public function install_and_activate() {
		$install = $this->install_hello_elementor();
		if ( $install instanceof WP_Error ) {
			return $install;
		}

		$activate = $this->activate_hello_elementor();
		if ( $activate instanceof WP_Error ) {
			return $activate;
		}

		return array(
			'installed'         => (bool) $install['installed'],
			'activated'         => (bool) $activate['activated'],
			'already_installed' => (bool) $install['already_installed'],
			'already_active'    => (bool) $activate['already_active'],
			'reason'            => $install['reason'] . ' ' . $activate['reason'],
		);
	}

	/**
	 * Whether Hello Elementor's files exist on disk. Checked by fetching the
	 * WP_Theme instance for the slug and asking if it exists() — this covers
	 * both parent themes and installs under non-standard theme directories.
	 */
	public function is_installed(): bool {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return false;
		}
		$theme = wp_get_theme( self::THEME_SLUG );
		return is_object( $theme ) && method_exists( $theme, 'exists' ) && (bool) $theme->exists();
	}

	/**
	 * Whether Hello Elementor is the currently active theme (stylesheet). We
	 * check the stylesheet, not the template, because a child theme scenario
	 * would have `hello-elementor` as template and the child as stylesheet —
	 * in that case Hello Elementor is effectively active for Theme Builder
	 * purposes and this method should still return true. We intentionally
	 * check both to be robust.
	 */
	public function is_active(): bool {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return false;
		}
		$current = wp_get_theme();
		if ( ! is_object( $current ) ) {
			return false;
		}
		$stylesheet = method_exists( $current, 'get_stylesheet' ) ? (string) $current->get_stylesheet() : '';
		$template   = method_exists( $current, 'get_template' ) ? (string) $current->get_template() : '';
		return self::THEME_SLUG === $stylesheet || self::THEME_SLUG === $template;
	}
}
