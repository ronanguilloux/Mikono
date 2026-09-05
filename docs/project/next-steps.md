# Next steps

**Last updated:** 2026-09-05 (the pilot server is live on GandiCloud
VPS at `deploy.mikono.guilloux.org` with no real data; item 0 is down to
the restore drill and the cutover, and item 1 is now a budget decision
rather than a shortlist)

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

What remains is genuinely open: finishing the deployment, the
Kenya-versus-France hosting decision it defers, escort
display/reporting, and the follow-ups the real-data fixtures left
behind. A pilot server is live as of 2026-09-05 with no real data on it
(`done.md`); what gates real data is the restore drill, not the code.

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

0. **The pilot server is up; what is left on it is the restore drill.**
   `srv-mikono` (GandiCloud VPS, Paris) was provisioned and deployed on
   2026-09-04/05 and all five things this item said needed a real machine
   are verified — ACME issuance, port 80 reachability, HTTP/3 on 443/udp,
   DNS, and the Docker/UFW interaction ([`done.md`](done.md)). It runs on
   the throwaway `deploy.mikono.guilloux.org`, with **no real data**.

   Two things remain before it can hold any:

   - **An encrypted off-site copy of the backups.** The daily cron and
     the restore drill are both done on this machine as of 2026-09-05
     ([`done.md`](done.md)), and the drill passed — but every copy still
     sits on the same disk as the database it protects, which is not a
     backup. `rclone` with a crypt remote, key held off the server;
     `hosting-plan.md` §4 explains why the destination is not a free
     choice, and it is the same residency question as the server's.
   - **Then the cutover** to `mikono.guilloux.org`: an added A/AAAA pair
     at the same IP, `SERVER_NAME` and `DEFAULT_URI` changed, redeploy,
     and delete the `deploy.` records. The throwaway hostname existed to
     keep Let's Encrypt's duplicate-certificate budget (five per week per
     hostname) off the real name while mistakes were still likely — the
     one failure in this sequence that retrying does not fix.

   Note the standing constraint on this box: **1 GB of RAM is
   `hosting-plan.md` §2's minimum, not its recommendation**, and a 2 GB
   swapfile is doing the work of the missing gigabyte. Resize the plan
   before real volunteer data lands.

1. **Send the pre-sales email, then choose the provider and region.**
   The shortlist is now four, verified against the providers' own pages
   on 2026-09-04 ([`done.md`](done.md); `hosting-plan.md` §5):
   **Lineserve** and **Hostnali** join Truehost and HostPinnacle, and
   both publish more of the answers than the two originals do. What is
   left is one email — [`provider-questions.md`](provider-questions.md),
   written to send unchanged to all four — and a reading of the replies.
   Nothing has been sent.

   **A fifth candidate changes the shape of the question.** GandiCloud
   VPS (France) was evaluated on 2026-09-04 and clears §1–§4 on
   everything but a contradicted snapshot answer — at roughly a third of
   the Nairobi cost, on the account that already holds `guilloux.org`.
   So the decision is no longer "which Kenyan provider" but
   **~$275/year in Kenya with no cross-border paperwork, against
   ~$78/year in France with a transfer safeguard someone has to own**.
   That is UCESCO's call, not an architecture call; `hosting-plan.md` §5
   states it in one sentence to put in front of them. Price Gandi's 2 GB
   tier before comparing — the €6 V-R1 is §2's minimum, not its
   recommendation.

   **If the answer is Kenya, question 1 is the one that decides it:
   where is the machine physically?** HostPinnacle's 1,100 KSh plan is five to ten times
   cheaper per GB than everything that names Nairobi, which is the price
   of European stock, and its page does not say. Budget for the honest
   Nairobi number instead — 2,600–3,000 KSh/month plus VAT for the §2
   recommended box, about **$260–290/year**, not the ~$100/year these
   documents used to assume. Worth naming to UCESCO before committing.

   The stated goal was hosting in Kenya for latency. That points at the
   right country for the wrong reason: latency plausibly saves well
   under a second per working session once the mobile access leg is
   accounted for, while the *stronger* argument is that this app holds
   personal data about Kenyan volunteers and Kenya's Data Protection Act
   2019 constrains taking that data out of the country. The `mtr` /
   `curl -w` protocol in `hosting-plan.md` §5 is worth running for the
   record if someone is already in Mombasa, but it should not block: §5
   argues latency is not the deciding factor, so latency numbers cannot
   decide it. The ranking to test against is Nairobi, then South Africa,
   then Europe — and *not* a European VPS behind Cloudflare, for the
   reasons given in that section.

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

   The **domain** no longer blocks anything: `mikono.guilloux.org` is an
   A record on a domain already owned (`hosting-plan.md` §3), and
   `deploy.mikono.guilloux.org` is the throwaway the box is brought up
   on in item 0 — not a second server and not the long-term name, just a
   hostname whose Let's Encrypt certificate budget is expendable while
   the first deploy is still error-prone. What is *not* settled is whose
   name it is: `guilloux.org` is the maintainer's, not UCESCO's, so the
   app outlives one person's registrar renewals only once a UCESCO-held
   name exists. A `.co.ke` at a few hundred shillings against the
   ~$275/year hosting bill is rounding error — a conversation to have
   with UCESCO when the app is theirs rather than a pilot, per
   `hosting-plan.md` §6.
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

