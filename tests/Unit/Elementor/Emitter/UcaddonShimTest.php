<?php

declare(strict_types=1);

namespace ElementorForge\Tests\Unit\Elementor\Emitter;

use ElementorForge\Elementor\Emitter\Widgets\UcaddonShim;
use PHPUnit\Framework\TestCase;

final class UcaddonShimTest extends TestCase {

	public function test_identifies_ucaddon_prefix(): void {
		$this->assertTrue( UcaddonShim::is_ucaddon( 'ucaddon_hover_text_reveal_content_box' ) );
		$this->assertTrue( UcaddonShim::is_ucaddon( 'ucaddon_anything' ) );
		$this->assertFalse( UcaddonShim::is_ucaddon( 'heading' ) );
		$this->assertFalse( UcaddonShim::is_ucaddon( 'ucaddons' ) );
	}

	public function test_widget_type_preserves_original(): void {
		$shim = new UcaddonShim( 'ucaddon_hover_text_reveal_content_box', array( 'foo' => 'bar' ), 'abcd1234' );
		$this->assertSame( 'ucaddon_hover_text_reveal_content_box', $shim->widget_type() );
		$arr = $shim->to_array();
		$this->assertSame( 'widget', $arr['elType'] );
		$this->assertSame( 'ucaddon_hover_text_reveal_content_box', $arr['widgetType'] );
	}
}
