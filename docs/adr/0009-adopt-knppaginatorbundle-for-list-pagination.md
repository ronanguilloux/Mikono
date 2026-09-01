# 9. Adopt KnpPaginatorBundle for list pagination across every index view

Date: 2026-08-31

## Status

Accepted

## Context

Nothing in the app paginates today. All five simple-list controllers
(Volunteer, Project, ActivityType, Activity, User) call
`findAllOrderedByName()` / `findAllOrderedByDateDesc()` / `findBy([], ...)`
with no `Request` parameter and render every row, and both tables on
`/reports` render every summary row. With a single Volunteer Manager
logging on the order of twenty activities a week, the Activities list is
the one that grows without bound; the others stay small.

The UI half of this was already settled. The 2026-08-28 mockup review
validated the Reports dashboard (mockup 5) including its pagination
treatment: a page-size selector offering 25 / 50 / 100 / All, and windowed
numbered pages with ellipsis truncation, reviewed at both today's tiny
scale and the hypothetical `« 1 2 3 … 66 67 »` large-scale case.
`docs/project/next-steps.md` scoped that as one shared pattern for all six
list views rather than a Reports-only fix, to be rendered through the
existing `DataTable` TwigComponent rather than hand-written per template.
What that document explicitly left open — recording a leaning
recommendation but "not decided here" — is the mechanism, which is what
this ADR closes.

Two forces make the mechanism choice non-obvious:

- **Two different data shapes need one pattern.** The five CRUD lists are
  Doctrine queries, where pagination means `LIMIT`/`OFFSET`. The two
  Reports tables are plain PHP arrays produced in memory by
  `ActivitySummaryCalculator::summarizeByVolunteer()` and
  `summarizeByProject()`, where it means slicing an array. A mechanism that
  only understands Doctrine forces a second, parallel code path for
  Reports — the screen that actually motivated the design.
- **The windowing math is the fiddly part, and the mockup's exact design
  is not any library's default.** Deciding which page numbers to show
  around the current page, and collapsing the rest behind `…` without
  off-by-one errors at either end, is the piece most likely to be quietly
  wrong.

Both candidate libraries were verified to resolve against this project's
actual constraints (Symfony 8.1, PHP 8.5) with `composer require
--dry-run`: `knplabs/knp-paginator-bundle` v6.10.0 (requiring PHP `^8.1`
and `symfony/http-kernel` `^6.4 || ^7.0 || ^8.0`) and
`babdev/pagerfanta-bundle` v4.6.0. Neither is blocked on framework
support.

Inspecting KnpPaginatorBundle's installed source settled two questions
that its reputation alone would not:

- It ships `templates/Pagination/tailwindcss_pagination.html.twig`
  alongside the Bootstrap/Foundation/Bulma/UIkit ones, so the claim in
  `next-steps.md` that its templates are Bootstrap-flavored and would need
  a Tailwind override is outdated. That shipped Tailwind template is still
  not the validated design — it renders `<< < [range] > >>` with no
  numeric first/last page and no ellipsis, and its palette is stock
  `text-blue-600` / `border-gray-400` rather than the app's brand scale.
- `SlidingPagination::getPaginationData()` exposes `pagesInRange`,
  `firstPageInRange`, `lastPageInRange`, `first`, `last`, `current`,
  `previous`, `next`, `pageCount`, and `totalCount`. That is precisely the
  windowing math, available as data rather than locked inside markup, so a
  project-owned Tailwind template can render the mockup's exact design —
  the ellipsis reduces to comparing `firstPageInRange` against `first`.

`Event/Subscriber/Paginate/ArraySubscriber` confirms `paginate()` handles
plain arrays as well as Doctrine queries.

## Decision

Adopt **`knplabs/knp-paginator-bundle` (^6.10)** as the single pagination
mechanism for all six list views, rendering through a project-owned
Tailwind template driven by the `DataTable` TwigComponent.

Concretely:

- One `paginate()` call site shape covers both data shapes — a
  `QueryBuilder` for the five CRUD lists, a plain array for the two
  `ActivitySummaryCalculator` summaries — so Reports is not a special
  case.
- Page size comes from a `perPage` query parameter offering 25 / 50 / 100
  / All, defaulting to 25, identical on every list view.
