# WordPress.org releases

This runbook owns Free releases. Pro is a separate repository/release. See the [investigation](release-investigation.md), [requirements](prd/0001-safe-wordpress-releases.md) and [architecture decision](adr/0002-guarded-wordpress-releases.md).

## Setup

Required locally: Git, GitHub CLI authenticated with repository/workflow access, SVN (including svnadmin for tests), Python 3.9+, PHP, curl and ZIP tooling. `actionlint` and `shellcheck` validate workflows/scripts. The smoke test also needs an isolated MySQL server/client and PHP mysqli. CI uses Ubuntu 24.04 and its MySQL/PHP packages.

Configure repository Actions secrets `SVN_USERNAME` (authorized WordPress.org committer) and `SVN_PASSWORD` (SVN application password). Use `gh secret set SVN_USERNAME --repo akshuvo/ajax-filter-posts` and `gh secret set SVN_PASSWORD --repo akshuvo/ajax-filter-posts` interactively. Never paste values into code, commands recorded in chat, logs or artifacts. `gh secret list` lists names only. The workflow supplies them only to the commit step; the SVN password goes through stdin with no authentication cache. `github.token` supplies `contents: write` for the release asset; no personal GitHub token is stored.

The workflow `.github/workflows/wporg.yml` deploys only on publication of a non-prerelease GitHub release. Tag pushes do not deploy. PRs, pushes to `dev`, and `workflow_dispatch` run validation/staging only. The FTP workflow on `main` is separate; do not push `main` to test releases. Deployment jobs are serialized and never cancelled by a newer run. Actions are pinned to commit SHAs.

## Package and integrity policy

`.github/release-files.txt` is the reviewed, sorted runtime inventory (currently 45 files). The builder compares it with Git's tracked files under admin/inc/assets/templates/languages/appsero and the four approved root files. Missing/empty files, symlinks and submodule gitlinks abort. Existing README/package.json are retained to avoid silently removing published files. All required minified JS/CSS are tracked; no npm build exists or is required. No development directories, agent files, CI configuration or `.wordpress` listing images enter the package. There is no `.distignore` or `.gitattributes` packaging fallback: this explicit inventory is authoritative.

The deterministic ZIP has exactly one `ajax-filter-posts/` root. `manifest.json` records SHA-256 by recursive relative path. ZIP paths, CRCs and every content hash are checked. `.github/release-baseline.json` pins the verified 3.4.18 SVN tag, independently matched with both downloadable ZIPs. The first guarded deployment used 3.4.17 as its prior known-good baseline; the pin was advanced only after independent 3.4.18 verification. Stage compares that tag against the committed hashes, and checks new-package deletions against both the known-good release and the actual current SVN trunk.

Default removal policy is empty. An intentional removal requires an exact path and a meaningful reviewed reason under the new version in `.github/release-removals.json`, plus an inventory change and release documentation. Wildcards and stale exceptions are rejected. Example structure: `{"3.4.19": {"assets/obsolete.css": "Reviewed removal: superseded by the documented replacement asset."}}`. Do not copy this example as an actual exception.

Stage captures SVN revision, before-trunk/baseline manifests and listing-asset hashes. It copies individual approved files, schedules only explicitly allowed file deletions, then copies trunk to a new tag. No blind recursive SVN deletion or trunk replacement is used. Full staged trunk/tag hashes, untouched listing assets, allowed SVN statuses/property changes and path scope must pass. The full `svn-status.xml` and `svn-diff.txt` are saved. Publish revalidates their digest and the plugin repository's scoped last-changed revision immediately before one commit. A changed remote state requires a fresh build/stage/review. Existing tags are refused.

Runtime `assets/` belongs in `trunk/assets/` and each tag. Listing assets belong in SVN root `assets/`. This pipeline preserves the live listing assets unchanged; `.wordpress/` holds source listing images for separately reviewed updates. It never deletes remote listing images to match a partial local directory.

## Prepare and validate

1. Check the intended Git root, branch, status and remote. Preserve unrelated user work. Confirm the latest GitHub release/tags, SVN tags and WordPress.org version. Do not overwrite Git or SVN tags.
2. Select an unused patch version and update the plugin header, `GRIDMASTER_VERSION`, readme.txt Stable tag, README Stable tag and package.json. Update changelogs. Compatibility claims such as Tested up to need separate verification.
3. Run checks from the repository root, placing builds outside the checkout:

```sh
python3 -B -m unittest discover -s tests -p 'test_release.py' -v
actionlint .github/workflows/wporg.yml
shellcheck scripts/smoke-test.sh
python3 -B scripts/release.py build --output /tmp/gridmaster-candidate
python3 -B scripts/lint-package.py /tmp/gridmaster-candidate/ajax-filter-posts
python3 -B scripts/release.py stage --output /tmp/gridmaster-candidate
```

