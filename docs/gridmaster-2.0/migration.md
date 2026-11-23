# Reversible legacy migration strategy

Status: Proposed; conversion is mandatory functionality but per-grid adoption is explicit. No migration executed. [PRD R10–R15](../prd/0002-gutenberg-grid-builder-v2.md), [schema](schema.md), [Pro converter design](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/gridmaster-2.0/migration.md).

## Safety invariants and backups

Never renumber table IDs, rewrite original serialized bytes or delete legacy/premium keys as part of conversion. Listing is available before conversion. The old builder and renderer remain in the initial release. A converter can suggest a candidate; it cannot select the public renderer. Schema upgrades, candidate creation, approval and reversion are separate operations with explicit outcomes.

Before any schema/migration QA on the existing Local site: stop concurrent mutating work, record exact plugin commits/configuration and DB/table engine state; take a timestamped full database dump plus relevant file/config backup to a private mode-0700 location outside all WordPress document roots; record hashes/restore instructions there, never in orchestration; restore into a disposable test database and verify schema/counts/IDs/representative shortcode output. Do not overwrite the working database to “test” restoration. Only proceed with the backed-up scoped task. An immutable per-grid recovery snapshot supplements this backup, never replaces it.

### M2-T02 guarded backup and scratch-restore rehearsal runbook

The repository provides `scripts/gm2/backup-restore-rehearsal.php` and its pure helper `scripts/gm2/ddl-normalizer.php` for the pre-schema private backup and disposable restore gate (`GM2-M2-T02` and recovery `GM2-M2-T02-R1`). Antigravity authors, tests, and lints the script without executing it against Local; Codex executes the rehearsal after independent code review:

```sh
php -l scripts/gm2/ddl-normalizer.php
php -l scripts/gm2/backup-restore-rehearsal.php
php tests/gm2-backup/run.php
php tests/gm2-backup/run.php --self-test-failure

php -d 'mysqli.default_socket=/Users/akshuvo/Library/Application Support/Local/run/T9DCTDfna/mysql/mysqld.sock' \
  /opt/homebrew/bin/wp --skip-plugins --skip-themes eval-file scripts/gm2/backup-restore-rehearsal.php \
  output_dir="/Users/akshuvo/Library/Application Support/GridMaster/private-backups" \
  mysqldump_bin="/absolute/path/to/mysqldump" \
  mysql_bin="/absolute/path/to/mysql"
```

- **Fail-closed guards**:
  - Invokes `/opt/homebrew/bin/wp` with the explicit Local PHP socket option (`-d 'mysqli.default_socket=/Users/akshuvo/Library/Application Support/Local/run/T9DCTDfna/mysql/mysqld.sock'`) and `--skip-plugins --skip-themes` before `eval-file` so WP-CLI does not load active plugins or themes during bootstrap (preventing unrelated boot-time code from running against the source database) without activating or deactivating any plugins or themes.
  - Requires `PHP_SAPI === 'cli'`, `WP_CLI === true`, initialized `$wpdb`, defined `DB_NAME`/`DB_USER`/`DB_PASSWORD`/`DB_HOST`, and the `mysqli` extension.
  - Requires explicit `output_dir`, `mysqldump_bin`, and `mysql_bin` inputs (via positional `key=value` arguments or `GM2_BACKUP_DIR` / `GM2_MYSQLDUMP_BIN` / `GM2_MYSQL_BIN` environment variables). Relative paths, traversal segments (`..`), symlinks, or paths resolving inside or containing `ABSPATH`, `WP_CONTENT_DIR`, or the plugin directories are rejected before directory creation.
  - Inspects every source base table's storage engine via `INFORMATION_SCHEMA.TABLES` before creating the private SQL dump or the scratch database, and fails closed unless all base tables are transactional `InnoDB` (guaranteeing that `mysqldump --single-transaction` captures a transactionally consistent snapshot with no source DB lock fallback).
  - Enforces mode `0700` on the private backup directory and mode `0600` on generated `.sql`, `.sql.sha256`, `.meta.json`, and temporary client configuration files; verifies after generating the random backup stem and before `mysqldump` writes that none of the `.sql`, `.sql.sha256`, or `.meta.json` artifact paths already exist, and writes the `.sql.sha256` and `.meta.json` sidecars via exclusive creation (`fopen` mode `xb`) so existing private backup artifacts can never be overwritten.
  - Executes `mysqldump` and `mysql` strictly via `proc_open()` with an argument vector and `bypass_shell => true` (never through a shell), passing connection credentials through a temporary mode-`0600` `--defaults-extra-file` unlinked in `finally` so credentials never appear in `argv` or output.
  - Scans the generated dump file before restore to confirm `CREATE TABLE` presence and reject any `USE`, `CREATE DATABASE`, or `DROP DATABASE` statement that could redirect restoration to the source database.
  - Generates a CSPRNG scratch schema name matching `/^gm2_restore_[0-9a-f]{16,32}$/`, confirms via `INFORMATION_SCHEMA.SCHEMATA` that it does not exist prior to `CREATE DATABASE`, restores only into that scratch schema, and restricts `finally` cleanup (`DROP DATABASE IF EXISTS`) strictly to the schema name created in the same invocation.
