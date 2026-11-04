# M1–M9 implementation milestones and task DAG

Status: Approved implementation plan, 2026-11-04. Maintainer approval recorded 2026-11-04. Antigravity implements only individually delegated tasks; Codex owns review, independent tests/browser verification and milestone acceptance. Database changes remain gated by M2-T02 backup/restore acceptance.

The table below is the public coordination contract (owner, dependencies, acceptance, tests). Premium implementation internals stay in the [Pro plan](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/gridmaster-2.0/implementation-plan.md). Every task has a matching orchestration specification. A task is normally one focused worker submission; split it further before assignment if its diff cannot be reviewed coherently. No calendar estimates are asserted before the test/editor/storage spikes.

## Milestone gates and order

| Milestone | Exit gate |
| --- | --- |
| M1 — Architecture and requirements | Approved PRDs, ADRs, designs and tasks recorded; Codex records task-specific implementation gates |
| M2 — Storage, schema, repositories | Meaningful isolated harness; private backup restore rehearsal; additive schema postconditions; stable IDs/bytes and validated private aggregate repository/controller with conflict/failure tests |
| M3 — Gutenberg builder and management UI | Complete listing and native single-card editor/settings, draft/save/revision UX, reproducible scoped assets on approved runtime floor |
| M4 — Dynamic card blocks and repeated preview | Every required native/basic/addon component, real-post shared server preview and isolated responsive styling |
| M5 — Query and rendering engine | Same bounded compiler/context renderer for ID shortcode/embed/REST/preview; isolated legacy compatibility and multi-instance safety |
| M6 — Facets, filtering, pagination | Reusable essential facets and all five pagination modes with base state/race/retry/no-JS/accessibility evidence |
| M7 — Pro extensions | Compatible providers, gated legacy bridge, premium layouts/facets/blocks/carousel and certified pair/absence matrix |
| M8 — Legacy migration | Every old row discoverable, original IDs/bytes preserved, explicit candidate comparison/approval and reversible restore; no forced cutover |
| M9 — QA, security, accessibility, performance | Independent complete critical matrix, measured budgets, backup restore and version-pair evidence, unresolved risks explicitly accepted; release readiness only |

Milestones are reporting groups, not an assumption of strictly serial execution. In particular M5-T01/T02 query/render foundations follow M4-T01/T02 and precede M4-T03 full repeated preview; there is no cycle. The critical path is M1 approval → M2 → M3 native editor + M4 blocks → M5 compiler/renderer → M4 preview + M5 ID route → M6 → M7 → M8 → M9. Pro M7-T01 compatibility work can start as soon as its Free contract seams exist. M4 is not accepted until its M5 dependencies pass. V2 public enablement remains gated until applicable migration/pair/QA gates, even when individual earlier tasks are accepted.

First approved implementation task is **GM2-M2-T01**, storage characterization and disposable test harness. It makes the preservation contract executable before any schema upgrade. It changes only tests/test tooling/owning documentation, installs nothing into the existing site and performs no existing-site DB writes. M2-T02 private restore rehearsal precedes M2-T03 schema work.

## Task breakdown

## M1

### GM2-M1-T01 — Current source and experiment audit

- Owning repository: `ajax-filter-posts`; assigned role: Codex; reviewer/acceptor: Codex.
- Dependencies: none (read-only architecture start).
- Scope: docs/gridmaster-2.0/source-audit.md.
- Acceptance criteria: Trace saved-ID/storage/render/query/browser contracts and record current Free/Pro baselines without merging experiments.
- Required tests: Read source/history; compare remote base HEADs; read-only WP schema/counts and legacy builder navigation.

### GM2-M1-T02 — Core requirements and ownership PRD

- Owning repository: `ajax-filter-posts`; assigned role: Codex; reviewer/acceptor: Codex.
- Dependencies: GM2-M1-T01.
- Scope: docs/prd/0002-gutenberg-grid-builder-v2.md.
- Acceptance criteria: Document every product requirement, proposed Free/Pro boundary, edge cases, support floor and measurable acceptance; no code changes.
- Required tests: Trace user requirements to R01–R19 and cross-check companion ownership; validate local document links.

### GM2-M1-T03 — Storage, Gutenberg and extension ADRs

