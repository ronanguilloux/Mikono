# 0012. Seed fixtures from the real WhatsApp roster archive, never from generated data

Date: 2026-09-01

## Status

Accepted — except point 6 of the Decision ("Region lives in the project
name"), superseded by
[ADR 0015](0015-keep-a-projects-region-in-its-location-not-its-name.md).

## Context

Before Mikono existed, UCESCO's Volunteer Manager already ran a working
system: every weekday afternoon, Edna posts the next day's schedule to
the volunteers' WhatsApp group — sites, who goes where, and which staff
member accompanies them.
[`docs/brainstorm/04`](../brainstorm/04-system-of-work-for-the-volunteer-manager.md)
took that as the design brief for the home screen. The app's fixtures
never caught up with it.

`src/Story/AppStory.php` is currently half real and half invented. The
project names and activity types in it were transcribed from those
rosters, so they hold up. Everything else is Faker: `VolunteerFactory`
fills first name, last name, email and phone from generators, and most
activities are `::many(n)` with random dates spread over three months.
The result seeds a database that *looks* populated but represents
nothing that ever happened.

That matters more than it sounds, for three reasons:

- **The fixtures are the demo.** `foundry:load-fixtures` is what the VM
  will be shown the app with. A screen full of `Sarah Wilkinson,
  s.wilkinson@example.org` tells her nothing about whether the app fits
  her week.
- **Invented data validates nothing.** Faker produces the average case.
  The archive produces the awkward ones — a volunteer sent to a second
  site the same afternoon, a per-person note stuck on a roster line, a
  volunteer who simply stops appearing because her stint ended, two
  staff escorting one group. Every one of those is a design question the
  generated fixtures silently answer "no" to.
- **It hides model gaps.** Fixtures built from the archive immediately
  surfaced one the app had wrong (two escorts on one activity, see
  [ADR 0013](0013-record-every-escort-on-an-activity.md)) and one it
  had not scoped (Uganda sites). Generated data would never have.

The archive itself is a WhatsApp export covering 03/08/2026 →
01/09/2026: 22 roster messages plus ordinary group chatter. It is not
clean data. Most roster messages are truncated by WhatsApp's own "Voir
plus" cut, so only the first two or three sites of each evening survive.
And it carries material this app does not model and must not store —
Vanesah's scholarship announcements name individual sponsored children
with their school and their grade level, alongside the donors funding
them. This repository is public, and Kenya's Data Protection Act 2019
already drives the hosting argument in
[`hosting-plan.md`](../project/hosting-plan.md#5-where-to-host).

So the decision has to cover both halves: that fixtures come from real
data, and how real data gets into a public repository without carrying
identifiable information about minors along with it.

## Decision

**The seeded dataset is derived exclusively from what actually happened
at UCESCO. No person, project, escort, date, or roster in
`src/Story/AppStory.php` is generated.**

Concretely:

1. **The source of truth is `docs/fixtures/`.** Raw WhatsApp exports go
   there as `*_dumps.txt` and are **gitignored** — they stay on the
   machine that produced them. Dropping a newer export in the same
   directory is how the dataset grows.
2. **What is committed is a derived, roster-only extract**
   (`docs/fixtures/rosters.yaml`): volunteers by roster name, escorts,
   projects, and one entry per dated roster line, with the notes those
   lines carry. `AppStory` reads that file through
   `App\Fixture\RosterArchive`; it does not read the raw dump and does
   not hard-code the data a second time.
3. **The extract carries no sponsored child, no donor, and no contact
   details.** Volunteers appear as the roster names them — first name
   only. Escorts appear as the roster names them (Mr Maeba, Sam,
   Hassan, Ofright, Edna), which is UCESCO staff acting in an official
   capacity. The scholarship announcements are dropped entirely: they
   are a domain this app does not model, and the one part of the archive
   whose publication would be indefensible.
4. **Absent data stays absent.** `Volunteer::$email` and `$phone` are
   nullable and are seeded `null`, because the rosters do not contain
   them. Filling them with plausible-looking addresses would be the
   exact failure this ADR exists to prevent. Where the model *requires*
   a field the archive has no source for — `duration` and `loggedBy` —
   the fixture applies one documented default for every row (a half day;
   the single admin user), stated in
   [`docs/fixtures/README.md`](../fixtures/README.md) rather than buried
   in the code. A visible constant is not the same kind of claim as
   fifteen different generated values.
5. **Truncation is respected, not guessed.** Where a roster message ends
   in "Voir plus", only the visible sites are seeded. The hidden tail is
   not reconstructed.
6. **Region lives in the project name, not a new enum case.**
   `ProjectLocation` keeps its two cases; projects outside the VM's
   Nairobi base carry a prefix in their name ("Mombasa – Minto
   Children's Orphanage"). The Uganda rosters (Kampala/Luwero) appear in
   the archive only as truncated headers with no volunteers attached, so
   nothing about them is seeded today; when a complete Uganda roster
   arrives, the same prefix convention absorbs it and the enum question
   is reopened then rather than pre-empted now.
7. **The rule binds the seeded dataset, not the test factories.**
   `AppStory` may not generate. `tests/` may: a test asserting pagination
   needs twenty-six arbitrary escorts, not twenty-six real ones, and a
   test that depends on a real name states it explicitly.

Dates are seeded as the archive records them, with one deliberate
exception already present in the code: the home screen's "today" and
"tomorrow" roster panels are date-relative, so the two most recent
archive days are re-anchored onto the day the fixtures are loaded. That
is a shift of the calendar, not an invention of content — the sites,
people, escorts and notes on those two days remain the real ones.

## Consequences

- **Positive:** the demo database is the VM's own August. She recognises
  her volunteers, her sites, and her escorts on first sight, which turns
  every fixture-loaded screen into a usable review of whether the app
  matches the work. The awkward real cases are permanently in the test
  bed rather than rediscovered as bugs.
- **Positive:** the archive becomes a growing requirements source. A new
  export that does not fit the extract's shape is a signal about the
  model, exactly as the two-escort rosters were.
- **Negative / trade-offs:** the dataset is only as large and as varied
  as the archive. One month of rosters means a few dozen activities, so
  anything needing volume (pagination edge cases, performance) still has
  to build its own rows in the test that needs them.
- **Negative / trade-offs:** the committed extract has to be maintained
  by hand when a new dump arrives, and each pass has to re-apply the
  privacy filter. That is deliberate friction: an automated dump parser
  would be one bug away from committing a child's name.
- **Negative / trade-offs:** anyone cloning the repo gets the extract
  but not the raw dump, so the derivation cannot be re-checked from a
  fresh clone. The extract is therefore written to be readable on its
  own, with the source message's date on every entry.
- **Reversibility:** cheap in the code (fixtures are dev-only, and
  `AppStory` could go back to factories in an afternoon), effectively
  irreversible in the repository history if the privacy half is ever got
  wrong — a raw dump committed once stays in the history of a public
  repo. That asymmetry is why the gitignore comes first.

## Alternatives considered

### 1. Keep Faker-generated fixtures and treat the archive as documentation only

**Rejected.** This is the status quo, and it is what let a single-escort
`Activity` survive to v0.1 feature-complete while the VM's own messages
had been saying "Accompanied by Edna and Sam" all along. Data that
cannot contradict the model cannot check the model.

### 2. Commit the raw dump for full reproducibility

**Rejected.** It names sponsored children with their school and grade
level. Publishing that to a public GitHub repository would be
indefensible on its own terms and sits squarely inside Kenya's Data
Protection Act 2019, which this project has already accepted as binding
for hosting decisions. Reproducibility of a dev fixture is not worth it.

### 3. Pseudonymise the volunteers in the committed extract

**Rejected.** It would defeat the point twice over. The VM has to
recognise her own team for the demo to mean anything, and swapping real
names for invented ones is precisely the fake data this ADR forbids —
just harder to spot. The extract stays limited to what the rosters
already treat as ordinary working information: a volunteer's first name
and a staff escort's name, with no contact details attached.

### 4. Parse the raw dump at fixture-load time

**Rejected.** It would put a text parser between a file containing
children's names and the database, and would make loading fixtures fail
on any machine without the dump — including CI. The extract is a
one-time human pass that puts the privacy filter where a reviewer can
see it.
