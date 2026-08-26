# Brainstorm — A System of Work for the Volunteer Manager

**Date:** 2026-08-26
**Author:** ronan.guilloux@gmail.com
**Related:** [`CLAUDE.md`](../../CLAUDE.md),
[`02-volunteer-manager-v0.1-context.md`](02-volunteer-manager-v0.1-context.md),
[`docs/adr/`](../adr/)

---

## Primary audience

Same audience as
[`02-volunteer-manager-v0.1-context.md`](02-volunteer-manager-v0.1-context.md):
the VM — a single non-technical user, working day to day from UCESCO's
projects in Kibera (Nairobi) and Mombasa, who needs the app to make sense
without a developer standing over their shoulder — plus future-self or a
future agent session that needs to understand why the app's scope grew
beyond plain CRUD after v0.1 shipped.

## Desired impact

v0.1 built a System of Record: CRUD for Volunteers, Projects, Activity
Types, Users, and Activities, a delete-guard protecting referential
integrity, and a historical summary report. That was the right first
milestone, but it is passive by nature — the VM only gets out what they
put in, and the app never tells them what to do next. The VM (who is
also UCESCO's actual Volunteer Manager, so this isn't a hypothetical
persona) has now flagged that the real goal is a System of Work: the app
should actively help them do their job, not just archive data about it.

Success looks like this: the VM opens the app and lands somewhere that
tells them what needs attention today, not a blank CRUD index or a
purely historical report. They can log a whole day's group activity —
one project, one activity type, one duration, several volunteers — in a
single pass instead of repeating the same single-volunteer form N times.
They can glance at one volunteer's history and notes without opening an
edit form to dig for them. And they can hand a report straight to UCESCO
leadership or a donor without any separate export step. In short, the
app should feel like it is doing some of the VM's thinking for them —
surfacing stale volunteers or projects, recognizing top contributors —
rather than sitting there waiting to be queried.

This also means the app has to serve two temporal directions, not one.
Most of what follows below looks backward — surfacing what already
happened so the VM can act on it. But the VM's actual daily rhythm
leans just as heavily on looking forward: almost every day, they decide
tomorrow morning's handful of activities — who is doing what, at which
project — and manually type that up as a roster message sent to
volunteers over WhatsApp. Nothing in the app today has any notion of a
*planned* activity; `Activity` (`src/Entity/Activity.php`) is
implicitly a past-tense log entry, and the one query that reads it,
`ActivityRepository::findAllOrderedByDateDesc()`, only ever looks
backward. A System of Work for this VM has to hold both halves of that
rhythm: the long history behind them and the short, high-stakes plan
just ahead of them.

The good news is that the forward-looking half doesn't need a new
entity or a lifecycle/status field to exist alongside the backward-looking
half. `Activity.date` is already a plain, unconstrained date column —
nothing stops it from being set to tomorrow today. So "tomorrow's
roster" is just the same `Activity` rows, the same batch/group logging
form (initiative 2 below) used with a future date instead of today's,
and a new view that filters to the next day (or next few days) and
groups by project instead of by volunteer or by date-descending
history. If a volunteer doesn't show up or a plan changes, the VM edits
or deletes that row — the exact same correction path that already
exists for a logging mistake, not a new workflow. The one trade-off
worth naming rather than quietly accepting: a future-dated row briefly
counts toward `totalDays` and `mostRecent` in the historical summary
before the day it describes has actually happened. Given the lookahead
is short — next-day, at most a few days — this is a minor,
self-correcting inaccuracy, not a reason to introduce a status field.

Nine concrete initiatives would deliver this, and all of them are
buildable within the app's current architecture, with no new mailer,
scheduler, or async infrastructure required:

1. **A work-focused home screen**, replacing the current plain Reports
   landing (`DashboardController` redirects `app_home` straight to
   `report_index` today). It surfaces volunteers and projects whose
   most-recent-activity date — already computed by
   `ActivitySummaryCalculator::summarize()` in
   `src/Report/ActivitySummaryCalculator.php` — is stale beyond a
   threshold, as an actionable "needs a check-in" list. The existing
   historical breakdown moves to a secondary view rather than being
   removed.
