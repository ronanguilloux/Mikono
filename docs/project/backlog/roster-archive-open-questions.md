---
title: Open questions for Edna about the fixture data
created: 2026-09-09
source: ronan
status: blocked
size: XS
priority: next
labels: [data, docs]
---

# Open questions for Edna about the fixture data

## Why

Gaps in the transcribed fixture data that only Edna can close. None
blocks anything — the fixtures work as they are — but both make
the dataset truer to what the rosters actually say.

## Done when

Each is answered and
[`../../fixtures/rosters.yaml`](../../fixtures/rosters.yaml) is updated:

- Are "Ellen" (early August) and "Hellen" (September) the same volunteer?
- Can she supply surnames for the fifteen volunteers in the archive?
- Which archive sites run which Volunteer World programs? The listing
  names only a city, so every listed program sits on its branch's hub
  project (`ucesco_hq`, `mombasa_office`). An answer such as "Teaching and
  School Support runs at Peggy Lucas and Bright Achievers" moves the
  program onto those sites.
- Is the roster's "Mombasa Office assisting in Communication" the listing's
  Content Creator & Social Media Assistant program, or separate office
  work?
- Does any program run in a set window, such as the free annual medical
  camp, and if so, when? Every program is seeded always-on, because the
  listing gives no dates.

## Notes & links

- The archive and the Volunteer World listing are the only sources of
  dev/demo data — never generate into them with Faker
  ([ADR 0012](../../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)).
- Transcription rules: [`../../fixtures/README.md`](../../fixtures/README.md).
  `tests/Integration/Fixture/RosterArchiveTest.php` enforces the ones a
  test can.
- A surname arriving is additive:
  [ADR 0014](../../adr/0014-make-a-volunteers-last-name-optional.md) makes
  `Volunteer::$lastName` optional precisely because the rosters name
  volunteers by first name only.
