# GridMaster 2.0 initial architecture package

Status: proposed design for review, 2026-11-04. Codex owns architecture and independent acceptance; Antigravity owns future assigned implementation. No feature implementation or database mutation is included in this phase.

| Deliverable | Owning document |
| --- | --- |
| Complete core PRD / proposed Free–Pro scope | [Free PRD 0002](../prd/0002-gutenberg-grid-builder-v2.md), [Pro PRD 0001](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/prd/0001-gutenberg-pro-extensions.md) |
| Storage ADR | [0003](../adr/0003-saved-grid-definition-storage.md) |
| Gutenberg ADR | [0004](../adr/0004-native-gutenberg-card-builder.md) |
| Extension contract ADR | [Free 0005](../adr/0005-versioned-extension-contract.md), [Pro 0002](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/adr/0002-versioned-free-provider-integration.md) |
| Source and experiment audit | [source-audit.md](source-audit.md) |
| Field authority and database schema proposal | [schema.md](schema.md) |
| Builder components, document and REST boundaries | [builder.md](builder.md) |
| Query/facet/filter/pagination engine | [query-facets.md](query-facets.md) |
| Reversible legacy migration | [migration.md](migration.md), [Pro mappings](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/gridmaster-2.0/migration.md) |
| Test strategy / release gates | [testing.md](testing.md), [Pro testing](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/gridmaster-2.0/testing.md) |
| M1–M9 task breakdown and dependencies | [implementation-plan.md](implementation-plan.md), [Pro plan](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/gridmaster-2.0/implementation-plan.md) |

The shared ID remains a table ID, never a WordPress post ID. A private CPT stores the whole editable v2 definition; the table binds it to a stable ID and retains legacy source. Legacy output remains live until the exact candidate is explicitly approved. New registries extend the Free engine, not fork it. These choices await review; existing accepted behavior is documented separately in [current architecture](../architecture.md).

## Decisions for review

| Proposed choice | Main trade-off / unresolved gate |
| --- | --- |
| Table ID + whole private CPT definition | Coherent native revisions; needs strict block-document validation, protected REST and safe binding/concurrency service |
| Native post editor/post blocks + shared repeat engine | Less editor reinvention; prove public APIs and context/style behavior at proposed WP6.5 minimum |
| Additive identity/history schema, no table config mirror | Clear authority and recovery; extra lifecycle metadata and durable history need backup/restore and atomic failure tests |
| Free responsive styling and public custom-taxonomy single filters | Broader useful Free core; product owner must approve change from current premium boundary |
| Reusable facet types/presets copied per grid | No conflicting shared store; globally linked facet entities/propagating edits are deferred scope |
| Per-grid explicit candidate approval and legacy retention | Strong visual/data safety; more UI/support and dual-renderer maintenance initially |
| New builder WP6.5+/PHP7.4, legacy runtime below it | Avoid a global abrupt support drop; exact compatibility matrix still needs verification and product approval |

Persistent HTML caching, arbitrary addon compatibility, exact conversion of theme overrides and an old-binary downgrade without restore are not promised. The local grid table has no rows, so representative preservation fixtures are a release gate. Apply the documented Nginx restriction before placing any confidential material under this site's document root; no raw backups/logs belong there even after protection.

Companion links use this review branch per repository conventions. They resolve against the sibling checkout during local review and will resolve remotely only if/when documentation is separately authorized for publication; no push is part of this work.
