---
title: Review adopting LiveComponent across the app
created: 2026-10-03
source: ronan
status: needs-decision
size: S
priority: later
labels: [ux, perf]
---

## Why

Every list interaction — sort, page, page size, filter, tab, date range —
is a full-page GET round-trip that re-renders the whole screen. ADR 0040
stopped those round-trips throwing the reader back to the top of the page,
but the whole page is still rebuilt each time; on `/usage` that includes
re-reading the access log to re-sort the Sign-ins table at the bottom.
Symfony UX LiveComponent would re-render just the component that changed.
Ronan asked (2026-10-03) whether that benefit is worth adopting it across
the app, rather than one screen at a time.

## Done when

An ADR records one of two outcomes:

- **adopt** — LiveComponent for a named set of screens, starting with a
  pilot (`/usage` Sign-ins is the obvious one), and the rule for when a
  new screen uses it; or
- **remove** — the package goes, with what that rules out.

Either way it amends ADR 0003's "installed, not yet used" line and closes
this card **and** [`cut-ux-live-component`](cut-ux-live-component.md),
which is the same decision taken from the other side.

The review weighs at least:

- shareable URLs and the Back button — today all list state lives in the
  query string (ADR 0011, ADR 0023);
- the JavaScript-off fallback every list currently has;
- the export sharing `listQueryBuilder()` with the index (ADR 0029);
- `RouteSmokeTest` and the WebTestCase suites, which assert on full pages;
- what a component re-render saves on `/usage`, whose cost is the log read.

## Notes & links

- [ADR 0003](../../adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)
  — why it is installed;
  [ADR 0011](../../adr/0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md),
  [ADR 0023](../../adr/0023-degrade-malformed-query-input-to-a-default.md),
  [ADR 0029](../../adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)
  — what a component would have to keep working.
- [ADR 0040](../../adr/0040-keep-the-readers-scroll-position-when-a-control-re-renders-the-same-page.md)
  — the Turbo `replace` + `turbo-refresh-scroll` fix this would
  build on or replace; it lists LiveComponent as a rejected alternative
  *for that fix only*.
- `.agents/skills/live-component/` — the project's skill for it.
