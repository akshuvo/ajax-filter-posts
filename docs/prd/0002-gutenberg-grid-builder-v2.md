# 0002 — Gutenberg saved-grid builder

- Status: Approved for planned implementation; maintainer authorization recorded 2026-11-04. Milestone gates and task-specific scope remain binding.
- Owner: Free; architect/reviewer: Codex; product decisions: maintainer.
- Date: 2026-11-04. Product name “2.0” does not select a plugin release version.
- Baseline: Free `d540c8c` (3.4.18), Pro `ec77705` (1.2.3), both on `feature/gutenberg-grid-builder-v2`.
- Companion: [Pro PRD](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/prd/0001-gutenberg-pro-extensions.md). Decisions/design index: [architecture package](../gridmaster-2.0/README.md).

## Problem and users

Site administrators need flexible post cards, saved reusable grids and predictable filtering without maintaining long shortcodes. Existing installations depend on numeric IDs, serialized data, template overrides and premium behavior. The current form builder previews real posts but does not offer a Gutenberg card composition workflow; its save/list navigation is hidden. A redesign must add flexibility without replacing working output silently.

Primary author: an administrator designing a reusable grid. Consumer: a page editor embedding its saved ID. Visitor: someone filtering and paging that grid. Extension author: a developer providing compatible blocks, facets, query options or layouts. Initial grid management remains restricted to `manage_options`; editors do not gain administrative access through a public embed block.

## Goals and release boundaries

All newly created grids receive persisted numeric IDs before embedding. One editable card repeats across query results. Server rendering, repeated editor preview and subsequent filter/page requests use the same definition and normalized query service. Existing saved IDs and original data survive opt-in reversible conversion. Free operates independently; Pro adds optional providers through a versioned Free API.

M1 delivers reviewable designs only. M2–M9 implementation is delegated to Antigravity only after architecture review and task authorization. The initial redesign release retains the old builder, renderer and inline shortcode path. No automatic site-wide conversion, removal of old APIs, license-service replacement, mass refactoring or publication is included.

## Proposed Free/Pro ownership

| Capability | Free | Pro |
| --- | --- | --- |
| Storage, ID registry, listing, Gutenberg builder, revisions, preview, migration coordinator, rendering/REST, public APIs | Owns | Uses, never forks storage/endpoints |
| Image/title/content/excerpt/read-more/author/date/terms/basic public scalar meta; Group/Columns/Spacer/Separator | Included, using native blocks where practical | Rich metadata formatting, conditional/premium blocks through registry |
| Fixed public post type, per-page/order/taxonomy/author/search query; extension query registry | Included | Archive-aware modes, registered advanced sorts/query providers |
| Essential visitor filters | Single-select taxonomy (including public custom taxonomies), search, reset; reusable facet definitions/presets | Multi-select, metadata/date/numeric ranges, advanced sort controls, term image/color layouts |
| All five pagination modes; responsive columns/gaps/native styling/basic hover presets | Included | Premium presets, additional layouts and optional carousel adapter |
| Legacy templates | Free layouts and safe fallback coordination | Existing premium layouts and filter adapters, owned and tested in Pro |

Responsive controls and explicit custom-taxonomy term selection are intentionally included in new Free grids even though inspected code currently places much of that behavior behind Pro. This is a proposed product boundary requiring review, not a statement about current entitlements. Existing purchased data is never deleted. Persistent globally linked facet entities are deferred; reusable facets mean common registered types, named presets and explicit copy-to-grid in this release, with each grid owning its copied values.

## Functional requirements

