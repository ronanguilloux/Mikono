---
title: First charts on the reports, from today's schema
created: 2026-10-11
source: ronan
status: needs-decision
size: M
priority: later
labels: [ux, data]
---

## Why

The app has one chart, impact by month on `/reports/achievements`.
`/reports/volunteers` is still KPI tiles and tables, so
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
- Each one is drawn the way
  [ADR 0048](../../adr/0048-draw-charts-as-server-rendered-html-and-css-from-twig-components.md)
  says: HTML and CSS from a Twig component, reusing `ColumnChart` where
  the form fits.
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
- Already drawn: impact per month on `/reports/achievements`
  ([ADR 0047](../../adr/0047-count-achievements-with-an-optional-indicator-and-quantity.md))
  and volunteers on site per week against capacity on the home screen
  ([ADR 0049](../../adr/0049-record-branch-capacity-and-program-volunteers-needed-as-optional-targets.md)) — the brainstorm's
  chart 7. Proposal 2 above, one row per volunteer, is still to do.
  Volunteers present per period now heads `?tab=month` (`done.md`,
  2026-10-17). Proposal 1, days stacked by branch, would replace it.
  The brainstorm's chart 4, where volunteers come from, is drawn by
  country of residence on `/reports/origins`: a world map and a bar chart
  of every country (`done.md`, 2026-10-18).
