<?php
/**
 * Layout judge rule contract.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Intelligence\LayoutJudge;

/**
 * A pure-PHP rule that inspects a normalized {@see SectionData} and either
 * returns a {@see Decision} (rule matches) or null (rule does not apply).
 *
 * Rules are stateless and side-effect free — they MUST be safe to call
 * concurrently. The judge instantiates them once at construction and reuses.
 */
interface Rule {

	/**
	 * Stable identifier for this rule. Used in decision audit trails. Must be
	 * unique across the rules registered with a single judge instance.
	 */
	public function id(): string;

	/**
	 * Evaluate the rule. Return null when the rule does not match the section.
	 */
	public function evaluate( SectionData $section ): ?Decision;
}
