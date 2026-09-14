---
title: Totals per branch on /reports
created: 2026-09-14
source: ronan
status: needs-design
size: M
priority: next
labels: [data, ux]
---

# Totals per branch on /reports

## Why

`/reports` totals days per volunteer and per project, but not per branch.
Now that every activity belongs to a branch
([ADR 0026](../../adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md),
[ADR 0027](../../adr/0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md)),
"how much work happened at Mombasa versus Nairobi" is one query away, but
the VM still has to add up project rows by hand to answer it.

## Done when

The open design questions are answered, then built:

- **Shape:** a separate table like the per-project one, a row of tiles, or
  project rows grouped under their branch?
- **Zero rows:** show branches with no activity (Samburu, Uganda, USA
  today) or hide them?
- **Print panel:** does it belong there?

Then:

- `/reports` shows days per branch, using the same duration-to-days rules
  as the existing totals, counted by the activity's stay branch.
- `tests/Integration/` covers the aggregation, including a half day and an
  "Other" duration.

## Notes & links

- `src/Report/ActivitySummaryCalculator.php` (`summarizeByProject()` is the
  closest prior art). It is in `composer infection`'s scope.
- A new summary adds another walk over every activity. Read
  [`reports-triple-walk-performance`](reports-triple-walk-performance.md)
  before adding a fourth.
