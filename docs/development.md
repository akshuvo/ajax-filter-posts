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

## Deployment boundaries

[demoServerPush.yml](../.github/workflows/demoServerPush.yml) deploys pushes to `main` by FTP. [wporg.yml](../.github/workflows/wporg.yml) runs on release events of type `released`, deploys to WordPress.org through 10up's action, generates a ZIP and uploads it to the release. The optional npm build step is commented out. No checked-in `.distignore` was found; packaging of newly added docs/skills should be reviewed before a future release. Workflow configuration does not prove credentials or successful deployment.

Do not use pushes/releases as verification commands. Publishing, commits, branch pushes and PR creation require explicit task authorization. Release packaging and toolchain improvements are follow-up proposals in [ideas](ideas/README.md), not changes introduced here.

## Documentation ownership

Keep this repository's architecture and implementation details here. Maintain only relevant compatibility notes in both repositories and link related ADRs/PRDs by repository URL when they live elsewhere. Update source baselines when revalidating behavior; old discovery versions do not define a support promise.
