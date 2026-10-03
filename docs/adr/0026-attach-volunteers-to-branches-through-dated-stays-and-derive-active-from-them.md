# 0026. Attach volunteers to branches through dated stays and derive their status from them

Date: 2026-10-03

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

The VM also needs to tell who is here, who is coming (she plans for them)
and who has been (she keeps in touch with them). Two states, active and
inactive, put a volunteer arriving next week in the same bucket as one who
left two years ago.

The stay is what ties an activity to a branch. Projects belong to a branch
too, and the project's branch must match the stay's
([ADR 0027](0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md)).
"Today" and every stay boundary are Nairobi calendar days
([ADR 0024](0024-treat-dates-as-calendar-days-in-nairobi-time.md)).

## Decision

**A volunteer is attached to a branch through dated `Stay` records. Every
activity carries a required link to the stay that covers its date, and a
volunteer's status (Present, Upcoming, Past or No stay) is derived from
their stay dates on every read, never stored.**

The `Stay` entity:

- `Stay` has a volunteer (required, deleted with the volunteer: DB
  `ON DELETE CASCADE` and cascade remove on `Volunteer::$stays`, which is
  ordered by `startDate` descending), a branch (required), and
  `startDate`/`endDate` as inclusive calendar days with
  `endDate >= startDate`.
- **A volunteer's stays never overlap.** The stay controller refuses an
  overlapping stay, so a volunteer has at most one branch on any day, and
  `Volunteer::getStayCovering(date)` is unambiguous. Relaxing this breaks
  stay resolution, the Present rule and the days-on-site sum.

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
- The activity forms do not read the status. The batch form lists every
  volunteer with a stay and narrows the list to the chosen date in the
  browser. The edit form offers volunteers with a current or upcoming stay
  (`endDate >= today`) and keeps the activity's own volunteer selectable
  even without one, or older activities could not be edited. These pickers
  decide which date can be logged, not where a volunteer stands.

Status:

- `App\Enum\VolunteerStatus` has four string-backed cases: `present`,
  `upcoming`, `past` and `none`, labelled Present, Upcoming, Past and
  No stay. There is no status or `isActive` column, and no checkbox, on
  `Volunteer`. The first rule that matches, against today, wins:
  1. **Present:** a stay covers today. Stays never overlap, so at most
     one does.
  2. **Upcoming:** a stay starts after today. This wins over past stays:
     the latest stay sets the status, so a returning volunteer reads as
     someone to plan for.
  3. **Past:** the volunteer has stays, and all of them ended before
     today.
  4. **No stay:** the volunteer has no stays at all, which is the case
     between saving a new volunteer and adding their first stay.
- The PHP rule has one home, `Volunteer::getStatus($today)`.
  `Volunteer::isActive()` means `getStatus(today)` is Present; it remains
  for the activity edit form's `(inactive)` label.
- The SQL twin is in the volunteer list query. It selects a HIDDEN
  `statusRank`, a `CASE` over `EXISTS` stay subqueries that yields the
  status's position in `VolunteerStatus::cases()`. The enum is declared in
  sort order, so reordering its cases reorders `/volunteers`. The default
  order is `statusRank`, then name, so present volunteers list first, and
  `ListPaginator` keeps it as the tie-break. The Status column sorts on it,
  Present → Upcoming → Past → No stay (`SORT_MAP` `'status' =>
  ['statusRank']`, per
  [ADR 0011](0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md)).
  **`getStatus()` and `statusRank` must change together.**
- `/volunteers?status=present|upcoming|past|none` filters with the same
  rank expression in `WHERE`. Any other value, the retired
  `active`/`inactive` included, means no filter
  ([ADR 0023](0023-degrade-malformed-query-input-to-a-default.md)). The
  index, its filter and its export share one query
  ([ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)).
- Combined with `?branch=`, the branch is that of the stay that gives the
  status. Present at Kibera means the stay covering today is at Kibera;
  upcoming at Mombasa means the upcoming stay is at Mombasa; past at a
  branch means any stay there, since every stay of a past volunteer has
  ended. No stay with a branch matches nobody.
