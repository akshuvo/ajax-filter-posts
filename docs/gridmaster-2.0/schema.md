# Database proposal and field authority

Status: Proposed, 2026-11-04; no DDL executed. Governing [storage ADR 0003](../adr/0003-saved-grid-definition-storage.md). Prefixes always derive from `$wpdb->prefix`, including multisite blog context; never assume `wp_`.

## One authority per current field

| Field | Legacy mode | Blocks mode | Notes |
| --- | --- | --- | --- |
| Stable public grid ID | Existing registry row id | Same registry row id | Never reuse deleted IDs or substitute CPT IDs |
| Title | Registry title | Definition post_title | Legacy title is retained history after conversion; no ongoing title sync |
| Card layout and block styles | Legacy template/attributes | Definition post_content → one card subtree | Core block attributes are canonical; parsed caches are derived |
| Query/facets/pagination/grid style/extensions | Registry attributes decoded by legacy adapter | Definition root attributes | Never copy current values into table attributes, post meta or options |
| Lifecycle active/trash | Registry lifecycle_state | Registry lifecycle_state | Soft trash hides both legacy and v2 without erasing source |
| Public renderer choice | Registry render_mode | Registry render_mode | Only explicit coordinator operation changes it |
| Active definition / candidate | Nullable registry definition_post_id / candidate_post_id | Same bindings | Pointers, not configuration copies |
| Revision / draft | Legacy snapshot history | Native definition revisions/autosaves | Historical versions are not merged at render time |
| Migration state/version/approval | Registry + immutable history operation | Same | Version/hash binds approval to source and exact candidate |
| Runtime selected filters/page/instance/sample | Request/client per instance | Request/client per instance | Not saved as grid defaults by visitor interactions |
| Global plugin settings / schema version | Existing gridmaster_settings / gridmaster_db_version | Same, only appropriate global fields | Not per-grid config or premium license storage |

A definition's ordinary save contains query and card together in post_content; revision restoration is the same single aggregate. The parent CPT post ID is internal and must not be editable as an embed reference. No post meta copy of query/settings is proposed. Source bytes in history are intentional immutable recovery evidence, not another live configuration store.

## Existing table and proposed additive changes

Existing columns remain: `id mediumint NOT NULL AUTO_INCREMENT PRIMARY KEY`, `title text NOT NULL`, `attributes text NOT NULL`, `dated datetime NULL DEFAULT CURRENT_TIMESTAMP`. Preserve id values, signedness, original dated values and original attributes. New-install schema matches this preserved legacy shape plus the additions below; all new writes are validated. Widening mediumint is deferred to a separately backed-up capacity change; detect exhaustion explicitly instead of renumbering IDs.

| Added column | Proposed type/default | Purpose |
| --- | --- | --- |
| definition_post_id | bigint unsigned NULL | Active private definition for blocks mode |
| candidate_post_id | bigint unsigned NULL | Unapproved conversion candidate; never publicly rendered |
| lifecycle_state | varchar(16) NOT NULL DEFAULT 'active' | active/trash, including untouched legacy rows; no hard delete |
| render_mode | varchar(16) NOT NULL DEFAULT 'legacy' | legacy or blocks; strict application enum |
| migration_state | varchar(24) NOT NULL DEFAULT 'untouched' | untouched/candidate/approved/reverted/blocked |
| migration_version | smallint unsigned NOT NULL DEFAULT 0 | Converter schema version, separate from plugin release |
| record_version | bigint unsigned NOT NULL DEFAULT 1 | Binding/state CAS version; not a second content revision |
| source_hash | char(64) NULL | SHA-256 of length-prefixed exact legacy id/title/attributes/dated bytes at conversion |
| updated_at_gmt | datetime NULL | Coordinator metadata, never rewrites dated |
| last_error_code | varchar(64) NULL | Safe diagnostic code only; no raw payload/SQL/credentials |

Indexes: unique nullable definition_post_id; unique nullable candidate_post_id; composite (lifecycle_state,id), (render_mode,id), (migration_state,id). The service rejects active/candidate pointer equality and cross-grid reuse; nullable unique indexes alone do not enforce cross-column uniqueness. No foreign keys to core tables: use application referential validation and guarded trash/delete hooks. Lists use bounded pagination and stable id ordering; optional title search joins active definition titles and uses escaped prepared LIKE. No JSON SQL column or database-specific JSON query dependency is required.

`{$wpdb->prefix}gridmaster_grid_history` is a new append-only recovery/audit table:

| Column | Type / purpose |
| --- | --- |
| id | bigint unsigned auto_increment primary key |
| grid_id | mediumint NOT NULL; index (grid_id,id), matches existing ID domain |
| operation_id | char(36) NOT NULL unique; idempotency key |
| operation | varchar(24): snapshot/candidate/approve/revert/error |
| payload | longtext NOT NULL: versioned JSON recovery envelope; raw serialized legacy attribute/title/date bytes base64-encoded; before/after bindings and relevant definition bytes/hash |
| payload_hash | char(64) NOT NULL: verify decoded recovery envelope |
| actor_id | bigint unsigned NOT NULL; internal user attribution, never public API |
| created_at_gmt | datetime NOT NULL |

Protect history through administrator service access only; no public endpoint or automatic purge. Recovery snapshots persist even when native revisions are pruned. Snapshot storage is in the database, not a file in the web root; base64 preserves bytes and is not encryption. A full private filesystem database backup is still required before DDL/migration QA. Future retention/export is an explicit maintenance decision.

## Definition document contract

Conceptual block document (not PHP to execute):

```html
<!-- wp:gridmaster/definition {"schemaVersion":1,"query":{"postType":"post","perPage":12,"orderBy":"date","order":"DESC","terms":[],"authors":[],"search":""},"facets":[],"pagination":{"mode":"numbered"},"gridStyle":{"columns":{"base":1,"sm":2,"md":3}},"extensions":{}} -->
<!-- wp:gridmaster/card -->
<!-- wp:post-featured-image /-->
<!-- wp:post-title {"isLink":true,"level":2} /-->
<!-- wp:post-excerpt /-->
<!-- /wp:gridmaster/card -->
<!-- /wp:gridmaster/definition -->
```

A server schema defines every known root field, nested defaults, allowed ranges, extension namespaces and block tree shape. Unknown format versions cause read-only mode, not silent normalization or downgrade. Schema1 uses `gridStyle.columns` mobile-first keys base/sm/md/lg/xl at 0/768/992/1200/1600px; legacy xs/320 behavior must be assessed explicitly by converters. Grid gaps and block spacing use approved CSS unit/preset schemas, not arbitrary CSS strings. Block styles stay on their owning blocks; gridStyle owns outer layout only.

## Write, concurrency and failure protocol

GridRepository owns ID allocation and pointers; DefinitionRepository owns validated CPT content/title. New-grid creation allocates registry draft identity, creates a draft definition, then binds with CAS. Failure leaves a diagnosable inert draft/orphan for recovery, never a default public query. Duplicate creates new table/CPT IDs and resets migration provenance.

All explicit editor saves/revision restores and coordinator operations use an expected content hash and binding record_version. Serialize writes per grid. Proposed implementation uses a transaction with a locked registry row, validates the expected hash under that lock, writes through WordPress APIs, records durable history when required, commits, then invalidates caches. M2 must verify InnoDB support for every table involved and post/revision hook side effects. Do not issue automatic engine conversion; if atomicity cannot be guaranteed, block new writes/migration and leave legacy reads working until an approved alternative. No unsupported global DB transaction assumption.

Native-compatible CPT controller, autosave and revision endpoints must enforce the same lock/hash/capability rules; do not leave an alternate unrestricted core write route. Autosave writes a separate recovery revision, never flips the live binding. Explicit save of an active private definition changes its live output; a converted legacy candidate remains draft until explicit approval. Invalid content/unknown providers must not be partially sanitized into a successful changed save. If registered third-party attributes cannot round-trip safely, retain the document read-only and report incompatibility.

Promotion clears candidate_post_id when atomically moving that ID to definition_post_id; approval history retains the candidate provenance. Reversion may park the v2 definition pointer while render_mode is legacy, so the work is retained without a second active configuration. New-only grids have no original legacy equivalent and Restore Legacy is unavailable unless an explicit legacy snapshot exists.

After errors, authoritative binding chooses the last consistent source. An orphan CPT is not a public grid. Missing/trash pointer never means “query default posts.” Cache flush is after commit and recovery revalidates bindings on retry. Sanitized errors distinguish missing/corrupt/conflict/schema/provider/unavailable-storage failures without SQL disclosure.

## Upgrade and rollback gates

Discover actual table/column/index/engine state read-only first. Private backup plus verified restore rehearsal precedes any schema task on the existing site. Separate additive schema upgrade from row content conversion; no row conversion during activation or public render. Use dbDelta-compatible declarations and explicit postconditions/error inspection. Only advance gridmaster_db_version after verified successful schema creation/upgrade; partial failures are retryable and leave the old marker.

Old-builder write paths get method allowlists/capability checks and pass through a legacy repository adapter with snapshots/conflict detection. Block-mode grids must use explicit Restore Legacy before old-builder edits. Deactivation/uninstall does not drop schema or erase definitions/history in initial release. Runtime migration rollback flips the saved ID back to preserved legacy output; binary downgrade requires a private full-database restore/validated export procedure and accounting for new-only grids. These are different operations.
