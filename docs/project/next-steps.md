# Next steps

**Last updated:** 2026-08-31

Only what's next goes here — forward-looking exclusively. Completed
work moves out: to an ADR in `docs/adr/` if it was an architectural
decision, otherwise to [`done.md`](done.md). See
[`docs/project/README.md`](README.md) for the full rule. For status —
what's already built and how it got there — read `done.md`, `git log`,
or `docs/adr`/`docs/brainstorm`.

## Open work

v0.1 is feature-complete (all five CRUD areas, auth, Reports view,
functional tests, one Panther E2E smoke test, scoped Infection, and dev
fixtures — see [`done.md`](done.md)). A UX review
([`docs/brainstorm/`](../brainstorm/) narrative referenced below) and a
System-of-Work brainstorm together identified the follow-on work in
this file. Five key screens were then mocked up as interactive HTML
Artifacts and reviewed (2026-08-28) before any Twig work started — see
["Validated screen designs"](#validated-screen-designs-2026-08-28-mockup-review)
below. **All five mockups were validated; mockups 1, 2 and 4 (the
work-focused home screen, the batch/group activity logging form, and the
Activities index mobile card layout) have since shipped** (`done.md`,
2026-08-31) and have been removed from this file, as has the escort
write-path parity fix that followed (`done.md`, same date). Mockup 5
remains, and is an implementation-ready spec rather than an open
question. Three things
are still genuinely undecided and are marked as such where they
appear: escort display/reporting, the two "not yet mocked" UX-review
findings, and the pagination mechanism (which needs an ADR).

### Next step: implement in Twig

1. Reports dashboard (mockup 5) — the KPI tiles and top-volunteers list
   don't need pagination and can ship on their own; pull in a
   pagination library once the ADR below is written.

### Validated screen designs (2026-08-28 mockup review)

Reviewed against the app's actual user — a single non-technical
Volunteer Manager logging activities day-to-day from Kibera/Mombasa,
who needs to adopt the app fast with no developer on hand (see
[`docs/brainstorm/02-volunteer-manager-v0.1-context.md`](../brainstorm/02-volunteer-manager-v0.1-context.md)).
Two things already work well and were kept as-is throughout every
mockup: server-rendered Twig + Turbo Drive + native `confirm()` for
deletes, and the app's real brand mark/colors (no placeholder logo).

**5. Reports dashboard**
([Mockup: "Reports Dashboard"](https://claude.ai/code/artifact/37d624b5-2d29-4224-9471-c4dcde7125ab)):
KPI tiles (Volunteers, Projects, Activities
logged, Total days contributed) above the existing content; a "Top
volunteers" recognition card (top 5 by total days, medal styling for
the top 3, built from `summarizeByVolunteer()`'s already-produced
data); the existing "By volunteer" / "By project" tables become tabs
over one shared table region with a page-size selector (25 / 50 / 100 /
All) and windowed-ellipsis pagination controls — validated visually at
both today's small scale and the hypothetical `« 1 2 3 … 66 67 »`
large-scale case (see
["Reports tabs + unified pagination design"](#reports-tabs--unified-pagination-design-2026-08-27)
below); a "Print-friendly view" button (`window.print()` + `@media
print` rules hiding the app chrome).

### Not yet mocked — still open

Three findings weren't part of the five priority mockups and still
need their own design pass before implementation:

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

### Reports tabs + unified pagination design (2026-08-27)

Scoped as one shared pattern, not a Reports-only fix — it applies to all
six list views: the Volunteer, Project, ActivityType, Activity, and User
index pages, plus both tables on the Reports page. None of these paginate
today; all five simple-list controllers call `findAllOrderedByName()` /
`findAllOrderedByDateDesc()` / `findBy([], ...)` with no `Request` param.
The Reports page's own tabbed + paginated treatment was mocked up and
validated as part of mockup 5 above (page-size selector, windowed page
numbers including the large-scale ellipsis case) — what follows is the
technical pagination-mechanism decision, still open, for wiring that
validated UI up to real data across all six views:

- **Page-size control**: a `perPage` query param offering 25 / 50 / 100 /
  All (default 25), identical on every list view.
- **Page navigation UI**: windowed numbered pages with ellipsis
  truncation (e.g. `« 1 2 3 … 66 67 »`) — always show first/last page, a
  small window around the current page, prev/next arrows, and collapse
  the rest behind `…`. This is the standard accessible pattern for long
  paginated lists and is what makes browsing "67 pages at 25-per-page vs.
  fewer pages at 50/100" tractable.
- **Rendering**: extend the shared `DataTable` TwigComponent
  (`src/Twig/Components/DataTable.php` /
  `templates/components/DataTable.html.twig`) to optionally accept a
  pagination summary (current page, total pages, perPage, total count)
  and render the controls, rather than hand-rolling pagination markup per
  template — it's already the one reused abstraction across every CRUD
  index page.
- **Pagination mechanism — comparison for a future ADR** (per this
  project's "every non-trivial architectural decision gets an ADR"
  convention — not decided here):

  *Hand-rolled* (Doctrine `LIMIT`/`OFFSET` or ORM `Paginator` in each
  repository, plus a small shared `PaginatedResult`-style value object
  that both DB-backed lists and the Report's in-memory
  `summarizeByVolunteer()`/`summarizeByProject()` arrays populate the same
  way):
  - \+ no new dependency; matches this project's default stance and its
    established pattern for wiring a contrib package by hand
    (`dama/doctrine-test-bundle`, despite `allow-contrib: false`) instead
    of avoiding contrib packages outright.
  - \+ full control, minimal surface for a 6-screen app.
  - − windowed/ellipsis page-number rendering is fiddly to get right by
    hand (off-by-one edge cases at both ends) and would need to be built
    and tested from scratch.
  - − no free sortable-column support if that's ever wanted later.

  *KnpPaginatorBundle* (`knplabs/knp-paginator-bundle`):
  - \+ `paginate()` accepts a Doctrine `Query`/`QueryBuilder` **or a
    plain array**, so it already unifies the DB-backed lists and the
    Report's in-memory summary arrays under one call — the "one unified
    solution" goal, without a bespoke value object.
  - \+ ships a windowed/ellipsis pagination Twig template out of the
    box — precisely the "best UI/UX" piece being asked for here,
    pre-built and battle-tested rather than hand-rolled.
  - \+ optional sortable-column helper (`knp_pagination_sortable()`) if
    column sorting is ever wanted, at no extra cost.
  - − a new dependency; its default templates are
    Bootstrap/Foundation-flavored and would need a custom Tailwind
    template override (a supported, documented customization point, not
    a hack).
  - − its Flex recipe would be ignored by this project's
    `allow-contrib: false`, so it needs the same manual-wiring treatment
    already proven for `dama/doctrine-test-bundle`.

  **Leaning recommendation for the ADR**: KnpPaginatorBundle — it directly
  solves the hardest part of this ask (correct windowed pagination UI)
  with a maintained implementation instead of a hand-rolled one, and this
  project's own convention already accommodates wiring a contrib package
  by hand rather than ruling it out. This is a recommendation only; the
  actual pick still belongs in an ADR once this is picked up for
  implementation.
- **Not decided here**: which library, and exactly how the pagination
  object plugs into `DataTable` — both still open, for the ADR.

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