- The pagination controls are rendered by our own Twig template, built on
  the `pagesInRange` / `first` / `last` data above to produce the reviewed
  `« 1 2 3 … 66 67 »` design in the app's brand palette. The bundle's
  shipped `tailwindcss_pagination.html.twig` is a reference, not the
  template we register.
- `DataTable` (`src/Twig/Components/DataTable.php` +
  `templates/components/DataTable.html.twig`) optionally accepts the
  pagination object and renders those controls, so no index template
  hand-rolls pagination markup — the same reuse rule that already governs
  the six CRUD areas.
- The bundle is wired by hand in `config/bundles.php` and
  `config/packages/`, because `composer.json` sets `allow-contrib: false`
  and its Flex recipe will be ignored. This is the treatment already
  proven for `dama/doctrine-test-bundle`; the global flag stays off.

## Consequences

- **Positive:** the off-by-one-prone windowing math arrives tested and
  maintained rather than hand-written, while the markup stays entirely
  ours, so the validated design is reachable without owning the algorithm.
  One mechanism spans Doctrine-backed lists and the Reports summaries, so
  there is no second in-memory pagination path to keep in sync.
  `knp_pagination_sortable()` makes sortable columns cheap if they are
  ever wanted, at no cost today. Pagination stays concentrated in
  `DataTable`, where a fix reaches all six screens at once.
- **Negative / trade-offs:** a new runtime dependency in an app that has
  deliberately stayed lean, and one that must be hand-wired and kept
  working across future Symfony majors. `paginate()` reads page and limit
  from the request via the request stack rather than taking them as
  explicit arguments, which is convenient but couples controllers to the
  bundle's conventions and is less obvious than a parameter. We still
  write and maintain the controls template — the bundle's shipped Tailwind
  one does not match the reviewed design — so the dependency buys the
  algorithm and the array/query unification, not the markup. At today's
  data volume none of this is load-bearing: it is bought for the years
  ahead, not for this month.
- **Reversibility:** cheap, and deliberately so. Because rendering happens
  in our own template inside `DataTable` and the page-size contract is a
  plain `perPage` query parameter, swapping the mechanism means replacing
  `paginate()` call sites and the object passed to `DataTable` — the URLs,
  the markup, and the user-visible behaviour survive. Removing pagination
  entirely is a matter of dropping the parameter and the component prop.

## Alternatives considered

### 1. Hand-rolled pagination

**Rejected.** This was the standing lean's main rival and is genuinely
defensible: a small `PaginatedResult` value object plus `LIMIT`/`OFFSET`
in the repositories and `array_slice` for the Reports summaries would add
no dependency, match this project's minimal-dependency stance, and put the
window calculation under the existing Infection scope where it would be
mutation-tested. It was rejected because the one piece worth outsourcing
is exactly the piece it would hand-roll: the windowed page range, whose
edge cases at both ends of the sequence are easy to get subtly wrong and
tedious to prove right. `getPaginationData()` already returns that range,
and adopting it costs nothing in rendering freedom, since the markup is
ours either way. The reversibility note above means this alternative
stays available at low cost if the dependency ever becomes a burden.

### 2. Pagerfanta (`babdev/pagerfanta-bundle`)

**Rejected.** It resolves on Symfony 8.1 and its explicit typed adapters
(`QueryAdapter`, `ArrayAdapter`) are arguably cleaner than Knp's
request-reading convenience. It loses on the one axis that matters for the
`DataTable` plan: its windowing lives inside PHP `View` classes that also
own their markup, so producing custom Tailwind controls means writing a
view class or overriding its templates, where Knp hands the same
information over as plain data any template can consume. Given both carry
identical hand-wiring costs under `allow-contrib: false`, the one with the
better-separated data won.

### 3. Leave every list unpaginated

**Rejected.** Defensible today — one Volunteer Manager, a few hundred
activities — and it is why this was deferred through v0.1 rather than
built earlier. It stops being defensible on the Activities index, the one
list that grows monotonically as the log accumulates, and mockup 5's
Reports tables were reviewed and validated *with* pagination as part of
the design. Deferring further would mean shipping the validated dashboard
in a knowingly incomplete form.
