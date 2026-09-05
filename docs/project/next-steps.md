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

Production stays on GandiCloud VPS in France (ADR 0017).
`deploy.mikono.guilloux.org` is the **UAT environment**, live with **no
real data** (`done.md`, 2026-09-05); it is where UCESCO accepts the app,
and it stays after production exists rather than being retired into it.
Production is a **separate deployment on the same stack and the same
provider**, under a UCESCO subdomain — see the next section, which is
where that is still unsettled. Three things gate real data:

1. **An encrypted off-site copy of the backups.** The daily cron runs and
   the restore drill passed, but every copy still sits on the same disk as
   the database it protects, which is not a backup. `rclone` with a crypt
   remote, key held off the server. The destination question is settled by
   ADR 0017 — it follows the server into Europe — so what is left is
   mechanical: install `rclone`, configure the crypt remote, add the push
   to the existing cron line in
   [`deployment-plan.md`](deployment-plan.md) §7, then drill a restore
   *from the off-site copy* rather than from the local one. Note the crypt
   layer is what keeps the destination cheaply changeable later: the
   remote holds ciphertext, and the key never goes on the server.

   The copy that has to exist is **production's**, not UAT's — but doing
   it on the UAT box first is a free rehearsal of exactly the procedure,
   in the spirit of [`deployment-plan.md`](deployment-plan.md) §10. Give
   production its own remote and its own key rather than sharing UAT's.
2. **Resize off the 1 GB plan.** V-R1 is
   [`hosting-plan.md`](hosting-plan.md) §2's minimum, not its
   recommendation, and a 2 GB swapfile is doing the work of the missing
   gigabyte. Price the 2 GB tier and move to it.
3. **A production hostname that resolves.** Mechanically this is small —
   A/AAAA records at the production box's IP, `SERVER_NAME` and
   `DEFAULT_URI` set to that name, deploy — but the name is not ours to
   create, so it is the item below, not this one.

Let's Encrypt's duplicate-certificate budget (five per week per hostname)
no longer pits UAT against production — two distinct names never contend
for it. But it still applies to the **production name itself**, and that
name will be UCESCO's, which makes exhausting its budget on a fumbled
first deploy considerably more awkward than burning a throwaway's. So
keep the pattern that worked in September: bring the production box up on
a spare `guilloux.org` name we control, get a clean deploy, and only then
have UCESCO point the real record at it.

## The meeting with Nickson (September 2026)

**This is what gates production.** Nickson is UCESCO's technical contact,
and the production hostname is a UCESCO subdomain that only UCESCO can
create. Nothing below is code; all of it decides where the code runs.

The good news first: a UCESCO-held name is what
[`hosting-plan.md`](hosting-plan.md) §6 has been asking for. It retires
half of ADR 0017's governance concern on its own — the domain and DNS
stop depending on one individual's personal Gandi account, leaving only
the server there.

**Ask about the name and the DNS:**

- What is the parent domain, who administers its DNS, and can they add a
  subdomain pointing at an IP we control? A `CNAME` is fine if an
  A/AAAA pair is not on offer.
- **What is the turnaround on a DNS change, and who can make one?** This
  is the question that matters most and the one most likely to be
  waved through. Certificates are issued by Let's Encrypt over HTTP-01,
  so the name must resolve to the box *before* the first deploy
  succeeds — and it must keep resolving, because renewal happens
  unattended every 60 days. A DNS that lives behind someone else's
  ticket queue is an operational dependency, not a one-off form to fill
  in.
- Does UCESCO want the VPS itself in a UCESCO account eventually? That
  is the other half of ADR 0017's governance concern, and it is a
  billing conversation more than a technical one.

**Ask about the data-protection safeguard**, which the meeting is the
natural place to raise even though it is not Nickson's to sign:
production is in France and holds personal data about Kenyan volunteers.
Kenya's Data Protection Act 2019 Part VI permits transfer abroad with
appropriate safeguards or consent, and France is an easy jurisdiction to
argue one for — so this is defensible, not a problem to fix. But somebody
at UCESCO has to own the documentation, and UCESCO has no DPO. The
sentence to put in front of them is in
[`hosting-plan.md`](hosting-plan.md) §5. Not legal advice; if UCESCO has
counsel, that is the sentence to show them.

**One decision the meeting does not settle, so make it separately:
one box or two.** UAT and production are two deployments, and V-R1 at
1 GB will not host both — a single FrankenPHP worker with a 256 MB
opcache is already why that box needs a 2 GB swapfile. Either production
gets its own VPS (a second bill, a second backup cron, and UAT stays
genuinely isolated from real data), or the boxes are resized and share
one. Decide before the DNS exists, because the answer is the IP the
record has to point at.

**If the question ever reopens**, none of the research was thrown away:
[`hosting-plan.md`](hosting-plan.md) §5 keeps the Nairobi candidates
table, the five pre-sales questions and the ranking that put Kenya first,
and [`provider-questions.md`](provider-questions.md) is still the email to
send. ADR 0017 is what a superseding ADR would have to argue against.

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
