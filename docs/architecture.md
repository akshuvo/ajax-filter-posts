# Free architecture

Source inspection baseline: 2026-11-04, branch `dev`, commit `2172483`, plugin version `3.4.17`. This describes checked-in behavior, not a runtime certification. Recheck source after changes. Existing [README](../README.md) and [WordPress readme](../readme.txt) remain product/release documentation.

## Bootstrap and request paths

[ajax-filter-posts.php](../ajax-filter-posts.php) guards `ABSPATH`, creates the singleton `GridMasterPlugin` through `grid_master_plugin()`, and defines `GRIDMASTER_VERSION`, `FILE`, `PATH`, `URL`, `ASSETS`, and `PRO_LINK` constants with the `GRIDMASTER_` prefix. It registers activation timestamp storage (`gridmaster_activation_time`), plugin action links, and frontend assets on `wp_enqueue_scripts` at priority 999.

On `plugins_loaded` (default priority 10), `init_plugin()` loads [inc/functions.php](../inc/functions.php) and initializes `GridMaster\Shortcode`. Under `is_admin()`, construction also initializes [GridMaster\Admin](../admin/Admin.php) and [GridMaster\Ajax](../admin/Ajax.php); this includes WordPress admin-AJAX requests. Appsero Insights initializes separately at file load; see [integrations](integrations.md).

## Shortcodes, queries, and templates

[inc/Shortcode.php](../inc/Shortcode.php) registers `[gridmaster]`, `[am_post_grid]`, and `[asr_ajax]` to `render_shortcode()`. An `id` can resolve a saved grid through `gm_get_grid()`; explicit attributes override stored ones and saved IDs produce `gm-{id}` DOM IDs. Otherwise a random `gm-…` ID is generated. `shortcode_atts(..., 'gridmaster')` allows extensions on all three aliases.

The renderer normalizes attributes, enqueues a grid-specific stylesheet, emits filters and a wrapper with JSON public attributes, then server-renders the first result through `get_args_from_atts()` → `render_grid()`. `render_grid()` builds `WP_Query` with `post_status => publish`, pagination, order, post type, and taxonomy constraints, renders each result, creates navigation, and calls `wp_reset_postdata()`. `meta_query` is an accepted/default attribute but is not forwarded to the query by this implementation.

`get_args_from_atts()` maps nonempty legacy `cat`, otherwise `terms`, into a category taxonomy map and marks `has_terms`. `filter_render_grid_args()` converts browser `tax_input` to query clauses, takes the first selected term, and handles `-1` as All (with selected-term restrictions if supplied). Its retention of raw `tax_input` when Pro's entry function exists is an extension seam. The initial-load and AJAX normalization paths differ; test both.

`render_filter()` uses `get_terms()` arguments from taxonomy, selected `terms`/legacy `cat`, and `hide_empty`. Free blocks selected-term custom-taxonomy filters when Pro is absent; unrestricted custom-taxonomy filters still have a rendering path. [Builder](../admin/views/build-grid.php) offers grid styles `default`, `style-2`, `style-3` and filter styles `default`, `style-2`, `style-3`, `style-4`. Templates live in [grid](../templates/grid) and [filter](../templates/filter) directories; several styles include inline CSS.

`get_template_part()` sanitizes the slug. `locate_template()` checks the active stylesheet directory's `gridmaster/templates/` **only if `GRIDMASTER_PRO_PATH` exists**, then Pro templates when that constant exists, then Free templates, followed by `gm_locate_template`. There is no separate parent-theme lookup in this method. The pagination file exists at [templates/pagination/default.php](../templates/pagination/default.php), but `render_grid()` emits navigation inline instead of loading it.

[inc/functions.php](../inc/functions.php) supplies title, excerpt/content, thumbnail, terms, author/date, comment, read-more, settings, style registry, grid lookup, and breakpoint helpers. Several render-time anonymous filters capture grid arguments and remain registered for the request. Multiple grids, nested rendering, and repeated saved IDs need regression checks.

## AJAX and browser behavior

