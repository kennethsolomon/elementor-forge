<?php
/**
 * Vendored copy of the WordPress Abilities API WP_Ability class.
 *
 * Guarded by class_exists() so core's future inclusion (WP 6.9+) wins automatically.
 * Source: https://github.com/WordPress/abilities-api (GPL-2.0-or-later)
 *
 * @package ElementorForge
 * @license GPL-2.0-or-later
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
 * phpcs:disable WordPress.Files.FileName.InvalidClassFileName
 * phpcs:disable Squiz.Commenting.FunctionComment.Missing
 * phpcs:disable Squiz.Commenting.VariableComment.Missing
 * phpcs:disable Squiz.Commenting.ClassComment.Missing
 */

declare(strict_types=1);

if ( class_exists( 'WP_Ability' ) ) {
	return;
}

/**
 * Encapsulates the properties and methods related to a specific ability.
 */
// phpcs:ignore Squiz.Classes.ClassFileName.NoMatch
final class WP_Ability {
 // phpcs:ignore WordPress.NamingConventions.ValidClassName.NotSnakeCase

	protected const DEFAULT_SHOW_IN_REST = false;

	/**
	 * @var array<string, bool|null>
	 */
	protected static $default_annotations = array(
		'readonly'    => null,
		'destructive' => null,
		'idempotent'  => null,
	);

	/** @var string */
	protected $name;

	/** @var string */
	protected $label;

	/** @var string */
	protected $description;

	/** @var string */
	protected $category;

	/** @var array<string, mixed> */
	protected $input_schema = array();

	/** @var array<string, mixed> */
	protected $output_schema = array();

	/** @var callable */
	protected $execute_callback;

	/** @var callable */
	protected $permission_callback;

	/** @var array<string, mixed> */
	protected $meta;

	/**
	 * @param string               $name Ability name (namespaced).
	 * @param array<string, mixed> $args Ability definition.
	 */
	public function __construct( string $name, array $args ) {
		$this->name = $name;

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
			throw new InvalidArgumentException( 'The ability properties must contain a `label` string.' );
		}
		if ( empty( $args['description'] ) || ! is_string( $args['description'] ) ) {
			throw new InvalidArgumentException( 'The ability properties must contain a `description` string.' );
		}
		if ( empty( $args['category'] ) || ! is_string( $args['category'] ) ) {
			throw new InvalidArgumentException( 'The ability properties must contain a `category` string.' );
		}
		if ( empty( $args['execute_callback'] ) || ! is_callable( $args['execute_callback'] ) ) {
			throw new InvalidArgumentException( 'The ability properties must contain a valid `execute_callback` function.' );
		}
		if ( empty( $args['permission_callback'] ) || ! is_callable( $args['permission_callback'] ) ) {
			throw new InvalidArgumentException( 'The ability properties must provide a valid `permission_callback` function.' );
		}
		if ( isset( $args['input_schema'] ) && ! is_array( $args['input_schema'] ) ) {
			throw new InvalidArgumentException( 'The ability properties should provide a valid `input_schema` definition.' );
		}
		if ( isset( $args['output_schema'] ) && ! is_array( $args['output_schema'] ) ) {
			throw new InvalidArgumentException( 'The ability properties should provide a valid `output_schema` definition.' );
		}
		if ( isset( $args['meta'] ) && ! is_array( $args['meta'] ) ) {
			throw new InvalidArgumentException( 'The ability properties should provide a valid `meta` array.' );
		}
		if ( isset( $args['meta']['annotations'] ) && ! is_array( $args['meta']['annotations'] ) ) {
			throw new InvalidArgumentException( 'The ability meta should provide a valid `annotations` array.' );
		}
		if ( isset( $args['meta']['show_in_rest'] ) && ! is_bool( $args['meta']['show_in_rest'] ) ) {
			throw new InvalidArgumentException( 'The ability meta should provide a valid `show_in_rest` boolean.' );
		}

		$args['meta'] = wp_parse_args(
			$args['meta'] ?? array(),
			array(
				'annotations'  => self::$default_annotations,
				'show_in_rest' => self::DEFAULT_SHOW_IN_REST,
			)
		);
		$args['meta']['annotations'] = wp_parse_args(
			$args['meta']['annotations'],
			self::$default_annotations
		);