| ID | Requirement / observable acceptance |
| --- | --- |
| R01 | Every new grid is created as a saved draft with a positive stable table ID; successful Save makes `[gridmaster id="123"]` render its last explicitly saved state. Updating title/card/query keeps the ID and existing embeds. Failed saves leave the previous saved output intact. |
| R02 | My Grids shows every existing table row with pagination/search, ID, title, renderer/migration status and actions. Do not truncate listing at the current 100-row limit. New draft rows can be removed through a guarded recoverable action; initial release has no automatic hard purge. Duplicate creates a new ID, never overwrites the source. |
| R03 | The native editor edits one card tree with inserter, drag/drop, keyboard movers, List View, nested Group/Columns, spacing and normal undo/redo. Exactly one definition root and one card container are required. Changes to a sample post's title/content in a card inspector must never edit the real source post. |
| R04 | Provide Featured Image, Post Title, Post Content, Post Excerpt, Read More Button, Separator, Post Author, Post Date, Post Terms, Post Meta, Group, Columns and Spacer. Public scalar meta is explicit opt-in to a registered display-safe field; arbitrary/protected meta is never exposed. Additional addon blocks require registered compatibility, safe attributes and contextual rendering. |
| R05 | Grid query supports a public post type, 1–100 posts/page, allowed order/orderby, included taxonomy terms, author IDs and search. Registered server extensions supply validated custom options. Visitors can change only controls explicitly enabled in the saved definition, never arbitrary WP_Query arguments. |
| R06 | Facets and top filters share one normalized state model. Free provides single-select public taxonomy/search/reset; Pro extends it. Base constraints always apply. “All” clears the visitor selection only, not the administrator's base term restrictions. Query-affecting changes reset page to 1. |
| R07 | Numbered, previous/next, load more, infinite scroll and no pagination work with identical active state. No pagination displays the first configured bounded page, not an unbounded query. Append modes avoid duplicate pages; stale requests cannot overwrite newer filters. Failure permits retry and preserves existing results. |
| R08 | Responsive columns, independent row/column gaps, spacing, typography, borders, colors, image controls, card composition and safe hover presets work in Free. Use native block controls where supported. Generated styles affect only their grid/card instance; reduced motion and narrow screens are supported. |
| R09 | The editor exposes a repeated preview of different real queried posts (default 6, maximum 12). Only one card is editable; repeats are read-only. It shows the saved-query draft, real empty states, loading/errors and missing fields, and uses the same PHP rendering path for the authoritative preview. Unsaved previews do not write grids or source posts. |
| R10 | Old saved rows appear without conversion. Their IDs and shortcodes remain valid. Convert creates a candidate and a report; the public grid stays on legacy output until an administrator explicitly approves that exact candidate version. Uncertain mappings stay on legacy. Restore Legacy restores the original preserved baseline without deleting the v2 candidate. |
| R11 | Legacy inline `[gridmaster]`, `[am_post_grid]`, `[asr_ajax]` and stored attribute overrides continue on the compatibility path. They are not automatically saved on public page requests. Offer an explicit Import as Saved Grid action. Newly created v2 embeds use ID only. Existing saved-ID embeds with attribute overrides remain legacy overrides until explicitly reviewed/imported. |
| R12 | Multiple independent grids, including two instances of the same saved ID, isolate filters, page, DOM IDs, requests, styles and post context. Legacy and v2 grids may coexist on one page without Pro callback leakage. |
| R13 | Free has no required Pro classes/assets. Pro checks Free feature/API compatibility before registering new providers. Missing/incompatible extensions preserve stored data; unsupported query requirements fail closed rather than silently widening results. Optional presentation providers may use a disclosed safe base fallback. |
| R14 | Authorized editing supports explicit save, dirty state, autosave recovery, edit locking and version conflicts. Restoring a definition revision restores query/facets/styles/card together. Host-disabled revisions are reported; durable migration snapshots remain independent of revision retention. |
| R15 | Export/import of a full migration backup is an administrator maintenance operation with private destinations; it does not imply cloud sync. Deactivation leaves all tables/CPT content and premium namespaces intact. Unsupported stored schema versions remain read-only with legacy fallback where available. |

## Security, accessibility and performance

