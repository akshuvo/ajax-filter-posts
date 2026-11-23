# 0005 — Versioned Free extension registries and isolated legacy contracts

- Status: Approved; maintainer authorization recorded 2026-11-04. Implementation remains subject to milestone gates and task-specific verification.
- Date: 2026-11-04. Owner: Free. Supersedes: none.

## Context and problem

Current Pro consumes attributes/query hooks at specific priorities, template paths, responsive values and browser events. Constant-based detection is not a version gate. New blocks/facets need stable validated contracts without giving browser payloads arbitrary PHP/query authority. Existing hooks must remain usable for legacy grids.

## Decision

Free exposes extension API major 1 through `gridmaster_api()` and `GRIDMASTER_API_VERSION` only after services are ready, then fires `gridmaster_register_extensions($registry, $api_version)` once per request. Implementations remain in GridMaster namespaces. Use immutable validated definition/state/render-context values and typed-by-documentation provider interfaces compatible with PHP 7.4. Duplicate provider IDs, missing schemas and incompatible API ranges are rejected with diagnostics, not silently replaced. Namespaced IDs use `vendor/name`; namespaced configuration lives only in root `extensions`.

| Registry/interface | Required contract |
| --- | --- |
| CardBlockProvider | Block names, schema/version, post-context/asset declarations, compatibility check and unavailable fallback; PHP registration plus JS metadata. No saved executable callback names. |
| QueryOptionProvider | Saved option schema, visitor-control allowlist, validate/normalize/apply method; returns WP_Query clauses through the compiler, never raw request-to-SQL. |
| FacetProvider | Type/version, saved schema, normalize selected value, compile constraint, render accessible control; optional bounded count strategy. State is passed explicitly. |
| SortProvider | Registered sort ID → validated deterministic order clauses; stable ID tie-breaker, required fields and cost policy. |
| LayoutProvider | Registered card/grid preset or renderer ID, assets and required providers; resolves trusted server paths only. |
| LegacyConverter | Provider/version match, analyze(source), convert(snapshot) returning candidate + warnings/unmapped fields + confidence; never promote or overwrite the source. |

Public service interfaces: GridRepository get/list/create/save; DefinitionRepository load/validate/save with expected hash; QueryCompiler compile(definition,state,context); GridRenderer render and render_results; MigrationCoordinator analyze/create_candidate/approve/revert. Return value objects or WP_Error, with documented error codes and no output side effects. Only coordinator/repositories write data.

New hooks: `gridmaster_v2_definition_validated($definition,$context)` is an observation action; `gridmaster_v2_query_args($args,$context)` is a trusted server filter followed by mandatory safety enforcement; `gridmaster_v2_before_render($context)` / `gridmaster_v2_after_render($context,$result)` are actions; `gridmaster_v2_assets($handles,$context)` filters registered handles only. Context includes grid ID, runtime instance, phase (public/editor/legacy), API/schema version and required provider versions. Never serialize user permissions or private configuration to the browser.

JS: `window.gridmaster` exposes `registerFacetType`, `registerInspectorPanel` and a metadata/capability manifest for compatible saved controls, not write access or query authority. DOM CustomEvents `gridmaster:before-request`, `gridmaster:results-updated`, `gridmaster:request-error` dispatch on the target root with `{gridId,instanceId,requestId,state,resultMeta}`. Provider mount/update/unmount lifecycle prevents duplicate initialization on replacements; no document-wide slider scan for v2. PHP schema validation remains authoritative even if an editor control hides a feature.

API major increments for incompatible interface/state changes. Additive optional methods/fields require feature detection, default behavior and contract tests. Document minimum/maximum supported API majors and required features in Pro's compatibility manifest; plugin marketing versions are not API majors. Final release Free/Pro version pair must be pinned after tests, not guessed now.

## Compatibility and dependency policy

Pro boots new modules only after Free API exists and API major/features match, independent of plugin load order; absence produces a safe administrator notice and no Free function call. Free never requires Pro. Existing legacy APIs remain scoped to legacy renders with documented signatures/priorities and old browser events. Do not call all old global filters from v2 and hope their unscoped callbacks are compatible. Upgrade Pro's compatibility bridge before enabling v2 on a site using old Pro; without compatible Pro, keep premium legacy grids on legacy rendering and disable unsafe v2 promotion. Current Pro binary 1.2.3 is not certified against v2.

Missing optional presentation: safe documented base placeholder/fallback. Missing query/filter requirement: unavailable result, not an unfiltered wider query. Preserve stored extension namespaces/blocks byte-for-byte or refuse an incompatible save; Free-only operation must not erase premium data. Licensing remains a Pro-owned policy with deterministic mock tests; no license/customer secrets appear in registry manifests.

## Alternatives and trade-offs

Continue constants/unversioned filters only: low setup cost but no capability negotiation or validation; rejected for new engine. Merge Pro logic into Free: violates ownership/release isolation; rejected. Depend on a generic third-party DI/plugin framework: unnecessary maintenance; use small WordPress registries. Explicit provider contracts cost initial fixtures/tests but allow paired review and graceful absence behavior.

## Affected repositories and evidence

Free owns definitions/APIs and canonical contract. Pro owns providers, dependency bridge and premium conversion internals in [Pro ADR 0002](https://github.com/akshuvo/gridmaster-pro/blob/feature/gutenberg-grid-builder-v2/docs/adr/0002-versioned-free-provider-integration.md). [PRD](../prd/0002-gutenberg-grid-builder-v2.md), [query/facets](../gridmaster-2.0/query-facets.md), [builder](../gridmaster-2.0/builder.md), [testing](../gridmaster-2.0/testing.md). No new interface is implemented or compatibility certified by this ADR.
