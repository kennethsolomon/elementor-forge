<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\MCP\Tools\ApplyTemplate;
use ElementorForge\MCP\Tools\BulkGenerate;
use ElementorForge\MCP\Tools\CreatePage;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * These tests exercise the happy + failure paths of the MCP tool execute
 * methods by stubbing out the WordPress functions they call via Brain\Monkey.
 */
final class ToolsExecuteTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'absint' )->alias( static fn ( $v ): int => abs( (int) $v ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof \WP_Error );
		Functions\when( 'wp_parse_args' )->alias(
			static function ( $args, array $defaults = array() ): array {
				if ( is_object( $args ) ) {
					$args = get_object_vars( $args );
				}
				return array_merge( $defaults, is_array( $args ) ? $args : array() );
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_create_page_missing_title_returns_error(): void {
		$result = CreatePage::execute( array( 'title' => '', 'content_doc' => array( 'title' => 'x', 'blocks' => array() ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_apply_template_invalid_cpt_returns_error(): void {
		$result = ApplyTemplate::execute( array( 'cpt' => 'bogus', 'post_data' => array( 'title' => 'X' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_apply_template_missing_title_returns_error(): void {
		$result = ApplyTemplate::execute( array( 'cpt' => 'ef_location', 'post_data' => array( 'title' => '' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_bulk_generate_invalid_cpt_returns_error(): void {
		$result = BulkGenerate::execute( array( 'cpt' => 'nope', 'items' => array() ) );
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_bulk_generate_iterates_items_and_collects_failures(): void {
		Functions\expect( 'wp_insert_post' )->twice()->andReturn( 101, new WP_Error( 'db_err', 'insert failed' ) );
		Functions\expect( 'get_permalink' )->with( 101 )->andReturn( 'https://example.test/loc/one' );

		$result = BulkGenerate::execute(
			array(
				'cpt'   => 'ef_location',
				'items' => array(
					array( 'title' => 'Location One' ),
					array( 'title' => 'Location Two' ),
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result['created'] );
		$this->assertCount( 1, $result['failed'] );
		$this->assertSame( 101, $result['created'][0]['post_id'] );
		$this->assertSame( 'Location Two', $result['failed'][0]['title'] );
	}
}
