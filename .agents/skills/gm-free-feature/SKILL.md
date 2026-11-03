---
name: gm-free-feature
description: Develop a scoped Free GridMaster feature from requirements through acceptance checks, including companion-plugin impact and documentation updates.
---

# Free feature development

Read [AGENTS.md](../../../AGENTS.md) and relevant [architecture](../../../docs/architecture.md), [integrations](../../../docs/integrations.md), and [compatibility](../../../docs/free-pro-compatibility.md).

- Find the PRD. For substantial work without one, draft behavior and acceptance criteria using the [PRD template](../../../docs/prd/README.md); keep minor enhancements lightweight. An idea is not approved scope.
- Trace affected Free producers and Pro consumers, including Free-owned builder serialization and stored premium attributes. Inspect both repositories when available and state unverified assumptions otherwise.
- Review [ADRs](../../../docs/adr/README.md). Propose a record for a significant new decision, with alternatives and compatibility implications; Proposed retrospective records are not approved policy.
- Present a focused implementation plan with minimal file changes, acceptance cases and the compatibility impact assessment. Preserve existing architecture unless changing it is authorized.
- Implement the scoped behavior. Use actual runtime assets and the verified tooling described in [development](../../../docs/development.md); no build command is established yet.
- Verify acceptance cases using [testing](../../../docs/testing.md), including Free alone and Free + Pro for shared changes. Distinguish executed results, characterization failures and skipped checks.
- Update owning docs/PRD evidence and both compatibility summaries as needed. Report behavior, tests, companion needs and risks.
