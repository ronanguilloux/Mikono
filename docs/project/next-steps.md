# Next steps

**Last updated:** 2026-09-01

Only what's next goes here — forward-looking exclusively. Completed
work moves out: to an ADR in `docs/adr/` if it was an architectural
decision, otherwise to [`done.md`](done.md). See
[`docs/project/README.md`](README.md) for the full rule. For status —
what's already built and how it got there — read `done.md`, `git log`,
or `docs/adr`/`docs/brainstorm`.

## Open work

v0.1 is feature-complete (all six CRUD areas, auth, Reports view,
functional tests, one Panther E2E smoke test, scoped Infection, and dev
fixtures — see [`done.md`](done.md)). A UX review
([`docs/brainstorm/`](../brainstorm/) narrative referenced below) and a
System-of-Work brainstorm together identified the follow-on work in
this file. Five key screens were then mocked up as interactive HTML
Artifacts and reviewed (2026-08-28) before any Twig work started.
**All five mockups were validated and all five have now shipped** — the
work-focused home screen, the batch/group activity logging form, the
Activities index mobile card layout, the escort write-path parity fix,
and both slices of the Reports dashboard (`done.md`, 2026-08-31 and
2026-09-01). Their entries have been removed from this file, along with
the pagination design spec that slice 2 implemented; the decision behind
it is [ADR 0009](../adr/0009-adopt-knppaginatorbundle-for-list-pagination.md),
and the seam it left behind — `DataTable` plus `App\Pagination\ListPaginator`
— is what the sortable-columns step below builds on.

What remains is genuinely open: sortable columns, hosting, escort
display/reporting, and the four "not yet mocked" findings.

### Next step: implement in Twig

