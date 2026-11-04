# GridMaster 2.0 test strategy and acceptance gates

Status: M2-T01 disposable CLI storage characterization harness (`tests/gm2-storage/run.php`), M2-T02 guarded backup/restore rehearsal utility (`scripts/gm2/backup-restore-rehearsal.php`), M2-T02 pure DDL normalizer (`scripts/gm2/ddl-normalizer.php`), and M2-T02-R1 deterministic backup/normalizer test runner (`tests/gm2-backup/run.php`) implemented; broader WordPress/MySQL integration, JS, and browser suites remain planned checks, not passing test evidence. Existing [Free tests](../testing.md) and [Pro strategy](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/gridmaster-2.0/testing.md) remain applicable. Codex independently executes/verifies Antigravity-authored tests and browser scenarios; task success claims do not establish acceptance.

## M2-T01 storage characterization runner and limits

Run the dependency-free PHP CLI characterization harness from the `ajax-filter-posts` repository root:

```sh
php -l tests/gm2-storage/run.php
php -l tests/gm2-storage/fixtures/legacy-grids.php
php tests/gm2-storage/run.php
php tests/gm2-storage/run.php --self-test-failure
```

- **Expected outcomes**: `php -l` exits `0` on both files. Normal execution (`php tests/gm2-storage/run.php`) runs 4 isolated suites (`83` assertions), exits `0`, and prints concise pass counts. Deliberate failure verification (`php tests/gm2-storage/run.php --self-test-failure`) triggers a sentinel mismatch, reports the caught failure on `STDERR`, and exits `1`.
- **Real production code vs test-only doubles**: The runner loads the actual `inc/functions.php` (`gm_get_grid()`), `admin/Grids.php` (`GridMaster\Grids`), and `inc/Shortcode.php` (`GridMaster\Shortcode::render_shortcode()`) against deterministic legacy rows (`id=42` and `id=108`) and absent IDs (`0`, `-5`, `9999`) in `tests/gm2-storage/fixtures/legacy-grids.php`. It uses explicitly labeled test-only doubles (`GM2_Test_Disposable_Wpdb`, `GM2_Test_WP_Env`, and a zero-post `WP_Query` stub) in memory and never connects to Local WordPress or MySQL.
- **Characterized baseline behaviors and known gaps**:
  - Numeric IDs (`int` and numeric `string`) reload expected `id`, `title`, `dated`, and unserialized `attributes` arrays, round-tripping through `serialize()` to the exact fixture byte streams (`1878` bytes for ID `42`; `1916` bytes for ID `108`, including stored Pro keys `pro-style-4`, `pro-filter-1`, `post_type_selection=auto`, `multiple_select=yes`, and `enable_slider=yes`) without mutating raw table bytes.
  - Missing or non-positive IDs (`9999`, `0` with `$defaults = false`, `-5`) return `null`; `gm_get_grid( 0, true )` returns an in-memory `stdClass` with `GridMaster\Grids::$defaults` without issuing SQL.
  - `Shortcode::render_shortcode()` resolves saved IDs to deterministic `gm-{id}` wrapper IDs and applies inline attribute overrides over stored values, while missing IDs fall back to generated `gm-*` wrapper IDs and `Shortcode` default attributes (`posts_per_page = 9`, `grid_style = 'default'`).
  - **Known baseline gap 1**: `Shortcode::render_shortcode()` filters merged attributes through `shortcode_atts( $default_atts, $atts, 'gridmaster' )`, dropping keys present in `GridMaster\Grids::$defaults` that are absent from `Shortcode`'s `$default_atts` (such as `heading_font_size`) unless retained by a filter.
  - **Known baseline gap 2**: `GridMaster\Grids::save()` with a non-existent positive `id` calls `$wpdb->update()` and returns that `id` even when `0` rows matched.
  - **Known baseline gap 3**: `GridMaster\Grids::list( $args )` ignores `$args` and enforces a hard `ORDER BY id DESC LIMIT 100` cap.
- **Untested WordPress/MySQL behavior**: This CLI harness does not claim full WordPress integration coverage. It does not exercise a real MySQL/InnoDB connection, `dbDelta` DDL (`admin/DB_Migration.php`), SQL engine/collation/locking behavior, real `WP_Query` post/term/meta execution, template file loading, `admin/Ajax.php` nonce/capability dispatch, or browser/DOM rendering.

## M2-T02 private backup and scratch-restore rehearsal utility

Validate syntax statically during implementation and run the rehearsal under WP-CLI only during Codex review/verification:

```sh
php -l scripts/gm2/ddl-normalizer.php
php -l scripts/gm2/backup-restore-rehearsal.php

php -d 'mysqli.default_socket=/Users/akshuvo/Library/Application Support/Local/run/T9DCTDfna/mysql/mysqld.sock' \
  /opt/homebrew/bin/wp --skip-plugins --skip-themes eval-file scripts/gm2/backup-restore-rehearsal.php \
  output_dir="/Users/akshuvo/Library/Application Support/GridMaster/private-backups" \
  mysqldump_bin="/absolute/path/to/mysqldump" \
  mysql_bin="/absolute/path/to/mysql"
```

- **Guard and execution contract**:
  - Uses `/opt/homebrew/bin/wp` with the explicit Local PHP socket option (`-d 'mysqli.default_socket=/Users/akshuvo/Library/Application Support/Local/run/T9DCTDfna/mysql/mysqld.sock'`) and `--skip-plugins --skip-themes` before `eval-file` so active plugins and themes are not loaded during WP-CLI bootstrap and active plugin/theme state is never modified.
  - Fails closed (exit `1`) unless `PHP_SAPI === 'cli'`, `WP_CLI === true`, required DB constants (`DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST`) and `mysqli` exist, `output_dir` resolves outside `ABSPATH`/`WP_CONTENT_DIR`/plugin directories with mode `0700`, and `mysqldump_bin`/`mysql_bin` are explicitly provided executable binaries.
  - Inspects every source base table's storage engine via `INFORMATION_SCHEMA.TABLES` before creating the SQL dump or scratch database, and fails closed unless all base tables use transactional `InnoDB` (ensuring `mysqldump --single-transaction` consistency with no source DB lock fallback).
  - Invokes client binaries via `proc_open()` with an argument vector (`bypass_shell => true`) and a temporary mode-`0600` `--defaults-extra-file` unlinked in `finally` (never exposing credentials in `argv` or output).
  - Verifies after generating the random backup stem and before `mysqldump` runs that none of the `.sql`, `.sql.sha256`, or `.meta.json` artifact paths already exist, writes a full source database dump (`.sql`), exclusively created (`fopen` mode `xb`) SHA-256 checksum (`.sql.sha256`), and exclusively created (`fopen` mode `xb`) private rehearsal metadata (`.meta.json`) with mode `0600` in `/Users/akshuvo/Library/Application Support/GridMaster/private-backups` without overwriting any existing private backup artifact, scans the dump to reject any `USE`/`CREATE DATABASE`/`DROP DATABASE` statements, confirms a randomly generated `gm2_restore_<hex>` scratch database is absent before `CREATE DATABASE`, restores into that scratch database only, verifies exact parity of table lists, storage engines (`InnoDB` across all base tables), normalized `SHOW CREATE TABLE` schemas, per-table and aggregate row counts, `active_plugins` option hash, and `{$wpdb->prefix}gridmaster_grids` IDs and raw serialized `attributes` hashes, drops only the scratch database created in that invocation, and verifies source DB invariants (including unchanged storage engines) before/after.
  - **Zero-saved-grid source limitation**: The current Local `{$wpdb->prefix}gridmaster_grids` table contains `0` rows, so live source saved-grid rendering cannot be compared; rerun the `GM2-M2-T01` synthetic saved-ID/legacy-render harness (`php tests/gm2-storage/run.php`) alongside the rehearsal to cover representative saved-ID reload and shortcode rendering.
  - **Sanitized reporting**: `STDOUT` emits only sanitized JSON status/counts/engine metadata/boolean flags. Never copy credentials, SQL dumps, private path contents, table rows, or hashes of user content into orchestration or Git.

## M2-T02-R1 backup and schema normalizer deterministic test suite

The repository provides a dependency-free PHP CLI test runner `tests/gm2-backup/run.php` testing the exact production normalizer (`scripts/gm2/ddl-normalizer.php`) and comparison evaluation logic:

```sh
php -l scripts/gm2/ddl-normalizer.php
php -l tests/gm2-backup/run.php
php tests/gm2-backup/run.php
php tests/gm2-backup/run.php --self-test-failure
```

