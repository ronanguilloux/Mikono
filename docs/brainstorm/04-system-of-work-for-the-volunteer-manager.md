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

Seven concrete initiatives would deliver this, and all of them are
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
