---
title: Two open questions for Edna about the roster archive
created: 2026-09-09
source: ronan
status: blocked
size: XS
priority: next
labels: [data, docs]
---

# Two open questions for Edna about the roster archive

## Why

Two gaps in the transcribed roster archive that only Edna can close.
Neither blocks anything — the fixtures work as they are — but both make
the dataset truer to what the rosters actually say.

## Done when

Both are answered and
[`../../fixtures/rosters.yaml`](../../fixtures/rosters.yaml) is updated:

- Are "Ellen" (early August) and "Hellen" (September) the same volunteer?
- Can she supply surnames for the fifteen volunteers in the archive?

## Notes & links

- The archive is the *only* source of dev/demo data — never generate into
  it with Faker
  ([ADR 0012](../../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)).
- Transcription rules: [`../../fixtures/README.md`](../../fixtures/README.md).
  `tests/Integration/Fixture/RosterArchiveTest.php` enforces the ones a
  test can.
- A surname arriving is additive:
  [ADR 0014](../../adr/0014-make-a-volunteers-last-name-optional.md) makes
  `Volunteer::$lastName` optional precisely because the rosters name
  volunteers by first name only.
