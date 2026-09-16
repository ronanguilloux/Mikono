---
title: Export any list to CSV or Excel, filtered or whole
created: 2026-09-14
source: ronan
status: needs-decision
size: L
priority: next
labels: [ux, data, security]
---

# Export any list to CSV or Excel, filtered or whole

## Why

The VM can read every list on screen, but can't take one away. Sending a
branch's activities to someone, or working on the volunteer list in a
spreadsheet, means copying rows out of the browser by hand. Each list
index needs an export with two scopes:

- **a. Current view:** the filters and sort applied on screen right now.
- **b. Whole list:** every row, ignoring the filters.

## Done when

- Every list index (the seven views that use `ListPaginator`: activities,
  volunteers, projects, activity types, escorts, branches, users) has an
  Export control that offers both scopes.
- **Current view** exports every row that matches the on-screen filters and
  sort, not just the visible page. `page` and `perPage` are ignored.
- **Whole list** exports every row in the view's default order.
- The file has the same columns as the on-screen table, with plain values
  (no `badges` decoration, see `DataTable` in `CLAUDE.md`), a header row,
  and a filename that names the list and the date, e.g.
  `activities-2026-09-14.csv`.
- A malformed filter on the export URL falls back to the default, the same
  as the list does
  ([ADR 0023](../../adr/0023-degrade-malformed-query-input-to-a-default.md)).
  It never causes a 400 or 500.
- Each area's functional test covers one filtered export and one whole-list
  export, and checks the row count of each. `RouteSmokeTest` picks up the
  new GET routes automatically.

## Decisions to make first

- **Format:** CSV only (no new dependency, and Excel opens it), or real
  `.xlsx` too (`phpoffice/phpspreadsheet`, a new dependency, which needs an
  ADR). If CSV only, write a UTF-8 BOM so Excel doesn't garble accented
  names.
- **Who may export:** the volunteers and users lists hold personal data.
  Decide whether export is admin-only for those, or open to every
  signed-in user.

## Notes & links

- Reuse the list's own query builder and `ListPaginator`'s sort resolution
  ([ADR 0011](../../adr/0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md)).
  Don't build a second query per area, or the export and the screen will
  drift apart.
- Build the export link from `app.request.query.all`, never
  `app.request.query.get()`. Drop `page` and `perPage`, and drop all filter
  parameters for "whole list".
- Stream the rows (`StreamedResponse` + `toIterable()`) rather than loading
  every entity at once.
- `/activities` is the only list with filters today (`?volunteer=`, plus
  [`activities-branch-filter`](activities-branch-filter.md) planned). On the
  other lists, both scopes differ only in sort order until those lists get
  filters.
- Out of scope: `/reports` and `/usage`. They are aggregates, not lists; the
  Reports print panel covers the first.
