# Architecture Decision Records

Use ADRs for significant choices affecting architecture, persistence, extension contracts, security boundaries or release compatibility. Routine fixes do not need a record. Number records locally as `NNNN-short-title.md`; sequence numbers need not match the other repository. Link companion records by repository URL and keep confidential details in the owning repository.

Statuses: **Proposed** (under discussion), **Accepted** (maintainer-approved), **Superseded** (link to replacement), **Deprecated** (no longer applicable). Preserve accepted history when replacing decisions. A coding agent should not infer approval from an idea, a draft record or existing implementation.

For retrospective records, describe observed source and explicitly mark original rationale as unverified. Recording existing behavior does not endorse every limitation.

## Index

- [0001 — Existing extension boundary](0001-existing-extension-boundary.md): Proposed retrospective record; maintainer review pending.

Other possible records, only when a real decision is needed: storage migration/lifecycle, public-AJAX security policy and a reproducible asset pipeline. Current observations and unapproved follow-ups are in the architecture and ideas docs; do not create one ADR per function.

## Template

Copy the following into the next numbered file and replace the prompts. Mark this repository as owner and cross-link companion decisions where needed.

```markdown
# NNNN — Title

- Status: Proposed | Accepted | Superseded | Deprecated
- Date: YYYY-MM-DD
- Owner / decision authority:
- Supersedes / superseded by: record link or none

## Context and problem
What requires a decision? Cite source/constraints; distinguish facts from assumptions.
For retrospective documentation, identify observed implementation and mark historical rationale unverified.

## Decision
Proposed or accepted choice and its scope. Identify who accepted it when applicable.

## Alternatives considered
Reasonable alternatives and why they were selected/rejected; do not invent historical reasoning.

## Consequences and trade-offs
Benefits, costs, operational risks and follow-up work.

## Affected repositories and components
Owning repository, companion impacts and relevant paths/hooks/storage.

## Compatibility implications
Existing installations, data, shortcodes, supported pairs, migration/fallback and release sequencing.

## Related PRDs and evidence
Links to requirements, companion records, implementation and validation results; mark gaps.
```
