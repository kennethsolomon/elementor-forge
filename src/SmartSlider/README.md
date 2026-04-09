# Smart Slider 3 Free — Reverse-Engineered Schema Notes

This document is the source of truth for Forge's Smart Slider 3 CRUD layer. Every
column name, type, and behavior recorded here was verified against the official
Smart Slider 3 plugin source on the WordPress.org SVN trunk
(`https://plugins.svn.wordpress.org/smart-slider-3/trunk/`) at revision **3502120**,
plugin **Stable tag 3.5.1.34**, on 2026-04-08. Anything in the "Unverified" section
is a known gap that needs live wp-env confirmation before being relied on.

The Smart Slider 3 Free plugin ships **no public PHP API for CRUD** — the entire
`Nextend\SmartSlider3\PublicApi\` namespace contains only `Project::clearCache($id)`
and `Project::import($pathToFile, $groupID)`. There is no `createSlider`, no
`addSlide`, no `updateSlider`. Direct database writes through `$wpdb` are the only
integration surface for programmatic slider authoring.

## Tested version range

| Constraint  | Value     | Source                              |
|-------------|-----------|-------------------------------------|
| Tested with | `3.5.1.34`| Stable tag in `readme.txt`          |
| Min         | `3.5.0`   | Conservative — schema columns last touched in 3.5.1.x |
| Max         | `3.6.x`   | Reject anything `>= 3.7.0` until re-validated |

`SliderRepository` enforces this range via the `is_supported_version()` gate. The
gate reads the value of `Settings::get('n2_ss3_version', ...)` from the option row
that Smart Slider's installer writes (Smart Slider stores its version in its own
`section_storage` table, not in `wp_options`). When the gate is unavailable
(option missing) Forge falls back to detecting the `NEXTEND_SMARTSLIDER_3_URL_PATH`
constant — presence proves the plugin is loaded but does not prove version. In
that case the repository operates in read-only mode and writes raise
`RuntimeException`.

## Feature detection

The canonical detect, in priority order:

1. `defined('NEXTEND_SMARTSLIDER_3_URL_PATH')` — set in `Defines.php` of the
   plugin and is the lowest-noise gate. **This is the primary detect.**
2. `class_exists('\Nextend\SmartSlider3\SmartSlider3')` — secondary gate that
   confirms the autoloader has the SmartSlider namespace registered.

If neither is true, every public `SliderRepository` method throws
`SmartSliderUnavailable` (a `RuntimeException` subclass). Forge never tries to
load the Smart Slider plugin itself.

## Verified tables

All tables use the WordPress `$wpdb->prefix` (default `wp_`). Smart Slider also
honors a Joomla-style `#__` prefix internally but on WordPress the prefix is
always WordPress's `$wpdb->prefix`. Forge reads the prefix from `$wpdb->prefix`
and never hard-codes `wp_`.

### `{prefix}nextend2_smartslider3_sliders`

The slider master record. One row per slider.

