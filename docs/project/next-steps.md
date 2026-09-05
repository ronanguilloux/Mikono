# Next steps

**Last updated:** 2026-09-05

Only what's next goes here — forward-looking exclusively. Completed work
moves out: to an ADR in `docs/adr/` if it was an architectural decision,
otherwise to [`done.md`](done.md). See
[`docs/project/README.md`](README.md) for the full rule. For status — what
has been built and how it got there — read `done.md`, `git log`, or
`docs/adr`/`docs/brainstorm`. Conventions and commands are in
[`AGENTS.md`](../../AGENTS.md), not here.

## Before real data can land on the server

The pilot is live at `deploy.mikono.guilloux.org` with **no real data**
(`done.md`, 2026-09-05). Two things gate putting any on it, in this order:

1. **An encrypted off-site copy of the backups.** The daily cron runs and
   the restore drill passed, but every copy still sits on the same disk as
   the database it protects, which is not a backup. `rclone` with a crypt
   remote, key held off the server. [`hosting-plan.md`](hosting-plan.md)
   §4 explains why the destination is not a free choice — it is the same
   residency question as the server's.
2. **Then the cutover** to `mikono.guilloux.org`: an added A/AAAA pair at
   the same IP, `SERVER_NAME` and `DEFAULT_URI` changed, redeploy, delete
   the `deploy.` records. The throwaway hostname exists to keep Let's
   Encrypt's duplicate-certificate budget (five per week per hostname) off
   the real name — the one failure in this sequence that retrying does not
   fix.

**Standing constraint on this box:** 1 GB of RAM is
[`hosting-plan.md`](hosting-plan.md) §2's minimum, not its
recommendation, and a 2 GB swapfile is doing the work of the missing
gigabyte. Resize the plan before real volunteer data lands.

## The hosting decision, and the ADR it owes

Send [`provider-questions.md`](provider-questions.md) — written to go
unchanged to all four Kenyan candidates (Lineserve, Hostnali, Truehost,
HostPinnacle) — then read the replies. Nothing has been sent.

The question is no longer "which Kenyan provider". GandiCloud VPS
(France) clears §1–§4 on everything but a contradicted snapshot answer,
at roughly a third of the Nairobi cost, on the account that already holds
`guilloux.org`. So the choice is **~$275/year in Kenya with no
cross-border paperwork, against ~$78/year in France with a transfer
safeguard someone has to own**. That is UCESCO's call, not an
architecture call; [`hosting-plan.md`](hosting-plan.md) §5 states it in
one sentence to put in front of them. Price Gandi's 2 GB tier before
comparing — the €6 V-R1 is §2's minimum, not its recommendation.

**If the answer is Kenya, question 1 decides it: where is the machine
physically?** HostPinnacle's 1,100 KSh plan is five to ten times cheaper
per GB than everything that names Nairobi, which is the price of European
stock, and its page does not say. Budget for the honest Nairobi number —
2,600–3,000 KSh/month plus VAT for the §2 recommended box, about
**$260–290/year**.

Four arguments belong in the ADR when it is written:

- **Latency points at the right country for the wrong reason.** It
  plausibly saves well under a second per working session once the mobile
  access leg is counted. The stronger argument is that this app holds
  personal data about Kenyan volunteers and Kenya's Data Protection Act
  2019 constrains taking it out of the country. The `mtr` / `curl -w`
  protocol in §5 is worth running for the record if someone is already in
  Mombasa, but it must not block: §5 argues latency is not the deciding
  factor, so latency numbers cannot decide it. The ranking to test
  against is Nairobi, then South Africa, then Europe — and *not* a
  European VPS behind Cloudflare, for the reasons in that section.
- **State the legal argument at its real size.** Kenya's DPA Part VI
  permits transfer abroad with appropriate safeguards or consent, and the
  localisation provision targets strategic and public-service categories
  that NGO volunteer records almost certainly fall outside. Hosting in
  Kenya is choosing not to have to document a safeguard, not
  compliance-by-necessity. The smaller true claim makes a more durable
  ADR. Not legal advice — if UCESCO has counsel or a DPO, that sentence
  is the one to show them.
- **The choice is cheaply reversible** — migrating is a `docker compose
  pull`, one SQLite file and a DNS record, which the restore drill mostly
  rehearses. It does not warrant being de-risked like a one-way door.
- **Provider maturity is a weaker argument against Nairobi than it
  looks**, for the mirror of §5's own reason about latency: at one user
  logging volunteer activity, a six-hour outage means a day written on
  paper. The risk that matters is data loss, which backups address and
  provider maturity barely touches.

