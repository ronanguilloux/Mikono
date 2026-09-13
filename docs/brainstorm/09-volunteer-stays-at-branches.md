# Brainstorm — Volunteer stays at branches

**Date:** 2026-09-14
**Author:** <ronan.guilloux@gmail.com>
**Related:** [`CLAUDE.md`](../../CLAUDE.md),
[ADR 0025](../adr/0025-model-ucesco-branches-as-a-standalone-reference-entity-seeded-by-migration.md),
[ADR 0012](../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md),
[ADR 0024](../adr/0024-treat-dates-as-calendar-days-in-nairobi-time.md),
[`docs/adr/`](../adr/)

---

## Primary audience

The Volunteer Manager (VM).

## Desired impact

The VM can see where a volunteer is and when. Every logged activity is tied
to a branch. "Active" is no longer a checkbox she has to remember to untick.
Success is obvious in hindsight: no activity row without a stay, no volunteer
wrongly listed as active or inactive, and no `isActive` column left in the
schema.

## Where things stand (2026-09-14)

The request: *"Management of stays and branches: a 'stays' model (start
date, end date, branch where the stay happens) allowing volunteers to be
attached to multiple branches over distinct periods. Each stay happens
geographically at a Branch; each volunteer activity is related to that
branch."*

`Branch` has just landed
([ADR 0025](../adr/0025-model-ucesco-branches-as-a-standalone-reference-entity-seeded-by-migration.md)):
five rows seeded by migration, referenced by nothing. `Volunteer::$isActive`
is a manual flag. Volunteers come for a stint of a few weeks and sometimes
come back later, possibly to another branch. One flag can't record that.

## The shape this landed on

Decided with the user on 2026-09-14:

1. **`Activity` gets a required FK to `Stay`**, resolved automatically from
   volunteer and date on every save. `Activity::$volunteer` stays because
   too much code reads it. It can't drift, because the stay is resolved
   again on each save.
2. **An activity dated outside every stay of its volunteer is blocked** with
   a form error that names the volunteer.
3. **Stays are managed from the volunteer page**:
   `/volunteers/{id}/stays/new` and `/stays/{id}/edit|delete`. No new nav
   area.
4. **`Volunteer::$isActive` is removed.** Active means a stay covers today.
   Activity pickers offer volunteers with a current or upcoming stay
   (`endDate >= today`).
5. **Integrity rules.** One volunteer's stays may not overlap. A stay can't
   be edited so that its activities fall outside it, and can't be deleted
   while activities reference it. `Branch` gets a delete-guard on stays.
6. **Data.** The migration backfills one stay per volunteer from their
   activities. The branch comes from the project location (`kibera` →
   Nairobi (HQ), `mombasa` → Mombasa), and the end date is extended to today
   if the volunteer was active. Fixtures derive stays from the roster
   archive, not a generator
   ([ADR 0012](../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)):
   first to last appearance, with an archive `active: true` extending the
   stay to the archive's last day.

## The "Options Not Taken"

- **Derive the branch at read time, with no column.** Every read that wants a
  branch would need the stay join, and nothing would guarantee that an
  activity is covered by any stay at all. The required FK makes coverage a
  schema fact.
- **Copy a `Branch` onto `Activity` at save.** Stays get edited: a date moves
  and the copied branch is wrong. Re-resolving a `Stay` FK on each save has
  no such gap.
- **Allow activities outside a stay, with an unknown branch.** That leaves
  gaps in exactly the "where was this volunteer" answer the feature exists to
  give. Blocking the save with a named error makes the VM fix the stay first.
- **A separate `/stays` area.** A stay only makes sense for one volunteer;
  listed on its own it is a table of dates without context. The volunteer
  page is where the VM already looks for that person.
- **Keep the manual `isActive` checkbox.** It would repeat what the stays
  already say, and the two would disagree the first time the VM forgot one of
  them.

## Constraints

- **Existing data must survive the migration.** Every activity needs a stay
  before the FK can be non-null, so the backfill runs in the migration itself,
  mapped from `ProjectLocation`. Projects have no branch of their own yet.
- **"Today" is a Nairobi calendar day**
  ([ADR 0024](../adr/0024-treat-dates-as-calendar-days-in-nairobi-time.md)),
  so "a stay covers today" and the `endDate >= today` picker rule use the
  same date semantics as activities.
- **Readers of `isActive` must move to the derived rule.** That includes the
  activity pickers, their edit-form escape hatch for a volunteer who is no
  longer active, and the `(inactive)` suffix on the `/activities` volunteer
  filter.
- **The dev dataset is the real archive, never generated** (ADR 0012), so
  stays come from `rosters.yaml` and its `active` markers, not from
  `AppStory` or Faker.
- **Deferred:** a Branch column or filter on `/activities`, per-branch totals
  on `/reports`, and limiting the project picker to the stay's branch. The
  last one needs projects to have a branch, which they don't yet.
