# Fibosearch integration layer

Forge configures Fibosearch (wp.org slug `ajax-search-for-woocommerce`) via the
single settings option Fibosearch persists all its configuration into.

## Option key

`dgwt_wcas_settings` — flat associative array, stored autoloaded-off via
`update_option(..., false)`. Source of truth:
`DgoraWcas\Settings\Controllers\DataStore::OPTION_KEY`.

## Defaults Forge applies

| Key                                 | Value      | Why                                             |
|-------------------------------------|------------|-------------------------------------------------|
| `search_in_product_title`           | `1`        | Core search signal                              |
| `search_in_product_content`         | `1`        | Long-form description matches                   |
| `search_in_product_excerpt`         | `1`        | Short description matches                       |
| `search_in_product_sku`             | `1`        | SKU-driven lookup for repeat buyers             |
| `search_in_product_categories`      | `1`        | Category-aware suggestions                      |
| `search_in_product_tags`            | `0`        | Noisy by default on SDM-style catalogues        |
| `show_product_image`                | `1`        | Visual dropdown                                 |
| `show_product_price`                | `1`        | Visual dropdown                                 |
| `show_product_desc`                 | `1`        | Visual dropdown                                 |
| `show_product_sku`                  | `0`        | Hide on public listings                         |
| `show_product_vendor`               | `0`        | No Dokan/WCFM dependency                        |
| `show_more_products`                | `1`        | "View all results" CTA                          |
| `show_product_headline`             | `1`        | "Products" section header                       |
| `show_product_categories`           | `1`        | Suggest categories below product results        |
| `show_product_tags`                 | `0`        | Keep categories primary                         |
| `is_fuzzy_matching`                 | `1`        | Tolerant of typos                               |
| `show_matching_fuzziness_level`     | `normal`   | Balance of precision vs recall                  |
| `min_chars`                         | `2`        | Start suggesting early                          |
| `show_preloader`                    | `1`        | Visual feedback                                 |
| `mobile_overlay`                    | `1`        | Full-screen search UX on phones                 |
| `mobile_breakpoint`                 | `992`      | Tablet + down                                   |
| `mobile_search_icon`                | `1`        | Icon toggle in mobile header                    |
| `enable_https_mixed_content_fixer`  | `0`        | Rarely needed on modern hosts                   |
| `disable_autocomplete`              | `0`        | Keep autocomplete enabled                       |

## Idempotency contract

`Configurator::apply_defaults()` only overwrites keys that are missing or
empty-string in the stored array. User-configured non-empty values are
preserved. Running `apply_defaults()` twice in a row produces the same state.

## Feature detection

All Fibosearch calls are gated on `function_exists('dgwt_wcas')`. The class is
safe to instantiate without Fibosearch installed; detection methods return
`false` / empty result shapes and `apply_defaults()` returns
`['applied' => false, 'reason' => ...]`.

## Version detection

Read from the `DGWT_WCAS_VERSION` constant when defined. Returns `'unknown'`
when the constant is absent.
