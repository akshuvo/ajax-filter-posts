# GridMaster 2.0 — Previous Experiments Research & Evaluation

**Author:** Antigravity (Lead WordPress Plugin Architect)  
**Date:** 2026-11-04  
**Status:** Complete Analysis  
**Repository:** `ajax-filter-posts` (Free) and `gridmaster-pro` (Pro)

---

## 1. Executive Summary

Before implementing the GridMaster 2.0 architecture, we conducted a comprehensive review of historical branches and redesign experiments in the repository:
1. `origin/gutenberg-grid-cards` (Early Gutenberg card prototype)
2. `origin/no-grid-db` (Database removal experiment)
3. `origin/gridmaster` (Legacy styling & layout tweaks)
4. `origin/free-post-grid` (CSS hover transitions & card styling)

The primary finding is that `gutenberg-grid-cards` successfully demonstrated that native Gutenberg blocks (`register_block_type`, `parse_blocks`, and `render_block`) can render dynamic post card templates. However, it suffered from severe architectural shortcomings that prevented it from becoming a production-grade post grid builder:
- **ID Disconnect & Dual Storage:** It created a custom post type `gm_grid_template` whose WordPress post IDs conflicted with the numeric grid IDs stored in the `{$wpdb->prefix}gridmaster_grids` table.
- **Absence of Unified Builder Canvas:** It relied on the standard WordPress post edit screen for `gm_grid_template`, separating post card layout editing from query configuration, facet settings, and pagination controls.
- **Lack of Live Repeated Card Preview:** The editor only displayed a single card with static or placeholder values; users could not see how the template repeated across real queried posts with actual database data.
- **Context Leaks & Nesting Fragility:** Dynamic field blocks relied on global post state rather than the formal Gutenberg Block Context API (`postId`, `postType`), causing failures when cards were nested inside core containers (Columns, Groups) or rendered in AJAX loops.
- **No Extensibility Framework for Pro:** Premium facets, query hooks, and layouts had no clean registry or dependency injection mechanism.

This document analyzes each branch in detail, identifies reusable components, and outlines the architectural improvements required for GridMaster 2.0.

---

## 2. Deep Dive: `gutenberg-grid-cards`

The `gutenberg-grid-cards` branch represents the most substantial prior redesign attempt.

### 2.1 What Was Attempted
- **Block Registration (`inc/Blocks.php`):**
  - Registered a private custom post type `gm_grid_template` (`show_in_menu => 'gridmaster'`, `supports => ['title', 'editor']`).
  - Registered dynamic field blocks under the `gridmaster/` namespace:
    - `gridmaster/featured-image`
    - `gridmaster/title`
    - `gridmaster/excerpt`
    - `gridmaster/terms`
    - `gridmaster/date`
    - `gridmaster/author`
    - `gridmaster/comments`
    - `gridmaster/read-more`
    - `gridmaster/grid` (Container block)
    - `gridmaster/post-template`
    - `gridmaster/filter`
- **Dynamic Server-Side Rendering (`inc/Template_Renderer.php`):**
  - Used `parse_blocks()` on the template's `post_content` followed by `render_block()` for each parsed block inside the WordPress query loop.
  - Implemented `Template_Renderer::field()` to translate block attributes into markup using existing helper functions (`gridmaster_get_post_title`, `gridmaster_post_thumbnail`, `gridmaster_the_content`).
- **Query & Layout Integration (`inc/Renderer.php`, `inc/Query_Builder.php`):**
  - Created a `Query_Builder` class that accepted normalized parameters (`post_type`, `posts_per_page`, `orderby`, `order`, `taxonomy`, `terms`) and executed a standard `WP_Query`.
  - Rendered a CSS grid container with responsive column attributes (`responsiveColumns` array: xs, sm, md, lg, xl) and column/row gaps.

### 2.2 Reusable Components
1. **Dynamic Field Helper Mapping:** The mapping from block names to server-side render methods in `Template_Renderer::field()` correctly reuses legacy sanitization and formatting helpers.
2. **Block Definitions Structure:** The registration signatures for attributes (e.g., `tagName`, `isLink` for title; `size` for featured image; `format` for date) provide a solid starting point for `block.json` schemas.
3. **Responsive Grid CSS Variables:** The CSS custom properties approach for grid gaps and columns (`--gm-col-gap`, `--gm-row-gap`, `--gm-columns`) proved lightweight and performant on the frontend.

