<?php
/**
 * Minimal WooCommerce class stub for Phase 2 unit tests. The class name is all
 * we need because {@see \ElementorForge\WooCommerce\WooCommerce::is_wc_active()}
 * feature-detects via `class_exists('WooCommerce')` — it never calls any method
 * on the class. Guarded with `class_exists` so the include is idempotent and
 * repeated requires from multiple test files do not redeclare.
 */

declare(strict_types=1);

if ( ! class_exists( 'WooCommerce' ) ) {
	// phpcs:ignore Generic.Classes.DuplicateClassName.Found
	class WooCommerce {}
}

if ( ! defined( 'WC_VERSION' ) ) {
	define( 'WC_VERSION', '9.4.0' );
}
