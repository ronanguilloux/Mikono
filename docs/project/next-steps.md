# Next steps

**Last updated:** 2026-08-26

Only what's next goes here — forward-looking exclusively. Completed
work moves out: to an ADR in `docs/adr/` if it was an architectural
decision, otherwise to [`done.md`](done.md). See
[`docs/project/README.md`](README.md) for the full rule. For status —
what's already built and how it got there — read `done.md`, `git log`,
or `docs/adr`/`docs/brainstorm`.

## Open work

v0.1 is feature-complete (all five CRUD areas, auth, Reports view,
functional tests, one Panther E2E smoke test, scoped Infection, and dev
fixtures — see [`done.md`](done.md)). A full UX review of the Tailwind
templates has since identified the work below — nothing in it is
started yet.

### UX review findings (2026-08-26)

Reviewed against the app's actual user — a single non-technical
Volunteer Manager logging activities day-to-day from Kibera/Mombasa,
who needs to adopt the app fast with no developer on hand (see
[`docs/brainstorm/02-volunteer-manager-v0.1-context.md`](../brainstorm/02-volunteer-manager-v0.1-context.md)).
Findings are grouped by priority; each item names the template(s) it
touches. Two things already work well and should be kept as-is:
server-rendered Twig + Turbo Drive + native `confirm()` for deletes
(no SPA/modal payload — well-matched to low-bandwidth field
conditions), and plain `<select>` (not JS-searchable) for the Activity
form's three `EntityType` fields at today's data volumes.

