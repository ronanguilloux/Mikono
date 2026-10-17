---
title: Open questions for Edna about the fixture data and the imported rosters
created: 2026-09-09
source: ronan
status: blocked
size: S
priority: next
labels: [data, docs]
---

## Why

Gaps that only Edna can close, in two places: the transcribed fixture
data, and the WhatsApp rosters imported into production on 2026-10-18
([brainstorm 16](../../brainstorm/16-whatsapp-roster-import.md)), where
the import had to infer stays, sites and mappings from the roster text.
None blocks anything, but each makes the data truer to what happened.

## Done when

### Production, from the roster import

Each is answered and, where the answer differs, corrected in the app.
Every imported activity's notes name the roster post it came from.

- **Two of her own entries were changed to match the roster**, at the
  owner's call: Margarita on 13–15/10 (HQ · Cook and Nutritionist →
  Peggy Lucas school) and Kirsten on 13/10 (Office · Turtles → Mtwapa
  beach clean-up). Which is right? The notes keep what they were.
- **Tina**: when did she arrive in July? Her stay starts at her first
  roster line, 04/08, and ends at her farewell, 28/08.
- **Helena, Hannah and Clio**: their stays were moved from Nairobi to
  Mombasa, where the rosters place them for weeks. Correct?
- **Gustavo**: a Samburu stay 09–11/09 was cut out of his Nairobi stay;
  the travel day, 08/09, stays in Nairobi. Correct?
- **Alicia**: one stay, 17/09 → 15/10, across the days with no roster
  post. Correct?
- **Mina and Alicia** were created from the rosters with a first name
  only: surname, contact, and the rest of the profile.
- **The twelve new projects** — names, and partner or UCESCO's own —
  above all the seven Kampala sites, which may be fewer real places;
  and whether Nyali beach is its own site or Mtwapa's.
- **St Kizito and Maisha medical outreach** are logged under Ggaba's
  medical program, as she had logged those days. Should St. Kizito, which
  exists as a project, get a program of its own?
- **Site guesses**: "Sports" on 21/08 → Toi School Field; Dreams of Hope
  Center → Dreams of Hope Kids; the Mombasa office "communication" work →
  Content Creator & Social Media (same question as below).
- **Escorts the roster doesn't name**: Peggy Lucas on 28/08, and the
  "-later MVETI" lines of 27–28/08.
- **No roster was posted** for 01–07/10 or 09–11/10: were there
  activities on those days?
- **July 2026** is not in the import: the export came from an account
  that joined the group on 03/08. Only a member present in July (Edna)
  can export it, if it's wanted.
- **Two duplicate pairs were merged** as the same person (Tina, Gwen):
  confirm.

### Fixtures

Each is answered and
[`../../fixtures/rosters.yaml`](../../fixtures/rosters.yaml) is updated:

- Are "Ellen" (early August) and "Hellen" (September) the same volunteer?
  The production records say no: the import matched them to two people
  with different stays.
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
