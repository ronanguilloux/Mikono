# 0038. Record achievements on a volunteer's stay

Date: 2026-09-28

## Status

Accepted

## Context

UCESCO wants reminders on the anniversaries of what volunteers achieved,
such as a library built or a program started. Nothing in the app recorded
that. An activity is one day's log entry, not an outcome, so the story of
what a volunteer left behind lived only in WhatsApp, and an anniversary
reminder had nothing to count from.

An achievement happens during a stay, at one of that stay's branch's
projects. It needs a real day for the anniversary to count from. Its
description is free text about places and people, so it is personal data
under [ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md),
and this ADR has to state its purpose, sensitivity, retention and
transfer basis.

## Decision

**An `Achievement` belongs to one `Stay` and one `Project`, has a
required title and a required day within the stay, and is deleted with
its stay.**

- Fields:
  - `title`: required and short ("Built a library").
  - `description`: optional free text.
  - `achievedOn`: a required date.
  - `project`: required.
- **`achievedOn` must fall within the stay's dates.** The anniversary
  needs a real day, and the stay's start or end would be a guess.
- **`project` is required, and it is the project, not the program.** The
  reminder sentence names the project. People talk about projects ("the
  library at Kibera"), even though activities hang off programs
  ([ADR 0030](0030-insert-programs-between-projects-and-activities.md)).
  An achievement that belongs to no project is rare, and it goes under a
  "General" project.
- **The project must be at the stay's branch.** This is the rule
  [ADR 0027](0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md)
  applies to activities. The schema does not enforce it, so the app
  checks it, along with the date rule, in three places:
  - On achievement save, in `AchievementController::fits()`.
  - On stay edit. A stay's dates or branch can't change in a way that
    leaves one of its achievements outside the stay's dates or at another
    branch's project.
  - On project edit. A project can't move to another branch while it has
    achievements in stays at its current branch.

  A new write path for any of these three needs the same check.
- **Trap: the delete cascade runs in the ORM, through `Stay::$achievements`
  with `cascade: ['remove']`.** SQLite runs here without foreign keys
  enforced, so the column's `ON DELETE CASCADE` alone would leave orphan
  rows. `Volunteer::$stays` works the same way, so deleting a volunteer
  reaches their achievements too. Don't drop either cascade in favour of
  the schema's.
- **Delete guard:** a project with achievements can't be deleted, and the
  project index shows its Delete action as unavailable. Nothing refers to
  an achievement, so achievements have no delete guard of their own.
- UI:
  - `/reports/achievements` lists every achievement, newest first, with
    `DataTable`. It is in the Reports menu and exports to CSV and XLSX
    ([ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)).
    It has no "New" button.
  - Achievements are added from a stay row on the volunteer's page (route
    `stay_achievement_new`), and that page has an Achievements panel,
    newest first.
- **Personal data**
  ([ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md)):
  - Purpose: to recognise a volunteer's contribution and to give staff a
    reason to get back in touch.
  - Sensitivity: the description may name places, children or donors.
    The export leaves it out, and the form's help text says not to name
    children or donors. It never goes into the public fixtures without
    being scrubbed.
  - Retention: the same as the stay, since the achievement is deleted
    with it.
  - Transfer: none.
- Out of scope:
  - A photo is left to the volunteer-document-attachments and
    photo-library-with-metadata work, not added as a blob column here.
  - Fixtures wait for real achievements from UCESCO in
    `docs/fixtures/rosters.yaml`. They are never generated
    ([ADR 0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)).

## Consequences

- **Positive:** what a volunteer left behind is recorded against the stay
  and project it belongs to, with a real day that anniversary reminders
  can count from. Staff can list it across volunteers and export it.
- **Negative / trade-offs:** the branch rule is checked in three
  controllers, not by the schema, so it can drift if a new write path
  skips it. Moving a stay or a project can now be refused because of its
  achievements. An achievement with no real project needs a "General"
  project at each branch.
- **Reversibility:** easy. It is one table with no inbound references.
  Dropping it loses only the achievements themselves.

## Alternatives considered

### 1. Attach the achievement to a program instead of a project

**Rejected.** People describe outcomes by project ("the library at
Kibera"), and the reminder sentence names the project. A program is
often too narrow or too short-lived for something like a building.

### 2. Make the project optional

**Rejected.** The reminder sentence would then need a second form
without the project. An achievement outside any project is rare enough
to go under a "General" project instead.

### 3. Use the stay's start or end date instead of `achievedOn`

**Rejected.** Neither is the day it happened, so the anniversary would
be a guess. A stay can last months, so the date could be off by that
much.

### 4. List achievements only on the volunteer's page

**Rejected.** The anniversary reminders and the Volunteer Manager both
need to see achievements across volunteers. Every list view exports
under ADR 0029, and a panel on the volunteer's page is not a list view
that can.

### 5. Rely on the database's `ON DELETE CASCADE` alone

**Rejected.** SQLite here runs without foreign keys enforced, so deleting
a stay would leave orphan achievements pointing at a missing stay. The
ORM cascade removes them whatever the connection's settings.