### 2.3 Limitations & Failures
1. **Confused Entity Identity:** A user saving a `gm_grid_template` had a post ID (e.g., `45`), while grids in `wp_gridmaster_grids` had table IDs (e.g., `3`). Users could not reliably know whether `[gridmaster id="..."]` expected a template post ID or a table ID.
2. **Missing Repeated Preview:** In Gutenberg's editor, the editor canvas only showed the blocks of the template post itself. The user had zero visual feedback on how the grid would actually look when repeated across 6, 9, or 12 posts with varying title lengths and thumbnail aspect ratios.
3. **No Facet / Top Filter Architecture:** The `gridmaster/filter` block was a hardcoded category tab renderer with no support for checkboxes, dropdowns, radio buttons, multi-select, search bars, or reset buttons.
4. **No Block Context API Utilization:** Instead of declaring `"usesContext": ["postId", "postType"]` in block definitions, the blocks depended on `get_the_ID()` during server-side execution. When WordPress REST API or editor canvas renders blocks, `get_the_ID()` returns the template CPT ID rather than the sample post ID, breaking the editor preview!
5. **Lack of Migration Layer:** Existing grids saved in `wp_gridmaster_grids` were completely ignored; there was no way to upgrade legacy grids to Gutenberg templates.

---

## 3. Review of Other Branches

### 3.1 `origin/no-grid-db`
- **What Was Attempted:** Commit `64cd86e` ("remove grid db code") removed database table queries and hidden input fields from `admin/Admin.php` and `admin/views/admin.php`.
- **Reasoning:** The author apparently explored removing the custom MySQL table entirely in favor of shortcode attributes or post meta.
- **Evaluation:** Abandoning the database table creates severe user friction: shortcodes become massive and unmanageable (`[gridmaster post_type="post" posts_per_page="12" columns_md="3" ...]`), and users cannot update a grid's layout in one place and have it reflect across multiple pages. The custom table with stable numeric IDs is an essential requirement of the GridMaster ecosystem.

### 3.2 `origin/gridmaster`
- **What Was Attempted:** Focus on padding, gap adjustments, and bug fixes for the legacy jQuery-driven filter system.
- **Evaluation:** Shows the limits of the legacy renderer where every CSS style was tightly coupled to hardcoded classes (`.am_ajax_post_grid_wrap`, `.gridmaster-default`). Demonstrates the necessity of scoped CSS custom properties and native Gutenberg layout supports.

### 3.3 `origin/free-post-grid`
- **What Was Attempted:** Explored hover transitions, box-shadows, and overlay styling for card style 1 and style 3.
- **Evaluation:** While the CSS transitions are visually appealing, hardcoding them into PHP views makes customization difficult. In GridMaster 2.0, card hover transitions and shadows must be configurable block attributes on the card container block.

---

## 4. Architectural Synthesis for GridMaster 2.0

Based on these evaluations, GridMaster 2.0 must adopt the following architectural principles:

```
+-----------------------------------------------------------------------------------+
|                            GRIDMASTER 2.0 ARCHITECTURE                            |
+-----------------------------------------------------------------------------------+
|  Authoritative Identity: Stable Numeric ID in wp_gridmaster_grids                 |
|  - id: mediumint (authoritative ID used in [gridmaster id="123"])                 |
|  - title: text (grid title)                                                       |
|  - config_json: longtext (authoritative query, facet, pagination, style settings) |
|  - layout_post_id: bigint (FK pointing to private gm_grid_layout CPT)             |
|  - schema_version: int (e.g. 2)                                                   |
|  - migration_status: varchar (legacy, converted_draft, converted, new_builder)     |
+-----------------------------------------------------------------------------------+
                                       |
                                       v
+-----------------------------------------------------------------------------------+
|  Card Template Storage: Private CPT 'gm_grid_layout'                              |
|  - post_content: Stores Gutenberg block markup for ONE card                       |
|  - Supports native Gutenberg revisions, autosaves, and REST API                   |
+-----------------------------------------------------------------------------------+
                                       |
                                       v
+-----------------------------------------------------------------------------------+
|  Dedicated Builder Experience: admin.php?page=gridmaster-builder&id=123          |
|  - Section A: Top Filter Area (Taxonomy, Search, Reset, Pro Facets)              |
|  - Section B: Post Card Canvas (Native Gutenberg editor for 1 card)               |
|  - Section C: Live Repeated Preview (Repeats template across real queried posts)  |
|  - Section D: Pagination Area (Numbered, Load More, Infinite Scroll)              |
+-----------------------------------------------------------------------------------+
                                       |
                                       v
+-----------------------------------------------------------------------------------+
|  Dynamic Block Context API: usesContext: ['postId', 'postType', 'gridmaster/grid']|
|  - Title, Excerpt, Image, Terms, Meta, Author, Date read from active post context |
|  - Works inside nested Columns, Groups, Spacers, and Separators                   |
|  - Zero global state leakage during repeated rendering                            |
+-----------------------------------------------------------------------------------+
```

This synthesis bridges the stability of numeric database grid IDs with the full power and modern extensibility of Gutenberg.
