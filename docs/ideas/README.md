# Ideas and future investigations

Everything here is **unapproved**. An observed limitation is not an approved implementation request, and a source-level concern is not a confirmed exploitable defect. Promote substantial approved work into a [PRD](../prd/README.md); record significant choices in an [ADR](../adr/README.md). Keep small fixes lightweight. No application changes are authorized merely by listing an idea.

For a new entry use: ID/title, type (feature, technical improvement, limitation, refactor, investigation), status (Idea / Investigating / Promoted / Deferred / Rejected), problem/evidence paths, possible benefit, Free/Pro scope, open questions, and next validation step. Add an individual Markdown file only when an entry needs more than a table row; link it here. Promoted entries link to their approved requirement/issue.

## Initial backlog

All entries below have status **Idea**. Evidence is from the 2026-11-04 source inspection, with details in [architecture](../architecture.md), [integrations](../integrations.md) and [testing](../testing.md). They have not been runtime-reproduced in this task.

| ID | Type | Source evidence | Next step |
| --- | --- | --- | --- |
| FREE-01 | Investigation | `admin/Ajax.php::register_ajax()` has no explicit capability guard/active method allowlist; settings/grid fields are not comprehensively validated | Reproduce with disposable role/nonce fixtures and specify authorized dispatch/write behavior |
| FREE-02 | Known limitation / proposal | `admin/DB_Migration.php` loads through the My Grids view, while My Grids navigation and builder save button are commented out | Verify fresh-install and upgrade/storage lifecycle before proposing changes |
| FREE-03 | Investigation | Initial category normalization differs from selected custom-taxonomy AJAX handling; render callbacks retain state; frontend load-more flag resets only on success | Characterize query parity, mixed grids and failed/rapid requests |
| FREE-04 | Technical improvement | No reproducible minifier/test suite or installed PHPCS runner; ruleset text domains differ from Free strings | Establish maintainer-approved tooling and meaningful regression coverage |
| FREE-05 | Release investigation | No checked-in distribution exclusion manifest found while deployment can package repository content | Inspect generated ZIP contents before a future release; decide whether docs/skills belong in distribution |

New product features should start from an actual user problem and Free/Pro scope; none are invented by this setup. Prioritize investigations with the maintainer before adding tools, redesigning integration, or modifying security behavior.
