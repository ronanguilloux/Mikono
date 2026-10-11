# Brainstorm — Dataviz for the VM and UCESCO management

**Date:** 2026-10-11
**Author:** <ronan.guilloux@gmail.com>
**Related:** [`CLAUDE.md`](../../CLAUDE.md),
[ADR 0029](../adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md),
[ADR 0030](../adr/0030-insert-programs-between-projects-and-activities.md),
[ADR 0032](../adr/0032-store-volunteer-profile-fields-as-optional-and-photos-as-re-encoded-jpeg-blobs-in-sqlite.md),
[ADR 0034](../adr/0034-comply-with-kenyas-data-protection-act-2019.md),
[ADR 0037](../adr/0037-record-a-volunteers-gender-for-accommodation-pairing.md),
[ADR 0038](../adr/0038-record-achievements-on-a-volunteers-stay.md),
[ADR 0042](../adr/0042-match-available-volunteers-to-programs-by-skills-or-past-activity-type-at-their-branch.md),
[ADR 0003](../adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md),
[`docs/adr/`](../adr/)

---

## Primary audience

Two readers, each with their own questions:

- **The Volunteer Manager (VM)**, for operations and planning: who is on
  site next month, whether a branch can take one more volunteer, which
  projects have gone quiet, whether escorts are evenly loaded.
- **UCESCO top management**, for impact, trend and what donors see: are we
  growing, where does volunteer effort go, which recruitment channels pay
  off, what does that effort achieve.

Behind both: a future contributor picking up the
[first-charts card](../project/backlog/dataviz-first-charts.md), who needs
to know which charts were ranked where and why. Source: Ronan, working
session, 2026-10-11.

## Desired impact

Every chart that ships helps somebody make a decision. A chart that only
looks good isn't built. The decisions are:

- **Accept or redirect a volunteer, given capacity.** The VM can see from
  the stays look-ahead whether a branch has room in a given week, and send
  an applicant to Mombasa rather than Kibera (or the other way round) on
  the evidence.
- **Where effort goes.** Management can read volunteer days by month and
  branch, by project (own versus partner), and by activity type, without
  exporting a spreadsheet.
- **What each recruitment channel is worth.** Management can compare
  volunteers by source and by nationality, year by year, and decide where
  to recruit.
- **Impact per unit of effort.** Once impact is recorded as numbers (slice
  A below), management can put "days contributed" next to "beneficiaries
  reached" per project and per month.

In hindsight, success means each of these four questions is answered by a
chart above the table it summarises, and nobody has to rebuild the numbers
by hand for a meeting or a donor report.

## Where things stand (2026-10-11)

The app has no charts. `/reports/volunteers` is KPI tiles plus tables, one
per tab (volunteer, project, program, type, escort, branch, group, source,
month). `/reports/achievements` is a list. The home screen shows rosters
and reminders. UCESCO's meeting recap
([`ucesco-meeting-requirements-raw.md`](../project/backlog/ucesco-meeting-requirements-raw.md),
"Impact & Reporting") asks for "volunteers engaged, hours, activities
supported and beneficiaries reached".

The question explored here: which charts does the **current** data model
already support, and which of them answer a real question for each
audience?

### What the model can count today, with no schema change

- **Stay**: volunteer × branch × start/end. Gives presence over time,
  arrivals, length of stay and a look-ahead.
- **Activity**: date × volunteer × program (→ project → branch) × type ×
  duration (half, full, other) × escorts. Gives effort, as "days
  contributed", via `ActivitySummaryCalculator`.
- **Volunteer**: nationality, country of residence, date of birth (age
  band), profession, skills, sources, `createdAt`. Gender exists but is
  sensitive and shown on the profile page only, so it is **excluded**
  ([ADR 0037](../adr/0037-record-a-volunteers-gender-for-accommodation-pairing.md)).
- **Program**: dates, needed skills, offered types, beneficiary groups.
  **Project**: branch, ownership (UCESCO or partner), `isActive`.
- **Achievement**: stay × project × date
  ([ADR 0038](../adr/0038-record-achievements-on-a-volunteers-stay.md)).
- **Not countable**: hours, attendance against plan, and beneficiaries as
  numbers (free text today). The page should say so rather than imply
  them.

## Candidate charts, ranked

### For UCESCO top management (impact, trend, where effort goes)

Charts are numbered 1–12 across both lists; the follow-up cards refer to
them by number.

- **1. Volunteer days per month, stacked by branch.** A 12–24 month column
  chart. `PeriodTotalsCalculator` already yields present, arrived and
  engaged per period; it needs days added. This is the headline "are we
  growing?" chart, at the top of `?tab=month`.
