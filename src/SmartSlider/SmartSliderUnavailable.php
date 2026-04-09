<?php
/**
 * Smart Slider 3 unavailable exception.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\SmartSlider;

use RuntimeException;

/**
 * Thrown by {@see SliderRepository} when Smart Slider 3 is not present, is the
 * wrong version, or any required table is missing. Always carries a
 * human-readable message — the MCP tool surface forwards this verbatim.
 */
final class SmartSliderUnavailable extends RuntimeException {

}
