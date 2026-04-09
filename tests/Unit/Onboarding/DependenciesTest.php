<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Onboarding;

use ElementorForge\Onboarding\Dependencies;
use PHPUnit\Framework\TestCase;

final class DependenciesTest extends TestCase {

	public function test_includes_every_required_plugin(): void {
		$slugs = array_column( Dependencies::all(), 'slug' );
		$this->assertContains( 'elementor', $slugs );
		$this->assertContains( 'advanced-custom-fields', $slugs );
		$this->assertContains( 'contact-form-7', $slugs );
		$this->assertContains( 'smart-slider-3', $slugs );
		$this->assertContains( 'elementor-pro', $slugs );
	}

	public function test_elementor_pro_is_not_auto_installable(): void {
		$pro = $this->find_dep( 'elementor-pro' );
		$this->assertFalse( $pro['auto_install'] );
	}

	public function test_fibosearch_is_conditional_on_woocommerce(): void {
		$fibo = $this->find_dep( 'fibosearch' );
		$this->assertArrayHasKey( 'conditional_on', $fibo );
		$this->assertSame( 'woocommerce', $fibo['conditional_on'] );
	}

	public function test_woocommerce_is_optional(): void {
		$wc = $this->find_dep( 'woocommerce' );
		$this->assertFalse( $wc['required'] );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function find_dep( string $slug ): array {
		foreach ( Dependencies::all() as $dep ) {
			if ( $dep['slug'] === $slug ) {
				return $dep;
			}
		}
		$this->fail( 'Dependency ' . $slug . ' missing.' );
	}
}
