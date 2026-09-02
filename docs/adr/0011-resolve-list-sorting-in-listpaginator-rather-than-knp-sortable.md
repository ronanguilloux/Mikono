# 11. Resolve list sorting in `ListPaginator` rather than with Knp's sortable support

Date: 2026-09-01

## Status

Accepted

## Context

[ADR 0009](0009-adopt-knppaginatorbundle-for-list-pagination.md) adopted
KnpPaginatorBundle for the windowing math behind every list view, and
noted in passing that `knp_pagination_sortable()` "makes sortable columns
cheap if they are ever wanted, at no cost today". Sortable columns are now
wanted: every column header on the seven list views — the six CRUD indexes
(Volunteer, Project, ActivityType, Activity, User, Escort) plus both
breakdown tables on `/reports` — should be a link that sorts ascending on
the first click and descending on the second. This ADR extends 0009 rather
than replacing it; the pagination decision stands unchanged.

Reading the bundle's installed source, rather than its README, retired
that cheap-sortable-columns assumption on three counts.

**`knp_pagination_sortable()` needs a translator.** It is registered on
the same `Pagination` Twig extension whose constructor requires a
`TranslatorInterface`. This app is single-locale and deliberately has no
translator — exactly the blocker already recorded in
`config/packages/knp_paginator.yaml` and
`templates/components/PaginationBar.html.twig` for
`knp_pagination_render()`, and a knock-on of the bundle's skipped contrib
Flex recipe (`composer.json` sets `allow-contrib: false`), which is what
would otherwise have turned the translator on.

**Knp's sort parameter must literally *be* the DQL path, and rejects
anything else with a 500.**
`Knp\Component\Pager\Event\Subscriber\Sortable\Doctrine\ORM\QuerySubscriber::items()`
reads the raw `sort` parameter, splits it on `.` into alias and field, and
feeds the pair to `OrderByWalker`; a value outside `sortFieldAllowList`
throws `InvalidValueException`. That is the opposite of the contract
`ListPaginator` already keeps for `page` and `perPage`, where bad input
never errors — and this app has one non-technical user working from
bookmarked URLs. It would also leak DQL aliases such as `v.lastName` into
the query string, and force `?sort=totalDays` to mean different things on
`/reports` (where `ArraySubscriber` expects `[totalDays]`-style property
paths) than on a CRUD index.

**Knp's sorting had to be actively switched off, not merely left unused.**
Its default `sortFieldParameterName` is `'sort'` — the very parameter this
feature owns. Left alone, `?sort=activityType` on `/activities` would
reach `OrderByWalker` as `a.activityType`, an association rather than a
scalar field, i.e. a 500.

## Decision

Keep KnpPaginatorBundle for the windowing math and resolve `sort` and
`direction` in `App\Pagination\ListPaginator`, applying the `ORDER BY`
ourselves with the bundle's own sorting explicitly disabled.

Concretely:

- `ListPaginator` gained `sortState()`, `applySort()` (for a
  `QueryBuilder`) and `sortArray()` (for `/reports`, which paginates an
  in-memory breakdown). It remains the one place `page`, `perPage`, `sort`
  and `direction` are read off the query string.
- Knp's sorting is disabled by passing
  `PaginatorInterface::SORT_FIELD_PARAMETER_NAME => null` in the options
  of both `paginate()` calls. Both Sortable subscribers short-circuit on a
  null field name, and `Paginator::paginate()` merges options with a plain
  `array_merge` and no `OptionsResolver`, so `null` passes through
  cleanly. This was chosen over renaming the bundle's parameter to
  something obscure but real, which stays forgeable by hand in the URL
  bar.
- **URL contract:** `?sort=<column key>&direction=asc|desc`. Column keys,
  never DQL paths, so the same `?sort=totalDays` means the same thing
  everywhere. A sort link resets `page` to 1 and carries `perPage` and the
  `/reports` `tab` through.
- Each controller declares a `SORT_MAP` constant mapping a column key to
  its DQL field or fields (to an array key on `/reports`). Because the map
  *is* the whitelist, no user-supplied string ever reaches DQL. An unknown
  or absent `sort` falls back to the view's existing default order, the
  same forgiving posture `perPage` already has.
- Sortability is carried by the map alone, not also by a flag on the
  column definition — one source of truth. Three columns opt out simply by
  not appearing in their map: ActivityType's Description (free text),
  Activity's Duration (enum cases plus a free-text `durationOther`, so any
  `ORDER BY` would be arbitrary), and User's Role (derived from the
  `roles` JSON via `isAdmin()`, not a column).
