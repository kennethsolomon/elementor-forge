<?php
/**
 * Vendored copy of the WordPress Abilities API WP_Ability_Categories_Registry class.
 *
 * Guarded by class_exists() so core's future inclusion wins automatically.
 * Source: https://github.com/WordPress/abilities-api (GPL-2.0-or-later)
 *
 * @package ElementorForge
 * @license GPL-2.0-or-later
 *
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName
 * phpcs:disable Squiz.Commenting.FunctionComment.Missing
 * phpcs:disable Squiz.Commenting.VariableComment.Missing
 * phpcs:disable Squiz.Commenting.ClassComment.Missing
 */

declare(strict_types=1);

if ( class_exists( 'WP_Ability_Categories_Registry' ) ) {
	return;
}

/**
 * Manages the registration and lookup of ability categories.
 */
final class WP_Ability_Categories_Registry {
 // phpcs:ignore WordPress.NamingConventions.ValidClassName.NotSnakeCase

	/** @var self|null */
	private static $instance = null;

	/** @var array<string, WP_Ability_Category> */
	private $registered_categories = array();

	/**
	 * @param array<string, mixed> $args
	 */
	public function register( string $slug, array $args ): ?WP_Ability_Category {
		if ( $this->is_registered( $slug ) ) {
			return null;
		}
		if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ) {
			return null;
		}

		$args = apply_filters( 'wp_register_ability_category_args', $args, $slug );

		try {
			$category = new WP_Ability_Category( $slug, $args );
		} catch ( InvalidArgumentException $e ) {
			return null;
		}

		$this->registered_categories[ $slug ] = $category;
		return $category;
	}

	public function unregister( string $slug ): ?WP_Ability_Category {
		if ( ! $this->is_registered( $slug ) ) {
			return null;
		}
		$unregistered = $this->registered_categories[ $slug ];
		unset( $this->registered_categories[ $slug ] );
		return $unregistered;
	}

	/**
	 * @return array<string, WP_Ability_Category>
	 */
	public function get_all_registered(): array {
		return $this->registered_categories;
	}

	public function is_registered( string $slug ): bool {
		return isset( $this->registered_categories[ $slug ] );
	}

	public function get_registered( string $slug ): ?WP_Ability_Category {
		return $this->registered_categories[ $slug ] ?? null;
	}

	public static function get_instance(): ?self {
		if ( ! did_action( 'init' ) ) {
			return null;
		}

		if ( null === self::$instance ) {
			self::$instance = new self();
			do_action( 'wp_abilities_api_categories_init', self::$instance );
		}

		return self::$instance;
	}

	public function __wakeup(): void {
		throw new LogicException( __CLASS__ . ' should never be unserialized.' );
	}

	public function __sleep(): array {
		throw new LogicException( __CLASS__ . ' should never be serialized.' );
	}
}
