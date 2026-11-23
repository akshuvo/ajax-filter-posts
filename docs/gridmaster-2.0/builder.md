# Builder components, rendering and REST design

Status: Proposed. [Gutenberg ADR](../adr/0004-native-gutenberg-card-builder.md), [definition authority](schema.md), [extension API](../adr/0005-versioned-extension-contract.md).

## PHP service boundaries

| Component (proposed) | Responsibility / prohibition |
| --- | --- |
| Storage/SchemaManager | Inspect/upgrade/postcondition-check schema; never convert rows on frontend |
| Storage/GridRepository | Stable table IDs, binding CAS, paginated listing and lifecycle; no HTML |
| Storage/DefinitionRepository + DefinitionValidator | CPT aggregate serialization/schema/hash/lock/revision integration; no second config store |
| Legacy/GridAdapter + LegacyRenderContext | Existing attribute/override compatibility; safe array decoding and scoped callback cleanup |
| Query/QueryCompiler + StateNormalizer | Saved constraints plus permitted visitor state → bounded WP_Query; no arbitrary argsArray |
| Rendering/GridRenderer + CardRenderer + AssetCollector | One definition per request, WP_Block context per post, instance wrappers/styles/assets; no writes |
| Facets/Registry + FacetEngine | Control schema, state, query constraints and bounded counts; same service for top filters |
| Rest/DefinitionController, ResultsController, PreviewController | Explicit auth/schema/controller orchestration; do not duplicate business logic |
| Migration/Coordinator + ConverterRegistry | Durable snapshot/candidate/approval/revert; never infer approval from confidence |

Names and folder structure are proposed, not existing classes. Retain existing namespaces and helpers; introduce small adapters instead of mass renaming. No Pro table/endpoints/repository copy.

## Native UI and one editable tree

My Grids lists registry IDs, legacy/v2/candidate/draft/unavailable states and escaped titles, supports search/page/sort, create/duplicate/trash and copy ID shortcode. Legacy rows offer Old Builder and Convert/Review; v2 rows offer Edit and Restore Legacy where history exists. A CPT editor is opened through the table-ID resolver, not by changing public shortcode identity.

DefinitionEditor uses the native post editor and saved CPT entity. DefinitionRootInspector writes query, facets, pagination, outer style and extensions into the root attributes. CardCanvas exposes exactly one InnerBlocks subtree using native inserter, keyboard movers, toolbar, List View and undo. SamplePostPicker selects a real query result for editable-context display; it is session UI state, not stored query data. Core data resolution is read-only for sampled posts; context must not enable editing their real content.

RepeatedPreview is separate and read-only, default 6 results, up to 12. Debounce changes (proposed 300ms), abort/discard stale requests, and request one full PHP preview per definition snapshot. Use isolated iframe/srcdoc content with the shared registered styles/theme context and disabled navigation/scripts except reviewed needs, not arbitrary HTML/URLs. Do not copy card blocks per post into the document. Unsupported server-only addons still display their batch server preview; editable placeholders must disclose limited local preview instead of inventing fake post data.

Native block mapping: core/post-featured-image, post-title, post-content, post-excerpt, read-more, post-author-name or post-author as supported, post-date, post-terms, group, columns/column, spacer, separator. Post Meta is a small Free dynamic block accepting only an explicitly public display-field ID. When a core block cannot meet a required control on the approved minimum (e.g. excerpt semantics), use a narrowly scoped Free wrapper/variation and migration tests, not a wholesale duplicate block library. Addon blocks are opt-in through the card registry, independent of commercial ownership.

## Shared render sequence

Resolve table ID and public eligibility → choose legacy or blocks explicitly → load/validate active definition once → normalize query/state → WP_Query → parse one card tree once → render each post with explicit postId/postType plus gridmaster/gridId and gridmaster/instanceId context → collect block supports/assets → output scoped grid and navigation/result metadata.