- Owning repository: `ajax-filter-posts`; assigned role: Codex; reviewer/acceptor: Codex.
- Dependencies: GM2-M1-T02.
- Scope: docs/adr/0003–0005; docs/gridmaster-2.0/.
- Acceptance criteria: Propose three choices with alternatives, source-of-truth map and compatibility/failure consequences, using next ADR numbers.
- Required tests: Review single-authority save/revision cases, provider absence, preserved IDs and current-source citations.

### GM2-M1-T04 — Premium PRD and integration ADR

- Owning repository: `gridmaster-pro`; assigned role: Codex; reviewer/acceptor: Codex.
- Dependencies: GM2-M1-T01, GM2-M1-T03.
- Acceptance criteria, allowed scope and required tests are authoritative in the [Pro implementation plan](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/gridmaster-2.0/implementation-plan.md), under GM2-M1-T04.

### GM2-M1-T05 — Detailed design and dependency plan

- Owning repository: `ajax-filter-posts`; assigned role: Codex; reviewer/acceptor: Codex.
- Dependencies: GM2-M1-T03, GM2-M1-T04.
- Scope: docs/gridmaster-2.0/; orchestration task references.
- Acceptance criteria: Deliver schema, components, engine, reversible migration, test strategy and a complete M1–M9 task DAG.
- Required tests: Check every task owner/dependency/acceptance/test, no cycles, all ten deliverables and requirement coverage.

### GM2-M1-T06 — Architecture review and first-task gate

- Owning repository: `ajax-filter-posts`; assigned role: Codex; reviewer/acceptor: Codex.
- Dependencies: GM2-M1-T05.
- Scope: docs indexes; orchestration reviews/status.
- Acceptance criteria: Codex reviews consistency, lists decisions/risks and presents the initial plan for maintainer review; implementation stays stopped.
- Required tests: Documentation/link/DAG/whitespace checks; independent Git scope review and explicit untested-runtime record.

## M2

### GM2-M2-T01 — Storage characterization and disposable test harness

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M1-T06.
- Scope: tests/; test-only manifests/lockfiles/config; docs/gridmaster-2.0/testing.md.
- Acceptance criteria: Pin meaningful test commands and isolated fixture setup; characterize stable legacy IDs/serialized bytes and shortcode lookup without changing runtime code or the existing site database.
- Required tests: Fresh disposable old-schema fixture; saved ID reload, exact attribute byte fixture, missing-ID behavior and test isolation/failure exit checks; current known gaps recorded, not hidden as green.

### GM2-M2-T02 — Private backup and restore rehearsal

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M2-T01.
- Scope: test/backup tooling and owning runbook; private temporary backup destination only.
- Acceptance criteria: Prove a private full backup/restore procedure outside web roots using a disposable database; preserve current Local DB and activation state.
- Required tests: Hash backup privately, restore to disposable DB, compare schema/counts/IDs and representative render; verify no dump/report secrets under web root.

### GM2-M2-T03 — Additive schema manager and recovery history

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M2-T02.
- Scope: admin/DB_Migration.php; new Storage/ schema classes; relevant bootstrap; integration tests.
- Acceptance criteria: Implement retryable verified schema upgrades/history, preserve original columns/IDs/bytes and bump storage version only after postconditions.
- Required tests: Fresh/existing/partial/error DDL fixtures; unchanged IDs/attributes; database engine preflight and failure-marker tests; PHP syntax/WPCS as available.

### GM2-M2-T04 — Stable ID repository and bounded listing

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M2-T03.
- Scope: Storage/GridRepository; legacy repository adapter seam; storage tests.
- Acceptance criteria: Add explicit CRUD/error outcomes, paginated >100-row listing, draft allocation/bindings and unique-ID duplication; no frontend engine switch.
- Required tests: 130-row pagination, missing/no-op/failed update, numeric IDs, failed bind inert draft, duplicate mapping and soft-trash/restore tests.

### GM2-M2-T05 — Private definition CPT and validator

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M2-T04.
- Scope: definition CPT/metadata/validator classes; unit/integration fixtures.
- Acceptance criteria: Register restricted definition entity and one-root/card schema with no duplicate config store, bounded attributes and lossless provider-data policy.
- Required tests: Serialize/parse round trip, nested/unknown/protected data, future schema, depth/payload limits, draft eligibility and full aggregate revision fixtures.

