# 12. Seed fixtures from the real WhatsApp roster archive, never from generated data

Date: 2026-09-13

## Status

Accepted

## Context

Before Mikono, UCESCO's Volunteer Manager already ran a working system:
every weekday afternoon Edna posts the next day's schedule to the volunteers'
WhatsApp group — sites, who goes where, which staff member accompanies them.
[`docs/brainstorm/04`](../brainstorm/04-system-of-work-for-the-volunteer-manager.md)
took that as the brief for the home screen.

Faker-generated fixtures fail this app in three ways:

- **The fixtures are the demo.** A screen of invented names tells the VM
  nothing about whether the app fits her week.
- **Invented data validates nothing.** Faker produces the average case; the
  archive produces the awkward ones — a volunteer at two sites the same
  day, a note on one roster line, a volunteer who stops appearing, two
  escorts on one group.
- **It hides model gaps.** Real data immediately exposed two escorts on one
  activity ([ADR 0013](0013-record-every-escort-on-an-activity.md)) and
  first-name-only volunteers
  ([ADR 0014](0014-make-a-volunteers-last-name-optional.md)).

The archive is not clean: WhatsApp truncates most roster messages ("Voir
plus"), and the export also carries scholarship announcements naming
sponsored children with their school and grade, alongside donors. This
repository is public, and Kenya's Data Protection Act 2019 applies.

## Decision

**The seeded dataset comes exclusively from what actually happened at
UCESCO. No person, project, escort, date or roster in `src/Story/AppStory.php`
is generated.**

1. **Raw WhatsApp exports** go in `docs/fixtures/` as `*_dumps.txt` and are
   **gitignored**. A newer export dropped there is how the dataset grows.
2. **What is committed is a roster-only extract**, `docs/fixtures/rosters.yaml`:
   volunteers by roster name, escorts, projects, one entry per dated roster
   line with its notes. `AppStory` reads it through `App\Fixture\RosterArchive`
   and never hard-codes the data a second time.
3. **The extract holds no sponsored child, no donor, no contact details.**
   Volunteers appear by first name as the rosters name them; escorts by the
   name staff use in an official capacity. Scholarship announcements are
   dropped entirely.
4. **Absent data stays absent.** `Volunteer::$email` and `$phone` are seeded
   `null`. Where the model requires a field the archive lacks (`duration`,
   `loggedBy`), one documented default applies to every row (a half day; the
   admin user), stated in [`docs/fixtures/README.md`](../fixtures/README.md).
5. **Truncation is respected.** Only visible sites are seeded; a hidden tail
   is never reconstructed.
6. **A project's region is `Project::$location`, never part of its name.**
   Names are the site as the messages call it ("Minto Children's
   Orphanage", "Office"), with `location: mombasa`; the messages' "(Mombasa)"
   is a qualifier, not a name. Test factories follow the same convention. A
   region the enum lacks (Uganda: Kampala, Luwero — so far only truncated
   headers with no volunteers) gets an enum case when a complete roster
   arrives, never a name prefix.
7. **The rule binds the seeded dataset, not test factories.** A pagination
   test needs twenty-six arbitrary escorts, not twenty-six real ones.

**Dates** are seeded as recorded, except that the two most recent archive
days are re-anchored onto today and tomorrow, because the home screen's
roster panels are date-relative. The calendar shifts; the content does not.

## Consequences

- **Positive:** the demo database is the VM's own August — she recognises
  her volunteers, sites and escorts, so every screen is a real review. The
  awkward cases stay permanently in the test bed, and each new export that
  doesn't fit is a signal about the model.
- **Negative / trade-offs:** the dataset is only as large as the archive, so
  volume tests build their own rows. The extract is maintained by hand and
  each pass re-applies the privacy filter — deliberate friction, since a
  parser would be one bug from committing a child's name. A fresh clone
  can't re-check the derivation, so every extract entry carries its source
  message's date. Two generic names ("Office", "Home visits") appear without
  a location on most screens; if Kibera ever gains one, show `location`
  beside the name there rather than putting the region back in the name.
- **Reversibility:** cheap in code; irreversible in history if the privacy
  half goes wrong — a raw dump committed once stays in a public repo's
  history. That is why the gitignore comes first.

## Alternatives considered

### 1. Keep Faker fixtures, treat the archive as documentation

**Rejected.** That is what let a single-escort `Activity` survive while the
VM's messages said "Accompanied by Edna and Sam". Data that can't contradict
the model can't check it.

### 2. Commit the raw dump

**Rejected.** It names sponsored children with school and grade; publishing
that is indefensible.

### 3. Pseudonymise volunteers in the extract

**Rejected.** The VM must recognise her team, and invented names are the
fake data this ADR forbids, just harder to spot.

### 4. Parse the raw dump at fixture-load time

**Rejected.** Puts a parser between children's names and the database, and
fails on every machine without the dump, CI included.

### 5. Put the region in the project name ("Mombasa – Office")

**Rejected.** Duplicates a validated column, drifts when a project moves,
and is not how the messages name the sites.
