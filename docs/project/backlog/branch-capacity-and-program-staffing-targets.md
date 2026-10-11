---
title: Branch capacity and program staffing targets
created: 2026-10-11
source: [ronan, nickson]
status: needs-decision
size: M
priority: later
labels: [data, ux]
---

## Why

Two numbers the schema doesn't hold limit the decisions the reports can
support:

- **Branch capacity.** Nothing says how many volunteers a branch can host,
  so a look-ahead of stays can't show "full" or help decide whether to
  accept a volunteer or send them to another branch.
- **Volunteers needed per program.** The recap asks for "volunteers
  required/assigned". Without a target, `/matches` can't show a staffing
  gap.

Each one is a small nullable addition, and neither touches a derived rule.
Impact counts, the third number in this set, shipped first as indicators on
achievements.

## Done when

- UCESCO has answered the questions below.
- `Branch` gains an optional `capacity`, and `Program` an optional
  `volunteersNeeded`.
- One ADR (via `adr-scribe`) records both. Neither is personal data, so
  [ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md)
  needs no new purpose or retention statement. The ADR says so.
- Each addition feeds a chart:
  - the stay look-ahead, with a capacity line;
  - needed vs matching volunteers per open program.

## Questions for UCESCO

- How many volunteers can each branch host at once?
- Do volunteers pay programme fees? If they do, fee income could be the
  chart management cares about most. It would be financial and personal
  data, so it needs its own decision and is not part of this card.

## Notes & links

- Full ranking, including hours, attendance and stay cancellation, and the
  reasoning:
  [`brainstorm/14-dataviz-for-vm-and-management.md`](../../brainstorm/14-dataviz-for-vm-and-management.md)
  (options B and C).
- The staffing gap reuses `ProgramMatchFinder`'s rule
  ([ADR 0042](../../adr/0042-match-available-volunteers-to-programs-by-skills-or-past-activity-type-at-their-branch.md)).
- Recap items this card answers in part:
  [ucesco-meeting-requirements-raw](ucesco-meeting-requirements-raw.md).
- Charts on today's schema:
  [dataviz-first-charts](dataviz-first-charts.md).