- Status cells (the volunteer index and its export) come from
  `VolunteerRepository::findStatusesOn()`. It runs one fetch-join query
  that fills the listed volunteers' managed, uninitialized `stays`
  collections, then asks `getStatus()`, so there is no lazy-load per row.
  `/matches` shows the status of the stay that matches a program instead,
  not the volunteer's overall status
  ([ADR 0042](0042-match-available-volunteers-to-programs-by-skills-or-past-activity-type-at-their-branch.md)).
- The volunteer page's header badge shows the status. On
  `/reports/volunteers`, the Volunteers tile reads "N present · N upcoming
  · N engaged"; it counts `getStatus()` in PHP over
  `findAllOrderedByName()`, which fetch-joins stays.
- Never reintroduce a stored flag for any of these.

Branch of attachment and days on site:

- A volunteer's branch of attachment (`Volunteer::getBranchOfAttachment()`)
  is the branch of the stay covering today, else of the most recent stay
  (`Volunteer::$stays` is ordered by `startDate` descending). A volunteer
  with no stay has none.
- There is no branch column or form field on `Volunteer`.
- Days on site (`Volunteer::getDaysOnSite($today)`) is the number of
  calendar days the volunteer's stays cover up to today. It has no column
  either.

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
  volunteer's old `isActive` flag was set. Volunteers with no activities
  got no stay, so they read as No stay. This was acceptable because no
  server held real data yet.
- Dev fixtures derive one stay per archive volunteer from the roster
  archive, never from a generator
  ([ADR 0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)).
  A stay runs from the volunteer's first appearance to their last, at the
  branch of the sites they worked. An archive `active: true` extends it to
  the archive's last day.
- Test factories follow the same model. A volunteer gets one stay covering
  today by default (Present), `inactive()` gives a past stay (Past) and
  `withoutStay()` gives none (No stay). An activity reuses the volunteer's
  covering stay or creates a one-day stay.

## Consequences

- **Positive:** every activity has a branch, and the schema guarantees that
  a stay covers it.
- **Positive:** the status can't go stale. It follows from the dates the
  VM has already entered, and it separates who is here, who is coming and
  who has been.
- **Positive:** repeat visits and branch changes are separate stays, so the
  volunteer page shows where and when each one happened.
- **Negative / trade-offs:** the VM must record a stay before she can log
  an activity for that volunteer. An uncovered date blocks the save, and
  blocks a whole batch.
- **Negative / trade-offs:** the status is `EXISTS` subqueries on every
  volunteer list, not a column read, and they are spelled again in `WHERE`
  when the list is filtered by status.
- **Negative / trade-offs:** the rule lives twice, in `getStatus()` and in
  the `statusRank` DQL. A change to one that misses the other makes the
  Status column sort and filter differently from the cells it shows.
- **Negative / trade-offs:** stay edits and deletions are constrained by
  the activities logged in them. Moving a stay's dates can mean moving
  activities first.
- **Negative / trade-offs:** an activity's branch is shown on its stay
  and, through its project, in the project pickers, which are grouped by
  branch; a project at another branch than the stay's is refused at save
  ([ADR 0027](0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md)).
  There is no branch column or filter on `/activities` and no per-branch
  totals on `/reports/volunteers`.
- **Reversibility:** expensive. Removing stays means dropping a required FK
  from `Activity`, restoring a stored status with a backfill, and
  rewriting the pickers, sorts, filters, reports, factories and fixtures
  that read stays.

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

### 6. A branch-of-attachment field on `Volunteer`

**Rejected.** Volunteers move between branches. A stored branch would
repeat what the stays already say and drift from them, like the `isActive`
checkbox in alternative 5.

### 7. Two derived states, active and inactive

**Rejected.** A volunteer arriving next week and one who left two years ago
both read inactive, yet the VM plans for the first and keeps in touch with
the second. Telling them apart costs two more `EXISTS` subqueries in the
rank.

### 8. Fold No stay into Past

**Rejected.** A volunteer with no stay has not been anywhere: they were
saved and still wait for their first stay, which the VM must enter before
she can log an activity for them. Labelled Past, they would sit among the
people she keeps in touch with, and the missing stay would go unnoticed.