1. **Sortable columns on every list view.** Every column header on the seven
   list tables becomes a link that sorts by that column — ascending on the
   first click, descending on the second, with the active column showing its
   direction. Reaches the same six CRUD index views plus both Reports
   breakdowns. Three columns stay plain, unsortable headers, deliberately:
   ActivityType's **Description** (free text — ordering it surfaces nothing
   anyone is looking for), Activity's **Duration** (stored as the enum values
   `half_day`/`full_day`/`other` alongside a free-text `durationOther`, so any
   `ORDER BY` on it is arbitrary), and User's **Role** (derived from the
   `roles` JSON array via `isAdmin()`, not a column). Implementation shape
   under ["Sortable columns design"](#sortable-columns-design-2026-08-31)
   below; it rides the `DataTable` / `ListPaginator` seam that the Reports
   slice-2 pass has now put in place, so the groundwork already exists.

### Getting it hosted (2026-08-31 hosting review)

[ADR 0003](../adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)
deferred every hosting question out of v0.1. A review of the Docker,
FrankenPHP, and Symfony configuration turned that deferral into two new
living documents — [`hosting-plan.md`](hosting-plan.md) (what the
architecture requires of a server, and where that server should be) and
[`deployment-plan.md`](deployment-plan.md) (the runbook) — plus
[ADR 0010](../adr/0010-build-in-ci-and-deploy-by-image-pull.md) for the
build-and-ship shape. The defects that review found are fixed
(`done.md`, 2026-09-01). What is left is genuinely forward work:

1. **Choose the provider and region — needs measurement first.** The
   stated goal is hosting in Kenya for latency. That points at the right
   country for the wrong reason: latency plausibly saves well under a
   second per working session once the mobile access leg is accounted
   for, while the *stronger* argument is that this app holds personal
   data about Kenyan volunteers and Kenya's Data Protection Act 2019
   constrains taking that data out of the country. Before this is locked
   into an ADR, run the `mtr` / `curl -w` protocol in
   [`hosting-plan.md`](hosting-plan.md#5-where-to-host) from a real
   device on a real network in Nairobi and Mombasa, and record the
   numbers there. The ranking to test against is Nairobi, then
   Johannesburg, then Europe — and *not* a European VPS behind
   Cloudflare, for the reasons given in that section.
2. **Then do the first deployment**, following
   [`deployment-plan.md`](deployment-plan.md): domain, server bootstrap,
   `deploy.env`, backup cron, and the restore drill. The drill is not
   optional — an untested backup of the one file holding all of the VM's
   data is a hope, not a backup.
3. **Sessions are lost on every deploy.** They live in
   `var/cache/prod/sessions`, which is not a volume, so a redeploy signs
   everyone out. At one user this is a shrug; it is recorded so it isn't
   rediscovered as a bug. The fix, when a second `User` exists, is a
   volume or a different session handler.
4. **SQLite journal mode.** The database uses the default rollback
   journal. Switching to WAL plus a `busy_timeout` is a one-time
   `PRAGMA` and is the cheapest first move on the single-writer limit
   ADR 0003 flagged — worth doing when a second `User` account appears,
   not before.
5. **No regression test pins the timezone behaviour.** The home screen's
   rosters resolve `new \DateTimeImmutable('today')` against the PHP
   `date.timezone`, which is now `Africa/Nairobi`; nothing fails if that
   silently reverts to UTC. A test asserting the roster boundary at,
   say, 01:00 EAT would catch it.

### Not yet mocked — still open

Four findings weren't part of the five priority mockups and still
need their own design pass before implementation:

- **The "Planned" badge on the Reports breakdowns.** The mockup-5
  artifact tags a future-dated "Most recent" cell with a `Planned`
  badge, the way the Activities cards and the home screen's tomorrow
  roster do. Slice 2 shipped the tabs and pagination without it
  (`done.md`, 2026-09-01) because it isn't a template tweak:
  `ActivitySummaryCalculator` would have to track whether a bucket's
  most-recent date is in the future, which changes a class inside the
  Infection scope. Small, but it deserves its own pass with its own
  integration tests rather than riding along.
- Proactively surface the volunteer/project delete-guard message
  (today only shown as a flash *after* a blocked delete attempt) — an
  inline note near volunteers/projects with activity history, before
  delete is attempted, in `templates/volunteer/index.html.twig` and
  `templates/project/index.html.twig`.
- Enforce/highlight Project's conditional `partnerOrganizationName`
  requirement (currently static help text only) — needs light JS or a
  LiveComponent, more than a template tweak.
- **Escort display and reporting.** Distinct from the write-path
  parity fix, which has shipped (`done.md`, 2026-08-31): *where* escort
  should be read back out is genuinely open, and none of it was part of
  the 2026-08-28 mockup review.
  - The **Activities index** (`templates/activity/index.html.twig`)
    shows no escort column. Worth a 6th column given the table already
    scrolls horizontally on desktop — or is escort better left to the
    edit form? The mobile card layout shipped without it (`done.md`,
    2026-08-31) precisely because this is still open: answering "yes"
    means both a 6th table column *and* a fourth line on the card.
  - **Reports** (`ActivitySummaryCalculator`, `/reports`) don't break
    anything down by escort. Whether "days accompanied per escort" is
    a report the VM actually wants is unvalidated — worth asking before
    building, since every escort row is also a staff workload figure.

  The home screen's rosters (shipped 2026-08-31) are now escort's first
  read path, but they only cover today and tomorrow and render escort as
  a text line, not a column or a metric — so they settle neither
  question.

### Flagged for a future ADR (needs new infrastructure, not buildable as-is)

- Automated outbound reminders — still needs an outbound channel, and
  given the Kibera/Mombasa context SMS via a regional gateway (e.g.
  Africa's Talking) may be more reliable than email; worth an ADR
  comparing SMS vs. email vs. staying purely in-app before committing
  any infra. **What such a reminder should be about has changed**: this
  was originally framed as chasing stale *volunteers*, but the home
  screen shipped as "Projects needing volunteers" precisely because
  volunteers who stop appearing have usually finished their stint rather
  than lapsed (`done.md`, 2026-08-31). Don't reintroduce that premise
  through the back door — if an outbound channel is ever added, the
  message worth sending is about quiet projects or the day's roster, not
  a nudge to volunteers who have moved on.
- Task/assignment hand-offs once a second `User` account actually exists
  (the entity is already scoped to grow beyond one user) — e.g.
  assigning a follow-up to a colleague. Nothing to build until then.
- Scheduled/automated donor digest emails — needs a mailer/scheduler
  decision; the print-friendly view above covers the on-demand handoff
  case without one.
- WhatsApp Business API / automated roster sending — the manual
  copy-paste in the home screen's "Tomorrow's roster" takes well under a
  minute
  today; only worth an ADR if that manual step demonstrably becomes a
  bottleneck, not preemptively (API costs, volunteer opt-in/consent,
  message-template approval all apply).

### Sortable columns design (2026-08-31)

Scoped, like pagination was, as one shared pattern across all seven list
tables rather than a per-screen fix — and best built directly on top of that
now-shipped work (`done.md`, 2026-09-01), since both extend the same two
seams (`DataTable`'s column definitions and `App\Pagination\ListPaginator`).
The shape below was worked out against the bundle's actual code, not its
README:

- **The column definition carries the sort, not the URL.** `DataTable`'s
  columns are `['key' => …, 'label' => …]`
  (`src/Twig/Components/DataTable.php`). Add an optional third key naming the
  field to sort on. A column without it
  renders as a plain `<th>` — which is exactly how Description, Duration and
  Role opt out, with no special-casing anywhere.
- **URL contract**: `?sort=<column key>&direction=asc|desc`, e.g.
  `?sort=email&direction=asc`. Column keys, not DQL paths — the query string
  shouldn't leak `v.fullName`, and the same `?sort=totalDays` should mean the
  same thing on `/reports` as anywhere else. A sort link resets `page` to 1
  and keeps `perPage` and `tab`; `PaginationBar` and its page-size form
  already carry unknown query params through, so `sort`/`direction` survive
  paging and resizing for free.
- **Knp's own sorting is deliberately not used.** Two independent reasons,
  both verified against the vendor code:
  - `knp_pagination_sortable()` goes through
    `Knp\Bundle\PaginatorBundle\Helper\Processor`, whose constructor requires
    a `TranslatorInterface` — the same blocker that already stopped this app
    using `knp_pagination_render()` (see the note in
    `config/packages/knp_paginator.yaml`). The header markup is ours, for the
    same reason the pagination controls are.
  - `SortableSubscriber` reads the raw `sort` param and pushes it straight
    into an `ORDER BY` tree walker, so the query param has to *be* the DQL
    path, and a value outside `sortFieldAllowList` throws
    `InvalidValueException` — a 500. That is the opposite of the "bad input
    never 404s" contract `ListPaginator` already keeps for `page`/`perPage`,
    and this app has one non-technical user working from bookmarked URLs.
- **Mechanism**: give `ListPaginator` a per-view sort map supplied by the
  controller (`'email' => 'v.email'`, …). It resolves `sort` against that map,
  applies `orderBy()` to the `QueryBuilder` itself, and neutralises Knp's
  sortable subscriber by passing a `sortFieldParameterName` that never appears
  in a URL. An unknown or absent `sort` falls back to the view's existing
  default order (`createOrderedByNameQueryBuilder()` /
  `createOrderedByDateDescQueryBuilder()`) — same forgiving posture as
  `perPage`. Because the map *is* the whitelist, no user-supplied string ever
  reaches DQL.
- **Ties must break deterministically.** Sorting Volunteers by Status drops
  every row into two buckets; with no secondary `addOrderBy` on name (or
  `id`), SQLite is free to hand back rows on page 2 that were already on
  page 1. Each sort keeps the view's default order as its tie-break.
- **Joined columns already work.** `createOrderedByDateDescQueryBuilder()`
  joins `v`, `p` and `t`, so Volunteer / Project / Activity type sort on the
  joined name with no query change. All three joins are to-one, so paging
  can't multiply rows.
- **Reports sort in memory, before pagination.** `/reports` paginates arrays,
  not a query. Knp's `ArraySubscriber` would demand `[totalDays]`-style
  property paths in the URL, breaking the contract above; `usort` the
  `SummaryRow` list against the same key→field map before `paginateArray()`
  instead. Sort the whole list, not the page.
- **Mobile is in scope.** The Activities card layout has no header row to
  click, so it gets a "Sort by" + direction select beside its own
  `PaginationBar`, reusing the `auto-submit` Stimulus controller the way the
  page-size selector does and emitting the same two params. The one list that
  grows longest shouldn't be sortable only on desktop.
- **Accessibility**: the active column's `<th>` carries
  `aria-sort="ascending"`/`"descending"`, and the link text stays the plain
  column label — several tests resolve headers and buttons by text.
- **Tests**: extend `tests/Integration/Pagination/ListPaginatorTest.php` for
  map resolution, unknown-`sort` fallback and direction clamping; per index
  view, one functional assertion that `?sort=…&direction=asc` reorders the
  first row and one that a junk `sort` still returns 200 in default order.
- **ADR**: choosing our own sort resolution over the bundle's is a mechanism
  decision, so it gets its own ADR extending
  [ADR 0009](../adr/0009-adopt-knppaginatorbundle-for-list-pagination.md), via
  the `adr-scribe` subagent — written alongside the code, not reconstructed
  after it.

## Known conventions to not violate (see `AGENTS.md` for the full list)

- `static::createClient()` must be the first call in every
  `WebTestCase` test method, before any Foundry factory call.
- Every required `TextType`/`EmailType` form field needs
  `'empty_data' => ''` if its entity property is a non-nullable
  `string`.
- `opcache.enable_file_override` must stay off in
  `frankenphp/conf.d/20-app.dev.ini` (dev only) — it's a real prod
  optimization, but breaks live-reload under FrankenPHP's worker mode.
- Any future contrib Flex recipe (like `dama/doctrine-test-bundle`)
  gets wired by hand, not by flipping `composer.json`'s
  `allow-contrib` flag project-wide.
