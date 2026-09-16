# 0027. Tie projects to a branch and require an activity's project to share its stay's branch

Date: 2026-09-17

## Status

Accepted

## Context

A project used to record its place as a `ProjectLocation` enum (`kibera`
or `mombasa`). That overlapped with `Branch`
([ADR 0025](0025-model-ucesco-branches-as-a-standalone-reference-entity-seeded-by-migration.md)),
the geographic unit the Volunteer Manager (VM) already edits in Settings.
Two sources of geography can disagree, and only one of them is editable in
the app.

Activities get their branch from their stay
([ADR 0026](0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md)).
Projects had no branch, so nothing stopped an activity at a Mombasa project
from being logged in a Nairobi stay, and the project picker could not be
organised by branch.

The real roster data settles what the rule should be. Every activity at a
`kibera` project falls in a stay at "Nairobi (HQ)", and every activity at a
`mombasa` project in a stay at "Mombasa", with no exceptions. The fixture
story already refused a volunteer who worked sites in two locations. The
project owner decided that a volunteer staying at one branch may not log
activity at another branch's project.

An activity reaches its project through its program
([ADR 0030](0030-insert-programs-between-projects-and-activities.md)), so
the rule applies to the program's project.

## Decision

**Every project belongs to one branch, and an activity's project (through
its program) must be at the same branch as the activity's stay.**

The relation:

- `Project::$branch` is a required `ManyToOne` to `Branch` (join column not
  nullable, validation message "Choose a branch."). The `ProjectLocation`
  enum and the `location` column no longer exist.
- The project form has a branch picker listing every branch, with inactive
  ones labelled, as on the stay form. The project index has a Branch column
  that sorts by branch name through a fetch join in
  `ProjectRepository::createOrderedByNameQueryBuilder()`.
- The branch delete-guard counts stays **and** projects
  (`BranchRepository::countReferences`/`countReferencesFor`), as ADR 0025
  requires for every relation to `Branch`.
- A Uganda project needs no new enum case: Uganda is already a branch row.

The invariant:

- The foreign keys are Activity → Volunteer, Activity → Stay, Activity →
  Program, Program → Project, Stay → Volunteer, Stay → Branch and
  Project → Branch. There is no directed cycle; `Branch` and `Volunteer`
  are sinks. There are two redundant undirected paths, and **each is held
  by the application, not the schema**:
  1. `activity.volunteer == activity.stay.volunteer`, held by re-resolving
     the stay on every save (ADR 0026).
  2. `activity.program.project.branch == activity.stay.branch`, held by the
     four guards below.
- **Saving an activity** (single new, batch, edit):
  `ActivityController::resolveStays()` refuses a covering stay at a branch
  other than the program's project's. The error sits on the `program`
  field and names the volunteer(s) and their branch. A batch is
  all-or-nothing.
- **Editing a stay:** `StayController` refuses a branch change that would
  leave activities logged in that stay at another branch's projects
  (`StayRepository::countActivitiesAtOtherBranch`, which joins through the
  program). The error sits on `branch`.
- **Editing a project:** `ProjectController` refuses a branch change while
  activities at that project's programs fall in stays at another branch
  (`ProjectRepository::countActivitiesAtOtherBranch`, which joins through
  the program). The error sits on `branch`, and the response is 422.
- **Editing a program:** `ProgramController` refuses a project change while
  the program has activities (ADR 0030). That refusal is the guard: a
  program with activities never changes branch.
- **Any new write path** that creates or changes an activity, a stay's
  branch, a project's branch or a program's project must run the same
  check, or the invariant silently breaks.

Pickers:

- The program pickers on both activity forms group programs by branch
  (`group_by`, rendered as native `<optgroup>`), each labelled
  "Project — Program". They are **not** filtered by the stay: the stay is
  resolved from volunteer and date only after submit, so the save-time
  guard is what enforces the rule.

Data:

- The migration backfilled `project.branch_id` from `location` with the
  same mapping the stay migration used: `mombasa` became "Mombasa",
  anything else "Nairobi (HQ)". It then dropped `location`.
- In the roster archive
  ([ADR 0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)),
  a project record carries `branch: <seeded branch name>`. `AppStory` looks
  the branch up by name, and a volunteer's stay takes the branch of the
  sites they worked.
- Test factories: `ProjectFactory` and `StayFactory` both default to
  "Nairobi (HQ)", never a random branch, so an activity's project and stay
  agree unless a test says otherwise. `ActivityFactory` builds a program
  whose project matches the stay's branch.

## Consequences

- **Positive:** geography has one source, and the VM edits it in the app.
- **Positive:** the activity forms show programs grouped by branch, and a
  cross-branch mistake is caught at save with an error naming the
  volunteer and their branch.
- **Positive:** a branch reached through the program's project and through
  the stay always agree, so any report can group by either.
- **Negative / trade-offs:** the invariant lives in application code. A
  new write path that skips the check can store an activity whose two
  branches disagree, and the database will accept it.
- **Negative / trade-offs:** the path to a project's branch is two hops
  from an activity, so every query that needs it joins through the
  program.
- **Negative / trade-offs:** a project with activities cannot simply be
  moved to another branch. Its activities' stays have to move first.
- **Negative / trade-offs:** the "Kibera (Nairobi)" label is gone. "Kibera"
  survives only in `Branch::$projectZones` text, which nothing queries.
- **Negative / trade-offs:** `/reports` has no per-branch totals and
  `/activities` has no branch filter; both are backlog work.
- **Reversibility:** moderate. Dropping the invariant means deleting the
  activity, stay and project guards (the program guard also serves
  ADR 0030). Removing `Project::$branch` means a migration that restores a
  location value per project and a rewrite of the project form, index
  sort, pickers, archive and factories.

## Alternatives considered

### 1. Keep the `ProjectLocation` enum alongside the branch

**Rejected.** It says the same thing as the branch. The VM can edit a
branch but not an enum case, so the two would drift the first time a
project moved, and adding a place would need a deploy.

### 2. Allow activities at another branch's project

**Rejected.** The project owner ruled it out, the real rosters contain no
such activity, and with two paths from an activity to a branch, reports
grouped through the project and through the stay would disagree.

### 3. Derive a stay's branch from its projects instead of storing it

**Rejected.** A stay exists before any activity is logged in it, and it
drives "active" (ADR 0026). A stay with no activities would have no branch.

### 4. Filter the program picker by the stay's branch

**Rejected.** The stay is resolved from volunteer and date after submit,
and a batch has several volunteers. Grouping by branch in the picker, with
the check at save, covers it without JavaScript. (The type picker is
filtered in the browser because the program is a form field, not resolved
after submit; see ADR 0030.)

### 5. Enforce the invariant with a database trigger or check constraint

**Rejected.** A cross-table check needs a trigger, which is not portable
off SQLite (every enum here is mapped as a plain string for the same
reason) and is not how this codebase enforces rules. The controller guards
also give a form error on the right field rather than a 500.
