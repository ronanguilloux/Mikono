# 0025. Model UCESCO branches as a standalone reference entity seeded by migration

Date: 2026-09-13

## Status

Accepted

## Context

UCESCO works from five offices: Nairobi (HQ), Mombasa, Samburu, Uganda and
USA (Global). Each has a physical location, the zones its projects run in
and a program focus. The app had no record of them, and the Volunteer
Manager needs to see them and keep them up to date.

These rows are real reference data, not demo data. Production needs them
from its first deploy. They change rarely, but they do change, and the VM
makes those changes herself.

Nobody has decided yet how branches relate to Projects or Volunteers.

## Decision

**UCESCO branches are a standalone `Branch` entity that the VM edits in
Settings → Branches. The migration that creates the table inserts the five
real rows.**

- `Branch` has a required `name` and `physicalLocation`, free-text
  `projectZones` and `programFocus` (both nullable), `isActive` and
  `createdAt`/`updatedAt`. "(HQ)" and "(Global)" are part of the name.
- `/branches` has the same index/new/edit/delete shape as Projects:
  `DataTable`, sorting through `ListPaginator`, a CSRF-protected delete,
  and `ROLE_USER` access.
- `Branch` is not linked to any other entity. That is why it has no
  delete-guard. **The first relation added to `Branch` must add one**, or
  deleting a branch will break the rows that reference it.
- The table-creating migration inserts the rows, not `AppStory`. The
  entrypoint runs migrations, so production gets the rows on deploy.
  Foundry's reset mode is `migrate` in dev and test, so
  `foundry:load-fixtures` and `#[ResetDatabase]` replay the rows too.
- **In tests the `branch` table always starts with five rows.** A test
  that counts branches counts from 5, not from 0.
- This rule sits alongside
  [ADR 0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)
  and does not replace it. Migrations carry fixed reference data; the
  roster archive carries operational data. Operational data never goes
  into a migration, and reference data never goes into the archive.
- A "Uganda" branch row does not create a Uganda `ProjectLocation` case.
  That question stays with its own backlog card.

## Consequences

- **Positive:** every environment has the same five branches, with no
  manual seeding step and nothing to forget on deploy.
- **Positive:** the VM edits branches in the app like any other Settings
  list, without waiting for a deploy.
- **Negative / trade-offs:** tests cannot assume an empty `branch` table.
  Changing the seeded rows means a new migration, or else production and a
  fresh dev database drift apart.
- **Negative / trade-offs:** free-text zones and focus cannot be queried
  or validated. That is acceptable while nothing reads them.
- **Reversibility:** cheap. Nothing references `Branch`, so dropping the
  table or reshaping it takes one migration. Once a relation exists, it
  costs as much as any other entity change.

## Alternatives considered

### 1. An `isHeadquarters` flag

**Rejected.** Nothing in the app needs to find the headquarters. "(HQ)" in
the name tells a reader everything a flag would.

### 2. A separate table for project zones

**Rejected.** Zones are descriptive text that no query or relation uses.
They get structure when a relation needs it.

### 3. A `BranchLocation` enum, like `ProjectLocation`

**Rejected.** The VM edits branches. With an enum, every change to a
branch would need a code change and a deploy.

### 4. Seeding the branches from `AppStory`

**Rejected.** Fixtures never run in production, so the real branches would
never get there.
