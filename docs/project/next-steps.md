# Next steps

**Last updated:** 2026-09-01

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
write-path parity fix that followed (`done.md`, same date). **Mockup 5
has half shipped**: its KPI tiles, top-volunteers card, and
print-friendly view landed as slice 1 (`done.md`, 2026-08-31); only its
tabs + pagination remain, as slice 2 below. Two things are still
genuinely undecided and are marked as such where they appear: escort
display/reporting and the two "not yet mocked" UX-review findings. The
pagination mechanism is no longer among them — it is settled in
[ADR 0009](../adr/0009-adopt-knppaginatorbundle-for-list-pagination.md).

### Next step: implement in Twig

1. Reports dashboard, slice 2 (mockup 5, remainder) — turn the "By
   volunteer" / "By project" tables into tabs over one shared table
   region, and add the page-size selector plus windowed pagination
   controls, per ADR 0009 and the implementation shape recorded under
   ["Reports tabs + unified pagination design"](#reports-tabs--unified-pagination-design-2026-08-27)
   below. Same pass extends `DataTable` and reaches all six index views.

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

### Validated screen designs (2026-08-28 mockup review)

Reviewed against the app's actual user — a single non-technical
Volunteer Manager logging activities day-to-day from Kibera/Mombasa,
who needs to adopt the app fast with no developer on hand (see
[`docs/brainstorm/02-volunteer-manager-v0.1-context.md`](../brainstorm/02-volunteer-manager-v0.1-context.md)).
Two things already work well and were kept as-is throughout every
mockup: server-rendered Twig + Turbo Drive + native `confirm()` for
deletes, and the app's real brand mark/colors (no placeholder logo).

**5. Reports dashboard — remaining half**
([Mockup: "Reports Dashboard"](https://claude.ai/code/artifact/37d624b5-2d29-4224-9471-c4dcde7125ab)):
the existing "By volunteer" / "By project" tables become tabs
over one shared table region with a page-size selector (25 / 50 / 100 /
All) and windowed-ellipsis pagination controls — validated visually at
both today's small scale and the hypothetical `« 1 2 3 … 66 67 »`
large-scale case (see
["Reports tabs + unified pagination design"](#reports-tabs--unified-pagination-design-2026-08-27)
below). The tiles, the top-volunteers card, and the print-friendly view
from this mockup already shipped (`done.md`, 2026-08-31) and are not
repeated here.

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
numbers including the large-scale ellipsis case). The mechanism for wiring
that validated UI up to real data is now settled in
[ADR 0009](../adr/0009-adopt-knppaginatorbundle-for-list-pagination.md) —
KnpPaginatorBundle, hand-wired, rendering through a project-owned Tailwind
template inside `DataTable`. What remains here is the implementation
shape:

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
- **Mechanism**: `knplabs/knp-paginator-bundle` ^6.10, hand-wired in
  `config/bundles.php` + `config/packages/` (its Flex recipe is ignored
  under `allow-contrib: false`, same as `dama/doctrine-test-bundle`).
  Verified to resolve on Symfony 8.1 / PHP 8.5. One `paginate()` shape
  covers both the CRUD lists' `QueryBuilder`s and the Report's in-memory
  `summarizeByVolunteer()`/`summarizeByProject()` arrays. Rationale and
  the rejected alternatives (hand-rolled, Pagerfanta) are in
  [ADR 0009](../adr/0009-adopt-knppaginatorbundle-for-list-pagination.md).
- **Note for whoever builds it**: the bundle *does* ship
  `tailwindcss_pagination.html.twig` (an earlier note here claiming its
  templates are Bootstrap-only was wrong), but it renders only
  first/prev/range/next/last arrows in stock blue/gray — not the reviewed
  `« 1 2 3 … 66 67 »` design.
  Treat it as a reference and register our own template, built from
  `SlidingPagination::getPaginationData()`'s `pagesInRange` /
  `firstPageInRange` / `lastPageInRange` / `first` / `last` keys; the
  ellipsis is just `firstPageInRange > first`.

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