### GM2-M2-T06 — Authenticated save, revision and conflict service

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M2-T05.
- Scope: REST definition controller; repository locking/history; core CPT route integration; tests.
- Acceptance criteria: Route all definition/editor/revision/autosave writes through capability/schema/hash checks with proven locking and rollback-safe cache behavior.
- Required tests: Admin/editor/subscriber/anonymous × nonce/capability; concurrent stale saves; interrupted post/binding writes; core alternate routes; autosave not public; engines unsupported fail safely.

### GM2-M2-T07 — Legacy management write boundary

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M2-T04.
- Scope: admin/Ajax.php; old management adapters; targeted tests.
- Acceptance criteria: Replace dynamic admin method dispatch with an explicit allowlist/capability checks and repository bridge, preserving legitimate old settings/grid behavior.
- Required tests: Role/nonce/action allowlist, malformed settings/attributes, failed-save outcome and no changes to stored premium keys; old builder regression.

## M3

### GM2-M3-T01 — My Grids management UI

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M2-T06, GM2-M2-T07.
- Scope: admin menu/list components; management assets/tests.
- Acceptance criteria: List all legacy/v2/draft states, saved-ID shortcode, new/duplicate/trash/restore and safe links; no automatic migration or schema work on listing.
- Required tests: Browser >100 rows/search/pagination/escaping, same-ID edit, duplicate new ID, missing provider state and unauthorized navigation.

### GM2-M3-T02 — Native editor and minimum-version spike

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M2-T06.
- Scope: editor bootstrap; block metadata; reproducible editor build/test manifest and lockfile.
- Acceptance criteria: Open a table-ID-resolved CPT in the native editor with one locked root/card and public Gutenberg APIs; gracefully retain legacy below builder floor.
- Required tests: WP6.5 and installed-runtime smoke; keyboard inserter/List View/undo; native save/autosave; no source-post edit and no extra React/runtime.

### GM2-M3-T03 — Definition settings inspectors

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M3-T02.
- Scope: editor root inspector/sidebar components; schema metadata; component/browser tests.
- Acceptance criteria: Edit query/facet/pagination/outer style through the canonical root attributes and show dirty/conflict/read-only provider states.
- Required tests: Component/store tests for same-document save/revision, no options/meta mirror, validation errors, unknown namespace preservation; native browser reload.

### GM2-M3-T04 — Scoped editor and frontend asset build

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M3-T02.
- Scope: asset build scripts/manifests; enqueue/registration; asset tests.
- Acceptance criteria: Generate deterministic bundles/dependency metadata with only relevant editor/CPT assets and declared compatible addon handles.
- Required tests: Clean locked build, dependency metadata, source/built parity, no assets on unrelated admin/front screens; no vendor rewrite.

## M4

### GM2-M4-T01 — Native contextual card components

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M3-T02.
- Scope: card editor/context components; native block mapping; block integration tests.
- Acceptance criteria: Expose required native post/structural blocks and safe per-post editing context, with no replicated saved card trees.
- Required tests: All required image/title/content/excerpt/read-more/date/author/terms/group/columns/spacer/separator cases on different sample posts; no source post mutation.

### GM2-M4-T02 — Basic Post Meta and addon block registry

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M4-T01, GM2-M2-T05.
- Scope: Free meta block; extension registry; provider contract tests.
- Acceptance criteria: Implement safe public scalar meta block plus versioned addon-card registration, schema/asset declarations and unavailable handling.
- Required tests: Protected/unregistered/array meta rejected, escaped scalar output, client/server schema parity, duplicate provider IDs, missing/future addons lossless/read-only.

### GM2-M4-T03 — Repeated authoritative server preview

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M4-T02, GM2-M5-T02, GM2-M3-T03.
- Scope: preview REST/controller and repeated preview UI; shared renderer tests.
- Acceptance criteria: Render draft card across six different queried posts through shared server engine with one bounded request and no writes.
- Required tests: Preview/initial/results markup parity, 12-post cap, abort/stale/failed/empty behavior, private preview authorization, no per-card HTTP calls; browser real posts.

### GM2-M4-T04 — Native supports and scoped responsive styling

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M4-T01, GM2-M5-T02, GM2-M3-T04.
- Scope: card/grid supports/style compiler; editor/frontend styles; browser/style tests.
- Acceptance criteria: Apply block supports and validated outer columns/gaps/hover styles with unique repeat IDs and parity in preview/frontend.
- Required tests: Responsive widths, two same saved IDs, ID/ARIA uniqueness, malicious CSS/unit rejection, reduced motion, RTL/theme variations and scoped assets.

