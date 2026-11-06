# Free development workflow

## Discovery and tools

Inspected on 2026-11-04. Source baseline: `dev` at `2172483`, plugin `3.4.17`. Entrypoint: `ajax-filter-posts.php`. The sibling Pro repository was accessible on `main` at `7fc6d47`. `package.json` contains only a failing placeholder `test` script; it defines no build or lint script. `.phpcs.xml.dist` references WordPress-Core/Docs/Extra and WordPress, but no Composer manifest, lockfile, installed repository-local runner, PHPUnit or browser-test configuration was found. Bundled Appsero is checked in.

Both repositories have their own `.git` roots and initially clean worktrees. Existing documentation consisted of `README.md` and `readme.txt`; no existing `AGENTS.md` or local skills were found. Branches and installed tools are observations of this workspace, not permanent requirements.

Start each task from the intended repository:

```sh
git rev-parse --show-toplevel
git branch --show-current
git status --short
```

A sibling checkout is convenient, not required. Verify its root/branch separately. Never assume a commit or branch operation in one plugin covers the other. No new branch, commit or remote action was made during this setup.

This host exposed PHP CLI 8.4.11, Node v25.8.1 and npm 11.11.0 (version commands executed). `composer` and `wp` were found on PATH, but their execution/environment was not verified. `phpcs` was not found on PATH. CLI PHP availability does not establish the site's PHP version or supported-runtime compatibility. No WordPress/database/browser session was exercised for this setup.

## Task lifecycle

1. Understand the request and read this repository's [AGENTS.md](../AGENTS.md) plus relevant architecture, integration and testing docs.
2. Trace the affected code; identify Free/Pro ownership and inspect the companion's instructions/source when relevant.
3. For substantial features, create or update a [PRD](prd/README.md). For significant architectural choices, review existing [ADRs](adr/README.md) and propose a decision record. Routine bug fixes need neither by default; an idea is not a requirement.
4. Present a focused plan with acceptance checks and the [cross-repository impact assessment](free-pro-compatibility.md). Keep unknowns explicit.
5. Implement the smallest coherent change using existing WordPress patterns. Preserve compatibility and avoid unrelated refactors.
6. Run relevant [automated/manual checks](testing.md), assess both plugins when shared behavior changes, and review security/performance/regression risks.
7. Update owning docs and requirement/decision evidence as behavior changes. Cross-link related records across repositories without copying private implementation into Free.
8. Summarize changes, exact checks/results, companion impact, gaps and remaining risks. Remote publication is a separate authorized action.

Use the local `gm-free-feature`, `gm-free-bugfix`, and `gm-free-review` skills for the corresponding task. Each lives under `.agents/skills/` and routes to the relevant docs.

## Conventions and generated assets

Use surrounding PHP namespace/helper conventions, jQuery's existing event model, WordPress APIs and the owning plugin's text domain. Prefer context-specific escaping, input validation/unslashing and independent capability/nonces. Follow existing API/selector spellings even when awkward; see [integrations](integrations.md). Avoid broad formatting changes or vendor-library updates with an unrelated fix.

Readable JS and minified runtime JS are both tracked. There is **no verified build/minification command**. Before an asset change, establish the maintainer's tool/version and which runtime files need regeneration; do not invent `npm run build`, install new dependencies, or hand-edit only a minified file as a default. Test the asset WordPress actually enqueues as well as the readable source. Some CSS is loaded directly or inline; inspect the affected enqueue/template rather than assuming every CSS file needs minification.

## Gutenberg Card Block Registration Requirements

Whenever adding or modifying a block meant for use inside cards or the GridMaster Builder, verify all 6 registration touchpoints:

1. **PHP Registration**: `register_block_type()` in `inc/Blocks.php` (Free) or `inc/Blocks/Pro_Blocks.php` (Pro).
2. **Editor JS Registration**: `registerBlockType()` in `assets/blocks/editor.js` (Free) or `assets/js/pro-blocks-editor.js` (Pro).
3. **Card Container `allowedBlocks` Whitelist**: Must be in `defaultAllowedBlocks` in `ajax-filter-posts/assets/blocks/editor.js` and `proBlocks` array in `gridmaster-pro/assets/js/pro-blocks-editor.js`. Omitting this causes Gutenberg's `InnerBlocks` to silently hide the block from the in-canvas `+` inserter.
4. **Builder Inserter Catalog**: Must be added to `MoreBlocksPopover` -> `defaultCategories` in `ajax-filter-posts/admin/assets/builder.js`.
5. **Block Metadata Map**: Must be added to `blockMetaMap` in `ajax-filter-posts/admin/assets/builder.js` for proper tree outline/breadcrumbs.
6. **Canvas Quick-Add**: Add a toolbar button in `ajax-filter-posts/admin/assets/builder.js` if the block is a primary card component.

## Deployment boundaries

[demoServerPush.yml](../.github/workflows/demoServerPush.yml) deploys pushes to `main` by FTP. [wporg.yml](../.github/workflows/wporg.yml) validates PRs, pushes to `dev` and manual dispatches without publishing. Only a published non-prerelease GitHub release deploys its exact tag commit to WordPress.org through the guarded [release script](../scripts/release.py). It builds a deterministic ZIP, gates recursive content/deletions before SVN commit, verifies exported SVN and both published ZIPs, and uploads evidence. Runtime files follow an explicit inventory; docs, agents and development tools are excluded. No npm build step is needed for checked-in assets.

Follow the [release runbook](release.md) for secrets, version preparation, dry runs, publishing/monitoring with CLI, Chrome verification and recovery. Commits, pushes, publication and PR creation require task authorization. After a failed production attempt, obtain approval before another attempt. Pro remains a separate release unit.

## Documentation ownership

Keep this repository's architecture and implementation details here. Maintain only relevant compatibility notes in both repositories and link related ADRs/PRDs by repository URL when they live elsewhere. Update source baselines when revalidating behavior; old discovery versions do not define a support promise.
