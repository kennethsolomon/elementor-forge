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
		$fibo = $this->find_dep( 'ajax-search-for-woocommerce' );
		$this->assertArrayHasKey( 'conditional_on', $fibo );
		$this->assertSame( 'woocommerce', $fibo['conditional_on'] );
	}

	/**
	 * Regression test locking the canonical wp.org slug + main file for
	 * FiboSearch. The author-facing name "FiboSearch" is a rebrand; the wp.org
	 * plugin page slug is still `ajax-search-for-woocommerce`. Using
	 * `fibosearch` or `fibo-search` returns a 404 from `plugins_api()` and the
	 * install step silently no-ops — which is exactly the v0.4.0 bug this
	 * locks against. If someone "helpfully" renames the slug to match the
	 * author-facing name, this test catches it.
	 */
	public function test_fibosearch_slug_and_file_match_wp_org(): void {
		$fibo = $this->find_dep( 'ajax-search-for-woocommerce' );
		$this->assertSame( 'ajax-search-for-woocommerce', $fibo['slug'] );
		$this->assertSame(
			'ajax-search-for-woocommerce/ajax-search-for-woocommerce.php',
			$fibo['file']
		);
		$this->assertTrue( $fibo['auto_install'] );
	}

	public function test_find_rejects_legacy_fibosearch_slug(): void {
		// The pre-v0.5.0 slug was incorrectly `fibosearch` — verify the
		// allowlist lookup rejects it now so any stale caller fails fast
		// instead of silently 404-ing at wp.org.
		$this->assertNull(
			\ElementorForge\Onboarding\Dependencies::find( 'fibosearch', 'fibosearch/fibosearch.php' )
		);
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