## M5

### GM2-M5-T01 — Saved-definition query compiler

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M2-T05, GM2-M4-T02.
- Scope: Query compiler/state objects; provider interface; query integration tests.
- Acceptance criteria: Compile bounded public queries and server-registered options with immutable constraints and deterministic order, independent of renderer.
- Required tests: Taxonomy/CPT/author/search/order fixtures, private/password exclusion, invalid raw args, fixed+visitor search rule, tie ordering and mandatory safety after extension filters.

### GM2-M5-T02 — Repeated WP_Block card renderer

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M5-T01, GM2-M4-T01.
- Scope: Rendering services; asset collector; block rendering integration tests.
- Acceptance criteria: Render parsed card per post with explicit context/global restoration and required registered assets; empty/errors/recursion safe.
- Required tests: Different post values; nested post-content/grid recursion; mixed render orders; thrown callbacks restore globals; no per-card definition/meta lookup growth.

### GM2-M5-T03 — Scoped legacy renderer adapter

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M5-T02, GM2-M2-T07.
- Scope: inc/Shortcode.php compatibility seam; Legacy adapters; frontend selector gating; regression fixtures.
- Acceptance criteria: Keep old aliases/overrides/templates working with explicit legacy mode and prevent helper/JS leakage into v2 instances.
- Required tests: Three aliases; saved/inline overrides; legacy→v2/v2→legacy mixed pages; duplicate DOM IDs; Pro hook priority fixtures and filter cleanup without remove_all_filters.

### GM2-M5-T04 — ID-only embeds and public results route

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M5-T03, GM2-M2-T06.
- Scope: shortcode dispatcher; grid embed block; results REST/controller; integration/browser tests.
- Acceptance criteria: Use the same saved-ID service for shortcode/embed/public results, exposing only eligible active public output and registered controls/assets.
- Required tests: Saved edit without shortcode change, draft/missing IDs uniform404, no raw config leak, browser raw renderer/template/args spoof rejected, multiple independent roots.

## M6

### GM2-M6-T01 — Essential reusable facets and top filters

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M5-T04, GM2-M3-T03.
- Scope: facet engine/providers; inspector controls; server/component tests.
- Acceptance criteria: Register taxonomy single/search/reset types and copyable named presets; same state model for top/repeated controls, base constraints retained.
- Required tests: Custom taxonomy/CPT, allowed terms, All/reset, no counts leakage, invalid facet state, duplicate controls sync per instance and copied preset independence.

### GM2-M6-T02 — Five server pagination modes

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M6-T01.
- Scope: pagination strategy/result metadata; query integration tests.
- Acceptance criteria: Provide explicit numbered/prev-next/load-more/infinite/none server results with active state and finite bounds.
- Required tests: Filter+page matrix, no-pagination perPage bound, final/empty/out-of-range pages, deterministic fixtures and no global paged contamination.

### GM2-M6-T03 — Per-instance frontend request lifecycle

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M6-T02, GM2-M3-T04.
- Scope: frontend v2 controller; JS state/unit/end-to-end tests.
- Acceptance criteria: Implement independent normalized state, abort/request IDs, sequential append/dedup and retry-safe loading for v2 roots.
- Required tests: Out-of-order/rapid/slow/error/abort tests, filter-after-page race, two same saved IDs, legacy coexistence and finally-cleared state; real network browser scenarios.

### GM2-M6-T04 — URL/no-JS and accessible interaction

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M6-T03.
- Scope: frontend accessibility/URL components; public fallback state; browser tests.
- Acceptance criteria: Namespaced URL/back-forward/no-JS fallback and labeled/live/focus controls, finite infinite autoload/manual fallback without affecting sibling grids.
- Required tests: Keyboard/screen-reader smoke, no-JS links, back/forward/search debounce, three-auto-page limit, reduced motion and sibling URL keys preserved.

## M7

### GM2-M7-T01 — Compatibility gate and scoped legacy bridge

- Owning repository: `gridmaster-pro`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M1-T04, GM2-M5-T03.
- Acceptance criteria, allowed scope and required tests are authoritative in the [Pro implementation plan](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/gridmaster-2.0/implementation-plan.md), under GM2-M7-T01.