`am_post_grid_load_posts_ajax_functions()` serves both `wp_ajax_asr_filter_posts` and `wp_ajax_nopriv_asr_filter_posts`. It verifies `nonce` against `asr_ajax_nonce` unless `gridmaster_settings['disable-nonce-check'] === 'yes'`. It merges `argsArray`, legacy `term_ID`, parsed serialized `taxInput`, and `paged`; sets `ajax = true`; and returns rendered **HTML**, not JSON. The exact data and hook contract is in [integrations](integrations.md).

[assets/frontend.js](../assets/frontend.js) uses delegated jQuery events for taxonomy inputs, numbered/previous/next links, and load-more. Filter changes replace results; load-more appends `.am_post_grid` contents and replaces navigation. Infinite scroll clicks the load-more button when visible within a 1000px margin, using a closure-wide loading flag. Animation, filter-heading toggling, and slider event handoffs also live here. Requests are not cancelled or sequenced; error handling alerts and clears UI loading state, but the load-more flag is reset in success only.

## Administration and storage

[admin/Admin.php](../admin/Admin.php) registers the `gridmaster` menu with `manage_options`, assets, footer text, and the admin page. [admin/views/admin.php](../admin/views/admin.php) routes known `path` values to views; My Grids navigation and the builder save button are commented out, while routes/handlers remain. The builder uses [admin/admin-functions.php](../admin/admin-functions.php) form helpers and [admin/assets/admin.js](../admin/assets/admin.js) to generate shortcode strings, JSON responsive attributes and slider options, and a nonce-bearing preview iframe. Premium controls are partly defined in this Free-owned UI.

Frontend preview registers only for a non-admin request with `manage_options`. `Shortcode::init_hook()` checks `gm_shortcode_preview_nonce`, sanitizes the shortcode text, renders a standalone page with `wp_head()`/`wp_footer()`, and exits.

[admin/Ajax.php](../admin/Ajax.php) registers authenticated `gridmaster_ajax`. `register_ajax()` validates `gm_nonce` against `gm-ajax-nonce`, converts `gm-action` hyphens to underscores, and dynamically calls a method. Private handlers `save_settings`, `save_grid`, and `list_grids` persist options/grid data or return JSON. The dispatcher has no explicit capability check or active method allowlist. Settings and grid attributes are not comprehensively validated field by field.

[admin/Grids.php](../admin/Grids.php) owns `GridMaster\Grids`: defaults (including premium field values), serialized attributes, insert/update, a latest-100 list, ID lookup, and deletion. The table is `{$wpdb->prefix}gridmaster_grids` with `id`, `title`, `attributes`, and `dated`. ID lookup uses `$wpdb->prepare`; writes use `$wpdb` helpers. Deletion exists as a class method, not a registered delete AJAX handler.

[admin/DB_Migration.php](../admin/DB_Migration.php) runs `dbDelta` when `gridmaster_db_version` is older than its internal schema version. It is included by [My Grids view](../admin/views/my-grids.php), not by the activation callback or general bootstrap. A fresh activation alone does not establish from source that the table was created. Options include `gridmaster_settings` (`debug-mode`, `disable-nonce-check`, `disable-icon-color-options`), `gridmaster_db_version`, and `gridmaster_activation_time`. Pro's use of settings must be verified in Pro; a Free UI control alone does not prove it is honored.

## Assets, build, and security boundaries

Runtime loads `assets/frontend.min.js`, `assets/css/frontend.min.css`, `admin/assets/admin.min.js`, `admin/assets/admin.css`, and the Free-only admin premium-block stylesheet. Readable source counterparts are tracked, along with style-specific CSS. No reproducible minification/build command is checked in. [Development](development.md) describes tools and deployment triggers.

There are local sanitization/escaping/nonce checks, but this is not a security audit. Public AJAX accepts client attributes and taxonomy input; title tags are interpolated by `gridmaster_get_post_title()`, and filtered/template HTML is trusted at several output points. Privilege, type, output-context, and expensive-query checks belong in any affected implementation review. [Testing](testing.md) distinguishes current behavior from desired security expectations.

## Proposed GridMaster 2.0 design

The [initial redesign package](gridmaster-2.0/README.md) records new proposals dated 2026-11-04. It does not replace the observed runtime contracts above or certify a new version pair; no redesign feature is implemented by those documents.
