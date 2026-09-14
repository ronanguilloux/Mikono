---
title: Add a Programs section between Project and Activity
created: 2026-09-14
source: ronan
status: deferred
size: XL
priority: later
labels: [data]
epic:
---

# Add a Programs section between Project and Activity

## Why

A `Project` currently goes straight to `Activity`. UCESCO wants a
"Program" layer in between: each Program belongs to one Project, carries
its own dates, location, roles and activity types (the kind of work done
in that program), and every `Activity` belongs to one Program rather than
directly to a Project. A Program can be time-boxed (a medical camp) or
open-ended/long-running (e.g. a year-round program).

## Done when

Decided and, if built, shipped as an ADR plus the entity/form/reports
work. Not fillable yet — an ADR must settle these first:

- **`Activity::$project` vs `Activity::$program`.** Does `Activity` keep
  a direct `Project` link, or is the project now derived through
  `Program` the way [ADR 0026](../../adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md)
  derives a volunteer's branch through `Stay` (never a form field, always
  re-resolved on save)? The existing precedent in this codebase is
  "derive, don't duplicate."
- **The branch-consistency chain.** [ADR 0027](../../adr/0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md)
  already requires an activity's project to share its stay's branch. If
  `Program` sits between `Project` and `Activity`, that check now has one
  more hop (stay → branch, activity → program → project → branch) and
  needs to be written and tested at every save path that ADR 0027 already
  covers (activity save, stay edit, project edit) — plus a new one,
  program edit.
- **Roles and activity types "specific to each program."** `ActivityType`
  today is a flat, global list attached directly to `Activity`. Does each
  `Program` get its own scoped list of allowed activity types, or a
  subset of the global list? "Roles" doesn't exist as a concept yet
  anywhere except `User`'s auth roles — is this a new small entity
  (Program-scoped roles assignable to volunteers/escorts on an activity),
  or free text?
- **Open-ended programs.** A time-boxed program (a medical camp) has a
  clear start/end; a long-running one (year-round) may have no end date.
  Decide whether `endDate` is nullable on `Program` and what "the program
  covering this date" resolution does when it's null — this is the same
  shape of problem `Volunteer::getStayCovering(date)` solves for stays,
  and should probably reuse that pattern rather than inventing a new one.
- Migration path for existing `Activity` rows, which currently point at a
  `Project` directly and would need a `Program` backfilled (or one
  default "legacy" program per project) before `Activity::$project` could
  be dropped.

## Notes & links

- Precedent to follow, not reinvent: [ADR 0026](../../adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md)
  and [ADR 0027](../../adr/0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md)
  already solved "an entity is attached to something through a
  time-bounded relation, and a downstream save must re-resolve and
  validate the chain." Program-to-Project should be modeled the same way
  rather than as an independent parallel scheme.
- This is `size: XL` because it touches the `Activity` schema, both
  activity forms (single + batch), `DataTable`/reports grouping, and at
  least two ADRs' worth of validation logic. Once the ADR lands, split
  this card into smaller `ready` ones (entity + migration, form wiring,
  reports) rather than implementing it as one PR.

## Trigger

Deferred until the ADR decision above is made — revisit when Edna or
Nickson names a concrete program (e.g. the next medical camp) that
existing Project/ActivityType can't represent.