- Ties break deterministically. `applySort()` keeps the repository's own
  `ORDER BY` appended after the requested sort: sorting Volunteers by
  Status drops every row into two buckets, and without a secondary order
  SQLite may hand back a page-1 row again on page 2. On the array side
  PHP's `usort` has been stable since 8.0, so the calculator's own
  `totalDays` order survives as the tie-break for free.
- `/reports` sorts the whole list before `paginateArray()`, not the page.
  Nulls sort last in both directions — an empty "Most recent" cell is
  missing data, not a small value. `ActivitySummaryCalculator` was not
  modified, so this stayed outside the Infection scope.
- A new readonly `App\Pagination\SortState` carries sortable keys, active
  key and direction to templates. `DataTable` takes it as one optional
  prop and renders plain headers when it is null, which is what the
  `/reports` print panel needs.
- Mobile is in scope. The Activities index wraps its `DataTable` in
  `hidden md:block`, so `templates/components/SortSelect.html.twig` emits
  the same two parameters from a pair of selects, reusing the existing
  `auto-submit` Stimulus controller — the same reason `PaginationBar` was
  already hoisted out of `DataTable` on that screen.
- Accessibility: the active `<th>` carries `aria-sort`, and the link text
  stays the bare column label with the ↑/↓ arrow as a sibling outside the
  `<a>`, because several tests resolve headers and buttons by text.

## Consequences

- **Positive:** the sort contract is ours, so bad input degrades to the
  view's default order instead of a 500, and column keys stay stable,
  readable and identical across query-backed and array-backed lists — no
  DQL aliases in bookmarked URLs. The controller's `SORT_MAP` doubles as
  the whitelist, so adding a sortable column anywhere is a one-line map
  entry with no template change, and there is no second place a column can
  claim to be sortable. Sorting and pagination are resolved in the same
  class, which is why a sort link keeps `perPage` and resets `page`
  without any per-view code.
- **Negative / trade-offs:** we now own comparison semantics that a
  library would have supplied. Enum columns (Project's Location and
  Ownership) sort by the stored backing value rather than `label()`; for
  both enums today the two orders coincide (`kibera` < `mombasa`,
  `partner` < `ucesco`), but a future case where they disagree needs its
  own handling. String ordering uses SQLite's default BINARY collation and
  is therefore case-sensitive — unchanged from the pre-existing default
  ordering, but more visible now that a user can re-sort at will. Two sort
  paths exist, `applySort()` for queries and `sortArray()` for
  `/reports`, and their null and tie-break behaviour has to be kept
  deliberately aligned. Disabling the bundle's sorting relies on
  `SORT_FIELD_PARAMETER_NAME => null` surviving a future Knp major, which
  a stricter options resolver could break.
- **Reversibility:** cheap in the direction that matters. Templates and
  URLs speak only `SortState` and column keys, so the resolution strategy
  underneath can change without touching markup or bookmarks. Dropping
  sorting altogether means removing the maps and the `sortState` prop.
  Moving *to* Knp's sortable support would be the expensive direction —
  it would mean adopting a translator, exposing DQL paths in URLs, and
  giving up the "bad input never errors" contract — which is the point of
  recording this now.

## Alternatives considered

### 1. Use `knp_pagination_sortable()` with `sortFieldAllowList`

**Rejected.** The helper is unreachable without a translator this app
deliberately does not have, and the parameter it reads must be the DQL
path itself, with anything off the allow-list throwing
`InvalidValueException`. That puts `v.lastName` in bookmarked URLs and
turns a typo into a 500 for the single non-technical user this app is
built for, against a `ListPaginator` contract where `?page=abc` already
degrades quietly. It would also split the meaning of `?sort=totalDays`
between the CRUD indexes and `/reports`.

### 2. Pull in `symfony/translation` to unlock the bundle's helpers

**Rejected.** It would mean running a translator over templates that
contain no translatable strings, purely to reach markup we would restyle
anyway — the same trade ADR 0009 already declined for
`knp_pagination_render()` when it chose a project-owned Tailwind template
instead. Enabling it here would also mean either flipping `allow-contrib`
project-wide or hand-wiring a second bundle for markup we do not want.

### 3. Declare the sortable field on the column definition alongside the map

**Rejected.** It reads well in a template — the column knows how it sorts
— but it means a column's sortability is asserted in two places that can
drift, and a header could advertise a sort key the controller's map does
not honour. Keeping the map as the single source means an unsortable
column is expressed by omission, which is how Description, Duration and
Role opt out today.
