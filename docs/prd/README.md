# Product Requirements Documents

Use a PRD for substantial feature work or behavior changes that benefit from explicit acceptance criteria. A small bug fix can put expected behavior/regression cases in its task or issue. PRDs describe outcomes; architecture and implementation choices belong in the plan/ADRs.

Create `NNNN-short-title.md` using the template below. This repository owns its feature scope; link a companion PRD when both plugins need work. Avoid duplicating requirements or publishing private Pro details in Free.

Suggested lifecycle: Draft → Approved (maintainer decision) → Implemented → Verified, or Deferred/Withdrawn. Distinguish planned verification from results actually executed. No feature requirements have been approved by this setup.

## Index

- [0001 — Safe WordPress.org releases](0001-safe-wordpress-releases.md): verified for release-tooling acceptance; 3.4.18 published and independently checked.

## Template

```markdown
# NNNN — Feature title

- Status: Draft
- Owner / approval authority:
- Date: YYYY-MM-DD
- Related issue / companion PRD / ADR: links or none

## Problem statement
Who is affected, under what conditions, and what is missing today?

## Goals
Observable outcomes and how success will be assessed.

## Free/Pro scope
User-facing behavior available in Free, in Pro, and in each plugin combination.
Name affected owning repositories; mark unavailable-source assumptions.

## Functional requirements
Numbered expected behaviors, including initial render and later interactions when relevant.

## Non-functional requirements
Relevant accessibility, security/privacy, performance, localization and runtime constraints.

## Acceptance criteria
Testable examples (given / when / then) for normal, failure and boundary cases.
Reference requirement numbers without prescribing classes or algorithm choices.

## Dependencies
Plugin/version pair assumptions, WordPress/PHP environment and external-service needs.

## Edge cases
Empty content, malformed input, multiple grids, permissions, missing dependency and failure states as applicable.

## Backward compatibility
Existing shortcodes, settings/data, templates and old/new plugin pair expectations.
Document any intended transition requirements.

## Testing requirements
Required automated/manual cases, fixtures and environments; acceptance evidence after execution.
Clearly mark skipped checks and limitations.

## Out of scope
Explicit exclusions to keep the work focused.
```
