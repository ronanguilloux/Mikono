# Next steps

**Last updated:** 2026-09-09

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
2. **Size production off the 1 GB plan.** The UAT box moved to
   **V-R2 — 1 CPU / 2 GB** on 2026-09-06, so the swapfile is no longer
   standing in for a missing gigabyte there. Production has to be
   ordered on the same tier rather than on V-R1, which is
   [`hosting-plan.md`](hosting-plan.md) §2's minimum, not its
   recommendation. Note the CPU is still 1: the §2 recommendation is
   2 vCPU / 2 GB, and a deploy briefly runs two containers.
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
one box or two.** UAT and production are two deployments. At 2 GB
sharing is no longer arithmetically impossible the way it was on V-R1 —
but two FrankenPHP workers with a 256 MB opcache each, plus the deploy
overlap, is most of the box, and it would put real volunteer data on the
same disk as the environment we deliberately keep empty. Either production
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

- **SQLite journal mode.** The database uses the default rollback
  journal. Switching to WAL plus a `busy_timeout` is a one-time `PRAGMA`
  and the cheapest first move on the single-writer limit ADR 0003
  flagged.
- **Task/assignment hand-offs** — e.g. assigning a follow-up to a
  colleague. The `User` entity is already scoped to grow beyond one user;
  nothing to build until a second one exists.

## `/usage`: date filters

- **Filter the Usage screen by date range** — a custom `from`/`to` pair plus
  presets for last 7 / 30 / 90 days and year to date. Today
  `AccessLogReader::read()` aggregates the whole file and the screen reports
  one `since`/`until` span, so "did anyone use what I shipped last week" can
  only be answered by eyeballing `lastSeen`. Three things to keep in mind
  before writing the diff: the range has to be applied while streaming (skip
  a line whose `ts` falls outside it, not filter rows afterwards, since the
  per-row counters and p95 are computed in that same pass); the 60-second
  `usage.access_log` cache key has to include the resolved range or every
  filter serves the previous one; and the range must bound the `usage_event`
  table too (`UsageEventRepository::summarize()` groups over all rows), or the
  two halves of the screen will describe different periods. Note the ceiling:
  `roll_size 10MiB` / `roll_keep 3` means the log only reaches back as far as
  the rotation does, so a "year to date" preset will often be honest about a
  shorter window — say so on screen rather than implying a full year.

## `/usage`: report login attempts, with the login and the outcome

- **Show sign-ins on `/usage`: who (the submitted email), when, and whether it
  succeeded or failed.** Neither existing source can answer this. The Caddy
  access log has `POST /login` rows but Symfony redirects on both outcomes, so
  success and failure look alike, and the log carries no credentials — by
  design. The `usage_event` table is the other half of the screen and is
  explicitly *not* where this goes: `UsageEvent`'s docblock records that it has
  no user, IP or session column on purpose, and that adding one is "a new
  data-protection decision and needs its own ADR" (ADR 0021, ADR 0018).
  **So this item is an ADR before it is a diff** — a login audit trail names an
  identified colleague and, on a failed attempt, an arbitrary string somebody
  typed into the email box. Decide before writing code: retention (this is the
  first thing here that should expire), whether a failed attempt stores the
  submitted identifier verbatim or a masked form, whether the row records an
  IP, and whether the screen showing it stays admin-only like the rest of
  `/usage`.
  Mechanically it is small once decided: Symfony's `LoginSuccessEvent` and
  `LoginFailureEvent` (the failure event carries the passport, hence the
  attempted identifier) into a new listener and its own table — not
  `usage_event`, whose whole point is being non-personal. Note it also lands
  the one thing the throttler currently swallows silently: repeated failures
  against a real address. Whatever ships must be bounded by the date range
  from the filters item above, or the two halves of the screen will again
  describe different periods.

## Simplification backlog (from the ponytail audit)

One line each; the reasoning, line counts and file paths are in
[`docs/brainstorm/07-ponytail-audit.md`](../brainstorm/07-ponytail-audit.md).
Independent of each other — take them in any order, or none. The audit
deliberately left `/reports` walking every activity three times alone as a
performance question, not a simplification one; that is still unexamined.