		return $args;
	}

	public function get_name(): string {
		return $this->name;
	}

	public function get_label(): string {
		return $this->label;
	}

	public function get_description(): string {
		return $this->description;
	}

	public function get_category(): string {
		return $this->category;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_input_schema(): array {
		return $this->input_schema;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_output_schema(): array {
		return $this->output_schema;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_meta(): array {
		return $this->meta;
	}

	/**
	 * @param mixed $default_value
	 * @return mixed
	 */
	public function get_meta_item( string $key, $default_value = null ) {
		return array_key_exists( $key, $this->meta ) ? $this->meta[ $key ] : $default_value;
	}

	/**
	 * @param mixed $input
	 * @return mixed
	 */
	public function normalize_input( $input = null ) {
		if ( null !== $input ) {
			return $input;
		}
		$input_schema = $this->get_input_schema();
		if ( ! empty( $input_schema ) && array_key_exists( 'default', $input_schema ) ) {
			return $input_schema['default'];
		}
		return null;
	}

	/**
	 * @param mixed $input
	 * @return true|WP_Error
	 */
	public function validate_input( $input = null ) {
		$input_schema = $this->get_input_schema();
		if ( empty( $input_schema ) ) {
			if ( null === $input ) {
				return true;
			}
			return new WP_Error( 'ability_missing_input_schema', sprintf( 'Ability "%s" does not define an input schema.', $this->name ) );
		}
		$valid = rest_validate_value_from_schema( $input, $input_schema, 'input' );
		if ( is_wp_error( $valid ) ) {
			return new WP_Error( 'ability_invalid_input', sprintf( 'Ability "%s" invalid input: %s', $this->name, $valid->get_error_message() ) );
		}
		return true;
	}

	/**
	 * @param mixed $input
	 * @return mixed
	 */
	protected function invoke_callback( callable $callback, $input = null ) {
		$args = array();
		if ( ! empty( $this->get_input_schema() ) ) {
			$args[] = $input;
		}
		return $callback( ...$args );
	}

	/**
	 * @param mixed $input
	 * @return bool|WP_Error
	 */
	public function check_permissions( $input = null ) {
		if ( ! is_callable( $this->permission_callback ) ) {
			return new WP_Error( 'ability_invalid_permission_callback', sprintf( 'Ability "%s" does not have a valid permission callback.', $this->name ) );
		}
		return $this->invoke_callback( $this->permission_callback, $input );
	}

	/**
	 * @param mixed $input
	 * @return mixed|WP_Error
	 */
	protected function do_execute( $input = null ) {
		if ( ! is_callable( $this->execute_callback ) ) {
			return new WP_Error( 'ability_invalid_execute_callback', sprintf( 'Ability "%s" does not have a valid execute callback.', $this->name ) );
		}
		return $this->invoke_callback( $this->execute_callback, $input );
	}

	/**
	 * @param mixed $output
	 * @return true|WP_Error
	 */
	protected function validate_output( $output ) {
		$output_schema = $this->get_output_schema();
		if ( empty( $output_schema ) ) {
			return true;
		}
		$valid = rest_validate_value_from_schema( $output, $output_schema, 'output' );
		if ( is_wp_error( $valid ) ) {
			return new WP_Error( 'ability_invalid_output', sprintf( 'Ability "%s" invalid output: %s', $this->name, $valid->get_error_message() ) );
		}
		return true;
	}

	/**
	 * @param mixed $input
	 * @return mixed|WP_Error
	 */
	public function execute( $input = null ) {
		$input    = $this->normalize_input( $input );
		$is_valid = $this->validate_input( $input );
		if ( is_wp_error( $is_valid ) ) {
			return $is_valid;
		}

		$has_permissions = $this->check_permissions( $input );
		if ( true !== $has_permissions ) {
			return new WP_Error( 'ability_invalid_permissions', sprintf( 'Ability "%s" does not have necessary permission.', $this->name ) );
		}

		do_action( 'wp_before_execute_ability', $this->name, $input );

		$result = $this->do_execute( $input );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$is_valid = $this->validate_output( $result );
		if ( is_wp_error( $is_valid ) ) {
			return $is_valid;
		}

		do_action( 'wp_after_execute_ability', $this->name, $input, $result );

		return $result;
	}

	public function __wakeup(): void {
		throw new LogicException( __CLASS__ . ' should never be unserialized.' );
	}

	public function __sleep(): array {
		throw new LogicException( __CLASS__ . ' should never be serialized.' );
	}
}
