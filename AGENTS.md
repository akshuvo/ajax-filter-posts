# Post Grid Master (Free)

This repository owns the WordPress plugin bootstrapped by `ajax-filter-posts.php`. GridMaster Pro is a separate extension and Git repository; never assume one Git operation covers both.

## Read before changing code

- [Architecture](docs/architecture.md) maps bootstrap, rendering, storage, and assets.
- [Integrations](docs/integrations.md) defines the public hooks and browser contract.
- [Free/Pro compatibility](docs/free-pro-compatibility.md) routes ownership and impact checks.
- [Development](docs/development.md) and [testing](docs/testing.md) describe the workflow and available checks.
- Consult [ADRs](docs/adr/README.md), [PRDs](docs/prd/README.md), and [ideas](docs/ideas/README.md) as relevant. Ideas and Proposed ADRs are not approved requirements.

## Working rules

1. Read the relevant docs and trace the actual code before editing. Check Git roots, branches, and existing changes separately in each affected repository.
2. For non-trivial work, present a focused implementation plan. Use a PRD for substantial features and an ADR for significant architectural decisions; minor fixes need neither by default.
3. Make the smallest coherent change. Preserve shortcode aliases, attributes, stored grids/settings, hook signatures and priorities, template lookup order, and browser selectors/events. Assess Pro impacts before changing shared behavior.
4. Follow surrounding PHP/jQuery conventions and WordPress coding standards. Keep `GridMaster` namespaces and existing `gridmaster_`/`gm_` APIs; use the `ajax-filter-posts` text domain for new Free strings. Avoid broad reformatting, unnecessary refactors, and edits to bundled libraries.
5. Validate and unslash request input, check capabilities for privileged operations separately from nonces, escape output for its context, and use prepared SQL or WordPress database helpers. Existing security gaps are not patterns to copy; see testing notes.
6. Run appropriate checks and manual scenarios, including Free alone and Free + Pro when shared behavior changes. Report commands/results and untested cases honestly. Do not use the placeholder npm test as proof of coverage.
7. Update affected docs, PRD acceptance evidence, and compatibility notes when important behavior changes. Keep proprietary Pro internals in Pro.
8. Do not commit, push, publish, or open a PR unless requested. Deployment branches and release events have side effects; see [development](docs/development.md) and the [release runbook](docs/release.md). For authorized releases, use the guarded workflow and recursive manifest checks; never overwrite tags or bypass deletion gates. After a failed production attempt, obtain approval before another attempt.

## Card & Gutenberg Block Registration Checklist (Mandatory)

Whenever introducing a new card block or Pro element, **ALL 6 registration touchpoints must be completed** to ensure the block is visible, insertable, and rendered correctly:

1. **PHP Server-Side Registration (`inc/Blocks.php` or `gridmaster-pro/inc/Blocks/Pro_Blocks.php`)**:
   - Register block type via `register_block_type()` with all attribute types, default values, and `render_callback`. Pro blocks must hook to `gridmaster_register_blocks`.
2. **Editor JS Registration (`assets/blocks/editor.js` or `gridmaster-pro/assets/js/pro-blocks-editor.js`)**:
   - Register client-side block definition with `registerBlockType()` including `title`, `icon`, `category`, `attributes`, and `edit` component with `InspectorControls`.
3. **Card Container Whitelist (`assets/blocks/editor.js`)**:
   - Gutenberg's `InnerBlocks` strictly filters in-canvas inserters using `allowedBlocks`. Add the block name to `defaultAllowedBlocks` in `editor.js`. Pro blocks must also be listed in `proBlocks` array in `pro-blocks-editor.js` to pass through the `gridmaster.allowedCardBlocks` filter. Omission causes Gutenberg to silently hide the block from the `+` inserter.
4. **Builder Inserter Catalog (`admin/assets/builder.js`)**:
   - In `MoreBlocksPopover`, add the block definition to `defaultCategories` under the appropriate category (`Content & Editorial`, `Media & Badges`, `WooCommerce & Pro Elements`, etc.) with `name`, `title`, `icon`, `desc`, `attrs`, and `isPro: true/false`.
5. **Block Metadata & Outline Display (`admin/assets/builder.js`)**:
   - Add the block name to `blockMetaMap` with human-readable `title` and Dashicon `icon`. This controls the canvas tree outline, breadcrumbs, and selected block labeling.
6. **Canvas Toolbar Quick-Add (Optional for Primary Blocks)**:
   - If the block is a primary card component (like Title, Image, Video, Excerpt, Button), add a quick-add button in the builder canvas toolbar above the paper.

Repository-local workflows: `.agents/skills/gm-free-feature/`, `.agents/skills/gm-free-bugfix/`, and `.agents/skills/gm-free-review/`.