- **Expected outcomes**: Both files pass `php -l` syntax validation. Normal execution (`php tests/gm2-backup/run.php`) executes 3 suites (108 assertions), exits `0`, and prints detailed pass counts. Deliberate failure verification (`php tests/gm2-backup/run.php --self-test-failure`) triggers a known mismatch assertion, prints the failure to `STDERR`, and exits with code `1`.
- **Suite 1: Volatile DDL difference normalization and classification**:
  - MySQL < 8.0.19 integer display widths (`int(11)`, `mediumint(9)`, `bigint(20)`, `smallint(6)`) compare equal to modern canonicalized DDL (`int`, `mediumint`, `bigint`, `smallint`) in test fixtures; does not claim this was the cause of live rehearsal mismatches.
  - Strict preservation of `tinyint(1)` (boolean semantics) and any column with `zerofill` (`int(5) zerofill` remains unchanged).
  - Volatile table-level `AUTO_INCREMENT=<n>` values compare equal regardless of counter position or value.
  - Legacy unquoted `utf8` character set tokens compare equal to canonical `utf8mb3`.
  - Restored self-schema foreign key qualifiers (`REFERENCES \`gm2_restore_...\`.\`wp_posts\`` vs `REFERENCES \`wp_posts\``) compare equal.
  - Privacy-safe aggregate diagnostic evaluation (`gm2_diagnose_schema_mismatches` and `gm2_classify_create_table_mismatch`) verifying clause classifications and category detection without emitting DDL, table names, or column identifiers.
  - Exact whitespace, newline, and indentation normalization.
- **Suite 2: Meaningful schema difference detection (24 mutation cases)**:
  - Fails when column names differ, are added, or are removed.
  - Fails when column types differ (e.g. `varchar(191)` vs `varchar(255)`, `int` vs `bigint`, `mediumint` vs `int`).
  - Fails when signedness differs (`int unsigned` vs `int`).
  - Fails when nullability differs (`NOT NULL` vs `DEFAULT NULL`).
  - Fails when column default values differ (`DEFAULT '0'` vs `DEFAULT '1'`).
  - Fails when generated column expressions differ (`VIRTUAL` vs `STORED`).
  - Fails when index columns differ (e.g. index on `(post_id)` vs `(post_id, meta_key)`).
  - Fails when index types differ (`KEY` vs `UNIQUE KEY`).
  - Fails when composite index column order differs (`(a, b)` vs `(b, a)`).
  - Fails when table storage engine differs (`InnoDB` vs `MyISAM`).
  - Fails when table charset or collation differs (`utf8mb4` vs `latin1`, `utf8mb4_unicode_ci` vs `utf8mb4_general_ci`).
  - Fails when row format differs (`ROW_FORMAT=DYNAMIC` vs `ROW_FORMAT=COMPACT`).
- **Suite 3: Comparison evaluation and invariant reporting logic**:
  - Non-short-circuiting evaluation: all comparison checks are collected and reported as `passed` or `failed`.
  - Post-run invariant reporting: checks are reported as `passed`, `failed`, or `not_run` with clear sanitized reasons.
  - Guarantees that failure in a restore comparison does not prevent cleanup and evaluation of source post-run invariants.

## Harness and fixtures

M2 first establishes a pinned meaningful PHP/unit/integration and JS/build test runner; current npm test is a failing placeholder and release Python tests cover packaging only. Use WordPress integration tests against disposable databases, deterministic JS state/component tests, and browser end-to-end tests exercising enqueued built assets. New production code remains PHP 7.4 compatible under the proposal. Pin development Node/@wordpress/scripts/package lock only after actual compatibility verification; the installed Node 25.8.1 is an observation, not a support policy.

Proposed support runs: WP 6.5/PHP 7.4 for new-builder minimum, installed WP 7.1.4 with supported PHP 8.x, site PHP 8.5.12 and CLI PHP 8.4.11 distinguished, plus legacy WP 5.8/PHP 7.4 fallback verification. Exact supported combinations require maintainer review and real execution. Test block and classic themes, Twenty Twenty-Five on Local, child-theme legacy override, desktop/mobile and RTL.

Fixtures: >130 legacy grids and fresh v2 drafts; all Free layouts, provider-supplied templates, premium layouts in Pro; malformed/scalar/object serialized attributes; conflicting/missing experimental CPT IDs; old defaults versus explicit empty/false values. Posts: ≥35 published varied content with images/no image, title/no title, author/date/terms/meta diversity, public CPT/custom taxonomy, draft/private/password-protected posts, deleted posts/terms/authors and no matches. Include duplicate same-ID embeds, mixed Free/premium and old/new renderers, a post embedding its own grid, and malicious CSS/tag/meta/query inputs. Never use customer keys or copy a production dump into a public path.

## Required suites and independent acceptance

