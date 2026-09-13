# 9. Adopt KnpPaginatorBundle for list pagination across every index view

Date: 2026-08-31

## Status

Accepted

## Context

Every list view — the CRUD indexes and both breakdown tables on `/reports` —
rendered all rows. The Activities list grows without bound. The UI was
already validated in the Reports mockup review: a 25 / 50 / 100 / All
page-size selector and windowed page numbers with ellipsis
(`« 1 2 3 … 66 67 »`), as one shared pattern rendered through the
`DataTable` TwigComponent.

Two forces make the mechanism non-obvious:

- **Two data shapes.** CRUD lists are Doctrine queries (`LIMIT`/`OFFSET`);
  the Reports tables are in-memory arrays from `ActivitySummaryCalculator`.
  A Doctrine-only mechanism forces a second path for Reports.
- **The windowing math is the fiddly part** — which page numbers to show and
  where to collapse to `…` without off-by-one errors — and the validated
  design is no library's default markup.

KnpPaginatorBundle's `SlidingPagination::getPaginationData()` exposes the
window as data (`pagesInRange`, `first`, `last`, `current`, `previous`,
`next`, `pageCount`, `totalCount`), and its `paginate()` accepts both queries
and plain arrays. Its shipped Tailwind template does not match the design
(no numeric first/last, no ellipsis, stock colours).

## Decision

**`knplabs/knp-paginator-bundle` (^6.10) is the single pagination mechanism
for every list view, rendered by a project-owned Tailwind template.**

- One `paginate()` call shape covers a `QueryBuilder` and a plain array, so
  Reports is not a special case.
- `perPage` offers 25 / 50 / 100 / All, default 25, identical everywhere.
  Reading and validating query parameters is `ListPaginator`'s job; sorting
  is [ADR 0011](0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md).
- The controls are `templates/pagination/tailwind.html.twig`, built on
  `getPaginationData()` in the brand palette, and included by
  `PaginationBar`. `knp_pagination_render()` is not used: it needs a
  translator this single-locale app does not have.
- The bundle is wired by hand in `config/bundles.php` and
  `config/packages/knp_paginator.yaml`, because `allow-contrib: false`
  skips its Flex recipe — which would also have enabled the translator.

## Consequences

- **Positive:** the off-by-one-prone window arrives tested and maintained;
  the markup stays ours; one mechanism for queries and arrays; a pagination
  fix in `DataTable` reaches every screen.
- **Negative / trade-offs:** a hand-wired runtime dependency to keep working
  across Symfony majors, which buys the algorithm and the array/query
  unification, not the markup. At current data volume none of it is
  load-bearing yet.
- **Reversibility:** cheap by design. URLs, markup and behaviour live in our
  template and `ListPaginator`; swapping the mechanism means replacing the
  `paginate()` call sites and the object handed to `DataTable`.

## Alternatives considered

### 1. Hand-rolled pagination

**Rejected, narrowly.** A small value object plus `LIMIT`/`OFFSET` and
`array_slice` would add no dependency and put the window math under
Infection. But the window math is exactly the piece worth outsourcing, and
the markup is ours either way. This stays the fallback if the dependency
becomes a burden.

### 2. Pagerfanta (`babdev/pagerfanta-bundle`)

**Rejected.** Cleaner typed adapters, but its windowing lives in PHP `View`
classes that own their markup, so custom Tailwind controls mean writing a
view class. Knp hands over the same information as plain data.

### 3. Leave lists unpaginated

**Rejected.** The Activities list grows monotonically, and the validated
Reports design includes pagination.
