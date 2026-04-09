<?php
/**
 * Vendored copy of the WordPress Abilities API WP_Ability_Category class.
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

if ( class_exists( 'WP_Ability_Category' ) ) {
	return;
}

/**
 * Encapsulates the properties of a specific ability category.
 */
final class WP_Ability_Category {
 // phpcs:ignore WordPress.NamingConventions.ValidClassName.NotSnakeCase

	/** @var string */
	protected $slug;

	/** @var string */
	protected $label;

	/** @var string */
	protected $description;

	/** @var array<string, mixed> */
	protected $meta = array();

	/**
	 * @param string               $slug Category slug.
	 * @param array<string, mixed> $args Category definition.
	 */
	public function __construct( string $slug, array $args ) {
		if ( empty( $slug ) ) {
			throw new InvalidArgumentException( 'The ability category slug cannot be empty.' );
		}
		$this->slug = $slug;

		$properties = $this->prepare_properties( $args );
		foreach ( $properties as $property_name => $property_value ) {
			if ( ! property_exists( $this, $property_name ) ) {
				continue;
			}
			$this->$property_name = $property_value;
		}
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	protected function prepare_properties( array $args ): array {
		if ( empty( $args['label'] ) || ! is_string( $args['label'] ) ) {
			throw new InvalidArgumentException( 'The ability category properties must contain a `label` string.' );
		}
		if ( empty( $args['description'] ) || ! is_string( $args['description'] ) ) {
			throw new InvalidArgumentException( 'The ability category properties must contain a `description` string.' );
		}
		if ( isset( $args['meta'] ) && ! is_array( $args['meta'] ) ) {
			throw new InvalidArgumentException( 'The ability category properties should provide a valid `meta` array.' );
		}
		return $args;
	}

	public function get_slug(): string {
		return $this->slug;
	}

	public function get_label(): string {
		return $this->label;
	}

	public function get_description(): string {
		return $this->description;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_meta(): array {
		return $this->meta;
	}

	public function __wakeup(): void {
		throw new LogicException( __CLASS__ . ' should never be unserialized.' );
	}

	public function __sleep(): array {
		throw new LogicException( __CLASS__ . ' should never be serialized.' );
	}
}
