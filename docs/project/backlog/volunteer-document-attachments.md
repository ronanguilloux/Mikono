---
title: Attach documents to volunteers
created: 2026-09-17
source: [ronan, kingsley]
status: needs-decision
size: L
priority: next
labels: [data, security]
---

## Why

UCESCO wants to keep each volunteer's documents in the app, up to about
20 files per volunteer. Examples are passport scans, police clearance and
signed agreements. Today they live in inboxes and WhatsApp chats. The
first slice of the volunteer profile shipped fields and one photo, and
left documents out on purpose
([ADR 0032](../../adr/0032-store-volunteer-profile-fields-as-optional-and-photos-as-re-encoded-jpeg-blobs-in-sqlite.md)).

Kingsley since named the types wanted: PDF, photos, **videos**, Excel, Word,
receipts, IDs, certificates, contracts, bank documents.

## Done when

- A storage decision is recorded as an ADR: blobs in SQLite, as photos
  are, or files in a dedicated directory. Documents are bigger and more
  numerous than one 800 px photo, so the photo answer doesn't carry over
  automatically.
- **That ADR rules on video explicitly, or excludes it.** Video is one to
  two orders of magnitude larger than the documents this card was sized on,
  and on its own settles the blobs-vs-files question: a `VACUUM INTO` copy
  of the whole `.db` file on every backup, shipped off-site, on a 1 GB VPS
  ([production-vps-sizing](production-vps-sizing.md)) does not absorb it.
- On the volunteer page, staff can upload, list, download and delete
  documents.
- Documents are never served from a public path. Downloads go through an
  authenticated controller.
- The ~20-file cap is enforced, or at least shown, in the UI.
- Allowed types and a size limit are validated server-side, by the real
  MIME type and not by the file extension.
- Deleting a volunteer deletes their documents.
- Backups cover documents: `scripts/backup-db.sh`, the restore drill and
  the off-site copy. With blobs this is automatic. With files it isn't.
- `RouteSmokeTest` and `VolunteerControllerTest` cover the new routes.

## Notes & links

- **If files on disk:** decide the layout (e.g. by volunteer id), how name
  collisions are handled (store under a generated name and keep the
  original name in the database), and the volume. `db_data` is the only
  volume that is backed up, and only through `VACUUM INTO` of the `.db`
  file. The folder would need its own archive step in `backup-db.sh` and
  in [off-site-encrypted-backups](off-site-encrypted-backups.md).
- **If blobs:** a document row per file, with no inverse association on
  `Volunteer`. Stream downloads, and check what the database size does to
  backup time and to the 1 GB VPS.
- PDF and image metadata: re-encoding PDFs isn't practical the way it is
  for photos. Decide whether stored metadata is acceptable.
- This repo is public, and Kenya's DPA 2019 applies (server in France,
  [ADR 0017](../../adr/0017-host-production-on-gandicloud-vps-in-france.md)).
  Fixtures and tests never carry real documents. Consider a retention rule.
- Bank documents and IDs raise the sensitivity a step above passport scans.
  Whatever retention rule the ADR sets, they are the reason it needs one.
- The photo slice's reasoning:
  [brainstorm 12](../../brainstorm/12-volunteer-profile-and-photo.md).
- [photo-library-with-metadata](photo-library-with-metadata.md) has the same
  storage question for report photos. Decide both in one ADR, or say in that
  ADR why they differ.
