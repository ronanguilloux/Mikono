---
title: Add profile fields and file attachments to volunteers
created: 2026-09-14
source: ronan
status: needs-decision
size: L
priority: next
labels: [data, ux]
epic:
---

# Add profile fields and file attachments to volunteers

## Why

`Volunteer` currently holds only identity and stay data. UCESCO wants a
fuller profile per volunteer: country, date of birth, profession (free
text), skills (textarea), interests (textarea), a branch of attachment,
emergency contacts (textarea), and uploaded document attachments (soft
max ~20 per volunteer), all as mandatory fields.

## Done when

- `Volunteer` (or a related entity) carries country, date of birth,
  profession, skills, interests, emergency contacts as required fields,
  with a migration and form validation.
- Existing volunteers have a backfill/migration path for the new
  mandatory fields (they can't retroactively have a value).
- Attachments: upload, list and delete documents per volunteer, stored on
  disk outside the SQLite database in a dedicated directory, not served
  directly from a public path. Soft cap ~20 files/volunteer, enforced or
  at least surfaced in the UI.
- `RouteSmokeTest` and the volunteer functional test suite cover the new
  fields/routes.

## Notes & links

- **Open conflict to resolve before implementation:** "branch of
  attachment" as a direct field on `Volunteer` duplicates
  [ADR 0026](../../adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md),
  which deliberately has no branch field on `Volunteer` — branch comes
  from whichever `Stay` covers today, because volunteers move between
  branches over time. Adding a second, independent branch field would
  contradict that decision and could drift from the stay-derived branch.
  Needs a decision: is this "branch of attachment" actually the current
  stay's branch (derived, no new field), or a genuinely separate concept
  (e.g. a home/reporting branch distinct from where they're currently
  staying)? Write an ADR either way before coding.
- **Needs a storage design** for attachments: directory layout (e.g. by
  volunteer id), filename collision handling, allowed file types/size
  limits, and how deletion interacts with the volunteer delete-guard
  (`countReferencingActivities()` pattern) — deleting a volunteer should
  probably delete their attachment folder too.
- This repo is public
  ([`docs/fixtures/README.md`](../../fixtures/README.md) states the same
  constraint for rosters): emergency contacts and any uploaded documents
  are sensitive. Confirm attachments never end up under a web-served
  path, and that fixtures/demo data never carry real contact info or real
  documents.
