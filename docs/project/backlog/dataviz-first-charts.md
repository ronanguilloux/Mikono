---
title: First charts on the reports, from today's schema
created: 2026-10-11
source: ronan
status: needs-decision
size: L
priority: later
labels: [ux, data]
---

## Why

The app has no charts. `/reports/volunteers` is KPI tiles and tables, so
"are we growing?", "where does the effort go?" and "who is on site in
three weeks?" each mean reading a table row by row. The current schema
already holds enough to draw twelve useful charts. The exploration ranks
them for two audiences, the Volunteer Manager and UCESCO top management.
What's still open is which three management actually wants first.

## Done when

- UCESCO (via Nickson) has picked the first three charts. The proposal is
  these, each drawn on today's schema:
  1. Volunteer days per month, stacked by branch, at the top of
     `/reports/volunteers?tab=month`.
  2. A look-ahead of stays over the next 8–12 weeks, grouped by branch,
     with a line for today.
  3. Days per project, coloured by own vs partner (`ProjectOwnership`).
- An ADR (via `adr-scribe`) records how charts are drawn. The proposal is
  inline SVG from a TwigComponent, with no chart library.
- Each chart sits above the table it summarises. The table stays as it
  is: it is what screen readers read and what the export writes
  ([ADR 0029](../../adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)).
- Each chart prints on the existing print-friendly view.

## Notes & links

- Ranked candidates, rejected options and the chart-library reasoning:
  [`brainstorm/14-dataviz-for-vm-and-management.md`](../../brainstorm/14-dataviz-for-vm-and-management.md).
- No gender charts:
  [ADR 0037](../../adr/0037-record-a-volunteers-gender-for-accommodation-pairing.md)
  keeps gender on the profile page only.
- Data already computed: `PeriodTotalsCalculator` for the month tab,
  `ActivitySummaryCalculator` rows for the project tab, and `StayRepository`
  for the look-ahead.
- Impact per month can be charted from achievements' indicator and
  quantity. Charts that need fields not yet added (capacity, staffing
  targets) are in
  [branch-capacity-and-program-staffing-targets](branch-capacity-and-program-staffing-targets.md).