### Small, well-defined improvements

- **Volunteers should list the active ones first by default.** Opening
  `/volunteers` from the menu currently orders by surname alone
  (`VolunteerRepository`, `orderBy('v.lastName', 'ASC')`), so someone who
  has finished their stint sits between two people working this week.
  Volunteers leave after a few weeks, which is exactly why the activity
  forms already filter the picker to active volunteers — the index should
  reflect the same reality without hiding anything.

  The change is the repository's base order: `v.isActive DESC` before
  `v.lastName ASC`. It composes correctly with the existing sorting for
  free, and that is worth knowing before touching anything else:
  `ListPaginator::applySort` puts the reader's chosen column first and
  keeps the repository's own `ORDER BY` only as a tie-break
  ([ADR 0011](../adr/0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md)).
  So clicking any column header still re-orders the whole list as the
  reader expects; active-first only decides ties. No new sort flag, no
  change to `SORT_MAP`, no template change.

  Check that the Status column's own sort still behaves — it maps to
  `v.isActive`, so it would now share a field with the tie-break.

### Exercising the app: monkey testing, and the Panther question

Researched 2026-09-05, nothing decided or installed. The want is to let
an agent loose on the app in a test environment and see what breaks.

**The cheapest useful thing needs nothing new.**
[`scripts/panther-screenshot.php`](../../scripts/panther-screenshot.php)
already logs in and drives a real Chromium against the running app
([ADR 0007](../adr/0007-adopt-panther-for-adhoc-visual-verification.md)).
Given credentials to a test environment, that is already a usable driver
for *directed* exploration — walk the batch activity form and report what
breaks. Not random, but on a five-area CRUD app that usually finds more
than randomness does.

**The actual monkey-testing library is
[gremlins.js](https://github.com/marmelab/gremlins.js)** (Marmelab, ~9k
stars, still alive). It is a single `dist` file injected into the page —
no npm install, no Node, no new PHP dependency; it documents Playwright
integration via `addInitScript()`, and the Panther equivalent is
`executeScript()`. Two things to know before running it here: **Turbo
Drive** replaces the body under the horde on every link click, which
works but makes the logs hard to read; and **the horde will click
Delete**. That is fine on fixtures, and it is exactly why this must never
be pointed at the box someone is testing on.

