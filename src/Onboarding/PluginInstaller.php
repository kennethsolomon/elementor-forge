<?php
/**
 * Thin wrapper around WordPress's Plugin_Upgrader install/activate flow.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Onboarding;

use WP_Error;

/**
 * Handles one-shot installs from wp.org for plugins the wizard needs. Wraps
 * WordPress core's `plugins_api()` + `Plugin_Upgrader::install()` + `activate_plugin()`
 * so the wizard doesn't directly touch the upgrader API — all filesystem,
 * capability, and nonce concerns funnel through this one class.
 *
 * Returns a {@see WP_Error} on any failure; the wizard surfaces the error
 * message to the user and lets them retry without rolling the whole wizard
 * back.
 */
final class PluginInstaller implements PluginInstallerInterface {

	/**
	 * Install a plugin from wp.org and activate it.
	 *
	 * @return true|WP_Error
	 */
	public function install_and_activate( string $slug, string $file ) {
		if ( $this->is_active( $file ) ) {
			return true;
		}

		if ( ! $this->is_installed( $file ) ) {
			$installed = $this->install_from_wporg( $slug );
			if ( is_wp_error( $installed ) ) {
				return $installed;
			}
		}

		return $this->activate( $file );
	}

	/**
	 * Pull a plugin zip from wp.org and unpack it.
	 *
	 * @return true|WP_Error
	 */
	public function install_from_wporg( string $slug ) {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return new WP_Error( 'elementor_forge_install_denied', 'Current user cannot install plugins.' );
		}

		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}
		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		}
		if ( ! class_exists( 'WP_Ajax_Upgrader_Skin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
		}

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => array( 'sections' => false ),
			)
		);

		if ( is_wp_error( $api ) ) {
			return $api;
		}
		if ( ! isset( $api->download_link ) ) {
			return new WP_Error( 'elementor_forge_install_no_link', sprintf( 'Plugin "%s" has no download link.', $slug ) );
		}

		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( is_wp_error( $skin->result ) ) {
			return $skin->result;
		}
		if ( false === $result || null === $result ) {
			return new WP_Error( 'elementor_forge_install_failed', sprintf( 'Installation of "%s" failed.', $slug ) );
		}

		return true;
	}

	/**
	 * Activate a plugin by its file path.
	 *
	 * @return true|WP_Error
	 */
	public function activate( string $file ) {
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$result = activate_plugin( $file );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return true;
	}

	public function is_installed( string $file ): bool {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all = get_plugins();
		return isset( $all[ $file ] );
	}

	public function is_active( string $file ): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( $file );
	}
}