2. **Batch/group activity logging**, a form variant that matches how the
   work actually happens: one session, one date/project/activity
   type/duration, several volunteers at once — instead of forcing one
   single-volunteer submission per person. `Activity`
   (`src/Entity/Activity.php`) already links
   volunteer+project+activityType+duration+loggedBy per row; this only
   needs a multi-select `Volunteer` field on the form and a loop in the
   controller producing N rows from one submission, with no
   LiveComponent or new JavaScript involved.
3. **A volunteer detail/timeline "show" page.** None of the five CRUD
   areas currently has a show view (only index/new/edit), so there is
   nowhere today to see one volunteer's activity history and notes at a
   glance. This turns `Volunteer` into an actual lightweight CRM record
   instead of a row in a table.
4. **Surfacing the `notes` field** that already exists on `Volunteer`
   and `Project` (`src/Entity/Volunteer.php`, `src/Entity/Project.php`)
   somewhere it is seen again after entry — today it is captured once
   and then effectively invisible.
5. **Opportunistic data-completeness nudges**, flagging volunteers
   missing a phone number or email inline, without turning these
   legitimately-optional fields into blocking validation errors.
6. **A "top volunteers this period" recognition view**, built from data
   `summarizeByVolunteer()` already produces — supporting actively
   thanking and retaining volunteers, not just recording their hours
   after the fact.
7. **Print-friendly report styling** — `@media print` CSS only, no new
   dependency — so the existing Reports view can go straight to a donor
   or UCESCO leadership without a separate export step.
8. **Tomorrow's roster.** A view filtered to `Activity` rows dated
   tomorrow (or the next few days), grouped by project, alongside a
   one-click "copy as text" block formatted for pasting straight into a
   WhatsApp message — built entirely on the batch/group logging form
   (initiative 2) and a new query method, no schema change and no
   messaging infrastructure.
9. **Escort/chaperone capture.** Every real roster message groups
   volunteers by project with one line naming the staff member who
   accompanied them that day ("Accompanied by Mr Maeba"). Nothing today
   captures this. It's modeled as a new `Escort` lookup entity —
   id/name/`isActive`, the same shape as `ActivityType` — giving the
   app a 6th CRUD area (list/new/edit, managed from the Settings nav
   next to Activity Types), plus a new optional
   `Activity::$accompaniedBy` (`ManyToOne` → `Escort`, nullable) set
   once per submitted batch (initiative 2) and applied to every row the
   batch creates. Initiative 8's copy-as-text block then renders it
   back out as the "Accompanied by ..." line per project group, closing
   the loop with the real message format. This needs a migration and a
   new CRUD area, but reuses the app's already-established 5-area CRUD
   pattern rather than any new infrastructure.

## Evidence from real roster messages (2026-08-27)

Edna's actual nightly WhatsApp messages — the ones she manually types
up every evening for tomorrow's volunteers — were reviewed directly
(not a hypothetical reconstruction) and confirm several assumptions
above, plus surface the one gap initiative 9 addresses:

- **Project-grouping matches the real format.** Every message lists
  activities grouped by project/site ("Beyond Zero clinic", "Peggy
  Lucas school", "MVETI", ...), each with the volunteers attending
  underneath — exactly the shape initiative 8's "Tomorrow's roster"
  view already proposes.
- **A volunteer can hit multiple projects in one day**, e.g. "Rahel —
  later MVETI" moving from one site to another same day. This needs no
  schema change: it's already just multiple `Activity` rows for the
  same volunteer and date, at different projects — a confirmation, not
  a gap.
- **The opening line is the VM's own voice, not schedule data.** Every
  message opens with a personal motivational quote or greeting
  unrelated to the roster itself. Initiative 8's "copy as text" block
  should generate the schedule body only and leave room for Edna to
  prepend her own greeting before sending — not attempt to auto-generate
  it.
- **The escort/chaperone line is real and recurring** — see initiative
  9 above.
- Dev fixtures (`src/Story/AppStory.php`) were expanded to seed the real
  breadth of UCESCO's projects and activity types (clinics, schools,
  MVETI, orphanage, beach clean-ups, home visits, orientation, etc.)
  observed in these messages, so the app's worked examples stop looking
  like a two-project toy dataset.

