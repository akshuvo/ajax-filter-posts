# 0004 — Native Gutenberg card composition with shared repeated rendering

- Status: Approved; maintainer authorization recorded 2026-11-04. Implementation remains subject to milestone gates and task-specific verification.
- Date: 2026-11-04. Owner: Free. Supersedes: none.

## Context and problem

Users edit one card, repeated for many query posts. The experimental `gutenberg-grid-cards` branch provides useful CPT/renderer boundaries but uses template/legacy references plus inline configuration, placeholder field editing and request-wide legacy helper filters. It does not establish safe context isolation, saved-ID authority or preview parity. A full merge would import unrelated Elementor/widget work and unverified assets.

## Decision

Use the native post block editor on the private definition CPT with a locked single `gridmaster/definition` root and single editable `gridmaster/card` child. Lock structure, not card children. Inspectors/PluginSidebar write root attributes in the same editor document; do not create another React editor or parallel options store. An ID-only `gridmaster/grid` embed block selects a saved grid on ordinary pages.

Prefer native contextual post blocks (featured image, title, content, excerpt, author, date, terms, read-more) and structural Group/Columns/Spacer/Separator. Add small Free dynamic blocks only for gaps such as validated public Post Meta, safe content behavior or excerpt controls unsupported at the approved minimum. Supply real per-post `postId`/`postType` and namespaced grid context; never read the private definition's post as a query result. Gutenberg documents this mechanism in [block context](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-context/). Local core/post-template and core/post-content source were inspected as references.

Free owns a WP_Query adapter and repeat renderer so shortcode, embed, REST and full preview share semantics. Parse the card tree once; create per-post WP_Block instances with explicit context, set/restore template-tag globals and clean only plugin-owned temporary callbacks in finally blocks. Handle do_blocks/the_content nested rendering context with bounded recursion guards. Core Query Loop is not a second saved query source. Do not build the product by shipping a separate inner-block copy for every sampled post.

One editable canvas receives the selected sample post context through the supported BlockContextProvider. A separate authoritative repeated preview requests PHP-rendered HTML for up to 12 posts from the draft definition, with shared block/instance styles in an isolated preview document. Use native controls and [block supports](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-supports/) for spacing/type/colors/borders where available; validated grid responsive/hover extensions fill real gaps. Native supports do not promise every style has a responsive UI.

Addon registration explicitly declares card compatibility, server registration, attribute schemas, post-context use, assets and fallback behavior. Disallow nested grid/query blocks and unsafe self-reference. Unknown provider data is retained but incompatible documents cannot be silently rewritten. Experimental/private Gutenberg APIs are not required contracts; M3 proves minimum-version support with public APIs before committing to deeper editor customization.

## Alternatives considered

Core Query Loop as the complete product: excellent native starting point, but saved table-ID mapping, reusable external facets, legacy migration and one request engine still need a service. Reuse its post blocks/context patterns without a second query authority. A fully custom canvas: higher maintenance and duplicates native editing, rejected. Experimental branch wholesale merge: imports unreviewed API/storage/security assumptions and unrelated integration work, rejected. Per-card client HTTP render calls: poor latency and context consistency, rejected.

## Consequences and compatibility

Core blocks reduce maintenance and retain normal editor workflows, while requiring theme/style and minimum-version testing. Shared server preview has strong markup parity but theme/plugin content filters can still differ; show the active theme and sample context. Native post-content may recurse or load arbitrary post blocks: use a bounded rendering stack, safe default fallback and explicit addon policy. Serialize block data using normal Gutenberg formats, register metadata on client/server, and test parse/serialize round trips. Retain old shortcode, builder and renderer as isolated compatibility paths; no existing branch is merged.

## Affected repositories and evidence

Free: builder/document/CPT, block metadata, renderer and preview. Pro: compatible optional blocks and inspector panels through [ADR 0005](0005-versioned-extension-contract.md). [Builder architecture](../gridmaster-2.0/builder.md), [source audit](../gridmaster-2.0/source-audit.md), [PRD](../prd/0002-gutenberg-grid-builder-v2.md). These are proposals; no new editor behavior was tested.
