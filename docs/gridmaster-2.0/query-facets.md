# Query, facet and pagination engine

Status: Proposed. Free owns the compiler/state/runtime; compatible providers extend it through [ADR 0005](../adr/0005-versioned-extension-contract.md). This is a new saved-ID path; the legacy public HTML/AJAX protocol remains isolated.

## Normalized inputs and immutable constraints

Definition.query is authoritative: postType (one registered publicly queryable type), perPage 1–100, orderBy date/modified/title/menu_order/ID, ASC/DESC, base taxonomy clauses, author IDs and optional base search. Use deterministic ID tie-breakers for non-unique sorts. Validate actual post-type/taxonomy relationships, visible terms, bounded IDs and actual registered sort providers. Reject unknown sort/query fields; never pass a client object directly to WP_Query. Static base filters are ANDed with permitted visitor filters; a visitor cannot change post type, page size, status, meta keys or allowed term universe.

Definition.facets is an ordered list `{id,type,version,label,settings,defaultValue}`; IDs are stable inside one grid. Type IDs are registered (`core/taxonomy-single`, `core/search`, extension IDs). Settings include taxonomy/allowedTermIds or a registered field/sort ID. Public visitor state is `{page,facets:{facetId:value},sortId?}` plus a render-instance key. Normalize schemas, defaults and canonical order server-side; ignore no fields silently where that could widen results. Invalid/disallowed requests receive 400 and keep existing results in the client. No arbitrary tax_query, meta_query, post_status, offset, unlimited perPage, SQL or callback names.

Base taxonomy terms are OR within one IN clause by default, AND between clauses. Free single-select facet adds one allowed term. All/reset removes only its visitor clause and retains base restrictions. Pro supports per-facet any/all selection semantics (OR/AND within taxonomy) with AND between independent facets; selecting All clears selections rather than sending -1 to SQL. Empty selections mean no visitor restriction. Base author/search constraints remain mandatory; enabled visitor search adds a safely combined search requirement, never replaces a narrower base term. If core WP_Query cannot express the intended combined search semantics, normalize the saved base search as immutable tokens and compile the conjunction via a tested server-owned adapter; do not concatenate arbitrary SQL. Prefer a single search source in the initial editor: disallow enabling visitor search when fixed base search is nonempty until this adapter is verified.

Public queries enforce publish status, public visibility and exclusion of password-protected content after trusted query-extension filters. Admin preview initially uses the same public-post universe; privileged draft sampling is deferred. Preserve WordPress/plugin visibility filters; do not use suppress_filters to bypass them. Registered providers run server-side and still cannot override the mandatory public safety constraints.

## Reusable facets and top filters

One FacetEngine handles both top filters and inspector-defined facet controls. FacetProvider normalizes values, emits schema-constrained query clauses, renders accessible controls and optionally counts. Free registers taxonomy single-select, search, reset and neutral markup layouts. Pro registers multi-select, range/date/meta fields, advanced sorting and image/color/filter layouts. Public custom-taxonomy single-select is proposed Free functionality; not all existing Pro data becomes an advanced Free feature.

Reusable means the same type/schema/render lifecycle works in multiple grids/positions, extensions can register named presets, and authors can explicitly copy a facet preset into another grid. Copied values live in that target definition. No hidden globally linked facet store or automatic propagation exists in this release. One facet selection model drives all views in an instance; repeated controls for the same facet synchronize only inside that instance.

Facet count semantics are explicit: omit live counts in essential Free controls. When Pro enables dynamic counts, compute base constraints plus other facets, excluding the current facet's selection while retaining its allowed universe (disjunctive counts). Include zero-count visibility/disable policy; selected values remain removable. Batch and cap counts, cache only with complete state/definition/data keys; no query per option/card. Metadata facets expose only administrator-approved public query fields with validated number/date types, comparisons, min/max and finite precision. Never enumerate arbitrary/protected keys or leak private values through counts.

## Request and rendering path

Initial shortcode/embed, public results, editor preview and counts use the same QueryCompiler. The public request supplies saved grid ID, permitted state and a scoped instance key. Server resolves current saved definition, checks required providers and renders CardRenderer once per result. Do not allow current archive context inferred from an unauthenticated Referer: Pro archive-aware queries use a server-created signed context containing only allowlisted public archive identifiers, grid ID, definition hash and expiry; revalidate on every request. Reject tampered/expired context; do not replace it with a broader generic query.

No raw provider callbacks or source attribute snapshots reach the browser. Results return one fragment plus page/hasMore/state hashes and registered asset handles. Empty results provide useful escaped content. Missing grid/private/draft/unavailable provider never means “fall back to default WP_Query.” Stable ordering removes ties but does not freeze a changing database: tests assume fixed fixtures, and content updates during paging trigger definition/data-version refresh warnings/reset rather than promising immutable snapshot pagination.

## Pagination and independent state

| Mode | Server/client behavior |
| --- | --- |
| Numbered | Replace results for selected bounded page; total pages and real no-JS links |
| Previous/Next | Same page state as numbered, only adjacent controls |
| Load more | Sequential append; reserve one next-page request per instance; replace navigation; deduplicate completed request/page tokens |
| Infinite | Load-more engine plus IntersectionObserver, manual Load More fallback and finite auto-load limit (proposed 3 pages per interaction); reduced-motion/focus support |
| None | First configured perPage only, no controls/no totals query when unnecessary; not all posts |

Every query-affecting facet/sort change resets page to 1 and replaces the collection. Per-instance controller stores normalized state, request counter, AbortController, loading/error and seen pages. Filter changes invalidate append responses immediately; compare request ID, instance, state hash and definition hash before applying. A finally path clears loading on success/failure/abort. Empty/final-page responses disable further auto-load; failures preserve results and expose Retry. Race tests include two identical saved IDs and mixed legacy/v2 grids.

Use unique embedding key for URL state `gm[embedKey][page/facets]`, not a global paged query variable. An optional author-set instance key is layout placement metadata, not saved grid configuration; otherwise derive a deterministic placement key for fallback links and a unique runtime DOM key. No-JS GET fallback runs the same saved-ID service with namespaced state. Do not make browser history push mandatory for every keystroke; replace debounced search state, preserve other grids' keys and test back/forward behavior. Include live-region announcements and predictable focus on explicit pagination, avoid moving focus during automatic appends.

## Performance and cache correctness

Memoize loaded/parsed definitions per request; WP_Query caches prime terms/meta in batches. Only counts/page totals needed by the mode are computed. Enforce page ≤1000, maximum 10 facets/50 selected values each, 200-character search, 200-block/depth-10 tree and 256-KiB definition/preview payload ceilings. Limits are server policy extension points for trusted code, not request parameters.

Do not introduce persistent full-HTML caching in the first engine iteration. It can leak capabilities/theme/language state and depends on content/term/meta/provider/theme invalidation. A later bounded cache for public result IDs/counts needs keys for blog/grid/definition hash, normalized state, signed archive context, locale, provider versions and content-data generation. Invalidate after successful grid save/promotion/revert and post/status/meta/term changes. Never cache privileged preview into public entries. Benchmark cold/warm behavior and worst-case range/count queries before enabling persistent caches; indexes for large metadata datasets are Pro follow-up work, not a claim core postmeta is a fast search engine.
