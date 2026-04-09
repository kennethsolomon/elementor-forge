# Safety Modes

Elementor Forge ships with a plugin-level scope lock so you can install it on a live client site and be guaranteed it can only touch a specific allowlisted set of post IDs — no site-wide surprises, no accidental wizard runs, no destructive MCP tool calls in the wrong context.

## The three modes

| Mode | UI color | Wizard | `add_section` | `create_page` | `configure_woocommerce` | Read tools |
|---|---|---|---|---|---|---|
| `full` | green | enabled | allow | allow | allow | allow |
| `page_only` | yellow | **disabled** | allow **iff** post_id in allowlist; empty allowlist = **reject** | allow (new page) | **reject** (site-wide) | allow |
| `read_only` | red | **disabled** | **reject** | **reject** | **reject** | allow |

### `full` (default)

The original plugin behavior. All tools enabled, wizard runs, `configure_woocommerce` installs Theme Builder templates site-wide. This is the default for new installs to preserve backwards compatibility. **Only use this on your own dev sites or sites where you explicitly want the wizard + site-wide changes.**

### `page_only`

The safest mode for client work. The wizard is disabled — visiting `Elementor Forge → Setup` shows a `wp_die` message. `configure_woocommerce` is rejected (it's inherently site-wide). `add_section` is gated on an explicit post ID allowlist: the plugin will refuse to modify any post that isn't in the `safety_allowed_post_ids` setting.

**Empty allowlist in `page_only` = `add_section` rejects every call.** This is deliberate. If you flip to `page_only` without populating the allowlist, the plugin stops modifying existing posts entirely. You must explicitly declare "the plugin can touch posts 52, 101, 150" before any modification happens.

Tools that create NEW posts (`create_page`, `apply_template`, `bulk_generate_pages`) still work in `page_only` — they're additive, not destructive.

### `read_only`

Diagnostic mode. Every MCP write tool returns `WP_Error`. Nothing is modified. Useful for production sites where you want the plugin's settings page visible but no MCP agent can write anything.

## How to switch modes

### Via the Settings UI

**Elementor Forge → Settings → Safety section:**
1. Pick a mode from the radio group (full / page_only / read_only)
2. Enter allowed post IDs as a comma-separated list (e.g. `52,101,150`) if using `page_only`
3. Click **Save settings**
4. Review the **Current gate status** panel — it shows the live allow/reject verdict for every MCP tool under the new mode

### Via WP-CLI

```bash
wp eval '\ElementorForge\Settings\Store::update([
    "safety_mode" => "page_only",
    "safety_allowed_post_ids" => "52,101,150"
]);'
```

### Via code (in a mu-plugin or activation hook)

```php
use ElementorForge\Settings\Store;
use ElementorForge\Safety\Mode;

Store::update([
    'safety_mode' => Mode::PAGE_ONLY,
    'safety_allowed_post_ids' => '52,101,150',
]);
```

## Recommended client-site install workflow

1. **Clone the production site to staging** — never first-install on production
2. **Full backup** (DB + files) before you install on staging
3. **Install the plugin ZIP** via `Plugins → Add New → Upload Plugin`
4. **Before activating**: if you can, pre-set the options via WP-CLI so the plugin is already in `page_only` mode on first boot:
   ```bash
   wp option add elementor_forge_settings '{"safety_mode":"page_only","safety_allowed_post_ids":""}' --format=json
   ```
5. **Activate** the plugin
6. **Elementor Forge → Settings → Safety** — verify mode is `page_only` and populate the allowlist with the post IDs you're editing
7. **Now** use the plugin's MCP tools. Any non-allowlisted post ID returns `WP_Error` with a specific error code.

## Error codes

When the gate rejects, the `WP_Error` returned has one of these codes:

- `elementor_forge_read_only_mode` — All writes rejected in `read_only`
- `elementor_forge_site_wide_in_page_only` — `configure_woocommerce` rejected in `page_only` (inherently site-wide)
- `elementor_forge_allowlist_empty_in_page_only` — `add_section` called in `page_only` but the allowlist is empty
- `elementor_forge_post_not_in_allowlist` — `add_section` called with a `page_id` not in the allowlist
- `elementor_forge_wizard_disabled` — Wizard page accessed in `page_only` or `read_only`

All error messages are deliberately explicit about why the rejection happened so MCP callers (including Claude Code) can surface the issue clearly to the user.

## What the gate does NOT protect

- **Direct database writes** — if someone bypasses the MCP tools and writes directly to `_elementor_data` via `update_post_meta`, the gate is not in the call path. Use WordPress-level file permissions and database access control for that.
- **CPT registration** — the 4 CPTs (`ef_location`, `ef_service`, `ef_testimonial`, `ef_faq`) are registered on plugin activation regardless of scope mode. They're structural, not destructive. If you need the CPTs gone, uninstall the plugin.
- **ACF field group registration** — same as CPTs. Fires on `acf/init` regardless of scope mode.
- **Manual edits via the WordPress admin** — users with `edit_pages` capability can still edit any page through the standard Elementor editor. The gate only applies to the plugin's own MCP tools and the wizard.

The gate protects the **Elementor Forge attack surface**, not the WordPress attack surface. WordPress's own capability system is still the primary access control.

## Phase 1.5 follow-ups

- Per-slider allowlist on `manage_slider` — slider IDs are currently un-gated in all modes except `read_only`
- Granular action allowlist — e.g. "allow `add_section` but not `bulk_generate_pages`"
- UI affordance to auto-populate the allowlist from a Gallery or Saved Template selection
- Audit log of gate decisions for post-mortem debugging

These are filed in `PHASE_1_5_BACKLOG.md`.
