# Source and local environment audit

Date: 2026-11-04. Read-only planning inspection. Latest existing local feature checkout: Free `d540c8c3f210460613b090f0877d03da8bc366cc`, Pro `ec77705f58903dc82a5c66e009ca2f44f82889ee`. Both began clean on `feature/gutenberg-grid-builder-v2`. Read-only `git ls-remote` verified that remote Free dev and Pro main HEADs match these exact baselines. The sandbox DNS check initially failed, then authorized local-network execution succeeded. No fetch, merge or worktree switch was performed; experimental refs were inspected from existing local objects. Existing docs carry older discovery baselines; this record distinguishes current observations.

## Current Free behavior traced

| Source | Observation | Design implication |
| --- | --- | --- |
| admin/DB_Migration.php | Version 1.0.0 creates id/title/serialized attributes/dated via dbDelta, then writes version without verifying all outcomes | Replace view-triggered lifecycle with verified guarded upgrades; never bump version after failed DDL |
| admin/Grids.php | IDs stable; list hard limit 100; save does not distinguish failed/no-op/missing update; maybe_unserialize on reads | Paginated repository + explicit errors + safe legacy decoding; preserve IDs/bytes |
| admin/views/my-grids.php | Loads migration as a side effect; table listing interpolates values in client HTML | Do not visit this route for read-only schema inspection; new listing escapes output and does not mutate schema |
| admin/Admin.php and views/admin.php | manage_options menus; My Grids and save UI commented out | Existing storage is real despite hidden management UI; add listing without silent conversion |
| admin/Ajax.php | Nonce then dynamic method dispatch; no independent capability/active method allowlist | New explicit REST permissions and legacy dispatcher hardening before exposing new management paths |
| inc/Shortcode.php | Three aliases; saved ID merges explicit overrides; uses gm-{id}; initial and AJAX normalize differently; request-wide temporary closures; browser supplies argsArray | Preserve legacy semantics in adapter; saved v2 requests resolve configuration server-side and isolate instance/context |
| inc/functions.php | gm_get_grid loads Grids only, not migration; two differing default sets; template/helper filters and fixed breakpoints | No default-merge migration; explicitly map old effective values. Keep helper contracts in legacy path |
| assets/frontend.js | Shared infinite-scroll flag; success clears it but error does not; public HTML endpoint; document-level events | New per-instance state/request lifecycle with stale-response rejection and retry |
| package.json | Failing placeholder npm test; no current build/lint scripts | Establish a reproducible test/build toolchain as a bounded task, not assume experimental scripts exist |

Current three Free layouts are default/style-2/style-3. Preserve registered extension styles rather than assuming those three are exhaustive. Theme override resolution currently depends on Pro path constant. Custom-taxonomy selected terms and responsive styling have premium dependencies today; proposed Free expansion is explicit in PRD, not an inference of current support.

## Experimental references inspected without checkout/merge

`gutenberg-grid-cards` at `001a7626613ff65130ff787de363961adc664da9`: inspected tree, history, Blocks.php, Query_Builder.php, Template_Renderer.php, Renderer.php, editor.js, functions.php and bootstrap/shortcode diffs using git show/diff. Useful separation of query/card/renderer and private template editor. Risks: template IDs and `template:`/`legacy:` refs introduce another public identity; per-embed query attributes duplicate saved configuration; no explicit postId/postType WP_Block context; growing global helper closures; broad editor enqueue; placeholder card editor fields; no meaningful test suite. It also adds Elementor/widget work outside this scope. Its package.json declares @wordpress/scripts but its test prints success without testing; read-more editor references TextControl without importing it in the inspected destructuring. No runtime execution or wholesale reuse is approved.

`save-grids` at `21724833214e3f36bd0ccceb134c4c73144b9ee7` is an ancestor of the current baseline (no unique three-dot diff); existing saved-ID design already carried forward. `origin/no-grid-db` at `64cd86ed8c03b08c919f395d6a3c457b6df2a447` was inspected for navigation/DB-related diff and Grids source: branch name is not proof the table disappeared. Cached Pro `origin/pro-grid` at `5b0593bfecaff2dd4d88d2c7a6688dce5fdf01f4` is older history, not an advanced redesign base. No branch was switched, merged or reset.

## Local observations and evidence limits

WP-CLI 2.12.0 with explicit Local MySQL socket and skipped plugins/themes returned WordPress 7.1.4, CLI PHP 8.4.11, existing grid table with source-matching columns, zero saved rows and one record across gm_grid_template/gm_grid_style. Only schema/aggregate counts were read; no attributes, credentials or source-post data exported. This experimental record is not automatically a saved grid and must be inventoried/mapped explicitly later. Site PHP response header previously reported 8.5.12; do not confuse it with CLI PHP.

Native Chrome read-only navigation to the existing legacy builder showed current grid/filter/slider/pagination controls and an iframe repeating two real posts. No save, migration or activation change was performed. Free and Pro were active; Free-only runtime testing is deferred to backed-up fixture QA. Connected browser host is unavailable; native Chrome accessibility automation works. The frontend has a coming-soon visibility notice, which must be accounted for in logged-out QA without incidental site-setting changes.

Raw private evidence stays outside the web root. Orchestration retains sanitized command outcomes and coordination only. Its documents are currently HTTP-readable under Nginx; server restriction remains unapplied. Product PRDs/ADRs/designs stay in owning repositories. Migration QA needs representative disposable old rows and premium fixtures; the empty local table cannot certify preservation.

## Primary platform references consulted

[Block context](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-context/), [block supports](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/), [block-editor public components](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/), [CPT registration](https://developer.wordpress.org/reference/functions/register_post_type/), [REST permission callbacks and validation](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/), [revision retention](https://developer.wordpress.org/reference/functions/wp_revisions_to_keep/). Local WordPress core post-template/post-content and block metadata were also read. These justify platform mechanisms, not proof of our planned implementation.
