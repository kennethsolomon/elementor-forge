<?php
/**
 * Theme Builder template specification DTO.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Elementor\ThemeBuilder;

use ElementorForge\Elementor\Emitter\Document;

/**
 * Simple immutable holder for a Theme Builder template ready to be installed.
 * Exposes the fields the installer needs: internal type slug, display title,
 * the pre-built {@see Document}, and the postmeta rows that Elementor uses to
 * flag the template as a Theme Builder Single / Header / Footer.
 */
final class TemplateSpec {

	/** @var string */
	private string $type;

	/** @var string */
	private string $title;

	/** @var Document */
	private Document $document;

	/** @var array<string, mixed> */
	private array $meta;

	/**
	 * @param array<string, mixed> $meta
	 */
	public function __construct( string $type, string $title, Document $document, array $meta ) {
		$this->type     = $type;
		$this->title    = $title;
		$this->document = $document;
		$this->meta     = $meta;
	}

	public function type(): string {
		return $this->type;
	}

	public function title(): string {
		return $this->title;
	}

	public function document(): Document {
		return $this->document;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function meta(): array {
		return $this->meta;
	}
}
