# Free testing and quality standards

No automated application test suite was found in this repository at the inspection baseline (2026-11-04). Select checks by affected behavior, and distinguish executed results from recommended checks.

## Applicable local checks

From this repository root, syntax-check the changed PHP files; for a deliberate full first-party scan:

```sh
find . -type f -name '*.php' -not -path './.git/*' -not -path './vendor/*' -not -path './node_modules/*' -not -path './appsero/*' -not -path './licenser/*' -exec php -l {} \;
```

`php -l` checks parsing only. Run on each supported PHP runtime for a compatibility claim; this workspace's CLI 8.4 does not prove PHP 7.4 or site-runtime support. Check vendor files separately if a task actually modifies them. For changed JavaScript, use `node --check <file>` and then browser scenarios; syntax checks do not exercise jQuery, WordPress data, AJAX or Slick.

Free has no installed PHPCS executable on this host. If a configured runner and WPCS are available in the development environment, inspect `phpcs -i`, then run `phpcs --standard=.phpcs.xml.dist <changed-php-files>` from Free's root. The existing ruleset's text-domain property lists `gridmaster`/`default`, while Free's plugin uses `ajax-filter-posts`; record this mismatch instead of changing strings to satisfy it or claiming a clean baseline. Toolchain/ruleset correction is separate work. `npm test` intentionally exits 1 with “no test specified”; it provides no regression coverage.

## Release automation checks

The [release runbook](release.md) documents Python failure tests, actionlint/shellcheck, package PHP lint, recursive SHA-256 manifests, real SVN dry staging, disposable WordPress ZIP activation/rendering and independent SVN/ZIP/browser publication checks. Run `python3 -B -m unittest discover -s tests -p 'test_release.py' -v` for the release tests. They do not establish full application or premium compatibility. The smoke runner uses an isolated database and deletes only its own generated fixture database.

## Manual baseline: Free alone and shared flows

Use an isolated WordPress development site and disposable fixtures: enough published posts for at least three pages, draft/private posts, empty and populated terms, a custom post type with a custom taxonomy, images/missing images, and distinct administrator/lower-privilege/logged-out sessions. Record WordPress/PHP/theme/browser, both plugin commits/versions, license-service mode and nonce setting. Tests that change settings/data belong on that fixture site.

- Activate Free alone, visit the builder/settings/frontend, deactivate/reactivate and check expected data retention and PHP logs. Check activation timestamp separately from saved-grid table creation.
- Render `[gridmaster]`, `[am_post_grid]`, `[asr_ajax]`, inline attributes and a saved `[gridmaster id="…"]` where storage is initialized. Compare initial HTML with subsequent AJAX results. Verify only published posts, selected post types, order, excerpt/read-more options, each Free layout, empty results and image fallbacks.
- Test category and custom-taxonomy filters, selected terms, All (`-1`), `hide_empty`, hidden filters/All button, headings/toggle, and changing filters after visiting page two. Free's selected custom-taxonomy terms are premium-restricted; check administrator notice and visitor behavior separately.
- Exercise numbered, next/previous, load-more and infinite-scroll modes to exhaustion; verify correct append versus replace, no duplicates/lost filters, loading states and animation. Use two different grids on one page, rapid repeated clicks, slow/failed requests and scroll after a failed load-more request. Report state/race failures instead of presuming isolation.
- Inspect browser network/console: runtime minified files, expected localized data, one intended request per action, HTML public-AJAX response, scoped result/navigation updates and no PHP warnings in fragments. Check mobile/desktop widths and a theme override fixture.
- Preview builder changes as an administrator; check the preview nonce and copied shortcode (including JSON responsive/slider values). Reload settings after saving. Exercise existing saved-grid read/update/list paths on a fixture site; the UI save button and My Grids navigation are commented out. Verify table setup explicitly through the existing My Grids route rather than assuming activation creates it.
- Deactivate Pro after using premium settings and verify Free still renders base grids without fatal errors or silently deleting stored premium values. Include initial activation in both plugin orders, Free deactivated/absent while Pro remains, and return to a paired setup; record dependency failures rather than claiming Pro-only support.

## Security expectations and observed gaps

These are verification criteria for future code work, not claims that the current code passes. Source inspection is not an exploit assessment. Use disposable local fixtures and do not send license secrets or destructive requests to live services.

- Public `asr_filter_posts`: with nonce checking enabled, valid nonce succeeds and missing/invalid nonce is rejected. Separately characterize `disable-nonce-check=yes`; this bypass is current behavior, not a recommended security default. Exercise logged-in and logged-out requests, malformed array/scalar fields, taxonomy/term IDs, page limits and oversized query requests; confirm no private/draft content or diagnostic leakage.
- Privileged `gridmaster_ajax`: check valid/invalid `gm_nonce`, allowed/unknown `gm-action`, and administrator versus lower-privilege behavior. Current dispatcher checks nonce but has no explicit capability guard or active method allowlist; do not equate nonce possession with authorization. Field-level sanitization is incomplete. Verify settings/grid writes against expected types and reject unauthorized changes as a future acceptance criterion.
- Preview must require both `manage_options` and a valid `gm_shortcode_preview_nonce`. Check malformed shortcodes and restricted sessions.
- Review changed code for validate/unslash input, context-specific escaping (`esc_html`, `esc_attr`, `esc_url`, allowed HTML), tag/CSS allowlists, prepared SQL, bounded public query cost, and localization. Inspect filtered HTML and templates rather than assuming `sanitize_text_field` makes every output safe. Free title-tag interpolation and Pro dynamic CSS/template/meta output need particular attention when touched.

## Paired premium checks

Run the base checklist with Free + Pro, then verify premium templates/filters, multi-select and initial/archive term behavior, responsive values and thumbnail/title settings, slider load-more/filter replacement, term metadata and dependency/lifecycle states. Repeat mixed Free/premium grids in different render orders and preserve stored premium attributes when Pro is deactivated.

The detailed premium/service matrix belongs in [Pro testing](https://github.com/akshuvo/gridmaster-pro/blob/main/docs/testing.md); use its local checkout when available. Private license/service test evidence stays in Pro. No companion access or runtime run means those cases remain explicitly unverified. Record exact old/new pairs before making compatibility claims.

## Recommended future coverage (not installed)

Start with WordPress integration tests for aliases and initial/AJAX query parity, nonce/capability rejection and serialized-grid round trips; add taxonomy, empty-state and schema/lifecycle cases. Browser tests should cover isolated multiple grids, pagination/infinite-scroll retries, builder preview/serialization, and paired slider behavior against runtime assets. Mock license HTTP for deterministic Pro tests. Establish supported WordPress/PHP and Free–Pro pairs, reproducible minification and WPCS before making CI a release gate.

## Setup validation record

This documentation task executed tool discovery/version commands. Documentation/skill validation results are recorded below. Application syntax, PHPCS, browser/WordPress runtime, database lifecycle, external services and compatibility scenarios were **not run** for this documentation-only change. No automated application regression suite was added, and no application tests are claimed to pass.

Validation executed on 2026-11-04 across both repositories: 26 Markdown files and 158 local/companion links resolved; code fences and whitespace checks passed; all six skill frontmatters passed Ruby YAML parsing, required name/description checks, folder/name consistency and scaffold checks. The skill-creator Python validator could not start because PyYAML was unavailable in both checked Python environments; no dependency was installed. Git tracked diffs were empty, with only 13 new Markdown files per repository. These checks validate documentation structure, not application behavior. Companion repository URLs target files present in the local pair; they will resolve remotely only after the documentation is published there.
