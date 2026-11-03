---
name: gm-free-review
description: Review Free GridMaster changes for actionable correctness, security, performance, requirements, and Free/Pro regressions with severity and source references.
---

# Free code review

Read [AGENTS.md](../../../AGENTS.md), relevant PRD/ADRs, [architecture](../../../docs/architecture.md), [integrations](../../../docs/integrations.md), [compatibility](../../../docs/free-pro-compatibility.md), and [testing](../../../docs/testing.md).

- Establish the requested diff/base in this repository and inspect companion changes as relevant. Identify producer/consumer contracts before conclusions.
- Compare behavior with acceptance criteria and surrounding code. Review correctness, backward compatibility, WordPress conventions/localization, input types, context escaping, independent capability/nonce checks, query cost and browser/request lifecycle.
- Pay particular attention to legacy aliases, initial/AJAX query parity, privileged dispatcher, serialized grids/settings, template precedence and HTML/DOM protocol. Match checks to the change; the missing/placeholder suite is not coverage.
- Report actionable findings with severity, repository, file/line reference, trigger, impact and rationale. P0 means urgent broad failures, P1 serious issues needing prompt action, P2 normal defects and P3 minor actionable issues. Distinguish verified findings from hypotheses and pre-existing follow-up items.
- If no actionable findings remain, say so and identify material gaps/risks. Do not manufacture findings or edit code unless fixes are requested.
