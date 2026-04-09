<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\CPT;

use ElementorForge\CPT\PostTypes;
use PHPUnit\Framework\TestCase;

final class PostTypesTest extends TestCase {

	public function test_all_returns_expected_cpt_slugs(): void {
		$all = PostTypes::all();
		$this->assertArrayHasKey( PostTypes::LOCATION, $all );
		$this->assertArrayHasKey( PostTypes::SERVICE, $all );
		$this->assertArrayHasKey( PostTypes::TESTIMONIAL, $all );
		$this->assertArrayHasKey( PostTypes::FAQ, $all );
	}

	public function test_slugs_are_under_twenty_chars(): void {
		foreach ( PostTypes::slugs() as $slug ) {
			$this->assertLessThanOrEqual( 20, strlen( $slug ), $slug . ' exceeds WP post_type 20-char limit' );
		}
	}

	public function test_location_has_archive_and_rewrite(): void {
		$args = PostTypes::all()[ PostTypes::LOCATION ];
		$this->assertTrue( $args['public'] );
		$this->assertTrue( $args['has_archive'] );
		$this->assertSame( 'locations', $args['rewrite']['slug'] );
	}

	public function test_service_has_archive_and_rewrite(): void {
		$args = PostTypes::all()[ PostTypes::SERVICE ];
		$this->assertTrue( $args['public'] );
		$this->assertTrue( $args['has_archive'] );
		$this->assertSame( 'services', $args['rewrite']['slug'] );
	}

	public function test_testimonial_and_faq_are_internal(): void {
		$tm  = PostTypes::all()[ PostTypes::TESTIMONIAL ];
		$faq = PostTypes::all()[ PostTypes::FAQ ];
		$this->assertFalse( $tm['public'] );
		$this->assertFalse( $faq['public'] );
	}
}