- **2. Effort by project, own versus partner.** Horizontal bars of days per
  project, coloured by `ProjectOwnership`. Answers "how much of our
  volunteer effort serves partner organisations?". The project tab rows
  already carry `totalDays`.
- **3. Effort by activity type, and by beneficiary group.** Horizontal bars
  from the type and group tab rows. Answers "what do volunteers actually
  do?".
- **4. Where volunteers come from.** Bars by nationality (ISO-2 code shown
  as a country name), and by recruitment source per year (the source tab
  already takes `?year=`). Tells management which channels are worth the
  effort. A world map is tempting, but bars read better at around ten
  countries.
- **5. Achievements timeline.** A dot or strip per project across months,
  putting concrete outcomes next to effort.

### For the Volunteer Manager (planning, operations)

- **6. Occupancy look-ahead (a Gantt of stays).** The next 8–12 weeks, one
  row per volunteer, one bar per stay, grouped by branch, with a line at
  today. For accommodation and pickup planning, it is the most
  operational chart of the set. Read straight from `StayRepository`, no
  aggregation.
- **7. Volunteers on site per week, next three months.** An area or column
  chart by branch, from stays. Capacity at a glance: "Mombasa is full in
  week 47".
- **8. Days since last visit per project.** Sorted bars, extending
  `QuietProjectFinder` (projects only, per its docblock). It is already a
  list on the dashboard; a bar makes "neglected" visible.
- **9. Skills supply versus demand per branch.** Paired bars: volunteers
  present with skill X against open programs needing X. Same rule as
  `ProgramMatchFinder`
  ([ADR 0042](../adr/0042-match-available-volunteers-to-programs-by-skills-or-past-activity-type-at-their-branch.md));
  feeds recruitment.
- **10. Escort workload per month.** Stacked bars by escort, from the
  escort tab. A fairness and burnout check.
- **11. Activity calendar heatmap.** Weekday × week, count of activities.
  Spots dead days. Lower priority.
- **12. Length-of-stay distribution.** A histogram (one week, two weeks,
  one month, three months and more). Leans towards management, and cheap.

## Schema changes that would unlock better decisions

Ranked by decision value over cost. Each is small and nullable, so old rows
stay valid, in line with ADR 0032's "optional, never backfilled" stance.
Each needs its own ADR. The attendance and stay changes are personal data,
so they need a stated purpose and retention under
[ADR 0034](../adr/0034-comply-with-kenyas-data-protection-act-2019.md).

| # | Change | Unlocks | Who decides with it | Cost |
| --- | --- | --- | --- | --- |
| A | **Impact indicators**: an `Indicator` list (unseeded, VM-entered like `BeneficiaryGroup`), plus `Achievement::$indicator` and `Achievement::$quantity` (both nullable) | Impact per month, project or branch ("children tutored", "meals served", "beneficiaries reached" as numbers), and days of effort per unit of impact. Management and donors ask for this, and today it is free text (`Program::$beneficiariesReached`) | Management | M: entity, two columns, form, `UserTimeline` and export drop-list check |
| B | **`Branch::$capacity`** (places or beds, nullable int) | Occupancy look-ahead with a capacity line: accept, decline, or send to Mombasa | VM | XS |
| C | **`Program::$volunteersNeeded`** (nullable int) | Staffing gap per open program (needed against present-and-matching, reusing `ProgramMatchFinder`). The meeting recap asks for "volunteers required/assigned" | VM and management | XS |
| D | **Hours**: `Activity::$durationHours` (nullable decimal) replacing the free-text `durationOther`; half and full map to fixed hours in `ActivityDuration`, no column | An "hours contributed" KPI and trend, the unit donors and volunteer certificates use. The meeting recap asks for it | Management | S: existing `durationOther` values converted by hand in the migration |
| E | **Attendance**: `Activity::$attended` (nullable bool; null means not yet confirmed) | Planned against actual, and no-show rate per program or volunteer. The planned rows already exist: future-dated activities get a "Planned" badge | VM | S in schema, but a real workflow cost: someone has to confirm each one |
| F | **Stay cancellation**: `Stay::$cancelledOn` (nullable date) | Cancellation rate, and a recruitment funnel by source (booked, then arrived). Booking lead time needs nothing new: `Stay::createdAt` is accurate for stays entered from now on | Management (recruitment) | L, hidden: every stay read (derived status, `statusRank` DQL, `resolveStays`, matches, period totals) must exclude cancelled stays. Only worth it if someone asks about cancellations |

