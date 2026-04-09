<?php
/**
 * Deterministic semantic layout judge.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Intelligence\LayoutJudge;

use ElementorForge\Intelligence\LayoutJudge\Rules\FaqRule;
use ElementorForge\Intelligence\LayoutJudge\Rules\GalleryRule;
use ElementorForge\Intelligence\LayoutJudge\Rules\IconBoxGridRule;
use ElementorForge\Intelligence\LayoutJudge\Rules\IconListRule;
use ElementorForge\Intelligence\LayoutJudge\Rules\NestedCarouselRule;
use ElementorForge\Intelligence\LayoutJudge\Rules\TextHeavyAccordionRule;

/**
 * Decides which Elementor widget pattern best fits a content section based on
 * structural and semantic signals (item count, text length, image presence,
 * icon presence, declared section type).
 *
 * Pure PHP, no external calls. Every decision is fully reproducible from the
 * input shape — no randomness, no I/O, no network. Designed to be unit tested
 * end to end without WordPress loaded.
 *
 * Architecture:
 *
 *   - The judge holds a list of {@see Rule} instances.
 *   - On `decide()`, every rule is evaluated against the section.
 *   - Matched rules are sorted by confidence descending.
 *   - The highest-confidence decision wins.
 *   - If NO rule matches, a low-confidence text-editor fallback is returned
 *     and `is_low_confidence()` returns true so callers can flag for review.
 *
 * Extension point: pass a custom list of rules to the constructor to swap the
 * deterministic engine for an LLM-backed judge or A/B test alternative rule
 * sets. Production code should use {@see self::with_default_rules()}.
 */
final class LayoutJudge {

	public const LOW_CONFIDENCE_THRESHOLD = 0.5;

	/** @var list<Rule> */
	private array $rules;

	/**
	 * @param list<Rule> $rules
	 */
	public function __construct( array $rules ) {
		$this->rules = array_values( $rules );
	}

	/**
	 * Build a judge wired with the canonical Phase 3 rule set, in priority
	 * order. Order does not affect correctness — the highest-confidence match
	 * wins regardless — but keeping deterministic order makes the audit trail
	 * easier to read.
	 */
	public static function with_default_rules(): self {
		return new self(
			array(
				new FaqRule(),
				new GalleryRule(),
				new IconListRule(),
				new IconBoxGridRule(),
				new TextHeavyAccordionRule(),
				new NestedCarouselRule(),
			)
		);
	}

	/**
	 * Decide which widget best fits the section. Always returns a {@see Decision} —
	 * the fallback is a low-confidence text-editor.
	 *
	 * @param array<string, mixed> $section_data Raw section shape from a content doc.
	 */
	public function decide( array $section_data ): Decision {
		$section = SectionData::from_array( $section_data );

		/** @var list<Decision> $matches */
		$matches = array();
		foreach ( $this->rules as $rule ) {
			$decision = $rule->evaluate( $section );
			if ( null !== $decision ) {
				$matches[] = $decision;
			}
		}

		if ( array() === $matches ) {
			return $this->fallback( $section );
		}

		usort(
			$matches,
			static function ( Decision $a, Decision $b ): int {
				return $b->confidence() <=> $a->confidence();
			}
		);

		return $matches[0];
	}

	/**
	 * Decide and return both the winning decision plus every match — useful
	 * for audit/diagnostic UI in the admin settings page.
	 *
	 * @param array<string, mixed> $section_data
	 * @return array{decision: Decision, matches: list<Decision>}
	 */
	public function decide_with_audit( array $section_data ): array {
		$section = SectionData::from_array( $section_data );

		/** @var list<Decision> $matches */
		$matches = array();
		foreach ( $this->rules as $rule ) {
			$decision = $rule->evaluate( $section );
			if ( null !== $decision ) {
				$matches[] = $decision;
			}
		}

		usort(
			$matches,
			static function ( Decision $a, Decision $b ): int {
				return $b->confidence() <=> $a->confidence();
			}
		);

		$winner = array() === $matches ? $this->fallback( $section ) : $matches[0];

		return array(
			'decision' => $winner,
			'matches'  => $matches,
		);
	}

	/**
	 * Total number of rules registered. Surfaced on the admin Intelligence
	 * sub-section so Kenneth can confirm the rules engine loaded.
	 */
	public function rule_count(): int {
		return count( $this->rules );
	}

	/**
	 * Whether the supplied decision should be flagged for human review.
	 */
	public static function is_low_confidence( Decision $decision ): bool {
		return $decision->confidence() < self::LOW_CONFIDENCE_THRESHOLD;
	}

	private function fallback( SectionData $section ): Decision {
		return new Decision(
			Decision::WIDGET_TEXT_EDITOR,
			sprintf(
				'No rule matched section "%s" with %d items — falling back to text editor.',
				$section->type(),
				$section->item_count()
			),
			0.2,
			'fallback.text_editor'
		);
	}
}
