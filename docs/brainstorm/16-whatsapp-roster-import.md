# Brainstorm — Import the WhatsApp roster posts as activities

**Date:** 2026-10-18
**Author:** <ronan.guilloux@gmail.com>
**Related:** [`CLAUDE.md`](../../CLAUDE.md),
[brainstorm 15](15-legacy-workbook-import.md),
[`done.md`, 2026-10-17](../project/done.md),
[ADR 0012](../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md),
[ADR 0013](../adr/0013-record-every-escort-on-an-activity.md),
[ADR 0030](../adr/0030-insert-programs-between-projects-and-activities.md),
[ADR 0034](../adr/0034-comply-with-kenyas-data-protection-act-2019.md),
[ADR 0043](../adr/0043-record-who-added-and-last-edited-volunteers-stays-achievements-activities-projects-and-programs.md),
[ADR 0044](../adr/0044-allow-syncing-production-database-to-local-development.md),
[ODPC review card](../project/backlog/odpc-compliance-review-of-imported-data.md),
[`../adr/`](../adr/)

## Primary audience

Future-self and any agent resuming the import mid-way, or reading the
`done.md` entry later and wondering why an activity differs from the
roster.

## Desired impact

Every roster line UCESCO's VM posted from August to mid-October 2026 is an
`Activity` in production, as UCESCO top management directed on
2026-10-17. The run **completes the database and never overrides it**:
each line is reconciled against the branches, projects, programs,
volunteers, stays, escorts and activities already there. Every clash is
tracked, and the owner sees the report before anything is written.

## The "Options Not Taken"

- **Plan A: copy WhatsApp's `ChatStorage.sqlite` and query it in DB
  Browser.** Rejected on three counts:
  - macOS Full Disk Access blocks the copy ("Operation not permitted",
    verified);
  - the plan copies the `.sqlite` without its `-wal`, losing the recent
    messages;
  - its query takes `ZFROMJID` as the sender, which for a group message
    is the group's JID, not the person's.
- **Plan B: an agent copies the DB and the WAL, joins group members and
  checks its own counts.** Sound, but unnecessary: the messages are the
  same, and the extras (UTC times, JIDs, message ids, media) add nothing
  an activity needs.
- **Drive the WhatsApp app by computer use.** Rejected: it adds nothing
  the export lacks, risks posting into a live group, and exposes other
  chats.
- **Grow `docs/fixtures/rosters.yaml` instead (ADR 0012).** Rejected: the
  target is the production database, not the dev dataset.
- **Import July 2026 too.** Rejected by the owner: the exporting account
  joined the group on 03/08 and WhatsApp gives no earlier history. Only a
  member present in July could export it.

**Chosen:** the app's own "Export chat" `_chat.txt`, kept in the
gitignored `whatsapp-data-import/`. Every message carries
`[dd/mm/yyyy hh:mm:ss]`. Long posts aren't cut at "Voir plus", unlike
`docs/fixtures/whatsapp_dumps.txt`.

## Constraints

- The repo is public. The export and every output stay in
  `whatsapp-data-import/`. Scholarship posts name children and donors:
  the parser drops them, and they never reach any output.
- Edna's edits are frozen until the swap. The swap is checked by a
  per-table fingerprint, as for the workbook import.
- The local checkout equals the deployed commit (`3bb61b6`), so the dev
  entrypoint migrates nothing on the production copy.
- The ODPC review of imported data stays its own card, out of scope here.

## Context

The workbook import (brainstorm 15) landed on 2026-10-17. The remaining
source is the volunteers' WhatsApp group "UCESCO Africa Ambassadors
Network", where Edna, the VM, posts "Tomorrow's schedule" each evening:
site headers, `-Name` lines, "Accompanied by <escort>". Each line becomes
an `Activity`.

**The export:** 122 messages, 46 roster posts covering 04/08 → 15/10/2026,
240 roster lines. The gaps 01–07/10 and 09–11/10 are left empty. Posts go
out between 17:00 and 20:41, so "covers = posted + 1" never crosses
midnight. The export is not in date order.

**Shapes the parser handles:**

- 11/08, "schedule remains unchanged": repeats the previous roster;
- 27/08: no header;
- 28–30/09: inverted lines ("-Raquel-Peggy Lucas school",
  "Sarah-Travelling to Maralal");
- `-Name-later MVETI`: two lines (fixtures README rule 7);
- escorts written many ways: "Accompanied by X and Y", "and her team",
  "Working with/alongside Mr Antone", "Orientation (Sam)", or the escort
  on the next line.

**Mechanism:** a one-off command, `app:import:whatsapp-rosters`
(`src/Import/WhatsAppRoster*.php`), shaped after the deleted workbook
importer (`git show 3255225^`). One pass, in one transaction:

1. apply the owner-approved creations from `out/decisions.yaml`;
2. resolve each line and propose whatever is missing;
3. write the activities;
4. run the app's own checks: `ActivityController::resolveStays()` (a stay
   covers the day at the project's branch, the program covers the date
   and offers the type) and `StayController::fits` for stay edits;
5. roll back (`--analyse`) or commit (`--apply`).

It writes `out/report.md` (creations first, then counts, open decisions,
clashes, held-out lines), `out/lines.csv` and `out/decisions.yaml`.
`--apply` refuses while any decision a line uses is open. `AuthorStamper`
attributes the run to the owner's account.

**Matching:** sites by project-name tokens. Volunteers by first name, with
accents stripped and an edit distance of one for short names, two from six
letters, plus a stay overlapping the rostered dates. Two edits on
four-letter names had turned Tina and Mina into Lana.

**Pre-analysis against the production copy:** no branch and no activity
type is missing.

**Decisions (owner, 2026-10-18):**

- No branch or activity type is ever created.
- **Unmatched sites** get a project and one program each, after review:
  12 projects: Havillah school, KHDP Clinic, Nyali beach, Dalton school,
  Maralal women's empowerment and seven Kampala sites (Office (Kampala)
  and Mulago children's home included). Four are partner projects named
  after themselves; the rest are UCESCO's own. Plus an
  Orientation program at Office (Mombasa). Spelling variants of one place
  point at one entry (`same_as`).
- **Mapped to existing programs** instead: Peggy Lucas → School support;
  Dreams of Hope Center → Dreams of Hope Kids; "Sports" → Toi School
  Field; St Kizito and Maisha medical outreach → Ggaba's medical program,
  as Edna had logged those days. Travel days ("Journey/Travelling to
  Maralal") are skipped.
- **Escorts** Mr Kingsley and Mr Antone are created.
- **Two volunteers with no record** (Mina, Alicia) are created with nearly
  empty records.
- **Two duplicate pairs** in production are one person each and are
  merged: Tina (#353/#425) and Gwen (#240/#260). Tina's summer stay in
  Nairobi is estimated, best effort, from the rosters.
- **Stay changes** the analyser proposes (extend, move branch, split,
  create) are approved. For example, three volunteers' Nairobi stays move
  to Mombasa, where the rosters placed them for weeks.
- **Clashes with logged activities:** for all five (one on 04/08, four in
  October) the roster wins. The existing activity is changed to the
  roster's program and type, not duplicated. Everywhere else the database
  wins, and blank escorts are filled.
- **Clash tracking** lives in `out/report.md` and the `done.md` entry,
  never in a database field.
- **Write-back:** run first on a local production copy (`make db_update`,
  ADR 0044), then swap the file in, as for the workbook import.
