# 0003 — Page builder integrations

- Status: Implemented
- Owner / approval authority: AddonMaster / Product Maintainer
- Date: 2026-11-07
- Related issue / companion PRD / ADR: [0002 — Gutenberg saved-grid builder](0002-gutenberg-grid-builder-v2.md)

## Problem statement

GridMaster users currently have to copy shortcodes like `[gridmaster id="X"]` manually and paste them into their page builder (Gutenberg, Elementor, Beaver Builder, Bricks, WPBakery, etc.).
This workflow suffers from several critical usability drawbacks:
1. In the Gutenberg editor and page builder canvases, a pasted shortcode only displays as raw static text or a generic shortcode block rather than showing the real post grid layout.
2. Users cannot preview what the grid looks like while designing their pages without saving and visiting the frontend.
3. Users have no easy way to navigate back to GridMaster to edit the referenced grid layout without leaving their page editor and searching the WordPress admin menu.
4. Users must remember or look up the numeric grid ID.

## Goals

1. Provide native dropdown-based widgets / modules / elements in all top WordPress page builders (Gutenberg, Elementor, Beaver Builder, Bricks, WPBakery, Classic WP Widget).
2. The widget display name is consistently labeled **"Post Grid Master"** across all builder inserters and categories.
3. Support direct grid selection from a dropdown listing up to 500 saved grids (with a filter hook for larger databases).
4. Display live, server-rendered post grid previews directly inside the visual canvas where supported.
5. Provide a direct "Edit this grid" link (restricted to administrators) that opens the GridMaster builder in a new tab, with automatic canvas refresh when the user returns.
6. Provide Gutenberg transform helpers that automatically convert pasted or existing `[gridmaster id="X"]` shortcodes into the native block with live preview.
7. Strictly load integration files and editor assets conditionally: if a builder plugin is not active on the WordPress site, none of its specific integration files or assets are ever included or executed.

## Free/Pro scope

- **Free plugin (`ajax-filter-posts`)**: Owns the entire page builder integration suite (shared `Embed_Helper`, `Integrations_Manager`, Gutenberg block upgrade, Elementor widget, Beaver module, Bricks element, WPBakery mapping, and Classic widget).
- **Pro plugin (`gridmaster-pro`)**: Pro grids and styles render seamlessly through these integrations because they utilize the shared `Grid_Renderer` pipeline. No Pro modification required.

## Functional requirements

1. **Shared Embed Helper (`Embed_Helper`)**:
   - `get_grid_choices()` returns a map of grid IDs to display labels (e.g. `#12 — Blog Grid` or `#5 — News (Legacy)`), default limit 500, filtered by `gridmaster_embed_grid_choices_limit`.
   - `get_edit_url( $id )` returns the direct admin builder URL (v2 builder or legacy builder based on migration status) if `current_user_can( 'manage_options' )`, or empty string otherwise.
   - `render( $id )` renders the grid via `Grid_Renderer::render_grid()`. In builder preview mode, renders empty state placeholders or notices if no grid is selected or if the grid was deleted, along with an edit badge.
   - `render_with_assets( $id )` captures rendered HTML and required stylesheet/script dependencies for iframe-based editors (Gutenberg).
   - `is_builder_preview()` detects active editing sessions across Gutenberg, Elementor, Beaver, and Bricks.

2. **Conditional Loading (`Integrations_Manager`)**:
   - On `after_setup_theme` (priority 20), inspects active builders via robust environment checks (`ELEMENTOR_VERSION`, `class_exists('FLBuilder')`, `BRICKS_VERSION` or active template, `WPB_VC_VERSION`).
   - Requires only the specific builder's `Loader.php` when that builder is detected as active.
   - Heavy classes extending external builder classes (`Widget_Base`, `FLBuilderModule`, `\Bricks\Element`) are required strictly inside the builder's registration hooks.
   - Assets (`builder-preview.js`, `builder-preview.css`, `elementor-editor.js`) are enqueued solely within builder edit/preview actions, never on regular frontend visits.

3. **Gutenberg Block (`gridmaster/grid`) Upgrade**:
   - Upgraded from a static placeholder to a live server-rendered preview via a dedicated authenticated REST endpoint `GET /gridmaster/v2/grids/{id}/embed-preview`.
   - Block controls include "Change Grid", "Edit grid in GridMaster ↗" (for admins), and "Refresh preview".
   - Supports transform from `core/shortcode` block and auto-conversion on pasting `[gridmaster id="X"]` (or legacy aliases `am_post_grid`, `asr_ajax`).

4. **Elementor Widget (`gridmaster-grid`)**:
   - Category: `gridmaster` ("GridMaster").
   - Title: "Post Grid Master".
   - Control: Select2 grid picker.
   - "Edit selected grid" button in the inspector + hover badge in the canvas preview.
   - Re-renders dynamically when settings change or when returning to the tab.

5. **Beaver Builder Module**:
   - Category: "GridMaster", Title: "Post Grid Master".
   - Select field for saved grids.
   - Partial refresh enabled for instant canvas updates.

6. **Bricks Builder Element**:
   - Category: "gridmaster", Name: `gridmaster-grid`, Label: "Post Grid Master".
   - Select control with searchable grid choices.
   - Declares `$scripts = ['gridmasterBricksInit']` to ensure sliders and reactive behaviors re-bind on canvas refresh.

7. **WPBakery Mapping**:
   - Maps the existing shortcode `[gridmaster]` via `vc_map` with name "Post Grid Master" and dropdown grid parameter.
   - Live rendered in the WPBakery frontend editor.

8. **Classic WordPress Widget (`WP_Widget`)**:
   - Registered under "Post Grid Master" with title and grid picker.
   - Supports legacy sidebar areas and classic widget page builder integrations (SiteOrigin, etc.).

## Non-functional requirements

- Security: All admin edit links and builder actions verify `current_user_can('manage_options')`. The REST preview endpoint requires `edit_posts`.
- Performance: Zero overhead on frontend page loads when builders are inactive.
- Reliability: Missing or deactivated builder plugins must never trigger fatal errors or undefined class exceptions.

## Acceptance criteria

- `wp eval` verifying included files shows zero builder integration files loaded when builders are inactive.
- Elementor editor exposes "Post Grid Master" widget; selecting a grid renders post cards live.
- Gutenberg editor renders real post cards for `gridmaster/grid` block and provides transform from shortcode block.
- Clicking "Edit grid" opens the appropriate builder with the target grid ID.
