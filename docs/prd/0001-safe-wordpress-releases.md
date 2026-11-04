# 0001 — Safe WordPress.org releases

- Status: Implemented; local verification passed; live verification pending
- Owner / approval authority: repository maintainer, through the attached release automation task
- Date: 2026-11-04
- Related: [release runbook](../release.md), [investigation](../release-investigation.md), [ADR 0002](../adr/0002-guarded-wordpress-releases.md)

## Problem and goals

The old public 3.4.15 ZIP contains three root files and SVN trunk has empty runtime directories. A successful Actions run did not establish plugin integrity. Releases must include every approved runtime file, reject unexplained deletions before committing, and independently verify published SVN and downloads.

## Requirements and acceptance

1. A published, non-prerelease GitHub release is the only production trigger. Validate its exact tag commit, clean checkout and five matching version declarations. Existing Git/SVN tags are immutable.
2. Build one deterministic ZIP with the `ajax-filter-posts/` root and all tracked approved files. Reject missing/empty files, new unlisted runtime files, symlinks, gitlinks, duplicate ZIP entries and unsafe paths. Include checked-in minified assets and Appsero. Exclude docs, agents, tests, scripts, GitHub config and `.wordpress` listing images.
3. Pin a known-good SVN tag and its recursive SHA-256 manifest. Compare against both that baseline and current trunk. Missing paths need exact version-scoped reviewed reasons; default permits no removals.
4. Stage into a revision-pinned working copy. Validate every file in trunk and the new tag against the ZIP, preserve listing assets, inspect complete status/diff, reject changes outside trunk/new tag, and recheck the remote revision and staged digest immediately before commit. Never recursively delete or replace trunk.
5. Run local/CI failure tests, PHP syntax checks and disposable WordPress ZIP installation/activation/rendering. PRs, pushes to dev and manual dispatch stage only, without SVN credentials or a commit.
6. After the single SVN commit, export trunk and tag afresh, compare all paths/hashes, download and check the WordPress.org ZIP and listing, attach the identical ZIP to GitHub, download it again and compare its bytes. Preserve evidence even on failure.
7. Document setup, CLI operation, monitoring, confirmation delays, recovery and emergency manual use. After a failed production attempt, continue diagnosis/dry runs, but require maintainer approval before another attempt.

## Scope and compatibility

Free release tooling only; source baseline `87e01bb`, Pro clean `main` inspected separately. The package's runtime files match the known-good 3.4.17 tag except bootstrap version metadata. No hooks, attributes, storage, templates, selectors or script behavior changes. No Pro edits or coordinated Pro release required. Full browser/Free+Pro regression testing is outside the packaging change; do not imply a supported pair from source equality.

## Evidence

Local: 15 release tests pass, including disposable SVN acceptance/tampering checks; 45 package/ZIP paths and hashes match; revision-pinned production SVN staging passes with no commit; all 25 packaged PHP files parse on PHP 8.4.11. Disposable MySQL 8.4 / WordPress 7.1.4: ZIP install, activation, all three shortcode aliases, Appsero, deactivation/reactivation pass. Docker daemon unavailable; a separate temporary MySQL instance was used. CLI extraction initially exceeded PHP's default 128 MB limit; the committed smoke runner explicitly uses 512 MB.

Live release evidence is recorded in the runbook or linked Actions evidence after execution. Plugin Check 2.1.0 was run on candidate and baseline; existing runtime findings are outside this packaging change. The compatibility header format was corrected to major.minor (7.1), while the runtime smoke test uses 7.1.4. Full UI/AJAX matrix coverage is not claimed.