| Column          | Type              | Null | Default        | Notes                                  |
|-----------------|-------------------|------|----------------|----------------------------------------|
| `id`            | INT(11) AI PK     | NO   | —              | Auto-increment primary key             |
| `alias`         | TEXT              | YES  | NULL           | URL-style alias, optional              |
| `title`         | TEXT              | NO   | —              | Human-readable slider name             |
| `type`          | VARCHAR(30)       | NO   | —              | Slider engine type. Free supports `simple` and `block`. |
| `params`        | MEDIUMTEXT (JSON) | NO   | —              | **JSON object** of slider settings — see [params shape](#sliderparams-shape) |
| `slider_status` | VARCHAR(50)       | NO   | `'published'`  | Indexed. Other observed value: `trash` |
| `time`          | DATETIME          | NO   | —              | Last-modified. Indexed.                |
| `thumbnail`     | TEXT              | NO   | —              | Path or URL to slider thumbnail image  |
| `ordering`      | INT               | NO   | `'0'`          | Manual sort order                      |

**Indexes:** `slider_status`, `time`, primary on `id`.

### `{prefix}nextend2_smartslider3_slides`

Individual slides belonging to a slider. Linked to the parent via `slider`.

| Column         | Type              | Null | Default               | Notes                                  |
|----------------|-------------------|------|-----------------------|----------------------------------------|
| `id`           | INT(11) AI PK     | NO   | —                     | Auto-increment primary key             |
| `title`        | TEXT              | NO   | —                     | Slide title                            |
| `slider`       | INT(11)           | NO   | —                     | FK to `sliders.id`. Indexed.           |
| `publish_up`   | DATETIME          | NO   | `'1970-01-01 00:00:00'` | Schedule start. Indexed.             |
| `publish_down` | DATETIME          | NO   | `'1970-01-01 00:00:00'` | Schedule end. Indexed.               |
| `published`    | TINYINT(1)        | NO   | —                     | 0 or 1. Indexed.                       |
| `first`        | INT(11)           | NO   | —                     | 1 if this is the slider's "first" slide, else 0 |
| `slide`        | LONGTEXT (JSON)   | YES  | —                     | **JSON array** — the layer tree. See [slide layer JSON shape](#slide-layer-json-shape). |
| `description`  | TEXT              | NO   | —                     | Optional slide description             |
| `thumbnail`    | TEXT              | NO   | —                     | Optional slide thumbnail. Indexed (first 100 chars). |
| `params`       | TEXT (JSON)       | NO   | —                     | **JSON object** — slide-level settings (background, link, etc.) |
| `ordering`     | INT(11)           | NO   | —                     | Sort order within slider. Indexed.     |
| `generator_id` | INT(11)           | NO   | —                     | FK to `generators.id` if dynamic, else 0. Indexed. |

**Indexes:** `published`, `publish_up`, `publish_down`, `generator_id`,
`thumbnail(100)`, `ordering`, `slider`, primary on `id`.

### `{prefix}nextend2_smartslider3_sliders_xref`

Maps sliders to slider groups. Forge does **not** write this table — slider
groups are an admin-UI organizational feature and `group_id=0` is the canonical
"ungrouped" state. Forge's `delete_slider()` clears any xref row for the slider
being deleted to prevent orphans.

| Column      | Type   | Null | Default | Notes                                           |
|-------------|--------|------|---------|-------------------------------------------------|
| `group_id`  | INT(11)| NO   | —       | Composite PK part 1. `0` means ungrouped.       |
| `slider_id` | INT(11)| NO   | —       | Composite PK part 2.                            |
| `ordering`  | INT(11)| NO   | `'0'`   | Sort within group. Indexed.                     |

### `{prefix}nextend2_smartslider3_generators`

Forge does not touch this table. Documented here for completeness because
`slides.generator_id` references it. A row of `0` in `slides.generator_id` means
"static slide, no generator."

## `slider.params` shape

Verified directly from the sample slider INSERT in `Install.php`. The column is
a **JSON-encoded string** (not a PHP serialize). Top-level keys observed in the
3.5.1.34 sample:

```json
{
  "aria-label": "Slider",
  "alias-id": "",
  "alias-smoothscroll": "",
  "alias-slideswitch": "",
  "background": "",
  "background-fixed": "0",
  "background-size": "cover",
  "background-color": "FFFFFF00",
  "...many more fields...": "..."
}
```

Forge does NOT attempt to validate every key — Smart Slider tolerates unknown
keys and falls back to defaults. The repository writes a **minimal** params
object (`title`, `aria-label`, plus any caller-supplied keys) and lets Smart
Slider fill in defaults at render time.

The `Defaults::SLIDER_PARAMS` constant in `SliderRepository` holds the minimum
viable params shape, derived from the 3.5.1.34 sample.

## Slide layer JSON shape

The `slide` column is a **JSON-encoded array of layer objects**. Each layer has
a `type` field. Common types observed in the sample:

- `content` — root container
- `row` — flexbox row
- `col` — column
- `layer` — leaf widget (heading, text, button, image)

Each layer carries an exhaustive set of `desktopportrait*`, `tabletportrait*`,
and `mobileportrait*` keys for responsive behavior. Forge ships a
`SlideTemplate::minimal($title, $body)` factory that produces a simple
heading-plus-text layer tree using only the keys that the 3.5.1.34 sample
required to render successfully.

**Forge does not parse user-supplied slide trees.** Callers either pass a raw
layer array (advanced) or use `SlideTemplate::minimal()` (simple). Round-tripping
arbitrary slide JSON through PHP introduces drift risk that is not worth solving
in Phase 3.

## `slide.params` shape

```json
{
  "background": "color",
  "backgroundColor": "ffffff00",
  "...": "..."
}
```

Same approach as `slider.params` — minimal write, let Smart Slider fill defaults.

## Cache invalidation

Smart Slider 3 caches rendered slider HTML in its own cache directory.
The verified public method to invalidate is:

```php
\Nextend\SmartSlider3\PublicApi\Project::clearCache($projectID);
```

This is the **only** Smart Slider PublicApi method Forge uses. After every
write (`create_slider`, `update_slider`, `add_slide`, `update_slide`,
`delete_slide`), `SliderRepository` calls `clearCache($slider_id)` if the
class is loaded. If the class is missing the repository sets a Forge option
flag `elementor_forge_ss3_cache_dirty=true` so the next visit to the Forge
settings page can surface a "Smart Slider cache may be stale, clear from
Smart Slider admin" notice.

## Capability gating

Every public `SliderRepository` method calls `current_user_can('manage_options')`
**before** any `$wpdb` interaction. The MCP `manage_slider` tool wraps this with
the same capability. Smart Slider's own `manage_smartslider` capability is **not**
used because Forge wants the gate to be admin-only regardless of how Smart
Slider's roles are configured on the host site.

## SQL safety

Every `$wpdb` call uses `$wpdb->prepare()` with `%s`/`%d` placeholders. The only
non-prepared parts are static table names (resolved from `$wpdb->prefix` once at
class construction). No user-supplied input is concatenated into SQL.

The repository never uses `dbDelta()` because it does not own the schema —
Smart Slider's installer is the schema owner. Repository methods only ever
INSERT/UPDATE/DELETE rows in tables that Smart Slider has already created.

## Unverified / known gaps

The following items could not be confirmed from source reading alone and need
wp-env live testing before being relied on in production:

- **`slide.first` semantics under multi-slide updates.** Source shows the
  column exists and the sample slider sets it to `0` for the second slide and
  `1` for the first. Behavior under "promote slide N to first" via direct
  `UPDATE` was not exercised; Forge's `add_slide()` sets `first=1` for the
  initial slide and `first=0` for subsequent slides but does not provide a
  reorder API.
- **Effects of orphaning a slide** (`slider` FK pointing at a deleted slider).
  Smart Slider has no DB-level FK constraint, only an index. `delete_slider()`
  cascades by issuing a separate `DELETE FROM slides WHERE slider = ?` first;
  un-cascaded behavior is untested.
- **The `time` column format.** Verified to be `DATETIME` from the schema.
  Forge uses `current_time('mysql')` which produces the correct format. The
  Smart Slider admin UI behavior on a manually-edited `time` value is untested.
- **`alias` collisions.** The schema makes `alias` nullable TEXT with no unique
  constraint. Forge does not deduplicate aliases — callers are responsible.
- **`slider.params` keys that crash the editor.** The 3.5.1.34 sample contains
  ~80 keys and Forge writes only a subset. Smart Slider's editor was not
  exercised against a Forge-created slider; rendering on the front end works
  but admin editor behavior is wp-env validation work.
- **Smart Slider 3 Pro overlay.** All schema notes here are for Smart Slider 3
  Free. Pro adds extra columns and a generators ecosystem; Forge is gated to
  Free only and rejects Pro detection at the version gate level (Pro version
  strings start with `3.5` too, but the `N2SSPRO` constant in `Defines.php`
  is the discriminator. Forge does not currently check this — adding the check
  is a Phase 1.5 follow-up flagged in `Owner's Inbox`).
