<?php
/**
 * Vendored Abilities API procedural wrappers.
 *
 * Each function is guarded by function_exists() so WordPress core's future inclusion
 * (6.9+) wins automatically. Source: https://github.com/WordPress/abilities-api
 *
 * @package ElementorForge
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

if ( ! function_exists( 'wp_register_ability' ) ) {
	/**
	 * Register a new ability.
	 *
	 * @param string               $name Namespaced ability name (e.g. "my-plugin/my-ability").
	 * @param array<string, mixed> $args Ability definition.
	 */
	function wp_register_ability( string $name, array $args ): ?WP_Ability {
		if ( ! doing_action( 'wp_abilities_api_init' ) ) {
			return null;
		}
		$registry = WP_Abilities_Registry::get_instance();
		if ( null === $registry ) {
			return null;
		}
		return $registry->register( $name, $args );
	}
}

if ( ! function_exists( 'wp_unregister_ability' ) ) {
	function wp_unregister_ability( string $name ): ?WP_Ability {
		$registry = WP_Abilities_Registry::get_instance();
		if ( null === $registry ) {
			return null;
		}
		return $registry->unregister( $name );
	}
}

if ( ! function_exists( 'wp_has_ability' ) ) {
	function wp_has_ability( string $name ): bool {
		$registry = WP_Abilities_Registry::get_instance();
		if ( null === $registry ) {
			return false;
		}
		return $registry->is_registered( $name );
	}
}

if ( ! function_exists( 'wp_get_ability' ) ) {
	function wp_get_ability( string $name ): ?WP_Ability {
		$registry = WP_Abilities_Registry::get_instance();
		if ( null === $registry ) {
			return null;
		}
		return $registry->get_registered( $name );
	}
}

if ( ! function_exists( 'wp_get_abilities' ) ) {
	/**
	 * @return array<string, WP_Ability>
	 */
	function wp_get_abilities(): array {
		$registry = WP_Abilities_Registry::get_instance();
		if ( null === $registry ) {
			return array();
		}
		return $registry->get_all_registered();
	}
}

if ( ! function_exists( 'wp_register_ability_category' ) ) {
	/**
	 * @param array<string, mixed> $args
	 */
	function wp_register_ability_category( string $slug, array $args ): ?WP_Ability_Category {
		if ( ! doing_action( 'wp_abilities_api_categories_init' ) ) {
			return null;
		}
		$registry = WP_Ability_Categories_Registry::get_instance();
		if ( null === $registry ) {
			return null;
		}
		return $registry->register( $slug, $args );
	}
}

if ( ! function_exists( 'wp_unregister_ability_category' ) ) {
	function wp_unregister_ability_category( string $slug ): ?WP_Ability_Category {
		$registry = WP_Ability_Categories_Registry::get_instance();
		if ( null === $registry ) {
			return null;
		}
		return $registry->unregister( $slug );
	}
}

if ( ! function_exists( 'wp_has_ability_category' ) ) {
	function wp_has_ability_category( string $slug ): bool {
		$registry = WP_Ability_Categories_Registry::get_instance();
		if ( null === $registry ) {
			return false;
		}
		return $registry->is_registered( $slug );
	}
}

if ( ! function_exists( 'wp_get_ability_category' ) ) {
	function wp_get_ability_category( string $slug ): ?WP_Ability_Category {
		$registry = WP_Ability_Categories_Registry::get_instance();
		if ( null === $registry ) {
			return null;
		}
		return $registry->get_registered( $slug );
	}
}

if ( ! function_exists( 'wp_get_ability_categories' ) ) {
	/**
	 * @return array<string, WP_Ability_Category>
	 */
	function wp_get_ability_categories(): array {
		$registry = WP_Ability_Categories_Registry::get_instance();
		if ( null === $registry ) {
			return array();
		}
		return $registry->get_all_registered();
	}
}
