---
title: Show pagination controls only when a list needs them
created: 2026-10-03
source: ronan
status: needs-decision
size: S
priority: later
labels: [ux]
---

## Why

Every paginated list shows its page-size select and page links even when
everything fits on one page. On short lists — `/usage`'s In-page actions
(four possible rows today) and Sign-ins, most CRUD indexes — that is a
"Show 25 per page" control and a disabled « 1 » that do nothing. Ronan
asked for the controls to appear only once a list is longer than the
smallest page size, in every list.

## Done when

- `PaginationBar` hides its page-size form and page links when the total
  is ≤ the smallest `ListPaginator::PER_PAGE_OPTIONS` (25), in every list
  — the hoisted bar on the Activities index included.
- Tests that assume the bar is always there are updated (see Notes).
- The rule is written into
  [ADR 0009](../../adr/0009-adopt-knppaginatorbundle-for-list-pagination.md)'s
  Decision, rewritten in place via `adr-scribe`, beside "`perPage` offers
  25 / 50 / 100 / All … identical everywhere". The finished card lands as
  that ADR change, not a `done.md` line.

## Notes & links

- **Why this needs a decision:** it reverses the reviewed design recorded
  in `templates/components/PaginationBar.html.twig`'s docblock — "Always
  visible, even at a single page … the page-size selector is the point: it
  has to be reachable before the list is long."
- **Open question:** does the "Showing 1–4 of 4" count stay when the
  controls go? It's the only part that still says something on a short list.
- Hide by total, not by page count: on `perPage=100` a 30-row list is one
  page, but the reader still needs the select to get back to 25.
- Tests touching the bar: `ActivityControllerTest` (around lines 649 and
  967), `VolunteerControllerTest` (around line 698, the ADR 0040 controls
  check), `UsageControllerTest::theUsageControlsKeepTheReadersPlace`.
