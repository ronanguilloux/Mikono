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
