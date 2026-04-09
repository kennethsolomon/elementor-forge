<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Emitter;

use ElementorForge\Elementor\Emitter\Document;
use ElementorForge\Elementor\Emitter\Encoder;
use ElementorForge\Elementor\Emitter\Parser;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class EncoderWpTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'wp_json_encode' )->alias(
			static fn ( $data, int $options = 0, int $depth = 512 ) => json_encode( $data, $options, $depth )
		);
		Functions\when( 'wp_slash' )->alias(
			static function ( $value ) {
				if ( is_array( $value ) ) {
					return array_map( 'wp_slash', $value );
				}
				return is_string( $value ) ? addslashes( $value ) : $value;
			}
		);
		Functions\when( 'wp_unslash' )->alias(
			static function ( $value ) {
				if ( is_array( $value ) ) {
					return array_map( 'wp_unslash', $value );
				}
				return is_string( $value ) ? stripslashes( $value ) : $value;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_write_document_calls_update_post_meta_with_encoded_payload(): void {
		$doc = new Document( 'Page', 'page' );

		$captured = null;
		Functions\expect( 'update_post_meta' )
			->once()
			->with(
				42,
				'_elementor_data',
				\Mockery::on(
					static function ( $arg ) use ( &$captured ): bool {
						$captured = $arg;
						return is_string( $arg );
					}
				)
			)
			->andReturn( true );

		$this->assertTrue( Encoder::write_document( 42, $doc ) );
		$this->assertIsString( $captured );
		$this->assertSame( '[]', stripslashes( (string) $captured ) );
	}

	public function test_read_document_returns_null_when_meta_missing(): void {
		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, '_elementor_data', true )
			->andReturn( '' );

		$parser = new Parser( false );
		$this->assertNull( Encoder::read_document( 42, $parser ) );
	}

	public function test_read_document_parses_valid_payload(): void {
		$raw = addslashes(
			json_encode(
				array(
					array(
						'id'       => 'aa112233',
						'elType'   => 'container',
						'elements' => array(),
						'isInner'  => false,
						'settings' => array(),
					),
				)
			)
		);

		Functions\expect( 'get_post_meta' )
			->once()
			->with( 42, '_elementor_data', true )
			->andReturn( $raw );
		Functions\expect( 'get_the_title' )
			->once()
			->with( 42 )
			->andReturn( 'Page Title' );

		$parser = new Parser( false );
		$doc    = Encoder::read_document( 42, $parser );

		$this->assertNotNull( $doc );
		$this->assertSame( 'Page Title', $doc->title() );
		$this->assertCount( 1, $doc->content() );
	}
}