R16: Every management/preview/migration REST route has a capability permission callback, cookie REST nonce when using cookie authentication, strict schema and bounded payload validation. A nonce is not authorization. Private definitions, autosaves, revisions and snapshots must not be anonymously retrievable through core or custom routes. The public results route exposes only active public-grid results and minimal presentation state, never raw definitions, post meta schemas or snapshots.

R17: Published publicly queryable posts only on visitor routes; exclude password-protected posts from card content/query output. Preserve WordPress visibility and registered field permissions. Prepared database operations, object-free legacy decoding, escaped URLs/text and validated CSS/tag/unit values are mandatory. No executable code, arbitrary SQL, arbitrary template paths or arbitrary callbacks from saved/request data. Guard recursive grid/post-content rendering.

R18: Target WCAG 2.2 AA for plugin UI/output: labels, keyboard ordering, focus, live result announcements, non-color state and reduced motion. Infinite scroll always has an accessible manual fallback and finite loading behavior. RTL and translation domains are included. Native block support alone is not accessibility certification.

R19: Parse a definition once per request; query posts in batches with meta/term cache priming; no per-card preview HTTP calls or per-card definition database lookups. Do not enqueue editor/runtime/carousel assets on unrelated pages. Bound payloads to 256 KiB, card tree to 200 blocks/depth 10, facets to 10, values to 50 per facet and search to 200 characters (proposed configurable server ceilings, not browser-controlled). See [testing targets](../gridmaster-2.0/testing.md) for measurable provisional budgets and methodology.

## Dependencies and support proposal

Existing listing declares WordPress 5.8+/PHP 7.4+. Proposed new builder baseline is WordPress 6.5+ with PHP 7.4+ compatible production code; retain the legacy runtime below the builder baseline, without attempting to load new editor APIs. Test on minimum and installed WordPress 7.1.4; local CLI PHP 8.4.11 differs from site header PHP 8.5.12. This support split and exact release pair must be approved and verified, not inferred from one local site. Use WordPress-bundled editor packages rather than bundling another React runtime. M2 establishes pinned development tools and meaningful test commands.

## Acceptance scenarios and failure cases

Given saved ID 123, when its card/query is saved, then every unchanged ID embed displays that state; autosave alone does not publish a draft. Given 130 old rows, when paging My Grids, then every row is reachable and none is silently converted. Given a migrated candidate with an unmapped addon/premium layout, then the live route remains legacy and reports the reason. Given an approved candidate subsequently edited before promotion, then stale approval is rejected.

Given a taxonomy restriction and visitor “All”, then posts outside the base restriction remain excluded. Given a page-two request followed by a new facet choice, then an old response is discarded. Given two identical grid IDs on a page, changing one never changes the other. Given a Pro-dependent range filter without Pro, then an unavailable state is shown and data is retained rather than running an unfiltered query.

Given unauthorized users, malformed blocks/styles/meta keys or a private definition ID, then requests are rejected with safe errors and no content leakage. Given missing images/title/terms, no posts or a failed query, then the layout remains usable and communicates an appropriate state. Given a post containing this same grid, then rendering terminates with a safe recursion result. Unknown schema/block data is preserved for recovery and must not be silently stripped by a save.

## Backward compatibility, tests and evidence

Preserve original row bytes, dates and IDs, old builder/renderer, documented hooks/priorities and theme-template behavior through a scoped legacy adapter. Changing historical shortcode overrides is opt-in. New API contracts are separate; legacy hooks do not become global v2 mutation hooks. Required automated/manual matrix, fixtures and migration restore tests: [testing](../gridmaster-2.0/testing.md), [migration](../gridmaster-2.0/migration.md), [milestones](../gridmaster-2.0/implementation-plan.md).

Observed planning evidence only: source/branch inspection, read-only table schema/count (0 saved rows, 1 experimental template record), native browser legacy builder with real preview, and documentation validation. No new builder, migration, Free-only activation scenario, security exploit or performance test has passed yet.
