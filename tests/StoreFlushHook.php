<?php
/**
 * PHPUnit hook: flushes Store's static cache before each test to prevent
 * state leakage when Brain\Monkey stubs get_option with different values.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Tests;

use PHPUnit\Runner\BeforeTestHook;

final class StoreFlushHook implements BeforeTestHook {

	public function executeBeforeTest( string $test ): void {
		if ( class_exists( \ElementorForge\Settings\Store::class, false ) ) {
			\ElementorForge\Settings\Store::flush_cache();
		}
	}
}
