<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\MCP\Tools;

use Brain\Monkey;
use Brain\Monkey\Functions;
use ElementorForge\MCP\Tools\ApplyTemplate;
use ElementorForge\Settings\Store;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class ApplyTemplateSanitizationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Store::flush_cache();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'absint' )->alias( static fn ( $v ): int => abs( (int) $v ) );
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ): bool => $thing instanceof WP_Error );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn(
			array(
				'safety_mode'             => 'full',
				'safety_allowed_post_ids' => '',
			)
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}


	public function test_url_field_is_sanitized_with_esc_url_raw(): void {
		$acf_updates = array();
		Functions\when( 'wp_insert_post' )->justReturn( 10 );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/loc' );
		Functions\when( 'esc_url_raw' )->alias( static fn ( string $v ): string => 'sanitized::' . $v );
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'update_field' )->alias(
			static function ( string $key, $value, int $post_id ) use ( &$acf_updates ): void {
				$acf_updates[] = array( 'key' => $key, 'value' => $value );
			}
		);

		ApplyTemplate::execute(
			array(
				'cpt'       => 'ef_location',
				'post_data' => array(
					'title'      => 'Loc',
					'acf_fields' => array(
						'map_url' => 'https://maps.example.com/q?q=test',
					),
				),
			)
		);

		$this->assertCount( 1, $acf_updates );
		$this->assertSame( 'map_url', $acf_updates[0]['key'] );
		$this->assertSame( 'sanitized::https://maps.example.com/q?q=test', $acf_updates[0]['value'] );
	}

	public function test_link_field_is_sanitized_with_esc_url_raw(): void {
		$acf_updates = array();
		Functions\when( 'wp_insert_post' )->justReturn( 11 );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/svc' );
		Functions\when( 'esc_url_raw' )->alias( static fn ( string $v ): string => 'url::' . $v );
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'update_field' )->alias(
			static function ( string $key, $value, int $post_id ) use ( &$acf_updates ): void {
				$acf_updates[] = array( 'key' => $key, 'value' => $value );
			}
		);

		ApplyTemplate::execute(
			array(
				'cpt'       => 'ef_service',
				'post_data' => array(
					'title'      => 'Svc',
					'acf_fields' => array(
						'external_link' => 'https://partner.com',
					),
				),
			)
		);

		$this->assertCount( 1, $acf_updates );
		$this->assertStringStartsWith( 'url::', $acf_updates[0]['value'] );
	}


	public function test_description_field_is_sanitized_with_wp_kses_post(): void {
		$acf_updates = array();
		Functions\when( 'wp_insert_post' )->justReturn( 20 );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/loc' );
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'wp_kses_post' )->alias( static fn ( string $v ): string => 'kses::' . $v );
		Functions\when( 'update_field' )->alias(
			static function ( string $key, $value, int $post_id ) use ( &$acf_updates ): void {
				$acf_updates[] = array( 'key' => $key, 'value' => $value );
			}
		);

		ApplyTemplate::execute(
			array(
				'cpt'       => 'ef_location',
				'post_data' => array(
					'title'      => 'Loc',
					'acf_fields' => array(
						'description' => '<p>Hello <script>bad()</script></p>',
					),
				),
			)
		);

		$this->assertCount( 1, $acf_updates );
		$this->assertSame( 'kses::<p>Hello <script>bad()</script></p>', $acf_updates[0]['value'] );
	}

	public function test_body_field_is_sanitized_with_wp_kses_post(): void {
		$acf_updates = array();
		Functions\when( 'wp_insert_post' )->justReturn( 21 );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/svc' );
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'wp_kses_post' )->alias( static fn ( string $v ): string => 'kses::' . $v );
		Functions\when( 'update_field' )->alias(
			static function ( string $key, $value, int $post_id ) use ( &$acf_updates ): void {
				$acf_updates[] = array( 'key' => $key, 'value' => $value );
			}
		);

		ApplyTemplate::execute(
			array(
				'cpt'       => 'ef_service',
				'post_data' => array(
					'title'      => 'Svc',
					'acf_fields' => array(
						'body_text' => '<strong>rich</strong>',
					),
				),
			)
		);

		$this->assertCount( 1, $acf_updates );
		$this->assertStringStartsWith( 'kses::', $acf_updates[0]['value'] );
	}

	public function test_content_field_is_sanitized_with_wp_kses_post(): void {
		$acf_updates = array();
		Functions\when( 'wp_insert_post' )->justReturn( 22 );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/loc' );
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'wp_kses_post' )->alias( static fn ( string $v ): string => 'kses::' . $v );
		Functions\when( 'update_field' )->alias(
			static function ( string $key, $value, int $post_id ) use ( &$acf_updates ): void {
				$acf_updates[] = array( 'key' => $key, 'value' => $value );
			}
		);

		ApplyTemplate::execute(
			array(
				'cpt'       => 'ef_location',
				'post_data' => array(
					'title'      => 'Loc',
					'acf_fields' => array(
						'page_content' => '<em>italic</em>',
					),
				),
			)
		);

		$this->assertCount( 1, $acf_updates );
		$this->assertStringStartsWith( 'kses::', $acf_updates[0]['value'] );
	}


	public function test_plain_text_field_is_sanitized_with_sanitize_text_field(): void {
		$acf_updates = array();
		Functions\when( 'wp_insert_post' )->justReturn( 30 );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/loc' );
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		// Override the default returnArg stub to mark sanitized strings.
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => 'text::' . $v );
		Functions\when( 'update_field' )->alias(
			static function ( string $key, $value, int $post_id ) use ( &$acf_updates ): void {
				$acf_updates[] = array( 'key' => $key, 'value' => $value );
			}
		);

		ApplyTemplate::execute(
			array(
				'cpt'       => 'ef_location',
				'post_data' => array(
					'title'      => 'Loc',
					'acf_fields' => array(
						'suburb_name' => 'Fitzroy',
					),
				),
			)
		);

		$this->assertCount( 1, $acf_updates );
		$this->assertSame( 'text::Fitzroy', $acf_updates[0]['value'] );
	}

	public function test_phone_field_is_sanitized_with_sanitize_text_field(): void {
		$acf_updates = array();
		Functions\when( 'wp_insert_post' )->justReturn( 31 );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/loc' );
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => 'text::' . $v );
		Functions\when( 'update_field' )->alias(
			static function ( string $key, $value, int $post_id ) use ( &$acf_updates ): void {
				$acf_updates[] = array( 'key' => $key, 'value' => $value );
			}
		);

		ApplyTemplate::execute(
			array(
				'cpt'       => 'ef_location',
				'post_data' => array(
					'title'      => 'Loc',
					'acf_fields' => array(
						'phone' => '03 9001 1234',
					),
				),
			)
		);

		$this->assertCount( 1, $acf_updates );
		$this->assertSame( 'text::03 9001 1234', $acf_updates[0]['value'] );
	}


	public function test_integer_value_is_sanitized_with_absint(): void {
		$acf_updates = array();
		Functions\when( 'wp_insert_post' )->justReturn( 40 );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/loc' );
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'update_field' )->alias(
			static function ( string $key, $value, int $post_id ) use ( &$acf_updates ): void {
				$acf_updates[] = array( 'key' => $key, 'value' => $value );
			}
		);

		ApplyTemplate::execute(
			array(
				'cpt'       => 'ef_service',
				'post_data' => array(
					'title'      => 'Svc',
					'acf_fields' => array(
						'sort_order' => 5,
					),
				),
			)
		);

		$this->assertCount( 1, $acf_updates );
		$this->assertSame( 5, $acf_updates[0]['value'] );
	}


	public function test_update_field_is_called_with_sanitized_value_and_post_id(): void {
		$acf_updates = array();
		Functions\when( 'wp_insert_post' )->justReturn( 60 );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/loc' );
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'update_field' )->alias(
			static function ( string $key, $value, int $post_id ) use ( &$acf_updates ): void {
				$acf_updates[] = array( 'key' => $key, 'value' => $value, 'post_id' => $post_id );
			}
		);

		ApplyTemplate::execute(
			array(
				'cpt'       => 'ef_location',
				'post_data' => array(
					'title'      => 'Loc',
					'acf_fields' => array(
						'suburb_name' => 'Carlton',
						'phone'       => '03 9000 0000',
					),
				),
			)
		);

		$this->assertCount( 2, $acf_updates );
		foreach ( $acf_updates as $call ) {
			$this->assertSame( 60, $call['post_id'] );
		}
	}


	public function test_mixed_acf_fields_each_get_correct_sanitizer(): void {
		$acf_updates = array();
		Functions\when( 'wp_insert_post' )->justReturn( 70 );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/loc' );
		Functions\when( 'esc_url_raw' )->alias( static fn ( string $v ): string => 'url::' . $v );
		Functions\when( 'wp_kses_post' )->alias( static fn ( string $v ): string => 'kses::' . $v );
		Functions\when( 'sanitize_text_field' )->alias( static fn ( string $v ): string => 'text::' . $v );
		Functions\when( 'update_field' )->alias(
			static function ( string $key, $value, int $post_id ) use ( &$acf_updates ): void {
				$acf_updates[ $key ] = $value;
			}
		);

		ApplyTemplate::execute(
			array(
				'cpt'       => 'ef_location',
				'post_data' => array(
					'title'      => 'Loc',
					'acf_fields' => array(
						'website_url' => 'https://example.com',
						'description' => '<p>Rich text</p>',
						'suburb_name' => 'Richmond',
					),
				),
			)
		);

		$this->assertStringStartsWith( 'url::', $acf_updates['website_url'] );
		$this->assertStringStartsWith( 'kses::', $acf_updates['description'] );
		$this->assertStringStartsWith( 'text::', $acf_updates['suburb_name'] );
	}


	public function test_array_acf_value_passes_through_without_scalar_sanitization(): void {
		$acf_updates = array();
		Functions\when( 'wp_insert_post' )->justReturn( 80 );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/loc' );
		Functions\when( 'esc_url_raw' )->returnArg();
		Functions\when( 'wp_kses_post' )->returnArg();
		Functions\when( 'update_field' )->alias(
			static function ( string $key, $value, int $post_id ) use ( &$acf_updates ): void {
				$acf_updates[] = array( 'key' => $key, 'value' => $value );
			}
		);

		$repeater_data = array(
			array( 'day' => 'Monday', 'hours' => '9-5' ),
			array( 'day' => 'Tuesday', 'hours' => '9-5' ),
		);

		ApplyTemplate::execute(
			array(
				'cpt'       => 'ef_location',
				'post_data' => array(
					'title'      => 'Loc',
					'acf_fields' => array(
						'opening_hours' => $repeater_data,
					),
				),
			)
		);

		$this->assertCount( 1, $acf_updates );
		$this->assertSame( 'opening_hours', $acf_updates[0]['key'] );
		$this->assertIsArray( $acf_updates[0]['value'] );
	}
}
