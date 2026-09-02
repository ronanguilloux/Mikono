# Next steps

**Last updated:** 2026-09-01 (real-data fixtures shipped)

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
the pagination and sortable-columns design specs that followed; the
decisions behind those are
[ADR 0009](../adr/0009-adopt-knppaginatorbundle-for-list-pagination.md)
and
[ADR 0011](../adr/0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md),
and the seam they left behind — `DataTable` plus
`App\Pagination\ListPaginator`, which now owns `page`, `perPage`, `sort`
and `direction` — is where any future cross-cutting list behaviour
belongs.

What remains is genuinely open: hosting, escort display/reporting, and
the four "not yet mocked" findings — plus the follow-ups the real-data
fixtures left behind.

### Follow-ups from the real-data fixtures (2026-09-01)

The fixtures now come exclusively from the VM's own WhatsApp roster
archive, and the two model gaps that surfaced while building them are
fixed ([ADR 0012](../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md),
[0013](../adr/0013-record-every-escort-on-an-activity.md),
[0014](../adr/0014-make-a-volunteers-last-name-optional.md); `done.md`,
2026-09-01). What that work left open:

1. **Ask Edna two questions.** Are "Ellen" (early August) and "Hellen"
   (September) the same volunteer? And can she supply surnames for the
   fifteen volunteers in the archive? Both fill in
   [`docs/fixtures/rosters.yaml`](../fixtures/rosters.yaml); neither
   blocks anything.
2. **Uganda is deferred, not decided.** The Kampala/Luwero rosters at the
   end of August appear only as truncated headers with no volunteers, so
   nothing is seeded for them. When a complete one arrives, the naming
   convention absorbs it ("Uganda - ..."); whether `ProjectLocation`
   should grow a third case is the question to reopen then, and it is a
   scope question for the VM, not a modelling one.
3. **The escort field is now five stacked checkboxes.** The Tailwind
   form theme renders expanded choices with the label *under* the input
   (Duration has always looked like this), which made both activity
   forms noticeably taller. Worth a theme pass if it annoys in use — it
   is a styling question, not a model one.
4. **The test suite is not repeatable within 15 minutes** — found while
   running it several times over during this work, and pre-existing.
   `security.yaml` sets `login_throttling` to 5 attempts per 15 minutes,
   and the limiter's storage lives in `var/cache/test`, which no test
   resets. Run the suite three or four times in a row and
   `SecurityControllerTest::correctCredentialsAuthenticateAndLandOnTheHomeScreen`
   starts failing with a redirect back to `/login`;
   `bin/console cache:pool:clear --all --env=test` fixes it until next
   time. The proper fix is resetting the rate-limiter pool in the test
   bootstrap, so a green suite doesn't depend on how recently it last
   ran.

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
    means both a 6th table column *and* a fourth line on the card. Note
    that escort is now a *collection*
    ([ADR 0013](../adr/0013-record-every-escort-on-an-activity.md)), so
    such a column renders a list and cannot be a one-line `SORT_MAP`
    entry — the honest options are an unsortable column or none.
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
