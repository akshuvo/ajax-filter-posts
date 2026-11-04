# Release investigation — 2026-11-04

Findings recorded before deployment implementation. Free checkout: `dev` at `87e01bb`; only pre-existing changes were the two approved Tested up to updates. Pro checkout: clean `main`; its code and release workflow are outside this task.

- GitHub default branch is `dev`; latest GitHub release/tag is `v3.4.15`. WordPress.org reports `3.4.17`, and SVN has tags through `3.4.17`. `3.4.18` is absent from both tag lists at inspection.
- Downloaded WordPress.org ZIP `3.4.15` has only three files: `ajax-filter-posts.php`, `README.md`, `readme.txt`. ZIP `3.4.17` has 45 files, including admin, inc, assets, templates, languages and bundled Appsero.
- Existing workflow checks out with `actions/checkout@v2`, invokes the mutable `10up/action-wordpress-plugin-deploy@stable`, and uploads a ZIP through softprops. It has no content manifest, deletion gate, version-consistency check, exact exported-tag comparison or independent ZIP verification. SVN username/password secret names exist; values were not read.
- Neither `.distignore` nor `.gitattributes` exists in the current source or the `v3.4.15` source. There is no actual npm build task. All current runtime assets are checked in.
- The 10up stable action resolves to `54bd289b8525fd23a5c365ec369185f2966529c2` (2.3.0). Its `deploy.sh` uses `git archive` without `.distignore`, then recursive `rsync --delete --delete-excluded`, schedules every missing SVN path for deletion, and commits. It offers dry-run mode but no independent pre-commit validation hook between staging and commit. It can accept an incomplete source package and publish its omissions.
- The `v3.4.15` Git tree includes all first-party nested files, but Appsero is a gitlink (`160000`) and no `.gitmodules` is tracked. `git archive` does not embed that dependency. Later commits `b252984` and `014e127` vendor the three PHP files. This explains a dependency omission, but does not explain removal of all the other nested files.
- GitHub run `18258238996` reports success. Its logs now return HTTP 410, so the precise historical loss point cannot be established. Do not claim that recursive rsync itself omitted nested files or that the three-file ZIP proves SVN currently has the same contents.
- Reference FedTax deploy.yml uses a published-release trigger, a build followed by 10up deployment, and a ZIP upload. Its verify.yml builds a package and runs disposable WordPress/Cypress checks. Its old action versions and Composer/npm steps are not appropriate dependencies to copy into this repository.
- Docker is installed but its daemon is unavailable. WP-CLI and PHP are available; disposable WordPress testing will use a separate temporary database if the local MySQL service binary can run.

## Sources

- [Previous successful GitHub run](https://github.com/akshuvo/ajax-filter-posts/actions/runs/18258238996)
- [3.4.15 download](https://downloads.wordpress.org/plugin/ajax-filter-posts.3.4.15.zip)
- [3.4.17 download](https://downloads.wordpress.org/plugin/ajax-filter-posts.3.4.17.zip)
- [10up deploy implementation](https://github.com/10up/action-wordpress-plugin-deploy/blob/54bd289b8525fd23a5c365ec369185f2966529c2/deploy.sh)
- [FedTax deploy](https://github.com/FedTax/simplesalestax/blob/master/.github/workflows/deploy.yml), [verify](https://github.com/FedTax/simplesalestax/blob/master/.github/workflows/verify.yml)

Further SVN, dry-run and publication evidence is recorded in [release instructions](release.md).

## Additional SVN evidence

`svn log --xml -v` and revision-pinned `svn list -R` show trunk already contained only three files and empty nested directories at r3353562 (before the automated 3.4.15 commit). Automated r3373115 changed two root files and deleted only the empty `lang` directory; the log does not record mass nested-file deletions. Thus the user's historical symptom is confirmed, but attributing its origin to that automated commit is unsupported. The old pipeline did fail to detect/preserve/restore a complete package. Later r3382254 added the complete 3.4.17 tag but updated only three trunk root files, leaving trunk incomplete. The known-good tag exports 45 files and matches all 45 hashes in the public 3.4.17 ZIP.
