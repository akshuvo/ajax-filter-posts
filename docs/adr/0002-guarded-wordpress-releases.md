# 0002 — Guarded WordPress.org releases

- Status: Proposed; implemented for the maintainer-requested release task
- Date: 2026-11-04
- Owner / decision authority: Free repository maintainer
- Supersedes: none

## Context

The previous action can synchronize an incomplete package with deletion enabled and then commit. A successful historical run produced a three-file ZIP. SVN trunk was already incomplete before that run; see the [investigation](../release-investigation.md). The requirement is an independent pre-commit gate over the entire staged SVN changeset, plus independent verification after publication.

## Decision implemented

Use a small Python standard-library release script in the existing workflow. Keep one production event (`release: published`, excluding prereleases); other events validate only. Use an explicit tracked runtime inventory, pinned known-good manifest and version-scoped exact removal exceptions. Build the ZIP once. Stage individual files in a revision-pinned sparse-root/full-trunk SVN working copy, copy validated trunk into the new tag, review/validate status and diff, check the scoped remote revision again, and commit once. Preserve WordPress.org listing assets. Verify exported trunk/tag and both downloadable archives separately.

## Alternatives

10up action 2.3.0 was inspected at `54bd289b8525fd23a5c365ec369185f2966529c2`. It supports dry-run mode but exposes no independent hook between staging and commit. A separate dry-run followed by the normal action would stage twice and would not prove that the second staged changeset was the inspected one. Forking its shell script adds more third-party code to maintain. The custom guarded path supports the required checks directly without rebuilding or resynchronizing after validation.

## Consequences

The repository now maintains its own release script and focused failure tests. A maintainer must update the file inventory for runtime additions and review exact removal exceptions. The known-good baseline stays pinned until deliberately replaced after independent verification. Existing SVN tags must never be overwritten. Publication can still be delayed by WordPress.org processing/committer confirmation; a verification timeout fails the run and does not authorize another release attempt.

## Compatibility

Free tooling only; runtime APIs and Pro source remain unchanged. Packaging preserves all 45 previously published files, including bundled dependencies and compiled assets. `.wordpress` files remain listing-only and are not packaged or automatically synchronized. Updating listing assets is a separate reviewed SVN operation.

## Requirements and evidence

[PRD 0001](../prd/0001-safe-wordpress-releases.md), [runbook](../release.md), [failure tests](../../tests/test_release.py).
