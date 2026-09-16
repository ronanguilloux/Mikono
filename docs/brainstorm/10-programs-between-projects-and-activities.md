# Brainstorm — Programs between projects and activities

**Date:** 2026-09-17
**Author:** <ronan.guilloux@gmail.com>
**Related:** [`CLAUDE.md`](../../CLAUDE.md),
[backlog card](../project/backlog/programs-section.md),
[ADR 0026](../adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md),
[ADR 0027](../adr/0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md),
[ADR 0012](../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md),
[`docs/adr/`](../adr/)

---

## Primary audience

Ronan, as future-self, when resuming the Programs work. Source: Ronan,
working session.

## Desired impact

A project can run more than one program, and every activity says which
program it belongs to. The first real case is "Computer Tuition" at Peggy
Lucas School, which runs next to that project's always-on "School support".
Some programs have fixed dates (a medical camp). Others run with no dates at
all.

In hindsight, success looks like this:

- Computer Tuition and School support both exist at Peggy Lucas and are
  logged separately.
- The activity-type picker on an activity lists only the types its program
  offers.
- No activity is dated outside its program.
- `Activity` has no `project` column left.

## Where things stand (2026-09-17)

The [backlog card](../project/backlog/programs-section.md) was deferred until
a concrete program came up. Ronan has now named one: Computer Tuition at
Peggy Lucas School.

His first proposal was:

- A Program belongs to one Project (mandatory).
- It has start and end dates, optional for an always-on program.
- It has suggested roles, entered as free text in a textarea for now.
- It has a list of activity types.
- An Activity belongs to one Program, and its activity-type picker lists
  only the types that program offers.
- Each activity type belongs to exactly one program, with no orphans.

The archive didn't support that last point. In `docs/fixtures/rosters.yaml`:

- "School support" appears at 3 projects: Peggy Lucas, Bright Achievers and
  Mt Hermon.
- "Clinic support" and "Orphanage support" appear at 2 projects each.

If each program owned its types, those names would be duplicated. That breaks
the `uniq_activity_type_name` constraint and splits the per-type lines on
`/reports`.

The archive also shows that each of its 12 projects has exactly one activity
type today. A Program layer only earns its keep once a project has a second
program.

## The shape this landed on

Ronan chose the recommended option on each point:

1. **`ActivityType` stays a global list with unique names.** Program and
   ActivityType are linked many-to-many, and every program offers at least
   one type. "No orphans" now means that activity pickers list only types
   offered by at least one program.
2. **`Activity::$project` is dropped.** An activity's project is derived
   through its program, following ADR 0026's rule: derive, don't duplicate.
3. **A program's start and end dates are each optional.** A program with no
   dates is always-on, and one with only a start date is open-ended. An
   activity dated outside its program is refused at save. Open-ended dates
   are new here, because a `Stay`
   ([ADR 0026](../adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md))
   always has both dates.
4. **A small Stimulus controller filters the type picker** when the program
   changes. The server checks the type on save either way, and without JS
   the picker still works, just unfiltered.

## The "Options Not Taken"

- **Each activity type owned by one program.** This was Ronan's first
  proposal. The archive shares type names across projects: "School support"
  is used at three of them. Program-owned types would duplicate those names,
  break `uniq_activity_type_name`, and split the per-type totals on
  `/reports`.
- **Keeping both `Activity::$project` and `Activity::$program`, with a check
  that they match.** That stores the same fact twice and adds a guard to keep
  the copies in sync. ADR 0026 set the rule for this codebase: derive, don't
  duplicate.
- **Program dates as both-or-neither.** That can't express a program that
  has started and has no planned end.
- **Program dates as information only.** Nothing would stop an activity from
  being logged outside its program's dates, which makes the dates pointless.
- **Checking the type only at save, with no filtering.** The VM would pick a
  type and only learn on submit that the program doesn't offer it. That's
  clunky.
- **A single combined "Program → Type" picker.** It would need a custom
  non-entity form field on both activity forms (single and batch), which is
  more work than filtering one picker.
- **ADR 0027's reason for not filtering in JS doesn't apply here.**
  [ADR 0027](../adr/0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md)
  turned down JS filtering of the project picker because the stay is only
  resolved after the form is submitted. The program is a field on the form,
  so the type picker can be filtered as soon as it changes.

## Constraints

- **ADR 0027's branch rule gets one more hop.** The check becomes
  `activity.program.project.branch == activity.stay.branch`.
- **Program edit is a new write path and needs its own guards.** It refuses
  to:
  - change the project while the program has activities;
  - narrow the dates so existing activities fall outside them;
  - remove a type that activities use.
- **Suggested roles stay free text.** No `Role` entity. The only roles in
  the app today are `User`'s authentication roles.
- **The card's "location" field is dropped.** The project already has a
  branch.
- **Fixtures:** this slice leaves `rosters.yaml` untouched, because
  [ADR 0012](../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)
  rules out invented data. The dev fixtures get one always-on program per
  project, named after that project's activity type.
- **Real programs come later.** A later step will seed them from
  [UCESCO's Volunteer World listing](https://www.volunteerworld.com/en/filter?ProjectId=71f31166-6a8c-45fb-acb5-cccd69a2a98e),
  which describes programs across UCESCO's branches and projects.
- **Deferred:** per-program totals on `/reports`, and a program filter on
  `/activities`.
