---
title: Switch Activity from Project to Program
created: 2026-09-17
source: ronan
status: ready
size: L
priority: next
labels: [data]
epic: programs
---

# Switch Activity from Project to Program

## Why

Programs exist (`/programs`, shipped 2026-09-17),
each activity has to belong to a program. The project then comes from the
program, and an activity can only use a type its program offers, on a
date the program covers.

## Done when

- `activity.project_id` is gone and `activity.program_id` is required.
  `Activity::getProject()` returns the program's project.
- The migration creates one always-on program per project, named after
  the project and linked to the types its activities used, and backfills
  `activity.program_id`. It has been checked on a dev DB migrated from the
  previous version.
- `ActivityController::resolveStays()` refuses a type the program doesn't
  offer (error on `activityType`), a date outside the program (error on
  `date`), and a stay at another branch than `program.project.branch`
  (error on `program`). A batch stays all-or-nothing.
- `ProgramController` edit refuses (422) a project change while the
  program has activities, dates narrowed past its activities, and removing
  a type its activities use. A program with activities can't be deleted.
- Every `a.project` join goes through the program: Project and Stay
  repositories, `ActivityRepository`, `ActivitySummaryCalculator`,
  `RosterBuilder`, `QuietProjectFinder`, `ReportController`.
- Both activity forms pick a program (grouped by branch, "Project —
  Program"), and the type picker lists only types offered by some program.
  `/activities` and its export gain a Program column.
- `AppStory` creates one always-on program per archive project, named
  after its `activity_type`. `rosters.yaml` is unchanged. `ActivityFactory`
  builds a consistent program.
- Tests cover each refusal. `composer quality` and phpunit are green, and
  CLAUDE.md's directory map mentions Program and the new save checks.

## Notes & links

- [ADR 0030](../../adr/0030-insert-programs-between-projects-and-activities.md),
  [ADR 0027](../../adr/0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md)
  (rewritten for the program hop).
- Read `QuietProjectFinder`'s docblock before touching it.
- SQLite rebuilds the `activity` table to drop a column, so review the
  generated migration by hand.