**Whose name the domain is in is still open.** `guilloux.org` is the
maintainer's, not UCESCO's, so the app outlives one person's registrar
renewals only once a UCESCO-held name exists. A `.co.ke` at a few hundred
shillings against the hosting bill is rounding error — a conversation to
have with UCESCO when the app is theirs rather than a pilot, per
[`hosting-plan.md`](hosting-plan.md) §6.

## Deferred until a second `User` account exists

- **Sessions are lost on every deploy.** They live in
  `var/cache/prod/sessions`, which is not a volume, so a redeploy signs
  everyone out. At one user this is a shrug; recorded so it isn't
  rediscovered as a bug. The fix is a volume or a different session
  handler.
- **SQLite journal mode.** The database uses the default rollback
  journal. Switching to WAL plus a `busy_timeout` is a one-time `PRAGMA`
  and the cheapest first move on the single-writer limit ADR 0003
  flagged.
- **Task/assignment hand-offs** — e.g. assigning a follow-up to a
  colleague. The `User` entity is already scoped to grow beyond one user;
  nothing to build until a second one exists.

## Questions for Edna

- Are "Ellen" (early August) and "Hellen" (September) the same volunteer?
- Can she supply surnames for the fifteen volunteers in the archive?

Both fill in [`docs/fixtures/rosters.yaml`](../fixtures/rosters.yaml);
neither blocks anything.

**Uganda is deferred, not decided.** The Kampala/Luwero rosters at the end
of August appear only as truncated headers with no volunteers, so nothing
is seeded for them. When a complete one arrives, the naming convention
absorbs it ("Uganda - ..."); whether `ProjectLocation` should grow a third
case is the question to reopen then, and it is a scope question for the
VM, not a modelling one.

## Needs a design pass before implementation

- **Escort display and reporting.** The write path shipped; *where*
  escort should be read back out is genuinely open, and none of it was
  part of the 2026-08-28 mockup review.
  - The **Activities index** (`templates/activity/index.html.twig`) shows
    no escort column. Worth a 6th column given the table already scrolls
    horizontally on desktop — or is escort better left to the edit form?
    Answering "yes" means both a 6th table column *and* a fourth line on
    the mobile card. Note that escort is a *collection*
    ([ADR 0013](../adr/0013-record-every-escort-on-an-activity.md)), so
    such a column renders a list and cannot be a one-line `SORT_MAP`
    entry — the honest options are an unsortable column or none.
  - **Reports** (`ActivitySummaryCalculator`, `/reports`) don't break
    anything down by escort. Whether "days accompanied per escort" is a
    report the VM actually wants is unvalidated — worth asking before
    building, since every escort row is also a staff workload figure.

  The home screen's rosters are escort's first read path, but they cover
  only today and tomorrow and render escort as a text line, not a column
  or a metric — so they settle neither question.

## Researched, nothing decided

The narrative for both lives in `docs/brainstorm/`; each needs a decision
before any code:

- [**Exercising the app: monkey testing, and the Panther
  question**](../brainstorm/05-exercising-the-app-and-the-panther-question.md)
  — a route-walking smoke test, gremlins.js, and the separate
  `playwright-php` migration question that
  [ADR 0016](../adr/0016-admit-nodejs-as-a-test-dependency-not-as-application-code.md)
  unblocked but deliberately did not decide.
- [**Usage analytics: an observability
  cockpit**](../brainstorm/06-usage-analytics-cockpit.md) — read the
  Caddy access log first; anything beyond it is a data-protection
  decision that needs an ADR, not a `<script>` tag.

## Flagged for a future ADR (needs new infrastructure)

- **Automated outbound reminders** — needs an outbound channel, and given
  the Kibera/Mombasa context SMS via a regional gateway (e.g. Africa's
  Talking) may be more reliable than email; worth an ADR comparing SMS
  vs. email vs. staying purely in-app before committing any infra. **What
  such a reminder should be about has changed**: this was originally
  framed as chasing stale *volunteers*, but the home screen shipped as
  "Projects needing volunteers" precisely because volunteers who stop
  appearing have usually finished their stint rather than lapsed. Don't
  reintroduce that premise through the back door — the message worth
  sending is about quiet projects or the day's roster, not a nudge to
  volunteers who have moved on.
- **Scheduled/automated donor digest emails** — needs a mailer/scheduler
  decision; the print-friendly view covers the on-demand handoff case
  without one.
- **WhatsApp Business API / automated roster sending** — the manual
  copy-paste in the home screen's "Tomorrow's roster" takes well under a
  minute today; only worth an ADR if that manual step demonstrably
  becomes a bottleneck, not preemptively (API costs, volunteer
  opt-in/consent, message-template approval all apply).