### GM2-M7-T02 — Taxonomy multi-select facet provider

- Owning repository: `gridmaster-pro`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M7-T01, GM2-M6-T03.
- Acceptance criteria, allowed scope and required tests are authoritative in the [Pro implementation plan](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/gridmaster-2.0/implementation-plan.md), under GM2-M7-T02.

### GM2-M7-T03 — Typed metadata and range providers

- Owning repository: `gridmaster-pro`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M7-T01, GM2-M6-T03.
- Acceptance criteria, allowed scope and required tests are authoritative in the [Pro implementation plan](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/gridmaster-2.0/implementation-plan.md), under GM2-M7-T03.

### GM2-M7-T04 — Advanced sort and archive context providers

- Owning repository: `gridmaster-pro`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M7-T01, GM2-M5-T04.
- Acceptance criteria, allowed scope and required tests are authoritative in the [Pro implementation plan](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/gridmaster-2.0/implementation-plan.md), under GM2-M7-T04.

### GM2-M7-T05 — Premium contextual blocks and controls

- Owning repository: `gridmaster-pro`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M7-T01, GM2-M4-T03.
- Acceptance criteria, allowed scope and required tests are authoritative in the [Pro implementation plan](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/gridmaster-2.0/implementation-plan.md), under GM2-M7-T05.

### GM2-M7-T06 — Premium editable layout and filter presets

- Owning repository: `gridmaster-pro`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M7-T05, GM2-M4-T04, GM2-M7-T02.
- Acceptance criteria, allowed scope and required tests are authoritative in the [Pro implementation plan](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/gridmaster-2.0/implementation-plan.md), under GM2-M7-T06.

### GM2-M7-T07 — Instance carousel lifecycle adapter

- Owning repository: `gridmaster-pro`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M7-T06, GM2-M6-T04.
- Acceptance criteria, allowed scope and required tests are authoritative in the [Pro implementation plan](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/gridmaster-2.0/implementation-plan.md), under GM2-M7-T07.

### GM2-M7-T08 — Provider contract and version-pair suite

- Owning repository: `gridmaster-pro`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M7-T02, GM2-M7-T03, GM2-M7-T04, GM2-M7-T07.
- Acceptance criteria, allowed scope and required tests are authoritative in the [Pro implementation plan](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/gridmaster-2.0/implementation-plan.md), under GM2-M7-T08.

## M8

### GM2-M8-T01 — Durable migration snapshot coordinator

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M2-T03, GM2-M2-T06, GM2-M5-T03.
- Scope: Migration coordinator/history/decoder; failure integration tests.
- Acceptance criteria: Implement analyze/snapshot/candidate state transitions with safe decoding, immutable exact-byte recovery and idempotent operation IDs; no automatic promotion.
- Required tests: Malformed/object/scalar source, hash equality, repeat/interrupted operations, lock/conflict behavior, revisions-disabled recovery and zero unsolicited frontend writes.

### GM2-M8-T02 — Free legacy candidate converters

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M8-T01, GM2-M4-T04, GM2-M6-T04.
- Scope: Free converter providers; fixtures; owning mapping docs/tests.
- Acceptance criteria: Map all Free layouts/effective defaults/query/filters/styles into draft definitions with explicit losses and trusted legacy-card bridge where needed.
- Required tests: Default/style-2/style-3 and registered addon/theme fixtures; ambiguous pagination/defaults, responsive values, untouched IDs/bytes and same-post visual comparisons.

### GM2-M8-T03 — Premium conversion providers

- Owning repository: `gridmaster-pro`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M8-T01, GM2-M7-T08.
- Acceptance criteria, allowed scope and required tests are authoritative in the [Pro implementation plan](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/gridmaster-2.0/implementation-plan.md), under GM2-M8-T03.

### GM2-M8-T04 — Compare, approve and Restore Legacy workflow

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M8-T02, GM2-M8-T03, GM2-M4-T03.
- Scope: Free migration management UI/routes/coordinator; paired browser/integration tests.
- Acceptance criteria: Show old/candidate output and warnings; approve only exact source/candidate hashes; reversible binding change retains originals and v2 work.
- Required tests: Browser side-by-side + explicit approval; stale hashes/concurrent tabs denied; interruption before/after flip; restore original ID/output with native revisions disabled.