The output directory must not exist. Read `/tmp/gridmaster-candidate/svn-status.xml`, `svn-diff.txt`, before/after manifests and ZIP. `stage` reads production SVN and mutates only its temporary working copy; it never commits and needs no credentials. The version must be newer than the known-good baseline and absent from SVN. A currently published version must be bumped before staging a subsequent release. Non-release CI uses `--check-existing`: if the version already exists, it only verifies exact published tag/trunk hashes and creates no SVN changes or publishable stage. This lets documentation-only commits validate after a release. Production never passes that option and always rejects existing tags.

Run disposable WordPress smoke testing with `WP_DB_USER`, `WP_DB_PASSWORD`, `WP_DB_HOST` set for an isolated server:

```sh
scripts/smoke-test.sh /tmp/gridmaster-candidate/ajax-filter-posts.zip /tmp/gridmaster-wp-candidate
```

This downloads pinned/hash-checked WP-CLI, installs WordPress 7.1.4, creates a unique database, installs the actual ZIP, checks activation/three shortcode aliases/Appsero and reactivation, then drops only that database. It retains the temporary site for diagnosis. Do not supply an existing production site path. This smoke test does not cover the full browser/AJAX or premium matrix. The placeholder npm test is not used.

## Publish with GitHub CLI

Publication/commits/pushes require task authorization. The attached 2026-11-04 task authorizes one real release after checks pass; it does not authorize repeated production attempts after failure.

Commit the reviewed source and workflow on the intended branch, push to `dev`, and wait for its validation-only run to pass. Confirm the working tree is clean and the remote commit is the one being tagged. Example for an unused 3.4.18:

```sh
gh secret list --repo akshuvo/ajax-filter-posts
gh run list --repo akshuvo/ajax-filter-posts --workflow wporg.yml --limit 5
git tag v3.4.18
git push origin refs/tags/v3.4.18
gh release create v3.4.18 --verify-tag --title 'Post Grid Master 3.4.18' --notes-file /tmp/gridmaster-release-notes.md --repo akshuvo/ajax-filter-posts
```

Tag checkout must exactly match clean HEAD and every version declaration. Publishing the release starts the only deployment event. Do not use a second tag-push trigger, manual workflow dispatch or release edit to repeat a production deployment.

```sh
gh run list --repo akshuvo/ajax-filter-posts --workflow wporg.yml --event release --limit 5
gh run view RUN_ID --repo akshuvo/ajax-filter-posts
gh run view RUN_ID --repo akshuvo/ajax-filter-posts --log-failed
gh run download RUN_ID --repo akshuvo/ajax-filter-posts --dir /tmp/gridmaster-evidence
```

CI checks PHP, installs/activates/renders the ZIP in disposable WordPress, stages/validates SVN, commits once, then verifies fresh SVN exports, WordPress.org ZIP/listing and the attached GitHub ZIP. A run is successful only after these steps. Diagnostics/manifests/ZIPs upload even when a later step fails; no credentials or SVN authentication cache are uploaded.

## Independent post-publication verification

Use the approved local build, not merely a version number or green Actions badge:

```sh
python3 -B scripts/release.py verify --output /tmp/gridmaster-candidate --attempts 12
gh release download v3.4.18 --pattern ajax-filter-posts.zip --dir /tmp/gridmaster-github-download --repo akshuvo/ajax-filter-posts
cmp /tmp/gridmaster-candidate/ajax-filter-posts.zip /tmp/gridmaster-github-download/ajax-filter-posts.zip
```

`verify` exports trunk and the new tag into fresh directories, compares every recursive path and SHA-256, downloads the versioned WordPress.org ZIP, checks every entry/hash and the plugin API's current version, and writes `verification.json` with revision and file count. Fresh verification output directories are required if exports already exist. The WordPress.org ZIP may have different ZIP metadata; its file contents must match exactly. The GitHub ZIP must be byte-identical to the approved archive.

Use connected Chrome to inspect the GitHub release, Actions result, WordPress.org listing and development/version page, and download links. Record observations/screenshots as supporting evidence. Browser verification cannot substitute for recursive hashes. If browser control is unavailable, report that limitation.

## Failure, confirmation and emergency recovery

If preflight fails, nothing is committed. Keep the pending diff/manifests, fix the cause, and repeat local/CI validation. If commit succeeds but post-publication checks fail, the deployment is **unverified/failed**, even with a public GitHub release. Inspect commit output, fresh SVN tag/trunk and downloaded ZIPs. Check for WordPress.org processing delays or release confirmation required from an authorized committer. `verify` retries ZIP/listing propagation for about six minutes; SVN/hash mismatches fail immediately. Do not rewrite tags or keep rerunning production blindly.

After a failed production attempt, obtain maintainer approval before another attempt. Restore user access to the last known-good complete release where appropriate, using an explicitly reviewed stable-tag recovery. A corrected public release must use a new unused patch version after its underlying cause has been fixed. Never overwrite an immutable published tag. Retain failed-run evidence.

