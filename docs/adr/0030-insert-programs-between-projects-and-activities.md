# 0030. Insert programs between projects and activities

Date: 2026-09-17

## Status

Accepted

## Context

A project can run more than one program. The first real case is "Computer
Tuition" at Peggy Lucas School, which runs next to that project's always-on
"School support". Some programs have fixed dates (a medical camp), others
have none. An activity recorded only its project, so two programs at one
project could not be told apart.

The roster archive
([ADR 0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md))
constrains the shape. Activity type names are shared across projects:
"School support" appears at three projects, "Clinic support" and
"Orphanage support" at two each. `ActivityType` names are unique
(`uniq_activity_type_name`), and `/reports` totals per type.

Two existing rules also apply. ADR 0026 derives a fact rather than storing
it twice
([ADR 0026](0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md)).
ADR 0027 requires an activity's project to share its stay's branch
([ADR 0027](0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md)).

## Decision

**Every activity belongs to a program, every program belongs to a project,
and an activity's project is derived through its program.**

The entity:

- `Program` has a required `name` and a required `ManyToOne` to `Project`.
- `startDate` and `endDate` are `date_immutable` and each nullable. No
  dates means always-on; a start date alone means open-ended from that
  date. When both are set, the end is on or after the start.
- `suggestedRoles` is nullable free text. There is no `Role` entity; the
  only roles in the app are `User`'s authentication roles.
- `activityTypes` is a `ManyToMany` to `ActivityType` through the join
  table `program_activity_type`, with at least one type.
- There is no `isActive` flag: the dates say whether a program runs.
- `Program::covers(date)` treats a null bound as open, and
  `Program::offers(type)` tells whether the program offers a type.

Activity types:

- `ActivityType` stays one global list with unique names. A program offers
  a subset of it, and several programs can offer the same type.
- The activity pickers list only types offered by at least one program.

The activity:

- `Activity::$project` no longer exists. `Activity::$program` is a
  required `ManyToOne` that replaces it.
- `Activity::getProject()` remains as a derived getter returning
  `program.project`. Don't turn it back into a stored column.

Saving an activity (single new, batch, edit) runs three checks in
`ActivityController::resolveStays()`:

- The program must offer the activity's type. The error sits on
  `activityType`.
- The program must cover the activity's date. The error sits on `date`.
- ADR 0027's branch check now goes through `program.project.branch`.
- A batch stays all-or-nothing.

Editing a program (`ProgramController`) is a write path of its own. It
refuses with a 422 and a field error to:

- change the project while the program has activities;
- narrow the dates so they exclude existing activities;
- remove a type that existing activities of that program use.

Delete guards:

- A program cannot be deleted while activities reference it.
- A project cannot be deleted while it has programs.
- An activity type cannot be deleted while activities or programs
  reference it.

Pickers:

- The program picker on both activity forms is grouped by branch, and each
  option reads "Project — Program".
- The type picker on both forms is filtered in the browser by a small
  Stimulus controller when the program changes. Each type option carries a
  `data-programs` attribute listing the programs that offer it.
- The server checks the type on save regardless. Without JavaScript the
  type list is simply unfiltered.

Export: the programs list exports to CSV and XLSX like every other list
([ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)).

Data:

- The migration creates one always-on program per existing project, named
  after the project. It links that program to the distinct activity types
  the project's activities used, sets `activity.program_id`, then drops
  `activity.project_id`.
- Dev fixtures (`AppStory`, `RosterArchive`) create one always-on program
  per archive project, named after that project's `activity_type`.
  `rosters.yaml` is unchanged, since ADR 0012 rules out invented data.
- Test factories: `ProgramFactory` defaults to an always-on program on a
  `ProjectFactory` project, offering one type. `ActivityFactory` builds a
  program whose project matches the stay's branch and which offers the
  activity's type.

## Consequences

- **Positive:** a project can run several programs, and each activity says
  which one it belongs to.
- **Positive:** type names stay unique, so per-type totals on `/reports`
  still add up across projects.
- **Positive:** the type picker shows only what the chosen program offers,
  and a dated program refuses activities outside its dates.
- **Negative / trade-offs:** ADR 0027's branch invariant, held by the
  application, is one hop longer.
- **Negative / trade-offs:** three more invariants are held by the
  application, not the schema: the type is offered by the program, the
  date is covered by the program, and the activity's project is derived. A
  new write path that skips them can store data the database will accept.
- **Negative / trade-offs:** about a dozen queries that joined
  `activity.project` now join through the program.
- **Negative / trade-offs:** a program cannot move to another project once
  it has activities.
- **Negative / trade-offs:** `/reports` has no per-program totals and
  `/activities` has no program filter; both are backlog work.
- **Reversibility:** expensive. Undoing it needs a migration that restores
  `activity.project_id` from the program, the removal of the program
  entity, its screens, guards, picker and Stimulus controller, and a
  rewrite of every query that joins through the program.

## Alternatives considered

### 1. Each activity type owned by one program

**Rejected.** The archive shares type names across projects ("School
support" at three of them). Program-owned types would duplicate those
names, break `uniq_activity_type_name`, and split the per-type totals on
`/reports`.

### 2. Keep `Activity::$project` alongside `Activity::$program`

**Rejected.** It stores the same fact twice and needs a guard to keep the
copies in step. ADR 0026 set the rule here: derive, don't duplicate.

### 3. Program dates as both-or-neither

**Rejected.** It cannot express a program that has started and has no
planned end.

### 4. Program dates as information only

**Rejected.** Nothing would stop an activity being logged outside its
program's dates, which makes the dates pointless.

### 5. Check the type only at save, with no picker filtering

**Rejected.** The VM would pick a type and learn only on submit that the
program doesn't offer it. ADR 0027's reason for not filtering in the
browser does not apply: the program is a form field, not something
resolved after submit, so the type picker can react as soon as it changes.

### 6. A single combined "Program → Type" picker

**Rejected.** It needs a custom non-entity form field on both activity
forms, single and batch, which is more work than filtering one picker.
