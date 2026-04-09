# Elementor Forge — MCP Tool Reference

Canonical reference for every MCP tool exposed by Elementor Forge. Six tools ship in Phase 3. All tools are registered as WP Abilities on the `wp_abilities_api_init` action and consumed by `wordpress/mcp-adapter` during `mcp_adapter_init`. The entire MCP server can be disabled via the `mcp_server` plugin setting (set to `disabled` for zero runtime footprint).

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
    "install_templates": { "type": "boolean", "default": true }
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