### GM2-M8-T05 — Bounded resumable migration batches

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M8-T04.
- Scope: batch coordinator/management UI; integration tests/runbook.
- Acceptance criteria: Process rows in bounded resumable batches with per-row private reports and no site-wide auto approval; listing remains usable after failures.
- Required tests: >130 rows, mixed success/failure, repeated checkpoint/retry, independent failed row, permission/payload limits and accurate sanitized counts.

### GM2-M8-T06 — Explicit inline and experimental import

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M8-T05.
- Scope: import adapters/UI; conflict/round-trip/browser tests.
- Acceptance criteria: Import legacy inline settings and experimental template records only by administrator action, allocate stable new IDs and report conflicts/unknown blocks.
- Required tests: CPT/table ID collisions, one observed experimental record modeled synthetically, no automatic remapping, original inline output retained and reversible import.

## M9

### GM2-M9-T01 — Independent Free security and regression gate

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M8-T06.
- Scope: Free targeted fixes/tests/security docs; no unrelated refactoring.
- Acceptance criteria: Resolve new-boundary security/regressions and certify private definition/history access, bounded queries, legacy compatibility and Free-alone runtime.
- Required tests: Role×nonce×route matrix, injection/serialized objects, public post privacy, all aliases/modes and backup/restore evidence; Codex reruns and browser-verifies.

### GM2-M9-T02 — Premium security and compatibility hardening

- Owning repository: `gridmaster-pro`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M8-T03, GM2-M7-T08.
- Acceptance criteria, allowed scope and required tests are authoritative in the [Pro implementation plan](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/gridmaster-2.0/implementation-plan.md), under GM2-M9-T02.

### GM2-M9-T03 — Accessibility, responsive and theme acceptance

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M9-T01, GM2-M9-T02.
- Scope: Free targeted UI/output fixes/tests; paired Pro issues returned to Pro task.
- Acceptance criteria: Meet reviewed WCAG2.2AA plugin acceptance, usable keyboard/reduced motion/no-JS/RTL/theme behavior across editor and output.
- Required tests: Automated accessibility scan plus manual keyboard/screen reader, focus/live regions, mobile/RTL, two themes/child override and all pagination modes.

### GM2-M9-T04 — Measured engine performance and assets

- Owning repository: `ajax-filter-posts`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M9-T03.
- Scope: Free performance/asset fixes and benchmark harness/docs.
- Acceptance criteria: Meet approved SQL/time/bundle budgets with documented 10k-post fixture and cache isolation; no unsupported cache pass claims.
- Required tests: 30-run cold/warm p95 protocol, 12/24-card SQL-growth comparison, three-grid race/load test and unrelated-page asset audit.

### GM2-M9-T05 — Premium performance and carousel QA

- Owning repository: `gridmaster-pro`; assigned role: Antigravity; reviewer/acceptor: Codex.
- Dependencies: GM2-M9-T02, GM2-M9-T04.
- Acceptance criteria, allowed scope and required tests are authoritative in the [Pro implementation plan](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/gridmaster-2.0/implementation-plan.md), under GM2-M9-T05.

### GM2-M9-T06 — Final paired milestone and release-readiness review

- Owning repository: `ajax-filter-posts`; assigned role: Codex; reviewer/acceptor: Codex.
- Dependencies: GM2-M9-T05.
- Scope: owning docs/acceptance evidence and orchestration status/review only.
- Acceptance criteria: Codex accepts only verified complete matrix, reversible migration/restore, certified version pairs and reviewed residual risks; no publication.
- Required tests: Independent all-critical suite/browser replay; exact IDs/bytes/rollback; package runtime inventory validation including new assets and no development/private artifacts; no release command.

## Delegation and completion policy

Before each implementation assignment, Codex verifies both branches/worktrees, marks approved PRD/ADR references and permitted files/commands in the task, and establishes exact runner commands from the accepted harness. Do not invent npm/composer commands as passing evidence. Use agy headless JSON with finite timeout and narrowly scoped session permissions; never skip all permissions. Raw transcripts/diagnostics/backups stay outside web roots. Sanitized reports record process status/denials/changed files/tests. Codex independently reviews actual diffs, runs checks and verifies the UI when relevant. A task needs evidence beyond worker SUCCESS. At most three automatic corrections after an initial submission, then BLOCKED with diagnosis. Update PROJECT_STATUS.md after each task. No push/merge/release/deployment is implied by acceptance.
