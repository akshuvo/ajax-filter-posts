---
name: gm-free-bugfix
description: Investigate and fix Free GridMaster bugs by tracing the failing request, identifying the root cause, and verifying regression and Free/Pro risks.
---

# Free bug investigation and fix

Read [AGENTS.md](../../../AGENTS.md), relevant [architecture](../../../docs/architecture.md), [integrations](../../../docs/integrations.md), and [ADRs](../../../docs/adr/README.md).

- Capture expected versus actual behavior and the smallest reproduction: plugin commits/versions, WordPress/PHP/theme, shortcode or saved attributes, role, nonce setting, and initial render versus AJAX. Use redacted/mocked or authorized staging data for Pro service bugs.
- Reproduce when feasible. Otherwise characterize from source/logs and state that limit; do not claim reproduction.
- Trace the request through normalization, query/hooks, template and runtime assets as relevant. Distinguish dormant saved-grid paths from visible UI and inspect the priority-15 Pro handoff when affected.
- Assess companion impact with [compatibility](../../../docs/free-pro-compatibility.md). Review relevant ADRs; a routine fix does not require a new PRD/ADR.
- Make the smallest safe fix in authorized scope. Avoid opportunistic cleanup and add a focused regression case where meaningful; existing validation gaps are not safe precedents.
- Verify the reproduction and relevant nearby cases from [testing](../../../docs/testing.md): initial/AJAX parity, old shortcodes/data, empty/error states, multiple grids and both plugins for integration changes. Record actual results and gaps.
- Update docs for important behavior changes; summarize root cause, fix, regression evidence and unresolved risks.
