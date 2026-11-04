# Architecture Decision Records

Use ADRs for significant choices affecting architecture, persistence, extension contracts, security boundaries or release compatibility. Routine fixes do not need a record. Number records locally as `NNNN-short-title.md`; sequence numbers need not match the other repository. Link companion records by repository URL and keep confidential details in the owning repository.

Statuses: **Proposed** (under discussion), **Accepted** (maintainer-approved), **Superseded** (link to replacement), **Deprecated** (no longer applicable). Preserve accepted history when replacing decisions. A coding agent should not infer approval from an idea, a draft record or existing implementation.

For retrospective records, describe observed source and explicitly mark original rationale as unverified. Recording existing behavior does not endorse every limitation.

## Index

- [0001 — Existing extension boundary](0001-existing-extension-boundary.md): Proposed retrospective record; maintainer review pending.
- [0002 — Guarded WordPress.org releases](0002-guarded-wordpress-releases.md): Proposed; implemented for the requested release task.
- [0003 — Stable table IDs and private definition storage](0003-saved-grid-definition-storage.md): Proposed.
- [0004 — Native Gutenberg card builder](0004-native-gutenberg-card-builder.md): Proposed.
- [0005 — Versioned extension contract](0005-versioned-extension-contract.md): Proposed.

Further architectural decisions should add a new numbered record only when needed; current observed behavior remains separate from the proposed redesign.

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