- **Verification, schema normalization, and failure-path reporting**:
  - Compares source pre-rehearsal and restored scratch snapshots across base table lists, per-table storage engines and engine counts (`InnoDB` transactional verification), normalized `SHOW CREATE TABLE` definitions, per-table and aggregate row counts, `active_plugins` option hashes, and `{$wpdb->prefix}gridmaster_grids` schema, ordered IDs, and raw serialized `attributes` hashes.
  - Uses `scripts/gm2/ddl-normalizer.php` (`gm2_normalize_create_table_ddl()`) to normalize proven volatile DDL differences without masking real structural schema drift:
    - Strips volatile table-level `AUTO_INCREMENT=<n>` values.
    - Normalizes MySQL < 8.0.19 deprecated integer display widths (e.g. `int(11)`, `mediumint(9)`, `bigint(20)`, `smallint(6)`) that MySQL 8.0.19+ canonicalizes away on `CREATE TABLE`, while strictly preserving boolean semantic `tinyint(1)` and any column with `zerofill`. Note that independent rehearsal on Local showed 47/54 table mismatches remained unresolved by this rule alone; privacy-safe aggregate diagnostics are emitted to classify differences without claiming an unverified root cause.
    - Normalizes legacy unquoted `utf8` character set tokens to canonical `utf8mb3`.
    - Strips explicit self-referential scratch schema qualifiers from foreign key `REFERENCES` clauses created during restore.
    - Preserves and fails on any meaningful differences in column names, types, signedness, nullability, defaults, generated column expressions, index columns/types/ordering, table storage engine, table charset/collation, or row format.
  - Evaluates all comparison checks completely without early-return short-circuiting. When a comparison fails, the mismatch is classified into privacy-safe aggregate categories (`clause_breakdown`, `raw_category_counts`, `residual_category_counts`, and isolated `grids_table_diagnostics` without emitting DDL, table names, column names, rows, content hashes, or credentials), execution proceeds to ensure scratch database cleanup (`DROP DATABASE IF EXISTS`), and source post-run invariant verification is evaluated against the live source database.
  - Reports all verification checks (`comparison_checks`, `cleanup_check`, and `source_invariant_checks`) with explicit `status` (`passed`, `failed`, or `not_run`) and sanitized reason strings.
  - If any comparison check, scratch cleanup, or source post-run invariant check fails, the rehearsal utility exits with a non-zero code (`1`).
  - Drops the scratch database, confirms its removal in `INFORMATION_SCHEMA.SCHEMATA`, and re-snapshots the source database to verify that source DB identity, table/schema definitions, storage engines, aggregate and per-table row counts, `active_plugins` option hash, and `gridmaster_grids` row hash remained unchanged.
  - Retains the full SQL dump (`gm2-pre-schema-backup-<timestamp>-<hex>.sql`), its `.sha256` digest file, and `.meta.json` inside `/Users/akshuvo/Library/Application Support/GridMaster/private-backups` for the M2-T03 schema gate while emitting only sanitized metadata (counts, engine metadata, modes, and boolean verification flags—never credentials, SQL contents, table rows, or user-content hashes) on `STDOUT`.
  - Because the current Local `{$wpdb->prefix}gridmaster_grids` table has `0` saved rows, no live source saved-grid render can be compared (`source_saved_grid_render_comparison = skipped_zero_saved_grids_use_gm2_m2_t01_synthetic_harness`); pair the rehearsal with the `GM2-M2-T01` disposable synthetic saved-ID/legacy-render harness (`php tests/gm2-storage/run.php`).

## State machine and idempotency

`untouched / legacy` → analyze (no write) → snapshot + draft candidate / legacy → compare → explicit approve exact hashes / blocks. Any warning may keep the grid at candidate/legacy. Failure → blocked/legacy with safe error; retry uses the same operation ID and existing validated snapshot/candidate. From blocks → explicit Restore Legacy → reverted/legacy while retaining the v2 definition/candidate and history. Legacy edits after revert create a new source hash and invalidate prior approval.

