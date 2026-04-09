<?php
/**
 * Layout judge decision value object.
 *
 * @package ElementorForge
 */

declare(strict_types=1);

namespace ElementorForge\Intelligence\LayoutJudge;

/**
 * Immutable value object describing a single LayoutJudge ruling. Returned by
 * every {@see Rule::evaluate()} that fires and by {@see LayoutJudge::decide()}.
 *
 * Confidence is a float in the closed range [0.0, 1.0]. The judge sorts
 * matched rules by confidence descending and returns the highest. Anything
 * below {@see LayoutJudge::LOW_CONFIDENCE_THRESHOLD} is flagged so callers can
 * down-rank, log, or escalate the decision.
 */
final class Decision {

	public const WIDGET_ICON_LIST        = 'icon_list';
	public const WIDGET_IMAGE_CAROUSEL   = 'image_carousel';
	public const WIDGET_ICON_BOX_GRID    = 'icon_box_grid';
	public const WIDGET_NESTED_CAROUSEL  = 'nested_carousel';
	public const WIDGET_NESTED_ACCORDION = 'nested_accordion';
	public const WIDGET_TEXT_EDITOR      = 'text_editor';

	private string $widget;
	private string $reason;
	private float $confidence;
	private string $rule_id;

	public function __construct( string $widget, string $reason, float $confidence, string $rule_id ) {
		$this->widget     = $widget;
		$this->reason     = $reason;
		$this->confidence = max( 0.0, min( 1.0, $confidence ) );
		$this->rule_id    = $rule_id;
	}

	public function widget(): string {
		return $this->widget;
	}

	public function reason(): string {
		return $this->reason;
	}

	public function confidence(): float {
		return $this->confidence;
	}

	public function rule_id(): string {
		return $this->rule_id;
	}

	/**
	 * @return array{widget:string, reason:string, confidence:float, rule_id:string}
	 */
	public function to_array(): array {
		return array(
			'widget'     => $this->widget,
			'reason'     => $this->reason,
			'confidence' => $this->confidence,
			'rule_id'    => $this->rule_id,
		);
	}
}
