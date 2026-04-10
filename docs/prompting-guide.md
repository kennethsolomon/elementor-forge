# Elementor Forge — Prompting Guide

How to use the MCP tools effectively with Claude Code.

## Workflow Order

Always follow this order when setting up a new site:

1. **Set Kit Globals** — brand colors, typography, button styles
2. **Create Header** — site-wide header template
3. **Create Footer** — site-wide footer template
4. **Create Pages** — individual pages with content blocks
5. **Add Sections** — append blocks to existing pages

## Tool Reference

### 1. set_kit_globals

Sets the site-wide brand palette. Call this FIRST — all other templates inherit these values.

**Example prompts:**
- "Set brand colors: primary=#1a73e8, secondary=#34a853, text=#202124, accent=#ea4335"
- "Set headings to Inter font, body to system-ui"
- "Set button style: white text on primary background with 8px border radius"

**Input structure:**
```json
{
  "colors": {
    "primary": "#1a73e8",
    "secondary": "#34a853",
    "text": "#202124",
    "accent": "#ea4335"
  },
  "typography": {
    "primary": { "font_family": "Inter", "font_weight": "700" },
    "secondary": { "font_family": "system-ui", "font_weight": "400" }
  },
  "button": {
    "text_color": "#ffffff",
    "background_color": "#1a73e8",
    "border_radius": { "unit": "px", "size": 8 }
  }
}
```

### 2. create_header

Creates a Theme Builder header template with responsive desktop + mobile layouts.

**Available presets:**

| Preset | Layout |
|--------|--------|
| `business` | Logo left + nav center + CTA right |
| `ecommerce` | Logo + search + cart row, nav row below |
| `portfolio` | Centered logo + centered nav |
| `blog` | Logo left + nav right (simple) |
| `saas` | Logo + nav + login + CTA |

**Example prompts:**
- "Create a business header"
- "Create a saas header with sticky behavior"
- "Create a header with logo centered in row 1, search bar and contact button in row 2"

**Custom layout with overrides:**
```json
{
  "preset": "business",
  "overrides": {
    "rows": [
      { "items": ["logo_center"], "align": "center", "hide_mobile": true },
      { "items": ["search", "button:Contact Us"], "align": "space-between", "hide_mobile": true }
    ],
    "sticky": true,
    "background_color": "#ffffff"
  }
}
```

**Available item keywords:**
- `logo` — Site title (left-aligned)
- `logo_center` — Site title (centered)
- `nav` — Primary navigation menu (horizontal)
- `hamburger` — Mobile hamburger menu
- `search` — Search form widget
- `cart` — WooCommerce cart icon
- `account` — My Account link
- `button:Label` — CTA button with custom label
- `text:Content` — Arbitrary text content

### 3. create_footer

Creates a Theme Builder footer template.

**Available presets:**

| Preset | Layout |
|--------|--------|
| `simple` | Single row with copyright |
| `multi_column` | 3 columns (About/Links/Contact) + copyright |
| `minimal` | Centered copyright text only |
| `newsletter` | CTA section + link columns + copyright |

**Example prompts:**
- "Create a multi-column footer with dark background #1a1a2e"
- "Create a newsletter footer"
- "Create a simple footer with copyright: 2024 My Company"

### 4. create_page

Builds a page from structured content blocks.

**Available block types:**

| Type | Description |
|------|-------------|
| `heading` | Heading widget (h1-h6) |
| `paragraph` | Text content |
| `cta` | Button with link |
| `image` | Image widget |
| `hero` | Full-width hero with heading + subheading + CTA + background |
| `card_grid` | Grid of icon-box cards |
| `faq` | Accordion with questions AND answers |
| `map` | Google Maps embed |
| `form` | Contact Form 7 shortcode |

**Example prompt:**
"Create a page called 'About Us' with a hero section (heading: 'About Our Company', subheading: 'Trusted since 2010'), then a card grid with 3 service cards, then an FAQ with 4 questions and answers."

**Input structure:**
```json
{
  "title": "About Us",
  "content_doc": {
    "title": "About Us",
    "blocks": [
      {
        "type": "hero",
        "heading": "About Our Company",
        "subheading": "Trusted since 2010",
        "cta": { "text": "Learn More", "url": "/services/" }
      },
      {
        "type": "card_grid",
        "cards": [
          { "heading": "Quality", "description": "We deliver excellence." },
          { "heading": "Speed", "description": "Fast turnaround times." },
          { "heading": "Support", "description": "24/7 customer support." }
        ]
      },
      {
        "type": "faq",
        "items": [
          { "question": "How do I get started?", "answer": "Contact us for a free consultation." },
          { "question": "What areas do you serve?", "answer": "We serve all of Melbourne." }
        ]
      }
    ]
  }
}
```

### 5. add_section

Appends a block to an existing page.

**Example prompts:**
- "Add a FAQ section to page 42"
- "Add a hero section to the About page"

### 6. apply_template

Creates a CPT post (location or service) with ACF fields.

**Example prompt:**
"Create a location for Richmond with phone 03-1234-5678 and a hero image."

### 7. bulk_generate_pages

Batch-creates CPT posts. Supports matrix mode (locations x services).

**Example prompt:**
"Generate location pages for Melbourne, Richmond, and South Yarra with the default service template."

### 8. configure_woocommerce

Sets up WooCommerce Theme Builder templates + Fibosearch + ecommerce header.

### 9. manage_slider

CRUD for Smart Slider 3.

## Safety Modes

All tools respect the plugin's safety mode:
- **Full** — everything enabled
- **Page-only** — site-wide actions blocked, modifications require allowlist
- **Read-only** — all writes blocked

## Tips

1. Always set Kit globals before creating headers/footers — they inherit the palette.
2. Use presets for quick setup, overrides for customization.
3. The `rows` override in create_header gives you full control over any layout.
4. FAQ blocks now support answers — always include both `question` and `answer`.
5. Hero blocks now have background colors by default (Kit primary).
6. Card grids render as proper columns (not vertical stacks).