**Recommended first slice: A + B + C together.** They are cheap, change no
derived rule, and each feeds a chart above: the impact trend, the occupancy
look-ahead (6) and the staffing gap (9). D comes next if UCESCO confirms
hours matter more than days. E and F wait until someone asks.

## Technical recommendation

- **Server-rendered inline SVG through a TwigComponent**
  (`<twig:BarChart rows=… />`, `<twig:Timeline … />`), fed by the rows the
  controllers already compute. No JavaScript library and no new
  dependency. It prints on the existing print panel, works with Turbo, and
  is testable by asserting on the markup.
- Each chart sits **above** the table it summarises. The table stays the
  accessible and exportable source, so
  [ADR 0029](../adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)
  is unchanged.
- Colour by branch from a fixed palette (the dataviz skill palette, once
  it is built).
- A print-friendly "Management" one-pager of charts 1–5 could later cover
  the on-demand case of the deferred donor digest.

Building chart 1 on `?tab=month` is the obvious first slice once a chart
is picked. The "inline SVG, no chart library" choice would need its own ADR
at that point.

## The "Options Not Taken"

- **`symfony/ux-chartjs`.** It is a new dependency, and this project runs
  `allow-contrib: false`, so it would be wired by hand
  ([ADR 0003](../adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)).
  Its canvas output doesn't print crisply on the Reports print panel, and
  it adds JavaScript to draw data that never changes once the page is
  rendered. Revisit only if someone asks for interactivity (hover, zoom).
- **`Activity::$beneficiaryCount`.** There is one `Activity` row per
  volunteer, so a four-volunteer outing would count its beneficiaries four
  times. `ActivitySummaryCalculator` only knows an outing as distinct
  date + project, derived after the fact; there is no outing row to hang a
  count on. A real outing or session entity (program × date) would fix
  that, but it restructures
  [ADR 0030](../adr/0030-insert-programs-between-projects-and-activities.md).
  Slice A (indicators on achievements) gets the same numbers, entered once
  and on purpose.
- **Project coordinates and maps.** Five branches in two cities. A bar
  chart says the same thing, and a map adds a tile provider or a geometry
  dataset for no extra decision.
- **Fees or donations per stay.** This could be the top management chart
  *if* UCESCO charges programme fees, which is unknown. It is financial
  data tied to a person, so it is a question for UCESCO first, not a schema
  proposal.
- **Gender splits and age pyramids.** Gender is profile-page only (ADR
  0037), and date of birth is optional and sparse (ADR 0032), so an age
  pyramid would describe the few profiles that happen to be complete.
- **Anything in hours, today.** Hours aren't recorded; days are. A chart in
  hours waits for slice D.

## Constraints

- **Gender is never charted**
  ([ADR 0037](../adr/0037-record-a-volunteers-gender-for-accommodation-pairing.md)):
  it is shown on the profile page only, never listed or aggregated.
- **Profile fields are optional and never backfilled**
  ([ADR 0032](../adr/0032-store-volunteer-profile-fields-as-optional-and-photos-as-re-encoded-jpeg-blobs-in-sqlite.md)).
  Date of birth is sparse, so any age-band chart covers only part of the
  volunteers and must say so.
- **New personal-data fields need their own ADR**
  ([ADR 0034](../adr/0034-comply-with-kenyas-data-protection-act-2019.md)),
  stating purpose, sensitivity, retention and transfer basis. That covers
  attendance (E) and stay cancellation (F) at least.
- **One `Activity` row per volunteer.** Any per-activity count of
  beneficiaries double-counts; outings exist only as date + project derived
  in `ActivitySummaryCalculator`. Impact numbers therefore go on
  achievements (slice A), not on activities.
- **The repo is public.** No real names or real figures go into this file,
  the backlog cards or chart fixtures. Examples stay illustrative.
- **`allow-contrib: false`** makes any chart package a hand-wiring job,
  which weighs against adding one for static charts.
- **Every chart needs its table.** Export stays on the table (ADR 0029), so
  a chart never becomes the only place a number appears.
- **Unknowns that only UCESCO can settle:** which three charts management
  actually wants, which impact indicators to record, how many places each
  branch has, and whether programme fees exist.

## Follow-ups

- [`dataviz-first-charts.md`](../project/backlog/dataviz-first-charts.md):
  charts 1, 6 and 2 on today's schema; the decision for UCESCO is which
  three charts management actually wants.
- [`branch-capacity-and-program-staffing-targets.md`](../project/backlog/branch-capacity-and-program-staffing-targets.md):
  schema slice B + C (A became [ADR 0047](../adr/0047-count-achievements-with-an-optional-indicator-and-quantity.md)), with the questions for UCESCO (which indicators,
  places per branch, whether fees exist).