The audit's "dead code" bullets were verified and folded into the lists
below; its file carries the corrections. One was wrong:
`ProjectFactory::partner()` has two live callers, so don't re-propose it.

**Shrink — same behaviour, less code:**

- **Make `createOrderedByNameQueryBuilder()` the single ordered-name query.**
  `ActivityFormType` and `BatchActivityFormType` hand-roll it inline four
  times (`ActivityFormType.php:63`, `:83`, `BatchActivityFormType.php:51`,
  `:75`); point them at the repository method instead. Then drop
  `EscortRepository::findAllOrderedByName()` and
  `ActivityTypeRepository::findAllOrderedByName()`, which nothing calls — but
  note the `VolunteerRepository`/`ProjectRepository` twins **are** live
  (`ReportMetricsCalculator`), so either fix the shared docblock or accept a
  deliberately asymmetric quartet.
- **`CreateUserCommand`: use `$io->ask()`/`$io->askHidden()`** instead of
  `getHelper('question')` and three `Question` objects.
- **Hand the delete token to Twig's `csrf_token()`** and build the id in
  `RowActions`, retiring the `csrfTokenId()`/`csrfToken()` pair and the
  `CsrfTokenManagerInterface` constructor arg copy-pasted into six controllers.
- **One anonymous component for the five byte-identical `_form.html.twig`
  shells**, taking `cancelUrl` — that is the only thing that differs.
- **Filter the existing checkbox labels in
  `batch_activity_form_controller.js`** rather than hand-rolling a listbox over
  them (arrow-key highlight, `aria-expanded`, the mousedown-vs-blur race). Keep
  the keyboard and screen-reader behaviour that hand-roll currently provides —
  that is the part worth checking before deleting, not the line count.
- **Write the "Other needs `durationOther`" rule once.** It exists twice today:
  `Activity::validateDurationOther()` and an identical `Assert\Callback` in
  `BatchActivityFormType::configureOptions()`.
- **Thin `RosterArchive`'s hand-rolled type layer** — ~110 lines of
  `rows`/`string`/`nullableString`/`bool`/`date` plus five one-caller readonly
  VOs, guarding a YAML file this repo owns and a maintainer hand-writes. Not a
  trust boundary. `RosterArchiveTest` enforces the transcription rules and
  stays either way ([ADR 0012](../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)).

**Needs a decision before a diff:**

- **Cut `symfony/ux-live-component`** — zero `AsLiveComponent`, zero
  `data-live`, but `live_controller.js` and `live.min.css` ship eagerly on
  every page load. Touches `composer.json`, `config/bundles.php`,
  `config/routes/ux_live_component.yaml`, the importmap and
  `assets/controllers.json`. ADR 0003 records it as "installed, not yet used",
  so removing it revises that record rather than merely deleting code.
- **Drop `/activities/new`**, the single-volunteer form, now that
  `/activities/new-batch` handles N≥1 and is what the home screen links to
  everywhere. `ActivityFormType` stays — `/edit` uses it. Confirm nothing
  bookmarked or documented points at the old route first.
- **Decide what `Escort::$isActive` is for.** Today it is a checkbox and a
  Status column with no reader, and both activity pickers list inactive
  escorts unlabelled. Either it filters those pickers — which is what the
  volunteer picker already does — or it goes. Read it as a missing filter and
  it is a normal review item, not a deletion.
  Settle it before touching `EscortFactory::inactive()`: uncalled today, but
  exactly the fixture a test for that filter needs. `ProjectFactory::inactive()`
  is the same shape and rides along.
- **Reconsider `knplabs/knp-paginator-bundle`** — the honest caveat first: it
  is [ADR 0009](../adr/0009-adopt-knppaginatorbundle-for-list-pagination.md)
  and [ADR 0011](../adr/0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md),
  and cutting it *adds* ~40 lines. But `ListPaginator` already parses
  `page`/`perPage`/`sort`/`direction` itself, switches the bundle's sorting off
  with `SORT_FIELD_PARAMETER_NAME => null`, and `PaginationBar` bypasses
  `knp_pagination_render()` for want of a translator. What is left is a count,
  a slice and a page window. Only worth doing behind a superseding ADR; lowest
  priority here.

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
