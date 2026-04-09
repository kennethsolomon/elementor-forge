<?php
/**
 * Normalized section data — input shape for the layout judge.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Intelligence\LayoutJudge;

/**
 * Strongly-typed wrapper around the loose `array<string, mixed>` shapes that
 * MCP tools and the emitter pass around. Computes derived signals (item count,
 * average text length, image presence) once on construction so rules are pure
 * lookups, not nested array walks.
 *
 * Recognized section types: `bullets`, `services`, `features`, `process_steps`,
 * `faq`, `gallery`, `testimonials`. Unknown types are accepted — rules decide
 * what to do with them.
 */
final class SectionData {

	private string $type;
	private int $item_count;
	private int $avg_text_length;
	private int $max_text_length;
	private int $images_present;
	private int $items_with_icon;
	private bool $is_text_heavy;

	/** @var list<array<string, mixed>> */
	private array $items;

	/** @var array<string, mixed> */
	private array $raw;

	/**
	 * @param array<string, mixed> $raw
	 */
	private function __construct( array $raw ) {
		$this->raw  = $raw;
		$this->type = isset( $raw['section'] ) && is_string( $raw['section'] ) ? $raw['section'] : '';

		$items = isset( $raw['items'] ) && is_array( $raw['items'] ) ? array_values( $raw['items'] ) : array();
		/** @var list<array<string, mixed>> $normalized */
		$normalized = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) ) {
				$normalized[] = $item;
				continue;
			}
			if ( is_string( $item ) ) {
				$normalized[] = array( 'text' => $item );
			}
		}
		$this->items      = $normalized;
		$this->item_count = count( $normalized );

		$lengths     = array();
		$with_image  = 0;
		$with_icon   = 0;
		foreach ( $normalized as $item ) {
			$lengths[]  = self::text_length( $item );
			$with_image += self::has_image( $item ) ? 1 : 0;
			$with_icon  += self::has_icon( $item ) ? 1 : 0;
		}
		$this->avg_text_length = array() === $lengths ? 0 : (int) round( array_sum( $lengths ) / count( $lengths ) );
		$this->max_text_length = array() === $lengths ? 0 : (int) max( $lengths );
		$this->images_present  = $with_image;
		$this->items_with_icon = $with_icon;
		$this->is_text_heavy   = $this->avg_text_length > 120 || $this->max_text_length > 240;
	}

	/**
	 * Build a SectionData from the raw array shape MCP tools and the emitter
	 * pass around.
	 *
	 * @param array<string, mixed> $raw
	 */
	public static function from_array( array $raw ): self {
		return new self( $raw );
	}

	public function type(): string {
		return $this->type;
	}

	public function item_count(): int {
		return $this->item_count;
	}

	public function avg_text_length(): int {
		return $this->avg_text_length;
	}

	public function max_text_length(): int {
		return $this->max_text_length;
	}

	public function images_present(): int {
		return $this->images_present;
	}

	public function items_with_icon(): int {
		return $this->items_with_icon;
	}

	public function is_text_heavy(): bool {
		return $this->is_text_heavy;
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function items(): array {
		return $this->items;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function raw(): array {
		return $this->raw;
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function text_length( array $item ): int {
		$candidates = array(
			isset( $item['text'] ) && is_string( $item['text'] ) ? $item['text'] : '',
			isset( $item['description'] ) && is_string( $item['description'] ) ? $item['description'] : '',
			isset( $item['answer'] ) && is_string( $item['answer'] ) ? $item['answer'] : '',
			isset( $item['body'] ) && is_string( $item['body'] ) ? $item['body'] : '',
		);
		$max = 0;
		foreach ( $candidates as $candidate ) {
			$len = strlen( $candidate );
			if ( $len > $max ) {
				$max = $len;
			}
		}
		return $max;
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function has_image( array $item ): bool {
		return isset( $item['image'] ) || isset( $item['url'] ) || isset( $item['src'] );
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function has_icon( array $item ): bool {
		return isset( $item['icon'] );
	}
}
