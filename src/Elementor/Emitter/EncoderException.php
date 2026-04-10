<?php
/**
 * Exception for Elementor data encoding failures.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\Emitter;

use RuntimeException;

/**
 * Thrown when {@see Encoder::encode_for_meta()} fails to JSON-encode the
 * Elementor content tree. Replaces the previous behavior of silently returning
 * an empty string, which would corrupt `_elementor_data` with no signal.
 */
final class EncoderException extends RuntimeException {
}
