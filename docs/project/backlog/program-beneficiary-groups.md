---
title: Tag programs with the beneficiary groups they serve
created: 2026-09-28
source: ronan
status: ready
size: M
priority: next
labels: [data, ux]
---

## Why

A program records who it reaches only as free text
(`Program::$beneficiariesReached`), so wording drifts ("pupils", "school
kids", "children") and nothing can count how many programs serve, say,
adolescent girls. A maintained list, kept like Activity types and picked
as a multi-choice on the program form, fixes both. The entries describe
groups, never people, so no personal data comes in
([ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md)).
That is also why the entity is `BeneficiaryGroup`, not `Beneficiary`.

## Done when

- A `BeneficiaryGroup` entity with a unique `name`, and a CRUD area built
  on the Activity type pattern: index/new/edit/delete, `DataTable`, export
  (ADR 0029), a Settings nav entry, and a delete guard that counts the
  programs referencing a group.
- `Program::$beneficiaryGroups`, a `ManyToMany` through
  `program_beneficiary_group`. Optional, zero allowed. Expanded checkboxes
  on `ProgramFormType`, shown on the program page and in the programs list
  and its export.
- `beneficiariesReached` stays, for counts ("40 pupils in grade 3").
- The starting list is seeded by the migration, as for branches
  ([ADR 0025](../../adr/0025-model-ucesco-branches-as-a-standalone-reference-entity-seeded-by-migration.md)),
  so production gets it. The VM edits it from then on. Fixtures don't
  generate groups (ADR 0012).
- `RouteSmokeTest`'s prefix map has the new id. A functional test covers
  the CRUD and the delete guard.
- [ADR 0030](../../adr/0030-insert-programs-between-projects-and-activities.md)
  rewritten in place (via `adr-scribe`): it currently says "There is no
  `Beneficiary` entity".

## Notes & links

Proposed starting list, to confirm with Edna before seeding. The age bands
follow common Kenyan NGO usage.

| Proposed label | Draft wording it replaces |
| --- | --- |
| Young children (under 6) | — |
| Primary school pupils | pupils |
| Secondary school students | high school students |
| Adolescent girls (10–19) | young teenager girls |
| Adolescents (10–19) | young teenagers |
| Young women (18–35) | young adult women |
| Youth (18–35) | — |
| Orphans and vulnerable children | — (the term Kenyan programmes use; fits the orphanage projects) |
| Patients | sick people |
| People with disabilities | — |
| Older people | — |
| Parents and caregivers | — |
| Families | families |
| Teachers and school staff | — |

- Leave out condition-specific groups ("people living with HIV") unless
  Edna asks for them. With small counts at one site they come close to
  identifying health data, which s.2 counts as sensitive.
- Out of scope, possible follow-up: a "By beneficiary group" tab on
  `/reports`.
- Code to copy: `src/Entity/ActivityType.php` and its controller, form and
  templates. Program side: `src/Entity/Program.php`, `ProgramFormType`.