Capture/restore source-post globals and any temporary plugin-owned context hooks in try/finally, including on exceptions and nested the_content/do_blocks calls. Never remove all filters or leave request-wide closures accumulating across grids. Limit recursion by grid-ID/post-ID stack and maximum depth; a post embedding its own grid must terminate safely. Prevent nested queries/grid embeds in a card document; content blocks that dynamically include them still need runtime guards. Core block IDs/anchors duplicated by repetition are normalized per instance/post/block path through reviewed markup handling; do not regex-rewrite arbitrary HTML indiscriminately. Test aria-controls/labels after ID changes.

Outer CSS is keyed by runtime root attribute/data ID; repeated same saved IDs receive distinct instance IDs. Keep legacy class contracts in the adapter and retain first-occurrence legacy ID where required; suffix later duplicate DOM IDs while retaining the shared class. New frontend controller targets only data-gm-v2 roots; old jQuery handlers must not consume v2 events/selectors accidentally. Pre-collect required block styles before page output and support late shortcode rendering; filter updates cannot invent unregistered asset URLs. Only used grids enqueue runtime/block/provider assets, only this CPT loads editor assets.

## Route and access contracts

Proposed namespace `gridmaster/v1`; numeric ID always registry ID. Internal CPT REST base `gridmaster-definitions` uses a restricted controller for native editor integration. Do not rely solely on show_in_rest/public flags for privacy. WordPress requires explicit [REST permission callbacks and argument validation](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/).

| Route / method | Access / behavior |
| --- | --- |
| GET /grids; POST /grids | manage_options; bounded list or create saved draft identity/document |
| GET /grids/{id}; PUT /grids/{id} | manage_options; complete editable aggregate; PUT requires expected hash/version and validated document/title |
| POST /grids/{id}/duplicate; /trash; /restore | manage_options + cookie REST nonce; duplicate new IDs, guarded soft deletion/restoration |
| POST /grids/{id}/preview | manage_options; unsaved validated aggregate, sample count ≤12, no persistent writes; no-store |
| POST /grids/{id}/migration/analyze; /candidate; /approve; /revert | manage_options; analyze read-only, others guarded writes; approval binds candidate/source hashes/version; private reports only |
| GET /grids/{id}/results | Public read only for an explicitly active renderable grid; accepts permitted facet state/page/instance only; no raw config or preview privileges |
| Internal CPT/revisions/autosaves | manage_options and parent binding check, same validation/concurrency; generic subscriber/editor access rejected |

Public response: `{gridId, instanceId, requestId, definitionHash, stateHash, html, pagination:{page,totalPages,hasMore}, facets:publicControls, assets:registeredHandles}`. Do not include definitions, original attributes, protected post data or arbitrary file paths. Payload validation is on server; safe error envelopes use codes missing_grid, unavailable_provider, invalid_state, conflict, invalid_definition, storage_unavailable. Anonymous missing/private drafts return a uniform 404. Public reads do not use nonces as authorization; expensive query controls are bounded regardless of nonce availability. Management cookie requests need both REST nonce and capability.

ID-only shortcode and embed render the same service. The old admin-ajax/public protocol remains for legacy grids only, with capability/nonce/argument/query-cost validation at its existing trust boundaries; preserving legitimate payload shapes does not preserve unsafe dispatch or unbounded input. it must not render v2 candidates by client-supplied template/renderer flags or raw args. Saved IDs with historical explicit overrides are deliberately sent to the old snapshot+override path; the migration review identifies these caveats, and new embeds do not generate overrides.

## Save/revision and error UI

Explicit Save of a new draft makes it private and publicly renderable through its table binding; explicit Update changes active v2 output. Autosave is recovery only. Candidate Save never publishes the candidate while registry mode is legacy. Native revision restoration restores the complete root configuration/card and enforces capability/schema/provider validation. Show conflicts with reload/compare, never last-write-wins silently. Retain dirty content on network failure; unknown/provider-missing blocks are read-only and not stripped. Migration approval requires separate Compare and Approve actions. Core edit locks assist UX; repository concurrency rules remain authoritative.