## The "Options Not Taken"

### 1. A full task/assignment system now

**Rejected for now.** Full `User` CRUD already exists and was
deliberately built ahead of need, because more UCESCO staff are expected
to get accounts later (per
[`02-volunteer-manager-v0.1-context.md`](02-volunteer-manager-v0.1-context.md)).
But today there is still only one real user, and a task/assignment
system (e.g. assigning a "check on this volunteer" follow-up to a named
staff member) has no one to assign to yet. This is worth revisiting once
a second account actually exists, not before.

### 2. Automated outbound reminders now

**Rejected for this pass.** The natural next step after the "needs
attention" home screen (initiative 1) is auto-notifying someone when a
volunteer goes stale — an email or text to the VM, or to the volunteer
directly. But no mailer, scheduler, or async transport is configured in
this app today, and choosing one is a real architectural decision, not a
template change. Worth flagging explicitly: given the Kibera/Mombasa
context, SMS via a regional gateway (e.g. Africa's Talking, widely used
in Kenya) may reach people far more reliably than email. That choice —
SMS vs. email vs. staying purely in-app — deserves its own ADR weighing
those options on their merits, rather than defaulting to email out of
habit because it's the path of least resistance in a typical Symfony
app.

### 3. A volunteer self-service portal

**Rejected**, consistent with the original v0.1 decision that
volunteers are CRM-style people who never log in
(see [`02-volunteer-manager-v0.1-context.md`](02-volunteer-manager-v0.1-context.md)).
Nothing in this System-of-Work narrative revisits that; volunteers
logging their own hours or updating their own profile is a different
product with a different trust model, and there is no indication UCESCO
wants that.

### 4. Scheduled/automated donor digest emails

**Rejected for the same infrastructure reason as option 2.** A
recurring, automated donor-facing email would need the same
mailer/scheduler groundwork this app doesn't have yet. The print-friendly
report view (initiative 7) covers the actual need — an on-demand handoff
to a donor or to leadership — without requiring a mailer at all.

### 5. WhatsApp Business API / automated roster sending

**Rejected.** Tomorrow's roster (initiative 8) could, in principle, be
sent automatically via the WhatsApp Business API instead of being
copy-pasted by the VM. That's real added complexity — API costs,
volunteer opt-in/consent handling, message-template approval — for a
step that today takes the VM well under a minute by hand. There's no
evidence that manual copy-paste is actually a bottleneck; build the
integration only if it demonstrably becomes one, not preemptively.

## Constraints

- No mailer, scheduler, or async infrastructure exists yet beyond what
  is already in the stack (Docker + FrankenPHP, Symfony 8.1, SQLite,
  Tailwind — see
  [ADR 0003](../adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)).
  Anything requiring a new outbound channel is deliberately deferred to
  its own future ADR rather than bundled into this pass.
- It is still a single non-technical VM user day to day, so every
  initiative above has to stay usable without training or a developer's
  help — the same constraint v0.1 was built under.
- Low-bandwidth field conditions in Kibera and Mombasa still apply:
  nothing proposed here should require a heavier client-side stack — no
  new JS framework, no LiveComponent forced in where a plain form still
  works, matching the reasoning already recorded in
  [`02-volunteer-manager-v0.1-context.md`](02-volunteer-manager-v0.1-context.md)'s
  "Options Not Taken" entry on LiveComponent-driven dependent selects.
- This narrative doesn't replace or reopen the UX-review findings
  already logged in `docs/project/next-steps.md` (form consistency,
  accessibility, mobile nav, and so on) — those stand independently;
  this document is additive scope on top of v0.1, not a correction of
  that earlier pass.
