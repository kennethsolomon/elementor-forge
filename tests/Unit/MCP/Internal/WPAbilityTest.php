<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Internal;

require_once dirname( __DIR__, 3 ) . '/../src/MCP/Internal/class-wp-ability.php';

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WP_Ability;
use WP_Error;

final class WPAbilityTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_parse_args' )->alias(
			static function ( $args, array $defaults = array() ): array {
				if ( is_object( $args ) ) {
					$args = get_object_vars( $args );
				}
				return array_merge( $defaults, is_array( $args ) ? $args : array() );
			}
		);
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof WP_Error );
		Functions\when( 'do_action' )->justReturn( null );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function validArgs( array $overrides = array() ): array {
		return array_merge(
			array(
				'label'               => 'Test Ability',
				'description'         => 'A test ability.',
				'category'            => 'test-category',
				'execute_callback'    => static fn () => array( 'ok' => true ),
				'permission_callback' => static fn () => true,
			),
			$overrides
		);
	}

	public function test_construct_stores_name_and_properties(): void {
		$ability = new WP_Ability( 'test/my-ability', $this->validArgs() );

		$this->assertSame( 'test/my-ability', $ability->get_name() );
		$this->assertSame( 'Test Ability', $ability->get_label() );
		$this->assertSame( 'A test ability.', $ability->get_description() );
		$this->assertSame( 'test-category', $ability->get_category() );
	}

	public function test_construct_throws_when_label_missing(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'label' );
		new WP_Ability( 'test/bad', $this->validArgs( array( 'label' => '' ) ) );
	}

	public function test_construct_throws_when_description_missing(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'description' );
		new WP_Ability( 'test/bad', $this->validArgs( array( 'description' => '' ) ) );
	}

	public function test_construct_throws_when_category_missing(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'category' );
		new WP_Ability( 'test/bad', $this->validArgs( array( 'category' => '' ) ) );
	}

	public function test_construct_throws_when_execute_callback_missing(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'execute_callback' );
		new WP_Ability( 'test/bad', $this->validArgs( array( 'execute_callback' => '' ) ) );
	}

	public function test_construct_throws_when_permission_callback_missing(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'permission_callback' );
		new WP_Ability( 'test/bad', $this->validArgs( array( 'permission_callback' => '' ) ) );
	}

	public function test_construct_throws_when_input_schema_not_array(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'input_schema' );
		new WP_Ability( 'test/bad', $this->validArgs( array( 'input_schema' => 'bad' ) ) );
	}

	public function test_construct_throws_when_output_schema_not_array(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'output_schema' );
		new WP_Ability( 'test/bad', $this->validArgs( array( 'output_schema' => 42 ) ) );
	}

	public function test_construct_throws_when_meta_not_array(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'meta' );
		new WP_Ability( 'test/bad', $this->validArgs( array( 'meta' => 'bad' ) ) );
	}

	public function test_construct_throws_when_meta_annotations_not_array(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'annotations' );
		new WP_Ability( 'test/bad', $this->validArgs( array( 'meta' => array( 'annotations' => 'bad' ) ) ) );
	}

	public function test_construct_throws_when_meta_show_in_rest_not_bool(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'show_in_rest' );
		new WP_Ability( 'test/bad', $this->validArgs( array( 'meta' => array( 'show_in_rest' => 'yes' ) ) ) );
	}

	public function test_get_input_schema_returns_empty_by_default(): void {
		$ability = new WP_Ability( 'test/ability', $this->validArgs() );
		$this->assertSame( array(), $ability->get_input_schema() );
	}

	public function test_get_input_schema_returns_provided_schema(): void {
		$schema  = array( 'type' => 'object', 'properties' => array( 'name' => array( 'type' => 'string' ) ) );
		$ability = new WP_Ability( 'test/ability', $this->validArgs( array( 'input_schema' => $schema ) ) );
		$this->assertSame( $schema, $ability->get_input_schema() );
	}

	public function test_get_output_schema_returns_empty_by_default(): void {
		$ability = new WP_Ability( 'test/ability', $this->validArgs() );
		$this->assertSame( array(), $ability->get_output_schema() );
	}

	public function test_get_meta_contains_default_annotations(): void {
		$ability = new WP_Ability( 'test/ability', $this->validArgs() );
		$meta    = $ability->get_meta();

		$this->assertArrayHasKey( 'annotations', $meta );
		$this->assertArrayHasKey( 'readonly', $meta['annotations'] );
		$this->assertArrayHasKey( 'destructive', $meta['annotations'] );
		$this->assertArrayHasKey( 'idempotent', $meta['annotations'] );
		$this->assertNull( $meta['annotations']['readonly'] );
		$this->assertFalse( $meta['show_in_rest'] );
	}

	public function test_get_meta_item_returns_value_or_default(): void {
		$ability = new WP_Ability(
			'test/ability',
			$this->validArgs( array( 'meta' => array( 'custom_key' => 'val' ) ) )
		);

		$this->assertSame( 'val', $ability->get_meta_item( 'custom_key' ) );
		$this->assertNull( $ability->get_meta_item( 'nonexistent' ) );
		$this->assertSame( 'fallback', $ability->get_meta_item( 'nonexistent', 'fallback' ) );
	}

	public function test_normalize_input_returns_input_when_provided(): void {
		$ability = new WP_Ability( 'test/ability', $this->validArgs() );
		$this->assertSame( 'hello', $ability->normalize_input( 'hello' ) );
	}

	public function test_normalize_input_returns_schema_default_when_null(): void {
		$ability = new WP_Ability(
			'test/ability',
			$this->validArgs( array( 'input_schema' => array( 'type' => 'string', 'default' => 'world' ) ) )
		);
		$this->assertSame( 'world', $ability->normalize_input() );
	}

	public function test_normalize_input_returns_null_when_no_schema_default(): void {
		$ability = new WP_Ability(
			'test/ability',
			$this->validArgs( array( 'input_schema' => array( 'type' => 'string' ) ) )
		);
		$this->assertNull( $ability->normalize_input() );
	}

	public function test_validate_input_returns_true_for_empty_schema_and_null(): void {
		$ability = new WP_Ability( 'test/ability', $this->validArgs() );
		$this->assertTrue( $ability->validate_input() );
	}

	public function test_validate_input_returns_error_when_schema_empty_but_input_provided(): void {
		$ability = new WP_Ability( 'test/ability', $this->validArgs() );
		$result  = $ability->validate_input( 'unexpected' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_missing_input_schema', $result->get_error_code() );
	}

	public function test_validate_input_delegates_to_rest_validate_value_from_schema(): void {
		Functions\when( 'rest_validate_value_from_schema' )->justReturn( true );

		$ability = new WP_Ability(
			'test/ability',
			$this->validArgs( array( 'input_schema' => array( 'type' => 'string' ) ) )
		);
		$this->assertTrue( $ability->validate_input( 'valid' ) );
	}

	public function test_validate_input_wraps_rest_validation_error(): void {
		Functions\when( 'rest_validate_value_from_schema' )->justReturn(
			new WP_Error( 'rest_error', 'Value must be a string.' )
		);

		$ability = new WP_Ability(
			'test/ability',
			$this->validArgs( array( 'input_schema' => array( 'type' => 'string' ) ) )
		);
		$result = $ability->validate_input( 123 );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	public function test_check_permissions_invokes_callback(): void {
		$ability = new WP_Ability(
			'test/ability',
			$this->validArgs( array( 'permission_callback' => static fn () => true ) )
		);
		$this->assertTrue( $ability->check_permissions() );
	}

	public function test_check_permissions_passes_input_when_schema_present(): void {
		$received_input = null;
		$ability        = new WP_Ability(
			'test/ability',
			$this->validArgs(
				array(
					'input_schema'        => array( 'type' => 'string' ),
					'permission_callback' => static function ( $input ) use ( &$received_input ) {
						$received_input = $input;
						return true;
					},
				)
			)
		);
		$ability->check_permissions( 'hello' );
		$this->assertSame( 'hello', $received_input );
	}

	public function test_execute_full_pipeline_success(): void {
		$executed = false;
		Functions\when( 'rest_validate_value_from_schema' )->justReturn( true );

		$ability = new WP_Ability(
			'test/ability',
			$this->validArgs(
				array(
					'input_schema'     => array( 'type' => 'string' ),
					'output_schema'    => array( 'type' => 'object' ),
					'execute_callback' => static function ( string $input ) use ( &$executed ): array {
						$executed = true;
						return array( 'received' => $input );
					},
				)
			)
		);

		$result = $ability->execute( 'test_input' );
		$this->assertTrue( $executed );
		$this->assertIsArray( $result );
		$this->assertSame( 'test_input', $result['received'] );
	}

	public function test_execute_returns_error_when_permission_denied(): void {
		Functions\when( 'rest_validate_value_from_schema' )->justReturn( true );

		$ability = new WP_Ability(
			'test/ability',
			$this->validArgs(
				array(
					'input_schema'        => array( 'type' => 'string' ),
					'permission_callback' => static fn () => false,
				)
			)
		);

		$result = $ability->execute( 'test' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_execute_returns_error_on_invalid_input(): void {
		Functions\when( 'rest_validate_value_from_schema' )->justReturn(
			new WP_Error( 'rest_error', 'bad' )
		);

		$ability = new WP_Ability(
			'test/ability',
			$this->validArgs( array( 'input_schema' => array( 'type' => 'string' ) ) )
		);
		$result = $ability->execute( 123 );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
	}

	public function test_execute_returns_error_on_invalid_output(): void {
		Functions\when( 'rest_validate_value_from_schema' )->alias(
			static function ( $value, $schema, $param ) {
				if ( 'output' === $param ) {
					return new WP_Error( 'rest_error', 'bad output' );
				}
				return true;
			}
		);

		$ability = new WP_Ability(
			'test/ability',
			$this->validArgs(
				array(
					'input_schema'     => array( 'type' => 'string' ),
					'output_schema'    => array( 'type' => 'object' ),
					'execute_callback' => static fn () => 'not-an-object',
				)
			)
		);

		$result = $ability->execute( 'test' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_output', $result->get_error_code() );
	}

	public function test_execute_propagates_execute_callback_wp_error(): void {
		Functions\when( 'rest_validate_value_from_schema' )->justReturn( true );

		$ability = new WP_Ability(
			'test/ability',
			$this->validArgs(
				array(
					'input_schema'     => array( 'type' => 'string' ),
					'execute_callback' => static fn () => new WP_Error( 'exec_fail', 'boom' ),
				)
			)
		);

		$result = $ability->execute( 'test' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'exec_fail', $result->get_error_code() );
	}

	public function test_wakeup_throws_logic_exception(): void {
		$ability = new WP_Ability( 'test/ability', $this->validArgs() );
		$this->expectException( \LogicException::class );
		$ability->__wakeup();
	}

	public function test_sleep_throws_logic_exception(): void {
		$ability = new WP_Ability( 'test/ability', $this->validArgs() );
		$this->expectException( \LogicException::class );
		$ability->__sleep();
	}

	public function test_unknown_properties_in_args_are_ignored(): void {
		$ability = new WP_Ability(
			'test/ability',
			$this->validArgs( array( 'nonexistent_prop' => 'ignored' ) )
		);
		$this->assertSame( 'Test Ability', $ability->get_label() );
	}
}
