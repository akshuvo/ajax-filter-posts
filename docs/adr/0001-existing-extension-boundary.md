# 0001 — Existing Free extension boundary

- Status: Proposed
- Date: 2026-11-04
- Type: retrospective observation; maintainer acceptance pending
- Historical rationale: unverified

## Context and problem

Free and Pro are separate source/release units. Future work needs an explicit account of which repository produces the shared rendering contract. This record documents the observed implementation; it does not claim to recover its original design discussion.

## Decision

Observed implementation: Free owns `GridMasterPlugin`, `GridMaster\Shortcode`, all three shortcode aliases, public HTML AJAX, builder/storage and shared helper/DOM contracts. Pro extends through hooks and template/constants/browser seams. This setup preserves that boundary and adds documentation only. See [architecture](../architecture.md) and [integrations](../integrations.md) for source evidence.

## Alternatives considered

No evidence of the original alternatives was found. A merged repository, independent Pro renderer or versioned extension SDK would be new proposals, not historical explanations. None is proposed for implementation by this record.

## Consequences and trade-offs

The current boundary centralizes rendering and permits extension, while requiring coordination across hook priorities, browser payloads and separate releases. It includes mixed detection mechanisms and request-wide callback state. Historical reasons for these trade-offs remain unverified.

## Affected repositories and components

Free: `ajax-filter-posts.php`, `inc/Shortcode.php`, `inc/functions.php`, `admin/`, `templates/`, `assets/frontend.js`. Pro: consumers of this public contract; proprietary details stay in that repository. See [compatibility](../free-pro-compatibility.md).

## Compatibility implications

Preserve aliases, stored attributes, hook names/signatures/priorities, template precedence and HTML/browser protocol. Inspection is not proof of a supported version range. No migration or application change is introduced here.

## Related PRDs and evidence

No feature PRD: this is an initial workflow/documentation task. Companion: [Pro observation](https://github.com/akshuvo/gridmaster-pro/blob/main/docs/adr/0001-existing-extension-boundary.md). Validation scope and untested runtime cases: [testing](../testing.md).
