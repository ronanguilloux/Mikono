---
title: Seed real programs from UCESCO's Volunteer World listing
created: 2026-09-17
source: ronan
status: ready
size: M
priority: next
labels: [data]
epic: programs
---

# Seed real programs from UCESCO's Volunteer World listing

## Why

The dev fixtures give each project one always-on program named after its
activity type, because the WhatsApp roster archive knows nothing about
programs (ADR 0030, ADR 0012). So the demo data never shows a project
running two programs, or a program with dates. That's the case Programs
were built for (Computer Tuition next to School support at Peggy Lucas).
UCESCO's public Volunteer World listing describes its real programs across
its branches and projects, so it can supply them.

## Done when

- `docs/fixtures/rosters.yaml` has a `programs:` section taken from the
  listing. Each entry has: name, project key, optional start/end dates,
  suggested roles, and activity types.
- `RosterArchive` reads that section, and `AppStory` creates those
  programs instead of the generated one per project.
  - A project the section doesn't cover keeps its always-on program, so
    every roster activity still has a program offering its type.
  - An archive activity's program is resolved from its project and type.
- `RosterArchiveTest` rejects:
  - a program naming an unknown project;
  - a program with no type;
  - an end date before its start date;
  - a roster activity that no program at its project covers.
- `docs/fixtures/README.md` names the listing as a second source, with its
  transcription rules. ADR 0012 is rewritten in place to admit it.
- `foundry:load-fixtures` loads cleanly, and `composer quality` and
  phpunit are green.

## Notes & links

- Source: <https://www.volunteerworld.com/en/filter?ProjectId=71f31166-6a8c-45fb-acb5-cccd69a2a98e>.
  If a plain fetch comes back empty, the page may need a real browser.
- This repo is public. Transcribe program facts only, never names of
  children, donors or staff that the listing may carry (same rule as the
  WhatsApp dumps).
- The listing may name places that aren't archive projects. Add them as
  projects with a `branch`, or leave them out. Don't guess: note anything
  unclear as a question for Edna, as in
  [roster-archive-open-questions](roster-archive-open-questions.md).
- Program dates must cover the archive's roster days *after* the fixtures
  slide them onto today (`AppStory`'s `$shift`). A program with dates may
  need the same shift, or none at all.
- [ADR 0030](../../adr/0030-insert-programs-between-projects-and-activities.md),
  [ADR 0012](../../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md),
  [brainstorm 10](../../brainstorm/10-programs-between-projects-and-activities.md).