**A route-walking smoke test would probably find more, for less.**
[`http-smoke-testing`](https://github.com/BlueTeaNL/http-smoke-testing)
or [`Pierstoval/SmokeTesting`](https://github.com/Pierstoval/SmokeTesting)
request every route in the router and assert no 5xx — around thirty
routes here, no browser, inside the existing PHPUnit suite. A monkey
finds crashes that come from *sequences*; a route walk finds crashes that
come from *coverage*, and coverage is what leaks in an app this size.
**The trap:** `WebTestCase` swallows exceptions and renders the error
page instead of failing, so a naive crawl stays green while pages return
500. Use `$client->catchExceptions(false)` or assert status codes
explicitly.

**Separately, and more important than any of the above: Panther has been
deprecated in Zenstruck Browser** in favour of
[`playwright-php/playwright`](https://packagist.org/packages/playwright-php/playwright)
(v1.4.0, August 2026), which runs the Symfony kernel *in the test
process* — container access, profiler, DAMA rollback, and parallel runs,
none of which Panther can offer because the browser and the app sit in
different processes. That is a real improvement over what
[ADR 0007](../adr/0007-adopt-panther-for-adhoc-visual-verification.md)
and `tests/E2E/` do today.

**It requires Node.js 20+, and that is no longer an objection.**
[ADR 0016](../adr/0016-admit-nodejs-as-a-test-dependency-not-as-application-code.md)
(2026-09-05) admits Node as a test dependency and ancillary tool, never
as application code, and partially supersedes
[ADR 0007](../adr/0007-adopt-panther-for-adhoc-visual-verification.md) —
specifically the "the project never needs Node" premise and the ground on
which it rejected Playwright. The production image and the server keep no
Node at all; a test dependency is not a runtime dependency.

So the remaining question is a straight tooling judgement, unblocked:
**migrate `tests/E2E/VolunteerManagerSmokeTest.php` and
`scripts/panther-screenshot.php` to `playwright-php`, or stay on
Panther?** What migration would buy: DAMA rollback in E2E tests like
every other test in the suite (dropping `#[SkipDatabaseRollback]`),
container and profiler access, and parallel runs. What it costs: an npm
supply-chain surface that `composer audit` does not see, a bigger dev
image, and the migration itself.

Nothing forces it — deprecated is not broken, Panther works in this
codebase today, and for monkey testing specifically Playwright's
advantages matter far less than they do for a test suite. ADR 0016
deliberately did not decide this.

### Usage analytics — an observability cockpit for the app

The want is real and currently unmet: **which screens get used, which
actions actually get performed, and how the app is used in practice**
rather than how it was imagined. Nothing in the app records this today.
The ask was specifically Google Analytics with JavaScript instrumentation
"here and there". Four things to settle before writing that snippet.

**1. Half of it is already being collected, for free.** Caddy writes a
JSON access log for every request
([`frankenphp/Caddyfile`](../../frankenphp/Caddyfile)), and Docker now
rotates it at 10 MB × 5 files. So "most frequent URLs" is a `docker
compose logs php | jq` away — no code, no third party, no consent
question. **Do that first.** At one or two users on a five-area CRUD app,
it may answer the whole question, and it costs an afternoon of `jq`
rather than a permanent dependency. What it genuinely cannot tell you is
what happens *inside* a page: which filter got used, whether the batch
form is preferred over the single one, where someone abandoned a form.
That gap is the honest case for instrumentation.

**2. Turbo Drive breaks the default GA snippet, silently.** This app uses
Turbo Drive, so navigations replace the body without a full page load.
The standard `gtag` snippet fires `page_view` **once**, on the first
load, and never again — the cockpit would show one pageview per session
and look broken for reasons that have nothing to do with the
configuration. Page views must be sent on the `turbo:load` event, and
custom events wired through Stimulus controllers rather than inline
`onclick`. Anyone implementing this without knowing that will lose an
afternoon to it.

**3. It needs an ADR, because it is a data-protection decision as much as
a technical one.** Every page in this app is behind a login, so every
event is *an identified staff member's behaviour*, and a URL like
`/volunteers/12/edit` carries a record identifier. Sending that stream to
Google means transferring behavioural data about named Kenyan staff to a
US provider — which reopens precisely the question
[`hosting-plan.md`](hosting-plan.md) §5 spent the whole hosting decision
closing. State it at its real size, as §5 does: this is not a blocker, it
is an obligation to document, and at one or two users **consent is a
conversation with Edna, not a cookie banner**. But it should be a
decision on the record rather than a side effect of a `<script>` tag.

That ADR should weigh the lighter options rather than assume GA4:
Plausible or a self-hosted Matomo are cookieless, keep the data out of
the US, and answer "which screens, which actions" perfectly well at this
scale — while GA4's strength (funnels, audiences, attribution over large
traffic) is precisely what a one-user app has no use for.

**4. Implementation notes for whoever does it.**

- The snippet belongs in `templates/base.html.twig`, inside
  `{% block javascripts %}`, and must be **gated on an environment
  variable** holding the measurement ID. Unset in dev, test and CI: a
  local click should never reach the production dashboard, and the
  functional tests assert on rendered page content.
- There is **no Content-Security-Policy** on this app today, so nothing
  needs allowlisting — but if one is ever added, third-party analytics is
  the first thing it will break.
- Events are worth naming deliberately rather than instrumenting
  everything: activity logged, batch form used, report viewed, roster
  copied. A cockpit showing twenty events nobody chose is noise, and the
  interesting question here is a short list.

### Not yet mocked — still open

One finding wasn't part of the five priority mockups and still needs
its own design pass before implementation (the other three — the
Reports "Planned" badge, the proactive delete-guard notice and the
conditional partner-organization field — all shipped on 2026-09-03,
`done.md`):

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

- **CI's bare `docker run -v "$PWD:/app"` is not the environment anyone
  develops in.** It has no `compose.override.yaml`, so no bind-mounted
  dev ini, no `APP_ENV`/`XDEBUG_MODE`, and no named volumes — and the
  mount lands *on top of* the image's own `/app`, hiding everything the
  image build wrote outside `vendor/`. `var/` and `assets/vendor/` are
  whatever the checkout contains, which is nothing: both are gitignored.
  A command that works via `docker compose exec` proves nothing about
  CI. Replicate the bare `docker run` against a clean clone instead.
  (Kept from the CI item this replaced; see `done.md`, 2026-09-04 for
  the three failures this cost.)
- `--entrypoint php` is needed for any bare `docker run` of a console
  command: the image entrypoint runs `dbal:run-sql` to wait for a
  database, and fails outside compose.
- Factories must produce values a round trip through the database would
  return — `ActivityFactory` dates to midnight because `Activity::$date`
  is a `date_immutable` column. A time component there made a created
  entity disagree with its own hydrated row and flipped `/reports`'
  "Planned" badge at random.
- `static::createClient()` must be the first call in every
  `WebTestCase` test method, before any Foundry factory call.
- Login throttling is inert in the test environment on purpose
  (`cache.rate_limiter` is an array pool under `when@test`, and the
  services resetter empties it between requests) — don't write a test
  asserting it fires, and don't move that pool back to the filesystem.
- Every required `TextType`/`EmailType` form field needs
  `'empty_data' => ''` if its entity property is a non-nullable
  `string`.
- `opcache.enable_file_override` must stay off in
  `frankenphp/conf.d/20-app.dev.ini` (dev only) — it's a real prod
  optimization, but breaks live-reload under FrankenPHP's worker mode.
- Any future contrib Flex recipe (like `dama/doctrine-test-bundle`)
  gets wired by hand, not by flipping `composer.json`'s
  `allow-contrib` flag project-wide.
