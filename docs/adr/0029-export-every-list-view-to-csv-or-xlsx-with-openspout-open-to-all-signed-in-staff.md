# 0029. Export every list view to CSV or XLSX with OpenSpout, open to all signed-in staff

Date: 2026-09-16

## Status

Accepted

## Context

The Volunteer Manager needs the app's records in a spreadsheet. The
app's seven list views (activities, volunteers, projects, activity types,
escorts, branches, users) are paginated and sorted through `ListPaginator`
([ADR 0009](0009-adopt-knppaginatorbundle-for-list-pagination.md),
[ADR 0011](0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md)),
so copying rows off a page is slow and loses anything past the current
page.

The forces:

- **The owner wants real `.xlsx`, not only CSV.** Excel is what the staff
  open files in, and a CSV of accented names opens garbled in Excel
  unless it carries a byte-order mark.
- **The file must match the screen.** An export that runs its own query
  can drift from the list's filters and sort order.
- **Names and notes are user input.** A cell starting with `=` becomes a
  formula when a spreadsheet opens a CSV (CSV injection).
- **Every account is internal staff today**: the Volunteer Manager and
  their assistants, if any. Anyone who can read a list can already copy
  it.
- **The export URL is still a query-string URL**, so it must degrade
  like every other one
  ([ADR 0023](0023-degrade-malformed-query-input-to-a-default.md)).

## Decision

**Every list view offers an Export control that downloads its rows as CSV
or XLSX, written row by row by OpenSpout, from the same query the list
uses, to any user who may view that list.**

**1. Two scopes, two formats.** The anonymous TwigComponent
`templates/components/ExportMenu.html.twig`, a `<details>` disclosure
that needs no JavaScript, offers:

- **Current view**: the on-screen query parameters minus `page` and
  `perPage`. Filters and sort are kept, and the file holds every matching
  row, not one page.
- **Whole list**: no parameters. Every row, in the view's default order.

Its links carry `data-turbo="false"`, or Turbo Drive would try to render
the download as a page.

**2. One route per area:** `GET /<area>/export.{format}`, named
`<area>_export`, with `format` required to be `csv|xlsx` and defaulting
to `csv`. The default is what lets `RouteSmokeTest` walk the route with
no change
([ADR 0004](0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md)).
In `VolunteerController` the route is declared **before** `show()`, or
`/volunteers/export` is read as an id.

**3. One shared writer.** `App\Export\ListExport::response($name,
$format, $columns, $rows)` returns a `StreamedResponse` with an
`attachment` disposition and the filename `<list>-<Y-m-d>.<format>`,
dated today in Nairobi time
([ADR 0024](0024-treat-dates-as-calendar-days-in-nairobi-time.md)).

**4. One query for screen and file.** Each controller has:

- `private const COLUMNS`: the header row.
- `listQueryBuilder(Request)`: the repository query, the filters and
  `ListPaginator::applySort`, called by both `index()` and `export()`.
  **Never write a second query for the export.**
- `cells($entity)`: plain values only. DataTable `badges` never reach
  the file.

Rows are streamed with `toIterable()`, except for volunteers. Their
status cell needs the batch `findIdsStayingOn()` lookup, and their query
has a `HIDDEN` select, so they use `getResult()`. That is bounded like the
existing `perPage=all`.

**5. The export reads its parameters through the list's own readers**, so
a malformed parameter falls back to its default exactly as on the list
([ADR 0023](0023-degrade-malformed-query-input-to-a-default.md)).

**6. OpenSpout (`openspout/openspout` ^5.11)** writes both formats one row
at a time. It needs `ext-zip` and `ext-xmlwriter`, both already in the
image. It is a plain library with no bundle or recipe, so
`allow-contrib: false` is untouched
([ADR 0003](0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)).

**7. Cell safety, per format:**

- **CSV** keeps OpenSpout's default UTF-8 BOM, so Excel reads accented
  names. A cell starting with `=`, `+`, `-`, `@`, a tab or a carriage
  return gets a leading apostrophe.
- **XLSX** cells are written as string cells, which a spreadsheet never
  evaluates, so they are left unchanged: `=1+1` reads back as text.
  Don't add the apostrophe there; it would show up in the data.

**8. Access follows the list.** Any signed-in user may export what they
can view. `/users/export` is admin-only because `UserController` carries
`#[IsGranted('ROLE_ADMIN')]` at class level. There is no separate export
gate.

`/reports` and `/usage` have no export: they show aggregates, not lists.

## Consequences

- **Positive:** every list can leave the app as a file that matches the
  screen, including filters and sort, without paging. Memory stays flat
  for all lists but volunteers. Both formats go through one writer, and
  each area adds only a column list and a cell mapper. Every export
  route is covered by the route walk. Each area's functional test reads
  the CSV back through `tests/Functional/ReadsListExports.php`.
- **Negative / trade-offs:** OpenSpout is a new runtime dependency to
  keep updated. The volunteers export is capped by the same ceiling as
  `perPage=all`. The volunteers and users exports carry personal data
  (names, emails, phone numbers), and any signed-in user who can view
  those lists can take them in bulk.
- **Standing rule: revisit who may export if a less-trusted role
  appears.** If accounts are ever given to people who are not internal
  staff (for example, volunteers who log in themselves), inheriting the
  list's access rule is no longer enough. Bulk export of personal data is
  more sensitive than viewing a page, so export will likely need its own
  gate, such as a voter or a role check, at least on volunteers and
  users.
- **Standing rule: every future list or records view gets the same
  export.** That means a `COLUMNS` constant, `listQueryBuilder()` shared
  with `index()`, `cells()`, an `export.{format}` route and
  `<twig:ExportMenu route="…_export" />`, and never a second query for
  the export.
- **Reversibility:** cheap. Remove the routes, the component, the writer
  and the dependency. Nothing else depends on them, and
  `listQueryBuilder()` can stay as a plain refactor of `index()`.

## Alternatives considered

### 1. CSV only, with `fputcsv`

**Rejected.** The owner wants real `.xlsx` files, which `fputcsv` cannot
write.

### 2. `phpoffice/phpspreadsheet`

**Rejected.** It builds the whole workbook in memory before writing, and
it is a much heavier dependency. The export writes flat rows only and
needs none of its formatting or formula features.

### 3. Admin-only export for volunteers

**Rejected for now.** Every account is internal staff who can already
read and copy the volunteer list. The standing rule above reopens this
the day a less-trusted role exists.

### 4. An `?export=` flag on the index route

**Rejected.** `RouteSmokeTest` walks routes, not query flags, so the
export would go untested. `index()` would also return either HTML or a
download depending on a parameter.
