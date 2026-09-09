---
title: Drop /activities/new, the single-volunteer form
created: 2026-09-09
source: ponytail-audit
status: needs-decision
size: S
priority: later
labels: [ux]
epic: simplification
---

# Drop /activities/new, the single-volunteer form

## Why

`/activities/new-batch` handles N≥1 and is what the home screen links to
everywhere, so the single-volunteer form is a second way to do the same
thing.

## Done when

The route is gone, or the reason to keep it is written down.

**`ActivityFormType` stays either way** — `/activities/{id}/edit` uses it.

## Notes & links

**Confirm nothing bookmarked or documented points at the old route
first.** `/usage` can answer the bookmarked half now: it counts by route
pattern, so `/activities/new` shows up there distinctly from
`/activities/new-batch`. Check it over a range long enough to mean
something before deleting.

`tests/Functional/ActivityControllerTest.php` and
`tests/Functional/RouteSmokeTest.php` both walk it —
`RouteSmokeTest` picks routes up from the router, so it needs no edit.

[`../../brainstorm/07-ponytail-audit.md`](../../brainstorm/07-ponytail-audit.md)
