<?php
/**
 * Phase 3 BulkGenerate tests — batching, transactions, matrix mode, dry run.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\MCP\Tools\BulkGenerate;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class BulkGenerateTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof WP_Error );
		Functions\when( 'wp_suspend_cache_addition' )->justReturn( false );
		Functions\when( 'wp_defer_term_counting' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'get_transient' )->justReturn( false );
		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_dry_run_returns_plan_without_writing(): void {
		Functions\expect( 'wp_insert_post' )->never();

		$result = BulkGenerate::execute(
			array(
				'cpt'     => 'ef_location',
				'dry_run' => true,
				'items'   => array(
					array( 'title' => 'A' ),
					array( 'title' => 'B' ),
					array( 'title' => 'C' ),
				),
			)
		);

		self::assertIsArray( $result );
		self::assertTrue( $result['dry_run'] );
		self::assertSame( 3, $result['planned'] );
		self::assertCount( 0, $result['created'] );
		self::assertCount( 0, $result['failed'] );
		self::assertArrayHasKey( 'plan', $result );
	}

	public function test_matrix_generation_crosses_items_with_service_items(): void {
		Functions\expect( 'wp_insert_post' )->never();

		$result = BulkGenerate::execute(
			array(
				'cpt'           => 'ef_location',
				'multiply_by'   => 'ef_service',
				'dry_run'       => true,
				'items'         => array(
					array( 'title' => 'Suburb A' ),
					array( 'title' => 'Suburb B' ),
				),
				'service_items' => array(
					array( 'title' => 'Plumbing' ),
					array( 'title' => 'Electrical' ),
					array( 'title' => 'Carpentry' ),
				),
			)
		);

		self::assertSame( 6, $result['planned'] );
		self::assertCount( 6, $result['plan'] );
		self::assertSame( 'Suburb A — Plumbing', $result['plan'][0]['title'] );
		self::assertSame( 'Suburb B — Carpentry', $result['plan'][5]['title'] );
	}

	public function test_meta_input_used_so_inserts_count_equals_item_count(): void {
		$insert_calls = 0;
		Functions\when( 'wp_insert_post' )->alias(
			static function ( $args ) use ( &$insert_calls ): int {
				++$insert_calls;
				if ( ! isset( $args['meta_input'] ) || ! is_array( $args['meta_input'] ) ) {
					throw new \RuntimeException( 'meta_input not used' );
				}
				if ( count( $args['meta_input'] ) !== 4 ) {
					throw new \RuntimeException( 'meta_input did not contain all ACF fields' );
				}
				return 100 + $insert_calls;
			}
		);
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/post' );

		$result = BulkGenerate::execute(
			array(
				'cpt'           => 'ef_location',
				'transactional' => false,
				'items'         => array(
					array(
						'title'      => 'Loc 1',
						'acf_fields' => array( 'phone' => '111', 'email' => 'a@x', 'address' => 'Addr 1', 'lat' => '0' ),
					),
					array(
						'title'      => 'Loc 2',
						'acf_fields' => array( 'phone' => '222', 'email' => 'b@x', 'address' => 'Addr 2', 'lat' => '1' ),
					),
				),
			)
		);

		self::assertSame( 2, $insert_calls, 'wp_insert_post must be called once per item, not once per ACF field.' );
		self::assertCount( 2, $result['created'] );
	}

	public function test_transactional_mode_breaks_on_first_failure(): void {
		Functions\expect( 'wp_insert_post' )->once()->andReturn( new WP_Error( 'db_fail', 'boom' ) );
		Functions\expect( 'get_permalink' )->never();

		$result = BulkGenerate::execute(
			array(
				'cpt'   => 'ef_location',
				'items' => array(
					array( 'title' => 'A' ),
					array( 'title' => 'B' ),
					array( 'title' => 'C' ),
				),
			)
		);

		self::assertTrue( $result['rolled_back'] );
		self::assertTrue( $result['transactional'] );
		self::assertCount( 0, $result['created'] );
		self::assertCount( 1, $result['failed'] );
	}

	public function test_empty_items_returns_error(): void {
		$result = BulkGenerate::execute(
			array(
				'cpt'   => 'ef_location',
				'items' => array(),
			)
		);

		self::assertInstanceOf( WP_Error::class, $result );
	}

	public function test_progress_polling_returns_null_when_transient_missing(): void {
		Functions\when( 'get_transient' )->justReturn( false );

		self::assertNull( BulkGenerate::get_progress( 'unknown_job' ) );
	}

	public function test_progress_polling_returns_normalized_struct(): void {
		Functions\when( 'get_transient' )->justReturn( array( 'planned' => 10, 'completed' => 4, 'status' => 'running' ) );

		$progress = BulkGenerate::get_progress( 'job_x' );

		self::assertIsArray( $progress );
		self::assertSame( 10, $progress['planned'] );
		self::assertSame( 4, $progress['completed'] );
		self::assertSame( 'running', $progress['status'] );
	}
}
