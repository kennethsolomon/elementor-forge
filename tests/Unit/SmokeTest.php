<?php
/**
 * Scaffold smoke test — confirms the test harness itself boots under the unit suite.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Tests\Unit;

use ElementorForge\Settings\Defaults;
use ElementorForge\Settings\OptionKeys;
use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase {

	public function test_defaults_match_locked_contract(): void {
		$defaults = Defaults::all();

		$this->assertSame( 'free', $defaults['acf_mode'] );
		$this->assertSame( 'preserve', $defaults['ucaddon_shim'] );
		$this->assertSame( 'enabled', $defaults['mcp_server'] );
		$this->assertSame( 'service_business', $defaults['header_pattern'] );
	}

	public function test_option_keys_includes_version_and_settings(): void {
		$keys = OptionKeys::all();
		$this->assertContains( OptionKeys::VERSION, $keys );
		$this->assertContains( OptionKeys::SETTINGS, $keys );
		$this->assertContains( OptionKeys::ACTIVATED_AT, $keys );
		$this->assertContains( OptionKeys::SCHEMA_VERSION, $keys );
	}
}
