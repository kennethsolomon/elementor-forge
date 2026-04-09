<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Emitter;

use ElementorForge\Elementor\Emitter\KitTag;
use PHPUnit\Framework\TestCase;

final class KitTagTest extends TestCase {

	public function test_color_reference_format(): void {
		$this->assertSame( 'globals/colors?id=primary', KitTag::color( KitTag::COLOR_PRIMARY ) );
		$this->assertSame( 'globals/colors?id=accent', KitTag::color( KitTag::COLOR_ACCENT ) );
		$this->assertSame( 'globals/colors?id=5585a52', KitTag::color( '5585a52' ) );
	}

	public function test_typography_reference_format(): void {
		$this->assertSame( 'globals/typography?id=70fe5a0', KitTag::typography( '70fe5a0' ) );
	}

	public function test_globals_wrapper(): void {
		$out = KitTag::globals(
			array(
				'title_color'           => KitTag::color( KitTag::COLOR_PRIMARY ),
				'typography_typography' => KitTag::typography( 'abc' ),
			)
		);
		$this->assertArrayHasKey( '__globals__', $out );
		$this->assertSame( 'globals/colors?id=primary', $out['__globals__']['title_color'] );
	}
}
