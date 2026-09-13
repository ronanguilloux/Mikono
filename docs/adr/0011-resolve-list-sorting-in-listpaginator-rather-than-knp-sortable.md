# 11. Resolve list sorting in `ListPaginator` rather than with Knp's sortable support

Date: 2026-09-01

## Status

Accepted

## Context

Every column header on the list views — the CRUD indexes and both breakdown
tables on `/reports` — should sort ascending on first click and descending on
the second. Pagination stays on KnpPaginatorBundle
([ADR 0009](0009-adopt-knppaginatorbundle-for-list-pagination.md)), but its
sortable support does not fit, for three reasons found in its source:

- **`knp_pagination_sortable()` needs a translator**, which this
  single-locale app deliberately does not have.
- **Knp's `sort` value must literally be the DQL path** (`v.lastName`), and
  anything outside `sortFieldAllowList` throws `InvalidValueException` — a
  500 for a typo, where `ListPaginator` guarantees bad input never errors. It
  would also put DQL aliases in bookmarked URLs and make `?sort=totalDays`
  mean different things on `/reports` (array paths) and on a CRUD index.
- **Knp's sorting has to be switched off, not merely unused.** Its default
  parameter name is `sort`; left on, `?sort=activityType` would reach its
  walker as an association path and 500.

## Decision

**Keep Knp for the windowing math; resolve `sort` and `direction` in
`App\Pagination\ListPaginator` and apply the `ORDER BY` ourselves, with Knp's
sorting disabled.**

- `ListPaginator` is the one place `page`, `perPage`, `sort` and
  `direction` are read. It exposes `sortState()`, `applySort()` (for a
  `QueryBuilder`) and `sortArray()` (for `/reports`).
- Knp's sorting is disabled by passing
  `PaginatorInterface::SORT_FIELD_PARAMETER_NAME => null` to `paginate()`.
  Both Sortable subscribers short-circuit on a null name, and Knp merges
  options without a resolver, so `null` passes through. A renamed-but-real
  parameter would stay forgeable from the URL bar.
- **URL contract:** `?sort=<column key>&direction=asc|desc`. Column keys,
  never DQL paths. A sort link resets `page` to 1 and keeps `perPage` and
  `/reports`' `tab`.
- **Each controller's `SORT_MAP` const is the whitelist**: column key → DQL
  field(s), or array key on `/reports`. No user string reaches DQL. An
  unknown or missing `sort` falls back to the view's default order. A column
  opts out by not being in the map — there is no second sortability flag on
  the column definition.
- **Ties break deterministically.** `applySort()` appends the repository's
  own `ORDER BY` after the requested sort; without it, a low-cardinality
  sort (Status) lets SQLite repeat rows across pages. On arrays, `usort` is
  stable since PHP 8.0, so the calculator's order is the tie-break.
- `/reports` sorts the whole list before paginating, not the page. Nulls
  sort last in both directions: an empty "Most recent" is missing data, not
  a small value.
- `App\Pagination\SortState` (readonly) carries sortable keys, active key
  and direction to `DataTable`, which renders plain headers when it is null
  (the `/reports` print panel).
- Mobile: where `DataTable` is hidden below `md` (Activities),
  `templates/components/SortSelect.html.twig` emits the same two parameters
  from selects via the `auto-submit` Stimulus controller.
- Accessibility: the active `<th>` carries `aria-sort`; the ↑/↓ arrow sits
  outside the `<a>` so the link text stays the bare label tests match on.

## Consequences

- **Positive:** bad input degrades to the default order, never a 500;
  stable, readable column keys identical across query and array lists; a
  sortable column is one map entry.
- **Negative / trade-offs:** we own comparison semantics. Enum columns sort
  by backing value, not label (identical for today's enums, not
  guaranteed). String order uses SQLite's case-sensitive BINARY collation.
  Two sort paths (`applySort()`, `sortArray()`) must keep null and
  tie-break behaviour aligned. The `null` option could break under a
  stricter future Knp options resolver.
- **Reversibility:** templates and URLs speak only `SortState` and column
  keys, so the strategy underneath can change freely. Moving *to* Knp
  sortable is the expensive direction: a translator, DQL paths in URLs, and
  losing "bad input never errors".

## Alternatives considered

### 1. `knp_pagination_sortable()` with `sortFieldAllowList`

**Rejected.** Needs a translator, exposes DQL paths, turns typos into 500s,
and splits the meaning of `?sort=` between CRUD and `/reports`.

### 2. Add `symfony/translation` to unlock Knp's helpers

**Rejected.** A translator over templates with nothing to translate, to
reach markup we'd restyle anyway, plus another hand-wired bundle.

### 3. Declare sortability on the column definition too

**Rejected.** Two places that can drift, and a header could advertise a sort
the controller doesn't honour.
