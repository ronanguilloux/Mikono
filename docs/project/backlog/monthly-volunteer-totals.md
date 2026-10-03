---
title: Monthly volunteer totals
created: 2026-10-03
source: edna
status: needs-design
size: M
priority: now
labels: [data, ux]
---

## Why

Edna wants the number of volunteers in each month, to track the trend and to
compare months, for example this August against last August, or this month
against the previous one. Nothing in the app counts anything per month:
`/reports` totals over all time, and the home screen covers today and
tomorrow. She doesn't know which screen it belongs on, so the card proposes
one, and the proposal is to be discussed with her.

## Proposal, to discuss with Edna

**Where:** a **"Month" tab on `/reports`** (`?tab=month`), next to Branch,
Project, Program and the other breakdowns.

- It is the screen that already answers "how many, over what".
- It inherits the print panel and the tab pattern.
- Each row is one Nairobi calendar month
  ([ADR 0024](../../adr/0024-treat-dates-as-calendar-days-in-nairobi-time.md)),
  newest first, so comparing months means reading down a column.

The other places, and why not:

- the home screen is a daily roster;
- `/volunteers` is a list of people, not of periods;
- a KPI tile holds one figure, not a series.

**What "a volunteer in a month" means.** This is the decision that matters
more than placement. Proposed columns:

| Column | Counts |
| --- | --- |
| Present | Distinct volunteers with a stay overlapping the month. This is the headline figure, and it follows the same rule as Present in [ADR 0026](../../adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md) (`Volunteer::getStatus()`) |
| Arrived | Stays starting in the month (new arrivals) |
| Engaged | Distinct volunteers with at least one activity dated in the month |

Months in the future, which only upcoming stays can fill, carry the `Planned`
badge, as a future "Most recent" already does on `/reports`.

## Questions for Edna

- Which figure does she mean by "total", **present**, **arrived** or
  **engaged**? Or all three, as proposed?
- **Per branch** (Kibera and Mombasa side by side), or one total?
- **How far back**: from the first recorded stay, or only the last 12 or
  24 months?
- **Comparison**: is reading down the rows enough, or does she want a
  "vs. previous month" or "vs. same month last year" column?

## Done when

- Edna has agreed the placement and the columns. That moves this card to
  `ready`, with this section rewritten to the agreed design.
- The tab renders one row per month from the earliest stay to the latest. The
  figures are computed in a `src/Report/` service with an integration test,
  and the controller holds no logic. Tests that must pass:
  - a stay spanning two months counts in both;
  - a volunteer with two stays in one month counts once;
  - the month boundaries are correct in Nairobi time.
- `RouteSmokeTest` walks `?tab=month` without any change (it is the same
  route).

## Notes & links

- Prior art:
  - `ActivitySummaryCalculator` and `ReportMetricsCalculator` (the `src/Report/`
    services behind `/reports`);
  - `ReportController::columnsFor()` and the tab `match`;
  - the DataTable `badges` prop (CLAUDE.md, `Planned`).
- Stays never overlap, so "distinct volunteers present in a month" is a count
  of volunteers who have *any* stay overlapping it. No de-duplication is needed
  across stays at different branches in the same month, beyond `DISTINCT`.
- `/reports?tab=source` already counts "present over a period" (a stay
  overlapping it) per year:
  `VolunteerRepository::countPresentBetweenBySource()`. Reuse that rule for
  the Present column so the two screens agree
  ([ADR 0041](../../adr/0041-record-where-each-volunteer-came-from-as-a-seeded-list-of-recruitment-sources.md)).
- The [/reports performance card](reports-triple-walk-performance.md) already
  flags that the page walks every activity several times. Count stays per month
  in SQL, or in one pass over stays, not by another walk over activities.
