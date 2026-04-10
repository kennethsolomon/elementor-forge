# Elementor Forge — MCP Tool Reference

Canonical reference for every MCP tool exposed by Elementor Forge. Fifteen tools ship in v0.8.0. All tools are registered as WP Abilities on the `wp_abilities_api_init` action and consumed by `wordpress/mcp-adapter` during `mcp_adapter_init`. The entire MCP server can be disabled via the `mcp_server` plugin setting (set to `disabled` for zero runtime footprint).

Every tool runs `current_user_can()` in its `permission` callback before any code executes. Capability requirements are listed per tool.

## Overview

| Tool | Capability | Purpose |
|---|---|---|
| [`create_page`](#create_page) | `publish_pages` | Build a one-off Elementor page from a content doc |
| [`add_section`](#add_section) | `edit_pages` | Append a section block to an existing page |
| [`apply_template`](#apply_template) | `manage_options` | Create a CPT post, populate ACF fields, assign Single template |
| [`bulk_generate_pages`](#bulk_generate_pages) | `manage_options` | Batched + transactional matrix page generation |
| [`configure_woocommerce`](#configure_woocommerce) | `manage_woocommerce` (falls back to `manage_options`) | WooCommerce Theme Builder + Fibosearch configuration |
| [`manage_slider`](#manage_slider) | `manage_options` | Smart Slider 3 CRUD — 8 actions behind a single tool |
| [`set_kit_globals`](#set_kit_globals) | `manage_options` | Write brand palette, typography, and button styles to the Default Kit |
| [`create_header`](#create_header) | `manage_options` | Install a Theme Builder header template from a preset |
| [`create_footer`](#create_footer) | `manage_options` | Install a Theme Builder footer template from a preset |
| [`get_page_structure`](#get_page_structure) | `edit_pages` | Read-only annotated tree of an Elementor page's sections and widgets |
| [`edit_section`](#edit_section) | `edit_pages` | Replace a top-level section on an existing page by index or ID |
| [`delete_section`](#delete_section) | `edit_pages` | Remove a top-level section from a page by index or ID |
| [`reorder_sections`](#reorder_sections) | `edit_pages` | Reorder top-level sections by providing the desired index sequence |
| [`update_widget`](#update_widget) | `edit_pages` | Deep-walk the document tree and merge new settings into a widget |
| [`duplicate_section`](#duplicate_section) | `edit_pages` | Deep-clone a section with regenerated IDs and insert it at a position |

---

## create_page

**When to use.** Use this when you have a structured content doc and want a single Elementor page created from it in one call.

**Capability:** `publish_pages`.

**Input schema:**
```json
{
  "type": "object",
  "required": ["title", "content_doc"],
  "additionalProperties": false,
  "properties": {
    "title":       { "type": "string", "minLength": 1 },
    "status":      { "type": "string", "enum": ["draft", "publish"], "default": "draft" },
    "content_doc": { "type": "object" }
  }
}
```

**Output schema:**
```json
{
  "type": "object",
  "properties": {
    "post_id": { "type": "integer" },
    "url":     { "type": "string" }
  }
}
```

---

## add_section

**When to use.** Use this when a page already exists and you want to append a pre-built section block to its Elementor JSON tree.

**Capability:** `edit_pages`.

**Input schema:**
```json
{
  "type": "object",
  "required": ["page_id", "block"],
  "additionalProperties": false,
  "properties": {
    "page_id": { "type": "integer", "minimum": 1 },
    "block":   { "type": "object" }
  }
}
```

**Output schema:**
```json
{
  "type": "object",
  "properties": {
    "post_id":  { "type": "integer" },
    "appended": { "type": "boolean" }
  }
}
```

---

## apply_template

**When to use.** Use this when you want a new CPT post created with ACF fields populated and the matching Theme Builder Single template automatically assigned. Primary use case is one-shot "create a location page for Suburb X with the default layout."

**Capability:** `manage_options`.

**Input schema:**
```json
{
  "type": "object",
  "required": ["cpt", "post_data"],
  "additionalProperties": false,
  "properties": {
    "cpt":       { "type": "string", "enum": ["ef_location", "ef_service"] },
    "post_data": { "type": "object" }
  }
}
```

**Output schema:**
```json
{
  "type": "object",
  "properties": {
    "post_id": { "type": "integer" },
    "url":     { "type": "string" }
  }
}
```

---

## bulk_generate_pages

**When to use.** Use this for matrix page generation — e.g. 10 suburbs × 5 services = 50 location pages. Single call, atomic transaction, batched cache handling, progress polling.

**Capability:** `manage_options`.

**Behavior:**
- Wraps the full loop in `wp_suspend_cache_addition(true)` + `wp_defer_term_counting(true)`. These are restored in a `finally` block so even an uncaught `Throwable` from `wp_insert_post` cannot leave the cache suspended.
- When `transactional: true` (default), wraps the loop in a single `START TRANSACTION`. On the first `WP_Error` from `wp_insert_post`, issues `ROLLBACK` and breaks the loop. On clean completion, issues `COMMIT`. On any uncaught throwable, issues `ROLLBACK` and re-throws.
- When `dry_run: true`, validates inputs and returns the planned post count + per-post field map without writing anything.
- When `multiply_by` + `service_items` are provided, crosses `items` × `service_items` to build a Cartesian product (matrix mode). Each combination produces one post with the service title appended to the base title.
- Meta-key filter rejects any `_`-prefixed key (WP-internal meta convention) unconditionally. When `allowed_fields` is provided, it acts as a hard allowlist on top of the underscore filter. Rejected keys are returned in the result as `rejected_meta_keys` so the caller can see what was stripped.
- Progress is recorded to a transient keyed by `job_id` so long-running jobs can be polled via `BulkGenerate::get_progress($job_id)`.

**Input schema:**
```json
{
  "type": "object",
  "required": ["cpt", "items"],
  "additionalProperties": false,
  "properties": {
    "cpt":           { "type": "string", "enum": ["ef_location", "ef_service"] },
    "items":         {
      "type": "array",
      "minItems": 1,
      "items": {
        "type": "object",
        "required": ["title"],
        "properties": {
          "title":      { "type": "string" },
          "status":     { "type": "string", "enum": ["draft", "publish"] },
          "acf_fields": { "type": "object" }
        }
      }
    },
    "multiply_by":   { "type": "string", "enum": ["ef_location", "ef_service"] },
    "service_items": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["title"],
        "properties": {
          "title":      { "type": "string" },
          "acf_fields": { "type": "object" }
        }
      }
    },
    "transactional":  { "type": "boolean", "default": true },
    "dry_run":        { "type": "boolean", "default": false },
    "job_id":         { "type": "string" },
    "allowed_fields": {
      "type":  "array",
      "items": { "type": "string" }
    }
  }
}
```

**Output schema:**
```json
{
  "type": "object",
  "properties": {
    "job_id":             { "type": "string" },
    "planned":            { "type": "integer" },
    "created":            { "type": "array" },
    "failed":             { "type": "array" },
    "dry_run":            { "type": "boolean" },
    "rolled_back":        { "type": "boolean" },
    "transactional":      { "type": "boolean" },
    "rejected_meta_keys": { "type": "array", "items": { "type": "string" } }
  }
}
```

---

## configure_woocommerce

**When to use.** Use this when WooCommerce is active on the target site and you want Forge to install its Shop / Single Product / Cart / Checkout Theme Builder templates and configure Fibosearch idempotently.

**Capability:** `manage_woocommerce` (falls back to `manage_options` when the WC capability is not present).

**Input schema:**
```json
{
  "type": "object",
  "required": [],
  "additionalProperties": false,
  "properties": {
    "install_templates": { "type": "boolean", "default": true, "description": "Install the four WooCommerce Theme Builder templates." },
    "apply_fibosearch":  { "type": "boolean", "default": true, "description": "Apply Forge default settings to Fibosearch if it is present." },
    "switch_header":     { "type": "boolean", "default": true, "description": "Switch header_pattern to ecommerce and install the ecommerce header variant." }
  }
}
```

**Output schema:**
```json
{
  "type": "object",
  "properties": {
    "wc_active":  { "type": "boolean" },
    "templates":  { "type": "object" },
    "fibosearch": { "type": "object" },
    "header":     { "type": "object" }
  }
}
```

---

## manage_slider

**When to use.** Use this for any Smart Slider 3 Free CRUD operation. Single tool, multi-action surface — the abilities API only registers one ability instead of eight. All writes run through a recursive `wp_kses_post()` walker on both top-level and nested string leaves before the JSON encode, so programmatic slider content cannot introduce stored XSS through `sliders.params` or `slides.slide`.

**Capability:** `manage_options`. Additionally gated on Smart Slider 3 presence (constant `NEXTEND_SMARTSLIDER_3_URL_PATH`) and version range (`3.5.0 <= ver < 3.7.0`). All failures return a `WP_Error` with error code `elementor_forge_smart_slider_unavailable`.

**Supported actions:**
| action | payload | result |
|---|---|---|
| `create_slider` | `{ title, params? }` | `{ slider_id }` |
| `update_slider` | `{ slider_id, title, params }` | `{ updated: bool }` |
| `get_slider` | `{ slider_id }` | `{ slider }` |
| `delete_slider` | `{ slider_id }` | `{ deleted: bool }` |
| `add_slide` | `{ slider_id, title, body?, layers?, params? }` | `{ slide_id }` |
| `update_slide` | `{ slide_id, title?, body?, layers?, params? }` | `{ updated: bool }` |
| `delete_slide` | `{ slide_id }` | `{ deleted: bool }` |
| `list_sliders` | `{}` | `{ sliders: list }` |

`delete_slider` wraps its three cascading deletes (slides → xref → slider) in a single `START TRANSACTION` / `COMMIT`, rolling back on mid-sequence failure to avoid orphaned rows.

**Input schema:**
```json
{
  "type": "object",
  "required": ["action"],
  "additionalProperties": false,
  "properties": {
    "action":  {
      "type": "string",
      "enum": [
        "create_slider",
        "update_slider",
        "get_slider",
        "delete_slider",
        "add_slide",
        "update_slide",
        "delete_slide",
        "list_sliders"
      ]
    },
    "payload": { "type": "object" }
  }
}
```

**Output schema:**
```json
{
  "type": "object",
  "properties": {
    "action": { "type": "string" },
    "result": { "type": "object" }
  }
}
```

---

## set_kit_globals

**When to use.** Call this first when setting up a new site. Writes brand colors, typography, and button styles to the Default Kit so every widget that references Kit globals picks up the values automatically.

**Capability:** `manage_options`.

**Input schema:**
```json
{
  "type": "object",
  "additionalProperties": false,
  "properties": {
    "colors": {
      "type": "object",
      "description": "Brand color palette. Keys: primary, secondary, text, accent (or custom IDs). Values: hex color strings (#rrggbb)."
    },
    "typography": {
      "type": "object",
      "description": "Typography settings. Keys: primary (headings), secondary (body), or custom IDs. Each value: {font_family, font_size, font_weight, line_height}."
    },
    "button": {
      "type": "object",
      "description": "Button style overrides. Keys: text_color, background_color, border_color, border_radius, padding, font_family, font_size, font_weight."
    }
  }
}
```

**Output schema:**
```json
{
  "type": "object",
  "properties": {
    "kit_id":  { "type": "integer" },
    "updated": { "type": "object" }
  }
}
```

---

## create_header

**When to use.** Use this to install a Theme Builder header template from a named preset (business, ecommerce, portfolio, blog, saas) with optional layout overrides. The header is installed as an `elementor_library` post with `include/general` display conditions.

**Capability:** `manage_options`.

**Input schema:**
```json
{
  "type": "object",
  "required": ["preset"],
  "additionalProperties": false,
  "properties": {
    "preset": {
      "type": "string",
      "enum": ["business", "ecommerce", "portfolio", "blog", "saas"],
      "description": "Header preset name."
    },
    "overrides": {
      "type": "object",
      "description": "Override the preset layout.",
      "properties": {
        "rows": {
          "type": "array",
          "items": {
            "type": "object",
            "properties": {
              "items":        { "type": "array", "items": { "type": "string" }, "description": "Item keywords: logo, logo_center, nav, hamburger, search, cart, account, button:Label, text:Content" },
              "align":        { "type": "string", "enum": ["center", "space-between", "space-around", "flex-start", "flex-end"] },
              "background":   { "type": "string", "description": "Hex background color for this row." },
              "hide_mobile":  { "type": "boolean" },
              "hide_desktop": { "type": "boolean" }
            }
          }
        },
        "background_color": { "type": "string" },
        "sticky":           {},
        "transparent":      { "type": "boolean" }
      }
    }
  }
}
```

**Output schema:**
```json
{
  "type": "object",
  "properties": {
    "post_id": { "type": "integer" },
    "preset":  { "type": "string" },
    "url":     { "type": "string" }
  }
}
```

---

## create_footer

**When to use.** Use this to install a Theme Builder footer template from a named preset (simple, multi_column, minimal, newsletter) with optional overrides. Works identically to `create_header` but for footer templates.

**Capability:** `manage_options`.

**Input schema:**
```json
{
  "type": "object",
  "required": ["preset"],
  "additionalProperties": false,
  "properties": {
    "preset": {
      "type": "string",
      "enum": ["simple", "multi_column", "minimal", "newsletter"],
      "description": "Footer preset name."
    },
    "overrides": {
      "type": "object",
      "description": "Override the preset.",
      "properties": {
        "background_color": { "type": "string" },
        "copyright_text":   { "type": "string", "description": "HTML string for the copyright line." }
      }
    }
  }
}
```

**Output schema:**
```json
{
  "type": "object",
  "properties": {
    "post_id": { "type": "integer" },
    "preset":  { "type": "string" },
    "url":     { "type": "string" }
  }
}
```

---

## get_page_structure

**When to use.** Use this to inspect an Elementor page before editing. Returns an annotated tree of top-level sections and their child widgets with content previews. Read-only — does not modify the page.

**Capability:** `edit_pages`.

**Input schema:**
```json
{
  "type": "object",
  "required": ["post_id"],
  "additionalProperties": false,
  "properties": {
    "post_id": { "type": "integer", "minimum": 1, "description": "The post/page/template ID to inspect." }
  }
}
```

**Output schema:**
```json
{
  "type": "object",
  "properties": {
    "post_id":       { "type": "integer" },
    "title":         { "type": "string" },
    "section_count": { "type": "integer" },
    "sections":      { "type": "array" }
  }
}
```

---

## edit_section

**When to use.** Use this to replace a top-level section on an existing page. Target by `section_index` (0-based) or `section_id`. Use `get_page_structure` first to find the right index or ID.

**Capability:** `edit_pages`.

**Input schema:**
```json
{
  "type": "object",
  "required": ["post_id", "block"],
  "additionalProperties": false,
  "properties": {
    "post_id":       { "type": "integer", "minimum": 1 },
    "section_index": { "type": "integer", "minimum": 0, "description": "Zero-based index of the section to replace." },
    "section_id":    { "type": "string", "description": "Elementor element ID of the section to replace." },
    "block":         { "type": "object", "description": "New block content (same format as create_page blocks)." }
  }
}
```

**Output schema:**
```json
{
  "type": "object",
  "properties": {
    "post_id":  { "type": "integer" },
    "replaced": { "type": "boolean" }
  }
}
```

---

## delete_section

**When to use.** Use this to remove a top-level section from an Elementor page. Target by `section_index` or `section_id`.

**Capability:** `edit_pages`.

**Input schema:**
```json
{
  "type": "object",
  "required": ["post_id"],
  "additionalProperties": false,
  "properties": {
    "post_id":       { "type": "integer", "minimum": 1 },
    "section_index": { "type": "integer", "minimum": 0 },
    "section_id":    { "type": "string" }
  }
}
```

**Output schema:**
```json
{
  "type": "object",
  "properties": {
    "post_id":         { "type": "integer" },
    "deleted":         { "type": "boolean" },
    "remaining_count": { "type": "integer" }
  }
}
```

---

## reorder_sections

**When to use.** Use this to reorder top-level sections on a page. Provide `order` as an array of current section indices in the desired new sequence. The array must contain exactly one entry per section.

**Capability:** `edit_pages`.

**Input schema:**
```json
{
  "type": "object",
  "required": ["post_id", "order"],
  "additionalProperties": false,
  "properties": {
    "post_id": { "type": "integer", "minimum": 1 },
    "order":   {
      "type": "array",
      "items": { "type": "integer" },
      "description": "Array of current section indices in the desired new order. E.g. [2, 0, 1] moves section 2 to the top."
    }
  }
}
```

**Output schema:**
```json
{
  "type": "object",
  "properties": {
    "post_id":   { "type": "integer" },
    "reordered": { "type": "boolean" }
  }
}
```

---

## update_widget

**When to use.** Use this to update a single widget's settings anywhere in the document tree. Walks the full tree to find the widget by ID and merges new settings into existing. Use `get_page_structure` to find widget IDs.

**Capability:** `edit_pages`.

**Input schema:**
```json
{
  "type": "object",
  "required": ["post_id", "widget_id", "settings"],
  "additionalProperties": false,
  "properties": {
    "post_id":   { "type": "integer", "minimum": 1 },
    "widget_id": { "type": "string", "description": "Elementor element ID of the widget to update." },
    "settings":  { "type": "object", "description": "Partial settings to merge into the existing widget settings." }
  }
}
```

**Output schema:**
```json
{
  "type": "object",
  "properties": {
    "post_id": { "type": "integer" },
    "updated": { "type": "boolean" }
  }
}
```

---

## duplicate_section

**When to use.** Use this to deep-clone a top-level section and insert the clone at a position. All element IDs in the clone are regenerated to avoid conflicts.

**Capability:** `edit_pages`.

**Input schema:**
```json
{
  "type": "object",
  "required": ["post_id"],
  "additionalProperties": false,
  "properties": {
    "post_id":       { "type": "integer", "minimum": 1 },
    "section_index": { "type": "integer", "minimum": 0 },
    "section_id":    { "type": "string" },
    "insert_after":  { "type": "integer", "minimum": 0, "description": "Insert the clone after this index. Defaults to right after the original." }
  }
}
```

**Output schema:**
```json
{
  "type": "object",
  "properties": {
    "post_id":    { "type": "integer" },
    "duplicated": { "type": "boolean" },
    "new_index":  { "type": "integer" }
  }
}
```
