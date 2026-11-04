# Free integration surfaces

The Free repository owns this public rendering contract. Pro-specific implementation and service details belong in Pro. Source: [bootstrap](../ajax-filter-posts.php), [shortcode](../inc/Shortcode.php), [helpers](../inc/functions.php), [builder](../admin/views/build-grid.php), and [frontend JS](../assets/frontend.js).

## Hooks and priorities

These are current call shapes, not a separately versioned SDK. Preserve argument meaning and return type, including existing spelling and punctuation.

| Surface | Shape / use |
| --- | --- |
| `shortcode_atts_gridmaster` | WordPress filter `(out, pairs, atts)`; Pro extends normalized attributes even for legacy aliases |
| `gridmaster_shortcode_public_atts` | Filter `(atts)` before stripping server/style-only fields from browser JSON |
| `gridmaster_shortcode_output` | Filter `(output, atts)` after rendering; returns HTML |
| `gridmaster_render_filters` | Action `(atts)`; Free's `render_filter` registered at 10 |
| `gridmaster_filter_input_type` | Filter `('radio', atts)`; an extension can return `checkbox` |
| `gridmaster_get_args_from_atts` | Filter `(data)` on both initial and AJAX normalization |
| `gridmaster_render_grid_args` | Filter `(args)`; Free taxonomy normalizer at 10; Pro also uses 10 and 15 |
| `gridmaster_render_grid_query_args` | Filter `(query_args, args)` immediately before `WP_Query` |
| `gridmaster_get_render_grid_args` | Filter `(array())` used by helpers; registered closures supply current render arguments |
| `gridmaster_excerpt_length` | Filter `(length)`, render-time override |
| `gridmaster_post_thumb_size` | Filter `(size)`, used by grid templates |
| `gridmaster_post_thumbnail_html` | Filter `(HTML)` around WordPress thumbnail HTML |
| `gridmaster_post_title_tag` | Filter `(tag)` in the title helper |
| `gridmaster-no-posts-found` | Filter `(HTML/text)` in empty-state output; name uses hyphens |
| `gm_locate_template` | Filter `(located_path, template_names, args)` before optional `load_template` |
| `gridmaster_grid_styles`, `gridmaster_filter_styles` | Filter style-ID-to-label maps; Free advertises premium choices at priority 9 |
| `gridmaster_grid_pro_styles` | Used by both premium grid and premium filter registry helpers in current code |
| `gridmaster_grid_settings_fields_before`, `gridmaster_grid_settings_fields_after` | Builder actions, no explicit arguments |
| `lmfwppt_license_activation_form_fields` | Settings view action, no explicit arguments, permits extension license fields |

`gridmaster_is_pro()` tests only whether `GRIDMASTER_PRO_VERSION` is defined. Template/style resolution uses `GRIDMASTER_PRO_PATH` and related asset constants. Tax-input retention instead checks `function_exists('GridMasterProPlugin')`; these checks are not interchangeable with license validation.

## Data and browser protocol

- Shortcode inputs include `post_type`, `posts_per_page`, `orderby`, `order`, `taxonomy`, comma-separated `terms`/legacy `cat`, `grid_style`, `filter_style`, `pagination_type`, `infinite_scroll`, excerpt/read-more settings, and optional saved `id`. Defaults are defined separately in `Shortcode::render_shortcode()` and `Grids::$defaults`; do not assume they match.
- Grid wrapper: `.am_ajax_post_grid_wrap`, unique `grid_id`, `data-grid-style`, `data-pagination_type`, and `data-am_ajax_post_grid` containing escaped JSON. Public JSON excludes `grid_col_gap`, `grid_row_gap`, `grid_item_per_row`, `tax_query`, `meta_query`, and `filter_style` after the public-attributes filter.
- Taxonomy controls: `.gm-taxonomy-item input`, names `tax_input[<taxonomy>][]`, term IDs as values, `-1` for All. `.asr_texonomy` is an existing label class, including its spelling.
- Localized `asr_ajax_params`: `nonce`, `asr_ajax_url`, `is_pro`, `breakpoints`. `gm_get_breakpoints()` keys are `xs`, `sm`, `md`, `lg`, `xl`; values are pixel strings 320, 768, 992, 1200, 1600; `lg` is marked default.
- POST action `asr_filter_posts`: `nonce`, legacy `term_ID`, serialized `taxInput`, nested `argsArray`, optional `paged`, plus browser `layout`, `pagination_type`, and `loadMore`. The handler derives rendering parameters from the normalized attributes/tax input; not every posted field is consumed independently.
- Response is an HTML fragment containing `.am_post_grid`, `.am_grid_col`, `.am_posts_navigation`, and optionally `.am-postgrid-wrapper`. `.asrafp-filter-result` holds results. Numbered navigation uses `.am_posts_navigation_init`; load-more uses `.am-post-grid-load-more` with `data-paged`/`data-next`; infinite scroll adds `.infinite_scroll`.
- Browser events: `gridmaster_init_slider` passes `[$wrapper, $args]`, `gridmaster_init_all_sliders` has no payload. Free calls Slick when Pro is detected and slider arguments are truthy. `am_ajax_post_grid_init` supports the older `initial` browser field, although it is not in current default shortcode attributes.
- Admin script handle `gridmaster-admin-script` localizes `gridmaster_params` including AJAX URL, nonce, home URL, breakpoints, Pro detection and demo links. Admin forms use `gridmaster_ajax`, `gm-action`, and `gm_nonce`; successful submissions emit `gm-ajax-success-<gmAction>` with response data. Preview uses `gm_shortcode_preview=1`, `_wpnonce`, and `shortcode` query parameters.

## External and theme integrations

Template overrides use `<active stylesheet directory>/gridmaster/templates/` and require Pro's path constant in the current lookup. Test actual child/parent theme configurations rather than assuming a generic theme override facility.

The bootstrap initializes bundled [Appsero](../appsero/src/Client.php) Insights. [Insights](../appsero/src/Insights.php) registers opt-in/opt-out admin handlers, deactivation feedback, activation/deactivation hooks, weekly scheduling, and tracking callbacks. `appsero_endpoint` filters the default `https://api.appsero.com` endpoint. Do not assume telemetry is always sent merely because the client is initialized; consent/tracking logic is in the bundled client. Live service behavior was not exercised. Avoid copying site identifiers, credentials, or private Pro data into public docs.

Related: [compatibility and ownership](free-pro-compatibility.md).

## Proposed GridMaster 2.0 design

The [initial redesign package](gridmaster-2.0/README.md) records new proposals dated 2026-11-04. It does not replace the observed runtime contracts above or certify a new version pair; no redesign feature is implemented by those documents.
