---
title: Filter the activity type picker by the chosen program
created: 2026-09-17
source: ronan
status: ready
size: S
priority: next
labels: [ux]
epic: programs
---

# Filter the activity type picker by the chosen program

## Why

After [programs-switch-activity-to-program](programs-switch-activity-to-program.md),
the server refuses a type the program doesn't offer. The picker should
only show the valid types in the first place.

## Done when

- Each type `<option>` carries `data-programs` with the ids of the
  programs that offer it.
- A Stimulus controller on both activity forms hides and disables the
  other options when the program changes, and clears a selection that is
  no longer valid.
- Without JS the form still works, with the list unfiltered.
- A Panther screenshot of `/activities/new-batch` at 375 px shows the
  filtered list.

## Notes & links

- [ADR 0030](../../adr/0030-insert-programs-between-projects-and-activities.md).
