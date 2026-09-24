# 12. Seed fixtures from UCESCO's real rosters and program listing, never from generated data

Date: 2026-09-24

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

The rosters also never name a program.
[ADR 0030](0030-insert-programs-between-projects-and-activities.md) put
programs between projects and activities, so a roster-only dataset could
never show a project running two programs. UCESCO publishes its programs on
its public [Volunteer World listing](https://www.volunteerworld.com/en/filter?ProjectId=71f31166-6a8c-45fb-acb5-cccd69a2a98e),
one detail page per program. That listing places each program in a city,
never at a site, gives no program dates (its "1–50 weeks" is how long a
volunteer may stay), and its pages carry reviewers' names.

## Decision

**The seeded dataset comes exclusively from what UCESCO actually recorded:
the WhatsApp roster archive and, for programs only, UCESCO's public
Volunteer World listing. No person, project, escort, date or roster in
`src/Story/AppStory.php` is generated, and nothing is ever Faker.** The
only derived program is point 7's fallback, named after a real site's real
activity type.

1. **Raw WhatsApp exports** go in `docs/fixtures/` as `*_dumps.txt` and are
   **gitignored**. A newer export dropped there is how the dataset grows.
2. **What is committed is a hand-made extract**, `docs/fixtures/rosters.yaml`:
   volunteers by roster name, escorts, projects, one entry per dated roster
   line with its notes, and a `programs:` section — the listing's programs,
   each read from its own detail page, plus "Computer Tuition" at Peggy
   Lucas, which the project owner named
   ([`docs/brainstorm/10`](../brainstorm/10-programs-between-projects-and-activities.md)).
   `AppStory` reads it through `App\Fixture\RosterArchive` and never
   hard-codes the data a second time.
3. **The extract holds no sponsored child, no donor, no reviewer, no contact
   details.** Volunteers appear by first name as the rosters name them;
   escorts by the name staff use in an official capacity. Scholarship
   announcements are dropped entirely. From the listing, only program facts
   are transcribed — title, offered activity types, suggested roles — never
   the reviewer names on its pages.
4. **Absent data stays absent.** `Volunteer::$email` and `$phone` are seeded
   `null`. Where the model requires a field the archive lacks (`duration`,
   `loggedBy`), one documented default applies to every row (a half day; the
   admin user), stated in [`docs/fixtures/README.md`](../fixtures/README.md).
   **Programs carry no dates**: every seeded program is always-on, because
   the listing's week range is a stay length, not a program window.
5. **Truncation is respected.** Only visible sites are seeded; a hidden tail
   is never reconstructed.
6. **No site is guessed for a listed program.** Each one sits on its
   branch's UCESCO-owned hub project: UCESCO HQ for Nairobi, the Mombasa
   office for Mombasa, and a hub with no roster activity for each branch the
   rosters never visit, named after the branch's physical location as its
   migration records it
   ([ADR 0025](0025-model-ucesco-branches-as-a-standalone-reference-entity-seeded-by-migration.md))
   — "Ichingei Village" for Samburu, "Ggaba" for Uganda. A program whose
   real site is unclear stays on the hub and becomes a question for Edna,
   not a placement.
7. **A roster activity's program is resolved, never chosen.** It is the
   listed program at the activity's project that offers the activity's type;
   if there is none, the project gets one always-on program named after its
   type. Two listed programs matching one roster site's type is an error,
   not a pick.
8. **A project's region is its branch, never part of its name.** Names are
   the site as the messages call it ("Minto Children's Orphanage",
   "Office"), with `branch: Mombasa`; the messages' "(Mombasa)" is a
   qualifier, not a name. Test factories follow the same convention.
9. **`RosterArchive` fails loudly** on anything the extract cannot mean: an
   unknown project, a program with no activity types, an end before a start,
   an ambiguous program resolution, a roster site at a project without an
   `activity_type`. Those checks and
   `tests/Integration/Fixture/RosterArchiveTest.php` enforce the
   transcription rules in `docs/fixtures/README.md`, including its section
   "Second source: the Volunteer World listing".
10. **The rule binds the seeded dataset, not test factories.** A pagination
    test needs twenty-six arbitrary escorts, not twenty-six real ones.

**Dates** are seeded as recorded, except that the two most recent archive
days are re-anchored onto today and tomorrow, because the home screen's
roster panels are date-relative. The calendar shifts; the content does not.

## Consequences

- **Positive:** the demo database is the VM's own August — she recognises
  her volunteers, sites, escorts and programs, so every screen is a real
  review. The awkward cases stay permanently in the test bed, and each new
  export that doesn't fit is a signal about the model. The hubs show a
  project running many programs, which the rosters alone never could.
- **Negative / trade-offs:** the dataset is only as large as the archive, so
  volume tests build their own rows. The extract is maintained by hand and
  each pass re-applies the privacy filter — deliberate friction, since a
  parser would be one bug from committing a child's name. A fresh clone
  can't re-check the derivation, so every roster entry carries its source
  message's date and the program section its fetch date and URL. Listed
  programs cluster on hub projects rather than at the sites that really run
  them, until Edna says where each one runs, and no program shows a date
  window. Two generic names ("Office", "Home visits") appear without their
  branch on most screens; if Kibera ever gains one, show the branch beside
  the name there rather than putting the region back in the name.
- **Reversibility:** cheap in code; irreversible in history if the privacy
  half goes wrong — a raw dump or a reviewer's name committed once stays in
  a public repo's history. That is why the gitignore comes first.

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

**Rejected.** Duplicates the branch, drifts when a project moves, and is
not how the messages name the sites.

### 6. Keep the dataset roster-only, one generated program per project

**Rejected.** Every project would run exactly one program, so the demo
could never exercise the project-to-program split ADR 0030 exists for.

### 7. Place each listed program at the roster site that looks closest

**Rejected.** The listing names a city, not a site; a guessed placement is
invented data that looks real, and would tie programs to partner sites
UCESCO never said run them.

### 8. Give programs dates derived from the listing's week range

**Rejected.** "1–50 weeks" is how long a volunteer may stay, not when a
program runs; turning it into a window invents dates the source doesn't
hold.
