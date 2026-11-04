# Free/Pro compatibility and ownership

Free and Pro are separate Git/release units. This document is the public-side contract; premium implementation belongs in [Pro's own documentation](https://github.com/akshuvo/gridmaster-pro/blob/main/docs/free-pro-compatibility.md) (access may be restricted). Keep this file useful without that access. Do not copy Pro source, license internals, or private requirements here.

## Responsibility and change routing

| Work | Owning repository | Companion assessment |
| --- | --- | --- |
| Bootstrap, base rendering/query, public AJAX, legacy aliases | Free (`ajax-filter-posts`) | Check Pro's attribute/query filters and dependent frontend script |
| Builder and shared field serialization, saved grids/settings/schema | Free | Include Pro field round trips and existing stored premium attributes |
| Base grid/filter templates, shared helpers, DOM, breakpoints | Free | Check premium templates, CSS, responsive settings and slider events |
| Premium rendering policies, premium layouts, slider behavior | Pro (`gridmaster-pro`) | Check base templates/AJAX and Free-owned builder controls |
| Premium term metadata or licensing/updater integration | Pro | Check shared settings hook/UI as relevant; keep details in Pro |
| A new shared hook, payload field, or storage transition | Usually Free producer plus Pro consumer | May require changes and linked records in both repositories |

Free owns three grid layouts, four filter templates, custom post type queries, basic taxonomy controls, pagination/load-more/infinite scroll, and the builder. Pro extends attributes, templates, query rules, responsive controls and sliders through the surfaces in [integrations](integrations.md). Free advertises premium options without containing their implementation.

## Initialization and compatibility limits

Free and Pro define constants during their own bootstrap and register `plugins_loaded` callbacks at default priority 10. Free detection of Pro uses a version constant, template lookup uses its path constant, and taxonomy handoff uses its entry function. Pro's current source supplies an admin missing-Free notice; no enforced Free minimum-version check or dependency stop was found. Do not describe Pro-only operation as supported or assume a licensing state is a dependency check.

Observed source pair: Free `3.4.17` / Pro `1.2.3` on 2026-11-04. Pro's existing README mentions compatibility with Free `3.4.13`; neither that note nor inspection proves a supported version range. This pair has **not been runtime-tested in this setup**. Record exact versions/commits and results for each future compatibility run.

Preserve existing shortcode aliases and attribute meaning, serialized grid attributes, option keys/defaults, hook names/signatures/priorities, template precedence, helper contracts, and the HTML/AJAX/browser protocol. Avoid silently dropping unfamiliar premium attributes from saved grids. For intentional incompatible changes, document migration/fallback behavior and coordinate releases in both repositories before claiming support.

## Cross-repository impact assessment

For any shared change, add this compact assessment to the implementation plan/PR description (even if only one repository needs edits):

- Repositories/components and source versions inspected; unavailable source explicitly marked.
- Producer/consumer contract affected: hooks, attributes, data/storage, templates, assets, initialization.
- Existing installations and old/new pair behavior; migration or fallback if necessary.
- Required companion change/linked PRD or ADR, or evidence that none is needed.
- Free-only and paired test cases, actual results, gaps, release sequencing and rollback considerations.

Free changes most likely to break Pro include removing `tax_input` before its priority-15 handler, changing hook arguments/order, dropping premium attribute payloads, changing `gm_get_breakpoints()`, renaming DOM selectors/script handles, and changing template precedence. Pro changes can alter Free layouts through shared filters or leave request-wide title/thumbnail state; check multiple mixed grids and Pro deactivation.

## Paired verification

Follow [testing](testing.md): Free alone, both active, both activation orders, Free absent/deactivated with Pro present, and return to Free-only operation. Exercise initial render and AJAX, legacy inline shortcodes and saved IDs, custom taxonomies, selected/All terms, mixed grids, all pagination modes, premium templates and responsive/slider settings. Missing dependency and old/new pairs require explicit observations rather than a presumed pass.

## Documentation maintenance

Each repository has its own `AGENTS.md`, implementation docs, skills, ADR/PRD indexes and ideas. Maintain Free's public API here and reference it from Pro. Keep only the relevant compatibility summary in both; do not duplicate architecture documents. Cross-repository links use repository URLs so independent checkouts work; if a branch moves, update links with the corresponding change. Discovery branch names describe the baseline, not a mandate for future branches.

## Proposed GridMaster 2.0 design

The [initial redesign package](gridmaster-2.0/README.md) records new proposals dated 2026-11-04. It does not replace the observed runtime contracts above or certify a new version pair; no redesign feature is implemented by those documents.
