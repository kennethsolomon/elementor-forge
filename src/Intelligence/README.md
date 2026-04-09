# Intelligence Layer — Layout Judge

## Overview

The Intelligence Layer decides which Elementor widget pattern best fits a section of content. It runs between the content-doc parser and the Emitter: the parser produces a flat list of semantic sections (`{type: "faq", items: [...]}`), the Layout Judge picks a widget (`nested-accordion`, `icon-list`, `nested-carousel`, etc.), and then `JudgedEmitter` hands the resulting decision to the Phase 1 Emitter to produce the final Elementor JSON.

Everything in this layer is pure PHP. No external calls, no randomness, no I/O, no network. Every decision is fully reproducible from the input shape — the same section data always yields the same decision. Unit tests run end-to-end without WordPress loaded.

The extension point is the `Rule` interface. `LayoutJudge` holds a `list<Rule>` and can be constructed with any rule set — the constructor takes the list as-is, and `LayoutJudge::with_default_rules()` wires the canonical Phase 3 set. A future LLM-backed judge can replace the deterministic rule list without touching any caller: construct the judge with a custom rule that wraps an LLM client and you're done.

## When it runs

`JudgedEmitter` composes with the Phase 1 Emitter. When a caller asks `JudgedEmitter` to build a page from a content doc:

1. The content doc is parsed into a list of section shapes.
2. For each section, `LayoutJudge::decide()` is called.
3. The returned `Decision` tells the Emitter which widget to use and any widget-specific settings (column count, sticky heading, etc).
4. The Emitter produces the Elementor JSON as normal.

When no rule matches a section, the judge returns a low-confidence fallback decision (text editor widget, confidence 0.2) and `LayoutJudge::is_low_confidence($decision)` returns true. Callers can surface low-confidence sections in a diagnostic admin panel so Kenneth can review them by hand before publishing the page.

## Confidence semantics

Every `Decision` carries a confidence score in `[0.0, 1.0]`. Rules emit confidence based on how well the section matches their signal profile. For example:

- `FaqRule` emits 1.0 when the section explicitly declares `type: "faq"` and has structured `question` + `answer` fields.
- `FaqRule` emits 0.7 when the section has Q&A-shaped content but no explicit type.
- `IconListRule` emits 0.85 when every item has an icon and short text.

On a tie (same confidence from multiple rules), PHP's `usort` preserves the original rule order — which is why `with_default_rules()` registers rules in the intended priority order even though the judge sorts by confidence independently. In practice exact ties are rare; when they happen, the Phase 1.5 backlog item #19 covers stricter tie-breaking logic.

## The 6 rules

### FaqRule

Matches sections that describe questions and answers. Primary signal is `type: "faq"` in the section data; secondary signal is item shape (`question` + `answer` keys on every item). Emits a `nested-accordion` widget decision with each Q&A as an accordion item. High confidence (0.9-1.0) when the type is declared, medium (0.6-0.7) when detected structurally.

### GalleryRule

Matches sections composed primarily of images with minimal accompanying text. Primary signal is `images_present=true && avg_text_length < 30`. Emits an `image-carousel` widget for 4+ images and an `image` grid container for 3 or fewer. Confidence scales with image count — a 12-image section scores higher than a 4-image section because the "gallery" intent is more obvious.

### IconListRule

Matches sections where every item has an icon and short descriptive text (typical feature lists, service highlights, trust signals). Primary signal is `all_items_have_icon && avg_text_length < 80`. Emits an `icon-list` widget. Confidence is 0.85 when every item has an icon; drops to 0.6 when 70-90% do (partial match).

### IconBoxGridRule

Matches sections that look like a feature grid — 4-9 items, each with an icon, a heading, and a short description. Distinguishable from `IconListRule` by the presence of a per-item heading and a 2-3 sentence description (longer than the icon-list's short text). Emits an `icon-box` grid container with a responsive column count based on item count (4 items → 2x2, 6 items → 3x2, 9 items → 3x3).

### TextHeavyAccordionRule

Matches sections that are semantically grouped paragraphs with headings (think "Our Process: Step 1 — long paragraph, Step 2 — long paragraph"). Primary signal is `item_count <= 8 && avg_text_length > 200 && every item has a heading`. Emits a `nested-accordion` widget so the long text is collapsible. Confidence is lower than FaqRule for overlapping cases — FaqRule should win for Q&A-shaped content even when the answers are long paragraphs.

### NestedCarouselRule

Matches sections that describe testimonials, case studies, or any group of self-contained blocks that should rotate rather than stack vertically. Primary signal is `type` in `["testimonial", "case_study", "logos"]` OR `item_count > 6 && items are similar shape`. Emits a `nested-carousel` widget with auto-play enabled for logo/trust sections and disabled for testimonials.

## How JudgedEmitter composes with the Phase 1 Emitter

```
ContentDoc
   │
   ▼
Parser ─────▶  list<SectionShape>
                 │
                 ▼
          LayoutJudge::decide(section)  ──▶ Decision { widget_type, settings, confidence }
                 │
                 ▼
          JudgedEmitter::emit_section(decision, section)
                 │
                 ├──▶ Phase 1 Emitter widget builders (heading, text-editor, icon-box, ...)
                 │
                 ▼
        list<ContainerNode>  ──▶ Emitter::wrap_in_page_container
                 │
                 ▼
          Elementor JSON v0.4 (ready for _elementor_data)
```

`JudgedEmitter` is a thin adapter. It does not replace any Phase 1 Emitter method — it just decides which Emitter method to call for each section. When the judge returns `WIDGET_NESTED_ACCORDION`, `JudgedEmitter` calls the Phase 1 Emitter's existing `build_nested_accordion()` method. Nothing in the Phase 1 Emitter had to change to accommodate the Intelligence Layer.

## How to add a new rule

1. Create a new class under `src/Intelligence/LayoutJudge/Rules/` implementing the `Rule` interface.
2. The interface requires one method: `evaluate(SectionData $section): ?Decision`. Return `null` when the rule does not match. Return a `Decision` with a confidence score in `[0, 1]` when it does.
3. Use the `SectionData` accessors for signals — `type()`, `item_count()`, `avg_text_length()`, `has_images()`, `all_items_have_icon()`, etc. Do not reach into the raw array — the `SectionData` wrapper exists specifically so rules cannot see the raw input shape and can be unit tested against a fluent fixture.
4. Add the new rule to `LayoutJudge::with_default_rules()` in priority order. Priority order is audit-trail documentation — the judge sorts by confidence regardless.
5. Write unit tests in `tests/Unit/Intelligence/LayoutJudge/Rules/` covering:
   - The happy path (rule matches, correct widget, correct confidence).
   - At least one near-miss (structurally similar section that should NOT match).
   - One overlap case with an adjacent rule (two rules both match, assert the tie-break).
6. Run the full test suite — no existing test should change behavior, because `LayoutJudge::decide()` is guaranteed stable for any section the new rule does not match.

## Reference files

- `LayoutJudge.php` — the judge itself (constructor, `decide()`, `decide_with_audit()`, `with_default_rules()`).
- `Rule.php` — the `Rule` interface.
- `Decision.php` — the `Decision` value object (widget type, settings, confidence, reason string, rule id).
- `SectionData.php` — the input wrapper that precomputes signals so rules do not touch the raw array.
- `JudgedEmitter.php` — the composition adapter between the judge and the Phase 1 Emitter.
- `Rules/*.php` — the 6 default rules.
