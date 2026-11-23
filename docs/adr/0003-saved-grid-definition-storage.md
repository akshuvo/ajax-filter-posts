# 0003 — Stable table IDs with one private Gutenberg definition

- Status: Approved; maintainer authorization recorded 2026-11-04. Implementation remains subject to milestone gates and task-specific verification.
- Date: 2026-11-04. Owner: Free. Supersedes: none.

## Context and problem

[DB_Migration](../../admin/DB_Migration.php) creates `id mediumint`, `title text`, serialized `attributes text`, `dated datetime`. [Grids](../../admin/Grids.php) persists numeric IDs, reads/writes attributes and lists only 100 rows. The local table has this schema and no rows; this does not establish migration safety. Existing shortcodes cannot be renumbered to WordPress post IDs.

Separately storing query/facet/style JSON in the table and card content in a CPT would require coordinated writes and revision snapshots across both. A layout-only revision could otherwise restore incompatible settings. We need one authoritative aggregate for editable v2 product state.

## Decision

Retain `{$wpdb->prefix}gridmaster_grids` as stable identity registry and original legacy data store. Add renderer/migration/binding/version metadata only. Each new or converted v2 grid maps to a private `gm_grid_definition` CPT. Its `post_content` contains exactly one `gridmaster/definition` root: versioned query/facets/pagination/grid styles/extension values as attributes, with exactly one `gridmaster/card` InnerBlocks subtree. The CPT's `post_title` is the v2 title. Root attributes contain no table ID, CPT ID, runtime instance ID or sample-post ID. Resolve identity through the registry; cloning cannot copy an identity accidentally.

No v2 query/facet/style configuration is mirrored in table attributes, options or post meta. Original table title/attributes/dated are legacy state, frozen by conversion, not competing v2 state. Autosaves and WordPress revisions are historical versions of the same aggregate. The migration history table contains immutable recovery snapshots, never runtime configuration. Explicit phase/mode selects the source of truth; never heuristically merge old/new fields.

CPT flags: `public=false`, `publicly_queryable=false`, `exclude_from_search=true`, `rewrite=false`, `query_var=false`, `show_ui=true`, `show_in_menu=gridmaster`, `show_in_rest=true`, supports title/editor/revisions. A Free REST controller/capability mapper restricts definitions, revisions and autosaves to `manage_options`; a private CPT alone is not an access policy. New draft is non-renderable publicly; explicit Save sets private saved state. Anonymous results are rendered by an ID-resolving service, not by exposing the CPT. WordPress supports these CPT/editor/revision settings through [register_post_type](https://developer.wordpress.org/reference/functions/register_post_type/).

Normal saves update one CPT aggregate. Candidate promotion/reversion atomically changes a guarded table binding after its immutable recovery record is durable. Repository writes must serialize competing edits and reject stale hashes; all write routes, including core-compatible editor/revision paths, delegate to that service. M2 must prove the transaction/lock approach on the actual table engines; do not assume WordPress APIs provide a cross-table transaction. Cache invalidation is after successful commit; interruption leaves old binding live or an unreferenced candidate, never half a query/layout.

## Alternatives considered

1. Extend attributes/config JSON for query, use CPT for card only: familiar table access but split revision/rollback and dual-save coordination. Rejected as the initial authority model; a derived query index may be added later with no write authority.
2. CPT-only with post IDs as grid IDs: simpler WordPress entity, violates stable existing numeric IDs and risks collisions. Rejected.
3. Entire definition in custom table: atomic aggregate possible but recreates editor save/revision/autosave machinery. Rejected.
4. Chosen table identity + complete CPT definition: preserves IDs and native editing/revisions; requires a clear binding service and block-document validation. No duplicate current configuration store.

## Consequences and trade-offs

Query configuration travels with block content and is revisioned consistently, but raw block-document editing needs strict server validation. Table listing joins private CPT titles for v2 rows. Unknown namespaced extension values must survive a Free-only read/save; editing incompatible definitions is read-only until providers return or an explicit downgrade copy is made. Do not add site-wide meta just for inspector convenience. A draft candidate is intentionally separate from the live legacy row until approval.

Original migration snapshots cannot depend on WordPress revision retention, which sites may limit/disable; see [revision policy API](https://developer.wordpress.org/reference/functions/wp_revisions_to_keep/). No automatic destructive cleanup or schema rollback on deactivation. Transaction capability and the proposed WordPress 6.5 builder floor are implementation gates.

## Affected repositories and compatibility

Free owns repositories/schema/controller/binding and both rendering paths. Pro registers fields/providers without a second store. Existing `gm_get_grid()` stays a legacy-shaped compatibility API; new services are explicit. Non-ID legacy embeds remain supported. Schema addition does not imply content conversion; old plugin binaries do not understand new CPT definitions, so binary downgrade is a separate verified restore procedure.

## Related PRDs and evidence

[PRD 0002](../prd/0002-gutenberg-grid-builder-v2.md), [schema and authority map](../gridmaster-2.0/schema.md), [migration](../gridmaster-2.0/migration.md), [source audit](../gridmaster-2.0/source-audit.md), [Gutenberg ADR](0004-native-gutenberg-card-builder.md), [contract ADR](0005-versioned-extension-contract.md). No schema or implementation change made by this record.
