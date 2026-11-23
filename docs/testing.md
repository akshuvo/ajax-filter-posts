# Free testing and quality standards

## Builder follow-up verification — 2026-11-05

Drag smoothness follow-up against Free `1324fd1` plus working-tree changes and Pro `4820a0a`: insertion-point feedback now renders separately from the canvas, the hint reserves a constant second line, and native guide entrance animations are suppressed only during a block drag. The admin page is held at its current visual position during the drag, then its original scroll position is restored. Chrome checks at 1280×800 measured constant canvas coordinates and guide opacity `1` throughout sibling reordering; starting with the page scrolled 30px preserved the canvas coordinates and restored that scroll afterward. A Title moved into an empty Group and Undo restored its parent. At 1280×600 Gutenberg still identified the canvas as its scroll container; an instrumented 30px canvas scroll succeeded while the page was held stationary. This checks scroll availability, not sustained edge autoscrolling. At 1024×768, Focus Mode with reduced motion also kept canvas coordinates constant. Dropping outside the canvas cleared the page lock, temporary properties and target highlight. No browser warnings/errors appeared; `node --check admin/assets/builder.js` and `git diff --check` passed. All browser test edits were discarded. This editor-only follow-up did not rerun PHP suites or Free-only browser checks; touch dragging, Safari and Windows scrollbars remain unverified.

Drop-guide follow-up: the standalone canvas now mounts Gutenberg `BlockTools`, which renders its actual insertion indicators and floating block toolbar. Real browser drags verified a visible blue indicator, the active card/Group outline and destination guidance. A Title reordered between card siblings, then moved into a nested Group; Undo restored the previous nesting. Target highlights cleared on drag end, and no console warnings/errors appeared. Test edits were discarded. The single existing root Card is locked in the standalone shell while its child list remains editable; no block serialization or frontend rendering contract changes.

The preview title, device controls, actions and Close now share the native Modal header via `headerActions` on desktop. At 1280×800 the single 72px header increases iframe height from 533px to 602px (69px reclaimed). At 1024px controls remain in one row; narrower screens wrap without hiding Close or adding outer vertical scrolling. JavaScript syntax and whitespace checks passed.

Preview modal visual correction: the native WordPress header and children now use normal flex flow, without the duplicated header margin/padding or 500px frame minimum. Browser checks at a 1280×800 host confirmed no outer content/stage vertical overflow; only the iframe content scrolls vertically. Tablet/mobile iframe widths were exactly 768/375px, and the same three filtered results survived each device switch. A 375×812 host used a full-screen modal with reachable Close/actions/footer and no page overflow. Preview shell styling does not alter saved columns/card styles. A pre-existing invalid `core/group` serialization warning on saved grid #2 is separate from this layout correction.

The current standalone builder follow-up was checked on WordPress 7.1.4, PHP CLI 8.4.11 and Chrome 156, against Free `05e43f9` plus working-tree changes and Pro `4820a0a`. This record does not certify the minimum supported runtime or the complete redesign.

- `php tests/gm2-storage/run.php`: 83 assertions passed.
- `wp eval-file tests/gm2-storage/test_v2_redesign.php`: 78 assertions passed with Pro; 72 passed with `--skip-plugins=gridmaster-pro`.
- `wp eval-file tests/gm2-storage/test_builder_preview.php`: 23 assertions passed in each of those configurations. The [new regression script](../tests/gm2-storage/test_builder_preview.php) removes its own posts/category/grid in `finally`; it checks draft pagination and card identities, retained unsaved markup, no persistent preview writes, real empty results, searchable terms and rejected anonymous draft access. Run it only against a disposable development site. Local WP-CLI required PHP's `mysqli.default_socket` setting to point at the site's Local MySQL socket.
- `wp eval-file tests/gm2-storage/test_embed_integrations.php`: 32 assertions passed. Validates Embed_Helper methods, REST `/embed-preview` permissions and payloads, Elementor widget and Beaver Builder module registration, Classic WP Widget, and strict conditional loading (no builder files included when builders are inactive). Runs cleanly on disposable fixtures.
- Browser checks: consecutive field insertion, Group with nested Title, Undo/Redo across tab switches, saved taxonomy inclusion and zero card padding/radius. Preview filtering returned 200 and retained draft settings; a forced 403 preserved results and recovered through Retry. Layouts at 1440, 1280, 1024, 782 and 375 CSS pixels had no page overflow, kept Save reachable and bounded the editor to the viewport.

Minimum WordPress/PHP, actual activation-order/deactivation browser runs, full premium facets/templates, automatic recovery, edit locking and revision restoration remain unverified. Process-level `--skip-plugins` checks do not certify those lifecycle scenarios. A pre-existing Elementor deprecation notice is separate from these results.

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
