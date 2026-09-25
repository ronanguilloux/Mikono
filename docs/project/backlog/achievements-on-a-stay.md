---
title: Record achievements on a volunteer's stay
created: 2026-09-25
source: [ronan, kingsley]
status: needs-decision
size: M
priority: next
labels: [data]
epic: dashboard-reminders
---

## Why

Kingsley wants anniversaries of what volunteers achieved — a library
built, a program started. Nothing in the app records that today: an
activity is a day's log entry, not an outcome. Without a place to write
"Built a library" against the stay it happened in, the anniversary
reminder has nothing to count from, and the story of what a volunteer
left behind lives only in WhatsApp.

## Done when

- An ADR records the new `Achievement` entity and the answers below.
- `Achievement` belongs to one `Stay` (cascade delete with it) and has:
  - `title` — required, short ("Building a library");
  - `description` — optional, free text;
  - `achievedOn` — required date, **within the stay's dates**. The
    anniversary needs a day; the stay's start or end would be a guess;
  - `project` — the project it was part of, which **must share the stay's
    branch**, checked on save like
    [ADR 0027](../../adr/0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md)
    checks activities. Also re-checked on stay edit, since moving a stay's
    dates or branch can orphan an achievement.
- CRUD from the stay (and listed on the volunteer's page, newest first),
  reusing `DataTable` and the Tailwind form theme; a list view exports per
  [ADR 0029](../../adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md).
- Foundry factory, functional tests, the new route covered by
  `RouteSmokeTest`'s prefix map.
- A few real achievements added to `docs/fixtures/rosters.yaml` if Edna can
  supply them — never generated
  ([ADR 0012](../../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)).

## Notes & links

Open questions for the ADR:

- **Is `project` required?** Recommended yes — the reminder sentence names
  it, and an achievement outside any project is rare enough to be a
  "General" project. If Edna disagrees, make it optional and drop the
  clause from the reminder.
- **Program instead of project?** Activities hang off programs
  ([ADR 0030](../../adr/0030-insert-programs-between-projects-and-activities.md)).
  Project is the level people talk about ("the library at Kibera"), so
  start there.
- **A photo** — Kingsley mentioned "a note or a current photo". Leave it to
  [volunteer-document-attachments](volunteer-document-attachments.md) /
  [photo-library-with-metadata](photo-library-with-metadata.md) rather
  than a second blob column here.
- **Privacy:** achievements will name places and possibly children or
  donors in the description; the same care as profile fields applies, and
  none of it goes into the public fixtures without being scrubbed.
