# 0015. Keep a project's region in its `location`, not in its name

Date: 2026-09-01

## Status

Accepted

Supersedes point 6 of the Decision in
[ADR 0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md).
The rest of ADR 0012 — fixtures come from the real archive, never from a
generator — stands unchanged.

## Context

`Project` has carried a `location` since the model was first sketched:
a `ProjectLocation` enum with two cases, `Kibera` and `Mombasa`
([ADR 0003](0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)).
It is a real column, it is validated `NotNull`, it is shown and sorted on
the Projects index, and it is the field the app would filter on if it
ever needed to.

When the fixtures were transcribed from the WhatsApp archive, the five
coast sites were nonetheless seeded with the region glued onto the front
of their names — "Mombasa - Minto Children's Orphanage", "Mombasa -
Office" — and ADR 0012 wrote that down as a convention:

> **Region lives in the project name, not a new enum case.**

That sentence answered a question nobody had asked. The enum was never
short of a case: Mombasa already had one. What the paragraph was really
reaching for was Uganda — Kampala and Luwero appear in the archive as
truncated headers with no volunteers attached, and the prefix looked
like a way to absorb them without deciding anything. Applying it to
Mombasa, which the enum already covers, duplicated the field instead.

The archive itself does not support the prefix either. The messages
write the region as a parenthetical qualifier on a site that has its own
name — "Minto children Orphanage (Mombasa)", "Home Visit (Mombasa)",
"Turtle and mangrove conservation Mtwapa beach(Mombasa)". Turning that
qualifier into part of the name is a transcription change, and ADR 0012
exists to keep transcription faithful.

The practical cost is small but real: the Projects index prints the
region twice on those five rows, once in the Name column and once in
Location; a project renamed to a different region would be inconsistent
with itself until someone noticed; and "Mombasa - Mombasa Office" is the
shape the next transcription pass was heading for.

## Decision

**A project's region is `Project::$location` and nothing else. Project
names do not repeat it.**

- The five coast sites in `docs/fixtures/rosters.yaml` are named for the
  site alone — `Mt Hermon school`, `Minto Children's Orphanage`,
  `Mtwapa Beach`, `Office`, `Home visits` — each with
  `location: mombasa`. Their `key`s are untouched, since the rosters
  reference sites by key and the keys are identifiers, not labels.
- The transcription rules in
  [`docs/fixtures/README.md`](../fixtures/README.md) gain the rule
  explicitly, so the next export transcribed does not reintroduce it.
- Test factories follow the same convention when they invent a project
  name. They are free to invent (ADR 0012 point 7), but a fixture that
  models the mistake teaches it.
- The Uganda question that the prefix was standing in for is reopened
  when a complete Uganda roster arrives, and is answered then by an enum
  case — the same way Mombasa is answered now — not by a naming
  convention.

## Consequences

- **Positive:** one field holds the region, so the Projects index stops
  printing it twice and a project can be moved between regions by
  editing the field that means it.
- **Positive:** the seeded names match what the roster messages actually
  call the sites, which is the whole point of seeding from the archive.
- **Negative / trade-offs:** two of the five names are generic on their
  own — "Office" and "Home visits" — and every surface except the
  Projects index shows a project by name with no location beside it (the
  home screen's roster panels, the Activities list, `/reports`). Today
  nothing collides, because Kibera has neither an office project nor a
  home-visits project in the archive; UCESCO HQ is the Nairobi
  equivalent. If Kibera ever gains one, the answer is to show `location`
  next to the name on those surfaces, not to put the word back into the
  name.
- **Negative / trade-offs:** an existing database seeded before this
  change keeps the prefixed names until fixtures are reloaded. The
  dev/demo database is disposable, so no migration is written for it.
- **Reversibility:** trivial — the names are five lines of YAML.

## Alternatives considered

### 1. Keep the prefix, as ADR 0012 wrote it

**Rejected.** It duplicates a validated column, and it puts the app one
transcription pass away from names that disagree with the field beside
them. The reasoning that produced it was about Uganda, and Uganda is not
seeded.

### 2. Drop the prefix and show the location beside every project name

**Rejected for now, kept as the escape hatch.** Showing "Minto
Children's Orphanage — Mombasa" on the home screen's roster panels would
close the ambiguity permanently, but it widens rows on the exact screen
that is already the tightest on mobile
([ADR 0007](0007-adopt-panther-for-adhoc-visual-verification.md)'s
measurements), to solve a collision that does not yet exist. It is
written down here so it is the first move if one appears.

### 3. Rename the two generic sites to something self-describing

**Rejected.** "Mombasa Office" is the prefix again, and "Coast Office"
is a name UCESCO does not use. The messages call it the office; the
archive records what the messages say.
