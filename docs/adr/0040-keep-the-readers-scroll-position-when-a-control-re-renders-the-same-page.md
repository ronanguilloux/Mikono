# 0040. Keep the reader's scroll position when a control re-renders the same page

Date: 2026-10-03

## Status

Accepted

## Context

Every list control re-renders the current page through GET query
parameters: the sort headers and the Activities mobile sort select
([ADR 0011](0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md)),
page links and the page-size form
([ADR 0009](0009-adopt-knppaginatorbundle-for-list-pagination.md)), the
search and filter forms, the `/reports/volunteers` tabs and the `/usage` date range.
State lives in the URL and is read under
[ADR 0023](0023-degrade-malformed-query-input-to-a-default.md)'s defaults,
so links stay shareable and every control works without JavaScript.

Turbo Drive treats each of those clicks as an `advance` visit and scrolls
to the top. On a long page the reader loses their place on every click.
`/usage` shows it worst: the Sign-ins table
([ADR 0028](0028-record-login-attempts-with-identifier-and-ip-for-90-days-admin-only.md))
is the third table, at the bottom of the page, and every sort click sent
the reader back to the top to scroll down again.

Turbo 8 (8.0.23, as vendored) covers this natively:

- A visit is a **page refresh** when it stays on the same `pathname` — the
  query string may differ — and its action is `replace`.
- A page refresh keeps the scroll position when the page's
  `<meta name="turbo-refresh-scroll">` says `preserve`.
- Turbo reads that meta from the **newly rendered** page, so the server
  decides per response.
- A GET form takes `data-turbo-action` from the form or its submitter, as
  a link does.
- Turbo 8 also makes a POST whose redirect lands on the exact current URL
  a `replace`: a delete from bare `/volunteers`, or deleting a stay or an
  achievement from a volunteer's page.

Reference: `.agents/skills/turbo/references/api.md`, meta tags section.

## Decision

**A control that re-renders the current route with other query parameters
makes a Turbo `replace` visit, and the page keeps the reader's scroll
position — unless the response carries a flash message, which scrolls it
back to the top.**

- **The base layout sets the meta.** It emits
  `<meta name="turbo-refresh-scroll">` as `preserve`, or `reset` when the
  response has a flash. The flash bag is read once into a variable in
  `<head>` and the flash loop in `<main>` reuses it: `app.flashes` empties
  the bag, so a second read would render no message.
- **A flash resets because of same-URL POST redirects.** Turbo treats them
  as page refreshes, so an unconditional `preserve` would keep the scroll
  after a delete, and the flash at the top of `<main>` — a blocked-delete
  or bad-CSRF error included — would land off-screen. A controller that
  adds a flash on a GET resets the scroll for that response too, which is
  intended.
- **Every such control opts in with `data-turbo-action="replace"`:**
  - sort header links in the `DataTable` component, so every list,
    `/reports/volunteers` and all three tables on `/usage`;
  - page links and the page-size form;
  - the Activities mobile sort select;
  - the search and filter forms, and their "Clear filters" links;
  - the `/reports/volunteers` tab links;
  - the `/usage` range presets and custom range form.
- **The rule is uniform.** Filters, tabs and the range control carry the
  attribute even though they sit at the top of their pages today: layouts
  change, and a control that re-renders its own route should not need a
  judgement call. A new one carries the attribute too.
- Links and forms that leave the route are untouched: a different
  `pathname` is never a page refresh, so they still land at the top.

## Consequences

- **Positive:** sorting, paging, filtering or switching tab keeps the
  table the reader was working on in view, on every list, `/reports/volunteers` and
  `/usage`. No new JavaScript and no new dependency: one meta tag and one
  attribute per control. With JavaScript off nothing changes — a full page
  load, at the top. State stays in GET parameters, so URLs stay shareable
  and ADR 0011 and ADR 0023 are untouched.
- **Negative / trade-offs:**
  - Back no longer steps through in-page states (sort, page, page size,
    filter, tab, range): `replace` overwrites the history entry, so Back
    leaves the page. Accepted.
  - Keyboard focus still returns to `<body>` after such a re-render, as
    before. `turbo-refresh-method="morph"` might keep it, but morph would
    also apply to same-URL POST redirects — `/activities/new` redirecting
    to itself after a batch save — without re-running Stimulus
    `connect()`. That is a separate decision, not taken here.
  - The opt-in is per control: one that forgets the attribute jumps to the
    top. The convention in `CLAUDE.md` and functional tests asserting the
    meta and the attribute on the existing controls are the only guards.
- **Reversibility:** cheap. Drop the meta tag and the attributes, and Turbo
  falls back to `advance` visits that scroll to the top.

## Alternatives considered

### 1. A Turbo Frame around each table, with `autoscroll`

**Rejected.** Row links, delete forms and the export menu inside each
frame would all need `data-turbo-frame="_top"` escapes, and the server
would still render the whole page for every frame request.

### 2. Symfony UX LiveComponent lists

**Rejected.** LiveComponent is installed but unused
([ADR 0003](0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)),
and using it here would mean rewriting every list as a component to keep a
scroll position. Whether to adopt it across the app is a separate question:
[`review-live-component-at-scale`](../project/backlog/review-live-component-at-scale.md),
the counterpart of
[`cut-ux-live-component`](../project/backlog/cut-ux-live-component.md).

### 3. URL `#fragment` anchors pointing at the table

**Rejected.** Native and JavaScript-free, but Turbo drops the fragment on
a GET form submission (it visits the fetch response's URL), so the
page-size and filter forms would still jump. An anchor also scrolls the
table to the top of the viewport rather than keeping the reader's place.

### 4. A Stimulus controller that saves and restores `scrollY`

**Rejected.** It reimplements what the meta tag does natively, and would
still need its own rule for the flash case.
