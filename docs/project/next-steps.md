# Next steps

**Last updated:** 2026-09-01 (test-deploy plan added under Getting it hosted)

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

0. **Throwaway test deploy first, deliberately not in Kenya.** Before
   any of the below, and before UCESCO is contacted, the runbook needs
   exercising end to end against a real server with a real certificate.
   That box holds no volunteer data, so none of the §5 reasoning applies
   to it and it must not be read as pre-empting the decision in item 1.
   The cheapest shape:

   - **Hetzner Cloud CX22** (2 vCPU / 4 GB, Falkenstein or Nuremberg),
     billed hourly at ~€4/month — a week of testing costs under €1 and
     the server is deleted afterwards. DigitalOcean's $6 droplet is the
     substitute if Hetzner's new-account ID verification stalls.
     **Not CAX11**: it is arm64, and the published image is amd64-only
     ([`hosting-plan.md`](hosting-plan.md) §2). Choosing it on price is
     precisely the trap that section records.
   - **A DuckDNS subdomain** (`hosting-plan.md` §3), pointed at the
     server *before* first boot so Caddy's ACME challenge succeeds on
     the first attempt. Watch Let's Encrypt's duplicate-certificate
     limit — five per week for the same hostname — if the box is
     destroyed and rebuilt repeatedly without preserving `caddy_data`.
     A fresh subdomain per rebuild sidesteps it.
   - **Check the GHCR package is public** before starting, or the
     server's `docker compose pull` needs a token that
     [`deployment-plan.md`](deployment-plan.md) §5 does not set up.
   - The box comes up **empty by construction**: Foundry is a
     `require-dev` dependency and is absent from the production image,
     so `foundry:load-fixtures` does not exist there. The August roster
     cannot reach a public server by accident. Create the account with
     `app:user:create` and click through.
   - **Run the restore drill there** ([`deployment-plan.md`](deployment-plan.md)
     §7). It is the only part of the runbook never exercised, and a
     disposable server is the right place to discover it is wrong.

1. **Choose the provider and region — the four questions decide it, not
   the stopwatch.** The
   stated goal is hosting in Kenya for latency. That points at the right
   country for the wrong reason: latency plausibly saves well under a
   second per working session once the mobile access leg is accounted
   for, while the *stronger* argument is that this app holds personal
   data about Kenyan volunteers and Kenya's Data Protection Act 2019
   constrains taking that data out of the country. Before this is locked
   into an ADR, get the four questions in
   [`hosting-plan.md`](hosting-plan.md#5-where-to-host) answered in
   writing by the shortlisted providers — plus a fifth the list is
   missing: **are snapshots offered, and has anyone restored one?**
   Those are a pre-sales email, not fieldwork, and they are what can
   actually flip the choice. The `mtr` / `curl -w` protocol in that same
   section is worth running for the record if someone is already in
   Mombasa, but it should not block: §5 argues latency is not the
   deciding factor, so latency numbers cannot decide it. The ranking to
   test against is Nairobi, then South Africa, then Europe — and *not* a
   European VPS behind Cloudflare, for the reasons given in that section.

   Two arguments that belong in the ADR when it is written. **The choice
   is cheaply reversible** — migrating is a `docker compose pull`, one
   SQLite file and a DNS record, which the restore drill mostly
   rehearses; so it does not warrant being de-risked like a one-way
   door. And **provider maturity is a weaker argument against Nairobi
   than it looks**, for the mirror of §5's own reason about latency: at
   one user logging volunteer activity, a six-hour outage means a day
   written on paper. The risk that matters is data loss, which backups
   address and provider maturity barely touches.

   State the legal argument at its real size, too: Kenya's DPA Part VI
   permits transfer abroad with appropriate safeguards or consent, and
   the localisation provision targets strategic and public-service
   categories that NGO volunteer records almost certainly fall outside.
   So hosting in Kenya is choosing not to have to document a safeguard,
   not compliance-by-necessity. The smaller true claim makes a more
   durable ADR. Not legal advice — if UCESCO has counsel or a DPO, that
   sentence is the one to show them.
2. **Then do the first deployment**, following
   [`deployment-plan.md`](deployment-plan.md): domain, server bootstrap,
   `deploy.env`, backup cron, and the restore drill. The drill is not
   optional — an untested backup of the one file holding all of the VM's
   data is a hope, not a backup. **Prefer an encrypted off-site copy**
   (`rclone` with a crypt remote, key held off the server) over an
   unencrypted one or none: it is the difference between a defensible
   safeguard and a second copy of every volunteer's details sitting
   unprotected in another country. `hosting-plan.md` §4 covers why the
   destination is not a free choice.

   The **domain** is coupled to this and is discussed with UCESCO, not
   decided here. `hosting-plan.md` §6 makes the case for a `.co.ke` over
   a free DuckDNS subdomain once real data is involved: a few hundred
   shillings against an ~$100/year hosting bill, and it keeps the
   project's namespace out of a third party's hands. The DuckDNS name in
   item 0 is for the disposable test box only.
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