1. Inventory every row with keyset batches (default 25), including corrupt/unrecognized attributes; original row remains listable even if conversion is blocked. Count totals outside current 100-row limit. Inventory gm_grid_template/gm_grid_style records separately; a CPT ID is not a saved table ID. Imported experimental definitions receive new table IDs unless an explicit conflict-free mapping is proven; no identity inference from equal integers.
2. Decode only a validated array/scalar legacy shape using unserialize with classes disabled and strict recursive type/depth/size checks. Retain original bytes even if decoding fails. Do not execute serialized objects or use blind maybe_unserialize for the new adapter. Malformed source is blocked, not replaced with default query.
3. Compute effective legacy settings using the actual path/default precedence and detected provider versions. Explicit stored fields differ from absent defaults; inline shortcode overrides cannot be discovered by reading the row alone. Show a scope warning and optionally provide a read-only shortcode-use audit, never rewrite posts automatically.
4. Write durable exact-byte recovery history before candidate/coordinator mutation. Include title, attributes, dated, ID, source schema/provider versions, source hash and previous bindings; verify read-back hash. Each operation has a unique idempotency key; repeated runs reuse rather than duplicate work.
5. Convert into a separate draft gm_grid_definition: root query/facets/pagination/style, one card tree and namespaced extension payloads. Converter returns mapped/approximated/unmapped fields, required providers, confidence and sample comparison evidence. Preserve unknown legacy keys in original snapshot, never public JSON. Unknown new extension keys may only be retained through validated opaque storage/read-only policy.
6. Review side-by-side old and candidate markup/screenshots using the same fixed sample posts and filters/breakpoints. Confidence “exact” still requires explicit approval. Changed content truncation/date formats/image/link behavior and theme overrides are called out, not hidden.
7. Approval requires manage_options, REST nonce for cookie requests, expected record_version, exact source hash and candidate hash, compatible providers, successful preview/validation and durable snapshot. Save candidate as private and flip binding/mode only after postconditions. If interrupted before flip, legacy stays live. After flip, any read sees the complete private definition; rollback restores the prior binding through recorded history.
8. Restore Legacy uses immutable original snapshot as selected source, guarded against concurrent edits. Preserve v2 work and history; restore title/attributes/dated only if an explicit source restoration is needed, with exact before/after evidence. Migration rollback never deletes CPTs, tables or source data. If a legacy edit occurred since snapshot, refuse blind overwrite and request version choice; do not merge silently.

## Mapping policy

| Legacy feature | Candidate mapping | Acceptance rule |
| --- | --- | --- |
| ID/title/date | Same table ID; candidate CPT title; original date retained | Never assign CPT ID as shortcode ID |
| post_type/per-page/order/terms/category aliases | Canonical query with explicit legacy effective defaults | Initial/AJAX parity and no scope broadening |
| show_filter/filter_style/All/hide_empty | Facet definitions and corresponding registered presentation preset | Same base term universe; no -1 SQL semantics |
| pagination_type/infinite_scroll/paginate | Explicit five-mode enum with legacy mapping report | Do not infer “none” from ambiguous empty historical field |
| default/style-2/style-3 | Source-traced Group/image/title/excerpt/etc block patterns and safe style presets | Visual/content comparison; exact CSS/theme equivalence is not presumed |
| responsive arrays/JSON, image/link/title/excerpt options | Outer gridStyle versus block-owned settings by authority | Preserve breakpoints and explicit values; unsupported units/options flagged |
| custom template hooks/registered addon style | Converter provider if supported; otherwise keep legacy | No generic scrape-to-block conversion claim |
| premium styles/sliders/metadata/auto archive | Pro-owned adapters and candidates | Compatible Pro + approved output required |
| inline-only shortcode | Explicit administrator Import as Saved Grid allocates new ID | No automatic writes on visitor rendering; old inline output remains |

A read-only `gridmaster/legacy-card` bridge may be used as an intermediate candidate for an unconvertible template. It resolves a trusted stored snapshot/style through the legacy adapter, never a client path or arbitrary shortcode. It does not count as a fully editable Gutenberg conversion and must be labeled “legacy card”; approval cannot hide that limitation. Until the administrator chooses it or a fully converted candidate, retain the whole legacy renderer.

## Missing Pro, version mismatches and concurrency

No Pro means no premium converter execution and no removal of premium source fields. Compatible old premium grids remain on their existing legacy path where possible; missing template/provider returns a safe unavailable state, never broadens a query or silently picks another layout. New candidate approval is blocked if required features are absent. Re-enabling Pro recovers retained data. Row locks/version checks prevent two tabs/workers creating conflicting candidates or approving a stale version.

## Recovery, batches and completion

Batches are resumable and bounded; checkpoint by last processed stable ID and immutable operation IDs, not “all done” flags. A failed row does not stop listing or erase success on other rows; report row status privately. No automatic all-site promotion. Dry-run analysis must be available before writes and report exact targeted counts/operations. Sanitized orchestration reports record counts/status only, never original attribute payloads.

Acceptance requires byte/hash equality before/after and after revert; stable IDs/shortcodes; blocked malformed/unknown layouts; retained premium attributes; interruption injection after snapshot/CPT creation/before and after binding flip; concurrency and retry idempotency; revisions disabled; mixed legacy/v2 output; experimental ID collision and >100-row listing. Actual sample fixtures and successful full restore evidence are required; the local zero-row table proves none of these. Binary rollback to pre-v2 plugins is a separate staged restore plan because new-only definitions have no historical equivalent; do not promise that merely flipping an option makes an old binary understand them.