| Suite | Required assertions | Milestones |
| --- | --- | --- |
| Storage | Upgrade fresh/old/partial schema; version advances only after verified success; existing ID/raw bytes unchanged; CRUD/list >100; missing/failed updates explicit; duplicate new ID; CAS conflict; atomic interruption recovery | M2, M8 |
| Definition | One root/card; bounds/schema/version; parse/serialize/revision aggregate; no duplicate config; registered/unknown/provider-missing fields; draft/private/autosave behavior; all write routes guarded | M2–M4 |
| Blocks/context | Every required component; different real posts in repetitions; correct native post context and restored globals; nested content/recursion; missing data; unique anchors/ARIA references | M4, M5 |
| Query | Base + visitor constraints, published/public/password policy, deterministic ties, taxonomy CPT validation, author/search/order, rejected raw WP_Query fields, trusted extension revalidation | M5 |
| Facets/pages | All five modes with active filters; All preserves base; reset page1; rapid/slow/out-of-order/aborted/failed/retried requests; two same IDs; no duplicates; exhaustion; no-JS fallback; back/forward | M6 |
| Pro contracts | Old/new pairs, API absence/version mismatch, load order, provider registration conflicts, missing Pro preservation/fail-closed query, premium rendering/lifecycle and mocked licensing | M7 + paired gates |
| Migration | Exact snapshots, no automatic conversion, candidate comparison, stale approval rejection, idempotent batches, errors/interruption/concurrency, original-ID output and reversible restore with revisions disabled | M8 |
| Security | Admin/editor/subscriber/logged-out × routes; nonce alone insufficient; private CPT/revisions/autosaves/history inaccessible; malicious serialized objects blocked; SQL/tag/style/path/meta validation; bounded anonymous requests | Every changed boundary + M9 |
| UI/accessibility | Native keyboard insertion/moving; save/conflict/dirty/errors; responsive/RTL/contrast/labels/live region/focus/reduced motion; manual infinite fallback; screen reader smoke + automated scan | M3–M9 |
| Assets/packaging | No unrelated admin/front assets; no duplicate React; built files match source; addon lazy assets available after filtering; legacy/pro bundles coexist; release runtime inventory includes new assets only through reviewed tooling changes | M3, M5, M9 |

For storage and migration tasks, backup/restore rehearsal in [migration](migration.md) is a hard prerequisite before touching the existing Local database. Pure analysis needs no database dump. Mutating tests run on disposable fixtures first. Local Free-only and Free+Pro tests are scheduled after backups/fixtures and restore the original activation state; no plugin activation was changed during planning.

## Browser and WP-CLI evidence

Use http://grid-master.local/wp-admin/ for actual management/editor/preview and frontend embeds after implementation. Native Chrome automation is available; connected-browser host is not. WP-CLI works with explicit Local socket. A task report records exact command/environment, plugin commits, fixture, result/exit code, relevant screenshots/console/network evidence and untested cases. Put private screenshots/dumps/raw logs outside web root; only sanitized summaries in orchestration. A coming-soon notice exists, so anonymous page delivery and public REST results need separate checks; never change visibility incidentally.

The planning phase only opened the old builder without saving and read table schema/counts via WP-CLI with plugins/themes skipped. It did not test Free alone, mutate fixtures, run a migration or exercise any proposed UI. Source security observations are not exploit validation.

## Provisional performance budgets

Measure on a documented local/staging fixture of 10,000 posts, 200 terms, 12 cards (six simple native fields), three independent grids; 30 measured repetitions after five warmups, cold and warm separated. Proposed public-results warm p95 ≤400ms server time, cold ≤1000ms; repeated six-post preview warm p95 ≤800ms; SQL count must not grow linearly per card for definition/meta/term reads. Compare 12 versus 24 cards, permitting the necessary result query/pagination differences but no N additional definition or metadata round trips. Plugin core frontend JS budget ≤40KiB gzip, excluding WordPress/theme/shared assets and separately measured optional Pro carousel. No editor bundle on frontend or unrelated admin screens. Record baseline hardware/runtime/cache and SQL/time evidence; these are proposed release targets, not measured facts. If targets fail, optimize or record an explicit reviewed requirement change rather than calling the task green.

Range/count providers must have bounded query cost, no query per term, capped term lists and slow-request behavior tested. Load tests include simultaneous search/range requests and timeout recovery. Persistent HTML caching is deferred until isolation/invalidation tests exist. Stable ordering alone does not guarantee no drift when content changes mid-pagination; explicitly test reset/version refresh.

## Exit gate and limitations

Each task needs reviewed diff, appropriate independent tests and applicable browser verification before Codex accepts it. M9 also requires representative full database restore, every critical legacy/pro layout and ID preservation scenario, support-pair matrix, security/accessibility/performance checks and documented residual risks. No skipped/blocked check counts as pass. At most three automatic correction attempts per task, then BLOCKED with diagnosis. Shipping/deployment is a separate authorized action, never a test.
