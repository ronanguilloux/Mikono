# 0036. Manage skills as a seeded list shared by volunteers and programs

Date: 2026-09-28

## Status

Accepted

## Context

The Volunteer Manager places volunteers on programs, and a program needs
people who can do particular things: teach, coach, do first aid, run
admin. She needs to ask two questions: who among the volunteers can do X,
and who suits this program.

A volunteer's skills were a free-text field, and a program only had
free-text `suggestedRoles`. Free text can't be counted, filtered or
matched against what a program needs: "teaching", "Teacher" and "can
teach maths" are three different strings.

## Decision

**Skills are a global `Skill` list, seeded by its migration, that
volunteers hold and programs need through many-to-many links; volunteers
are matched to a program by how many of its skills they hold.**

- `Skill` has a unique `name` and an optional `description`. `/skills` has
  the same index/new/edit/delete and export shape as Activity Types, the
  other global list
  ([ADR 0030](0030-insert-programs-between-projects-and-activities.md),
  [ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)).
- `Volunteer::$skills` and `Program::$skills` are `ManyToMany` collections
  (join tables `volunteer_skill` and `program_skill`), picked in their
  forms with expanded multi-choice checkboxes. A program's skills are
  optional; `suggestedRoles` stays as free text beside them.
- **The migration that creates the table inserts the list**: 31 suggested
  values condensed from the programs' `suggested_roles` in
  `docs/fixtures/rosters.yaml`. As with branches
  ([ADR 0025](0025-model-ucesco-branches-as-a-standalone-reference-entity-seeded-by-migration.md)),
  production gets it on deploy, and tests and dev get it because Foundry
  replays migrations. Staff edit or delete the values afterwards.
- **The volunteer free-text `skills` column was dropped, not converted.**
  Free text can't be mapped onto the list reliably, so staff re-pick each
  volunteer's skills.
- **Trap: the column is dropped with SQLite's `ALTER TABLE … DROP COLUMN`,
  not Doctrine's generated table rebuild.** That rebuild runs
  `DROP TABLE volunteer`, which cascades deletes into stays. Any future
  migration touching `volunteer` on SQLite must avoid the rebuild for the
  same reason.
- **Delete guard:** a skill can't be deleted while any volunteer holds it
  or any program needs it.
- `/volunteers` takes a `?skill=<id>` filter, applied in
  `listQueryBuilder()` so the export honours it too. Malformed input
  degrades to no filter
  ([ADR 0023](0023-degrade-malformed-query-input-to-a-default.md)).
- `/programs/{id}/matches`, with its export, lists the volunteers holding
  **any** of the program's skills, ordered by number of matched skills
  descending, then active first, then name. It is unpaginated and has no
  sort links: the order is the answer. A program with no skills matches
  nobody, and the page tells staff to tick its skills.
- A volunteer with no skills counts as an incomplete profile, as the empty
  free-text field did.
- **Personal data**
  ([ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md)):
  skills were already an admitted volunteer field, and their purpose,
  retention and transfer basis are unchanged. Skills are not an s.2
  sensitive category, and a picked list removes the risk of free text
  carrying s.2 data in that field.

## Consequences

- **Positive:** skills can be counted, filtered and exported, and a
  program's candidates are one click away. One list means one spelling
  per skill.
- **Negative / trade-offs:** every existing volunteer's free-text skills
  were lost and must be re-entered by hand. A skill missing from the list
  must be added in Settings before it can be recorded, and the list
  can drift into near-duplicates if staff add them.
- **Reversibility:** moderate. The entity, join tables and screens can be
  removed with one migration, but the dropped free text is gone; going
  back to free text would start empty again.

## Alternatives considered

### 1. Keep the free-text field alongside the list

**Rejected.** Two places for the same fact means staff fill one and not
the other, and the filter and matches screen would silently ignore
whatever sits only in the free text. The project owner chose to
replace it.

### 2. Seed the list through dev fixtures only

**Rejected.** Production doesn't load fixtures, so it would start with an
empty list and no program could be matched until someone typed 31 values
in. Seeding by migration is how branches already reach production.

### 3. Match only volunteers holding all of a program's skills

**Rejected.** UCESCO's pool of volunteers at a branch at any time is
small; requiring every skill would return nobody for most programs.
Any-match ranked by overlap puts the best fits first without hiding the
partial ones.
