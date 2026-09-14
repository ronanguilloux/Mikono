# 0026. Attach volunteers to branches through dated stays and derive active from them

Date: 2026-09-14

## Status

Accepted

## Context

Volunteers come to UCESCO for a few weeks and sometimes return later,
possibly to a different branch
([ADR 0025](0025-model-ucesco-branches-as-a-standalone-reference-entity-seeded-by-migration.md)).
The Volunteer Manager (VM) needs to know where a volunteer was and when,
and every logged activity must belong to a branch. A single manual
`isActive` flag on `Volunteer` cannot record repeat visits or a change of
branch. It also goes stale whenever the VM forgets to untick it.

The stay is what ties an activity to a branch. Projects belong to a branch
too, and the project's branch must match the stay's
([ADR 0027](0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md)).
"Today" and every stay boundary are Nairobi calendar days
([ADR 0024](0024-treat-dates-as-calendar-days-in-nairobi-time.md)).

## Decision

**A volunteer is attached to a branch through dated `Stay` records. Every
activity carries a required link to the stay that covers its date, and
"active" means a stay covers today.**

The `Stay` entity:

- `Stay` has a volunteer (required, deleted with the volunteer: DB
  `ON DELETE CASCADE` and cascade remove on `Volunteer::$stays`, which is
  ordered by `startDate` descending), a branch (required), and
  `startDate`/`endDate` as inclusive calendar days with
  `endDate >= startDate`.
- **A volunteer's stays never overlap.** The stay controller refuses an
  overlapping stay, so a volunteer has at most one branch on any day, and
  `Volunteer::getStayCovering(date)` is unambiguous. Relaxing this breaks
  stay resolution.

Activities:

- `Activity::$stay` is a required foreign key. Every save (single new,
  batch, edit) resolves it again from the volunteer's covering stay for the
  activity's date. **The stay is never a form field.**
- If no stay covers the date, the save is refused. The form error sits on
  the date field and names the volunteer(s). A batch is all-or-nothing:
  one uncovered volunteer blocks the whole batch.
- `Activity::$volunteer` stays because many readers use it. It cannot
  drift from the stay's volunteer, because the stay is re-resolved on
  every save. Any new write path for activities must resolve the stay the
  same way, including the project-branch check of ADR 0027.

Active status:

- There is no `isActive` column or checkbox on `Volunteer`.
  `Volunteer::isActive()` is true when a stay covers today.
- Queries use the same rule. The volunteer list query selects a HIDDEN
  `isCurrent` count of stays covering today. It sorts active volunteers
  first and backs the index's Status sort (`SORT_MAP` `'status' =>
  ['isCurrent']`, per
  [ADR 0011](0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md)).
  The Status cells and the `/reports` active tile count the same stays
  covering today. Never reintroduce a stored flag for any of these.
- Both activity forms offer volunteers with a current or upcoming stay
  (`endDate >= today`). The edit form keeps the activity's own volunteer
  selectable even without such a stay, or older activities could not be
  edited.

Managing stays:

- Stays live on the volunteer page, in a Stays panel on
  `/volunteers/{id}`: new at `/volunteers/{id}/stays/new`, edit at
  `/stays/{id}/edit`, and delete as `POST /stays/{id}/delete`. There is no
  `/stays` area.
- An edit that would leave activities outside the stay's new dates is
  refused.
- A stay with activities cannot be deleted. Delete is shown inert and the
  server refuses it as well.
- A branch with stays cannot be deleted either. Delete is guarded in the
  UI and on the server.
- A stay's branch cannot change while activities in it are at projects of
  another branch (ADR 0027).

Data:

- The schema migration backfills one stay for each existing volunteer who
  had activities. The branch was "Mombasa" when `MIN(project.location)`
  was `mombasa`, and "Nairobi (HQ)" otherwise. The stay runs from the first
  activity date to the last, or to the day of the migration if the
  volunteer was active. Volunteers with no activities got no stay, so they
  read as inactive. This was acceptable because no server held real data
  yet.
- Dev fixtures derive one stay per archive volunteer from the roster
  archive, never from a generator
  ([ADR 0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)).
  A stay runs from the volunteer's first appearance to their last, at the
  branch of the sites they worked. An archive `active: true` extends it to
  the archive's last day.
- Test factories follow the same model. A volunteer gets one stay covering
  today by default, `inactive()` gives a past stay and `withoutStay()`
  gives none. An activity reuses the volunteer's covering stay or creates a
  one-day stay.

## Consequences

- **Positive:** every activity has a branch, and the schema guarantees that
  a stay covers it.
- **Positive:** active status can't go stale. It follows from the dates the
  VM has already entered.
- **Positive:** repeat visits and branch changes are separate stays, so the
  volunteer page shows where and when each one happened.
- **Negative / trade-offs:** the VM must record a stay before she can log
  an activity for that volunteer. An uncovered date blocks the save, and
  blocks a whole batch.
- **Negative / trade-offs:** "active" is now a subquery on every volunteer
  list, not a column read.
- **Negative / trade-offs:** stay edits and deletions are constrained by
  the activities logged in them. Moving a stay's dates can mean moving
  activities first.
- **Negative / trade-offs:** an activity's branch is shown on its stay
  and, through its project, in the project pickers, which are grouped by
  branch; a project at another branch than the stay's is refused at save
  ([ADR 0027](0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md)).
  There is no branch column or filter on `/activities` and no per-branch
  totals on `/reports`.
- **Reversibility:** expensive. Removing stays means dropping a required FK
  from `Activity`, restoring a stored active flag with a backfill, and
  rewriting the pickers, sorts, reports, factories and fixtures that read
  stays.

## Alternatives considered

### 1. Derive the branch at read time, with no column on `Activity`

**Rejected.** Every read that needs a branch would have to join stays by
date. Nothing would guarantee that a stay covers an activity at all. The
required FK makes coverage a schema fact.

### 2. Copy a `Branch` onto `Activity` at save

**Rejected.** Stays get edited. When a stay's dates or branch change, a
copied branch is silently wrong. A `Stay` FK re-resolved on every save
can't fall out of step that way.

### 3. Allow activities outside any stay, with an unknown branch

**Rejected.** That leaves gaps in the "where was this volunteer" answer
this feature exists to give. Refusing the save with an error that names
the volunteer makes the VM fix the stay first.

### 4. A separate `/stays` area

**Rejected.** A stay only makes sense for one volunteer. Listed on its own,
it is a table of dates without context, and the volunteer page is already
where the VM looks up that person.

### 5. Keep the manual `isActive` checkbox alongside stays

**Rejected.** It would repeat what the stays already say, and the two would
disagree the first time the VM updated one and not the other.
