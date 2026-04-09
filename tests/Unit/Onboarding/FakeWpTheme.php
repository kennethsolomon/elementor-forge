<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Onboarding;

/**
 * Minimal stand-in for {@see \WP_Theme}. Exposes only the surface
 * {@see \ElementorForge\Onboarding\ThemeInstaller} reads: exists(),
 * get_stylesheet(), get_template(). Used by ThemeInstallerTest to stub
 * wp_get_theme() via Brain Monkey without loading WordPress core.
 */
final class FakeWpTheme {

	/** @var string */
	private string $slug;

	/** @var bool */
	private bool $exists;

	public function __construct( string $slug, bool $exists ) {
		$this->slug   = $slug;
		$this->exists = $exists;
	}

	public function exists(): bool {
		return $this->exists;
	}

	public function get_stylesheet(): string {
		return $this->slug;
	}

	public function get_template(): string {
		return $this->slug;
	}
}