P0 items (quick, low-risk) are done — see
[`done.md`](done.md#2026-08-27--p0-ux-review-fixes).

**P1 — moderate, before adoption scales past one user:**

- Proactively surface the volunteer/project delete-guard message (today
  only shown as a flash *after* a blocked delete attempt) — an inline
  note near volunteers/projects with activity history, before delete is
  attempted, in `templates/volunteer/index.html.twig` and
  `templates/project/index.html.twig`.
- Visually de-emphasize inactive volunteers/projects in the Activity
  form's selects (`templates/activity/_form.html.twig`) — currently only
  a `" (inactive)"` text suffix, easy to mis-pick during fast repetitive
  logging.
- Add a "Save and add another" option to the Activity form — the
  worked example this app is built around is logging several activities
  in one sitting; this is the single highest-leverage speed win for the
  daily task.
- Reuse the `<twig:DataTable>` component in
  `templates/report/index.html.twig` instead of its current
  hand-duplicated table macro.

**P2 — larger/structural, wants a mockup reaction first:**

- A responsive strategy for wide tables on mobile (Activity's index has
  5 data columns + actions; today it's horizontal-scroll only) — likely
  a card-based layout below a breakpoint.
- Pagination on index pages once data volume grows — every index
  currently loads the full result set; fine today, will slow down over
  a weak mobile connection as activity history accumulates.
- Enforce/highlight Project's conditional `partnerOrganizationName`
  requirement (currently static help text only) — needs light JS or a
  LiveComponent, more than a template tweak.
- A Reports/dashboard redesign (stat tiles/KPIs vs. the current two
  plain tables), if richer reporting is wanted.

### System of Work initiatives (2026-08-26)

v0.1 built a System of Record — CRUD plus a historical report. The real
goal is a System of Work: the app should actively help the VM do their
job, not just archive data about it. That means serving two temporal
directions: **looking back** at a long history of already-logged
activity (stale volunteers/projects, recognition), and **looking
forward** at a short, high-stakes plan — the VM almost always plans at
least the next morning's handful of activities ahead of time, then
manually types that up as a roster message sent to volunteers over
WhatsApp. Full reasoning (audience, desired impact, rejected
alternatives) is captured in
[`docs/brainstorm/04-system-of-work-for-the-volunteer-manager.md`](../brainstorm/04-system-of-work-for-the-volunteer-manager.md);
this is the resulting action list. These are additive to, not a
replacement for, the UX-review tiers above.

**Buildable now, no new infrastructure:**

- A work-focused home screen replacing the current plain Reports landing
  (`DashboardController` redirects `app_home` → `report_index` today) —
  surfaces volunteers/projects whose most-recent-activity date (already
  computed by `ActivitySummaryCalculator::summarize()`) is stale beyond
  a threshold, as an actionable "needs a check-in" list. Historical
  breakdown moves to a secondary view, not removed.
- Batch/group activity logging — a form variant matching how the work
  actually happens (one session: one date/project/activity
  type/duration, several volunteers at once) instead of one
  single-volunteer submission per person. Needs only a multi-select
  `Volunteer` field plus a controller loop — no LiveComponent or new JS.
- A volunteer detail/timeline "show" page — none of the 5 CRUD areas has
  one today (only index/new/edit); surfaces activity history and notes
  at a glance, turning `Volunteer` into an actual lightweight CRM
  record.
- Surfacing the `notes` field (already on `Volunteer` and `Project`)
  somewhere it's seen again after entry — today it's captured once and
  then invisible.
- Opportunistic data-completeness nudges — flag volunteers missing
  phone/email inline, without turning these legitimately-optional
  fields into blocking validation errors.
- A "top volunteers this period" recognition view, built from data
  `summarizeByVolunteer()` already produces — supports actively
  thanking/retaining volunteers, not just recording their hours.
- Print-friendly report styling (`@media print` CSS only, no new
  dependency) so the existing Reports view can go straight to a donor
  or UCESCO leadership without a separate export step.
- **Tomorrow's roster** — a view filtered to `Activity` rows dated
  tomorrow (or the next few days), grouped by project, with a one-click
  "copy as text" block formatted for pasting straight into WhatsApp.
  Needs zero schema change: `Activity.date` is already unconstrained,
  so planning tomorrow is just the batch/group logging form above used
  with a future date, plus a new query method and the copy-to-clipboard
  affordance (a small Stimulus controller). Corrections (no-show, plan
  changed) reuse the existing edit/delete flow, same as fixing a past
  logging mistake — no new "confirm/cancel" workflow needed. One
  accepted trade-off worth documenting rather than silently living
  with: a future-dated row briefly counts toward `totalDays`/
  `mostRecent` in the historical summary before that day actually
  happens — minor and self-correcting given the short lookahead, not
  worth a status field.
- **Escort/chaperone capture** — confirmed by Edna's real nightly
  roster messages (2026-08-27), which each name one staff member who
  accompanied volunteers per project per day ("Accompanied by Mr
  Maeba") — nothing today captures this. Add a new `Escort` lookup
  entity (id/name/`isActive`, same shape as `ActivityType`) as a 6th
  CRUD area under Settings; add a nullable `Activity::$accompaniedBy`
  (`ManyToOne` → `Escort`) set once per batch-logged session (the
  batch/group logging form above) and applied to every row it creates;
  surface it back out as the "Accompanied by ..." line in Tomorrow's
  roster's copy-as-text block. Needs a migration and a new CRUD area,
  but no new infrastructure — full reasoning in
  [`docs/brainstorm/04-system-of-work-for-the-volunteer-manager.md`](../brainstorm/04-system-of-work-for-the-volunteer-manager.md#evidence-from-real-roster-messages-2026-08-27).

Also confirmed by those same messages: dev fixtures
(`src/Story/AppStory.php`) were expanded to seed the real breadth of
UCESCO's projects and activity types (clinics, schools, MVETI,
orphanage, beach clean-ups, home visits, orientation, etc.), so the
mockups below can draw on realistic project names instead of just
"Bright Achievers" / "Mombasa Youth Centre".

**Flagged for a future ADR (needs new infrastructure, not buildable as-is):**

- Automated stale-volunteer check-in reminders — the natural next step
  after the home screen above, but needs an outbound channel. Given the
  Kibera/Mombasa context, SMS via a regional gateway (e.g. Africa's
  Talking) may be more reliable than email — worth an ADR comparing SMS
  vs. email vs. staying purely in-app before committing any infra.
- Task/assignment hand-offs once a second `User` account actually exists
  (the entity is already scoped to grow beyond one user) — e.g.
  assigning a follow-up to a colleague. Nothing to build until then.
- Scheduled/automated donor digest emails — needs a mailer/scheduler
  decision; the print-friendly view above covers the on-demand handoff
  case without one.
- WhatsApp Business API / automated roster sending — the manual
  copy-paste in "Tomorrow's roster" above takes well under a minute
  today; only worth an ADR if that manual step demonstrably becomes a
  bottleneck, not preemptively (API costs, volunteer opt-in/consent,
  message-template approval all apply).

### Next step: mock up key screens as an Artifact before touching Twig

Before implementing any of the above, mock up the following as
interactive HTML Artifacts (not code) so they can be reacted to
visually first, in priority order — the System of Work items now lead,
since they carry the bigger impact:

1. **The work-focused home screen** — mock both temporal directions
   together: **Tomorrow's roster** (grouped by project, with the
   copy-as-WhatsApp-text affordance) front and center as the
   highest-frequency daily action, alongside the "needs a check-in"
   list (stale volunteers/projects) — since this is the screen the VM
   would see first every day.
2. **Batch activity/roster logging form** — mock the shared
   group-session flow (one date/project/type/duration, multi-select
   volunteers) used for both planning tomorrow and logging a past
   session, together with the "Save and add another" idea and the
   de-emphasized-inactive select treatment from the UX review, since
   all three target this one screen.
3. **Volunteer detail/timeline page** — mock the missing "show" view:
   notes, activity history, and total days at a glance.
4. **Mobile index + nav** — mock a card-based alternative to the wide
   Activity table at a phone viewport, side-by-side with the current
   horizontal-scroll behavior.
5. **Reports/dashboard** (optional) — mock a stat-tile/KPI alternative
   to the current two-plain-tables layout, plus the "top volunteers"
   recognition view, only if richer reporting is wanted.

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
