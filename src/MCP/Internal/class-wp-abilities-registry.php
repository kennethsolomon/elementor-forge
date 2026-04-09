<?php
/**
 * Vendored copy of the WordPress Abilities API WP_Abilities_Registry class.
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

if ( class_exists( 'WP_Abilities_Registry' ) ) {
	return;
}

/**
 * Manages the registration and lookup of abilities.
 */
final class WP_Abilities_Registry {
 // phpcs:ignore WordPress.NamingConventions.ValidClassName.NotSnakeCase

	/** @var self|null */
	private static $instance = null;

	/** @var array<string, WP_Ability> */
	private $registered_abilities = array();

	/**
	 * @param array<string, mixed> $args
	 */
	public function register( string $name, array $args ): ?WP_Ability {
		if ( ! preg_match( '/^[a-z0-9-]+\/[a-z0-9-]+$/', $name ) ) {
			return null;
		}
		if ( $this->is_registered( $name ) ) {
			return null;
		}

		$args = apply_filters( 'wp_register_ability_args', $args, $name );

		if ( isset( $args['category'] ) && ! wp_has_ability_category( $args['category'] ) ) {
			return null;
		}

		if ( isset( $args['ability_class'] ) && ! is_a( $args['ability_class'], WP_Ability::class, true ) ) {
			return null;
		}

		/** @var class-string<WP_Ability> $ability_class */
		$ability_class = $args['ability_class'] ?? WP_Ability::class;
		unset( $args['ability_class'] );

		try {
			$ability = new $ability_class( $name, $args );
		} catch ( InvalidArgumentException $e ) {
			return null;
		}

		$this->registered_abilities[ $name ] = $ability;
		return $ability;
	}

	public function unregister( string $name ): ?WP_Ability {
		if ( ! $this->is_registered( $name ) ) {
			return null;
		}
		$unregistered = $this->registered_abilities[ $name ];
		unset( $this->registered_abilities[ $name ] );
		return $unregistered;
	}

	/**
	 * @return array<string, WP_Ability>
	 */
	public function get_all_registered(): array {
		return $this->registered_abilities;
	}

	public function is_registered( string $name ): bool {
		return isset( $this->registered_abilities[ $name ] );
	}

	public function get_registered( string $name ): ?WP_Ability {
		return $this->registered_abilities[ $name ] ?? null;
	}

	public static function get_instance(): ?self {
		if ( ! did_action( 'init' ) ) {
			return null;
		}

		if ( null === self::$instance ) {
			self::$instance = new self();
			WP_Ability_Categories_Registry::get_instance();
			do_action( 'wp_abilities_api_init', self::$instance );
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