For emergency manual SVN publication: perform the same build/stage commands, inspect every staged change and manifest, run the WordPress smoke test, verify remote last-changed revision has not moved, and re-run the validation gate immediately before committing. The normal `publish` command intentionally runs only in GitHub Actions. If Actions is unavailable, an authorized maintainer can use `svn commit --non-interactive --no-auth-cache --username YOUR_COMMITTER --password-from-stdin -m 'Reviewed emergency release VERSION' /tmp/gridmaster-candidate/svn` with a password supplied privately via stdin, without command logging. Do not bypass any manifest/deletion/status validation or add listing asset changes to this working copy. Run independent `verify`, attach/download/check the same GitHub ZIP, and perform Chrome checks afterward. Keep the SVN revision and evidence. This is an emergency procedure, not a second automated trigger.

## 2026-11-04 validation record

Initial Free checkout `dev` at `87e01bb`; Pro clean `main` inspected separately. Known-good tag/WordPress.org ZIP 3.4.17: 45 paths and SHA-256 hashes match. Current trunk at global snapshot r3782816: three root files and empty runtime directories; plugin-scoped last-changed revision r3382254. Local candidate 3.4.18: 45 files, 25 PHP files parse, recursive ZIP equality and SVN staging pass with no unexplained deletions or remote writes. Fifteen release tests pass. WordPress 7.1.4/MySQL 8.4/PHP 8.4.11 disposable smoke test passes. Docker was unavailable; a separate temporary MySQL instance was used. Plugin Check 2.1.0 was executed against both the candidate and 3.4.17: existing code findings are recorded separately; the Tested up to patch-number format was corrected to 7.1. Full frontend browser/AJAX and paired premium regression matrix remain untested. The first live release succeeded and was independently verified; see the publication record below.

Validation-only CI run [41744308921](https://github.com/akshuvo/ajax-filter-posts/actions/runs/41744308921) passed package, syntax, smoke and SVN staging but failed its final artifact upload because GitHub rejects `..` in artifact paths. The output path was corrected to a normalized absolute temporary path before any production attempt.

Plugin Check result on the final ZIP: 43 existing errors and 122 warnings (baseline 3.4.17: 44 errors and 122 warnings). No new finding locations/codes were introduced; the outdated Tested up to finding was removed. Existing errors include bundled Appsero output escaping/text domain, first-party escaping/review-link and direct-access protection findings. These are not fixed or claimed clean by this release-tooling task.

## Verified publication — 3.4.18

- Release source: clean tag `v3.4.18` at `22507f947d139e0965cd9072e629b63d1a803907`.
- [GitHub release](https://github.com/akshuvo/ajax-filter-posts/releases/tag/v3.4.18).
- [Passing validation-only CI](https://github.com/akshuvo/ajax-filter-posts/actions/runs/41744653343). Its ZIP is byte-identical to the approved local ZIP.
- [Passing production Actions run](https://github.com/akshuvo/ajax-filter-posts/actions/runs/41744968218); all build/test/stage/commit/verification/upload steps passed. Its `release-evidence-41744968218` artifact contains manifests, the full pending diff/status, commit output, ZIPs and verification results.
- SVN commit **r3782835**: [immutable version tag](https://plugins.svn.wordpress.org/ajax-filter-posts/tags/3.4.18/), [restored trunk](https://plugins.svn.wordpress.org/ajax-filter-posts/trunk/).
- [WordPress.org listing](https://wordpress.org/plugins/ajax-filter-posts/), [advanced/version view](https://wordpress.org/plugins/ajax-filter-posts/advanced/), [WordPress.org ZIP](https://downloads.wordpress.org/plugin/ajax-filter-posts.3.4.18.zip), [GitHub ZIP](https://github.com/akshuvo/ajax-filter-posts/releases/download/v3.4.18/ajax-filter-posts.zip).
- Approved local package: **45 files**. Independently exported SVN tag: **45 files**. Fresh SVN trunk, tag, WordPress.org ZIP, GitHub ZIP and production CI manifest all match every recursive path and SHA-256 hash. GitHub ZIP bytes also match the approved deterministic ZIP exactly. All six required directories (`admin`, `inc`, `assets`, `templates`, `languages`, `appsero`) contain their approved nested files. All **12 listing assets** match their pre-deployment hashes.
- Independent exports used global snapshot r3782836; the release's scoped committed revision is r3782835. This distinction matters because all WordPress plugins share the global SVN revision sequence.
- Chrome verified GitHub's 3.4.18 release/commit/download asset, Actions Success, the WordPress.org listing and advanced version information. The main listing initially served cached 3.4.17 content; a fresh query `?release=3.4.18` showed the updated 3.4.18 download/version and Tested up to 7.1.4. The advanced view and API also showed 3.4.18. The source declaration is `Tested up to: 7.1`; the public metadata reports 7.1.4.
- The versioned WordPress.org ZIP initially returned 404 during processing, then became available within the bounded verification retries. No second production attempt, tag overwrite or recovery release was needed.
- Remaining limitations: Plugin Check's **43 existing errors / 122 warnings**; full frontend/AJAX and Free+Pro browser regression matrix not run. These do not change the successful package-integrity evidence. Only bootstrap/version/readme/package metadata changed in the runtime package.

The known-good manifest was advanced to 3.4.18 after these checks. Future releases must use a new version and preserve its paths unless an exact reviewed removal exception is recorded.
