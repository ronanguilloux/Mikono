---
title: Export the whole database as one zip of per-table CSVs
created: 2026-09-28
source: ronan
status: needs-decision
size: M
priority: next
labels: [data, security]
---

## Why

Ronan asked for a way to take the whole database out in one download: a
single zip archive holding one CSV per table. Today each list view exports
on its own
([ADR 0029](../../adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)),
so a complete copy means a dozen separate downloads, and some tables
(stays, program/skill links, `usage_event`) have no list view at all.
Uploaded files are out of scope for now.

## Done when

- One action produces a `.zip` with one `<table>.csv` per table, header
  row first.
- Uploaded files (attachments, photos) are not included.
- Who can trigger it is decided and enforced (admin only, most likely,
  unlike the per-list exports).
- What is excluded or redacted is decided and tested. At minimum, passport
  numbers never leave in plaintext.
- A functional test checks the archive's table list and the access gate.

## Notes & links

- **This is a new personal-data export under
  [ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md)**,
  so it needs its own ADR first, covering purpose, sensitivity, retention
  of the downloaded file and who may take it. That is the `needs-decision`.
- Tables that need a ruling before they go into a zip:
  - `user.password`: hashes. Drop the column.
  - `volunteer.passportNumberCiphertext`: useless without the runtime key,
    and the key must never travel with a copy
    ([ADR 0033](../../adr/0033-encrypt-passport-numbers-at-rest-with-a-runtime-sodium-key.md)).
    Drop it, or ship it as ciphertext.
  - `login_attempt`: IPs, admin-only
    ([ADR 0028](../../adr/0028-record-login-attempts-with-identifier-and-ip-for-90-days-admin-only.md)).
  - `doctrine_migration_versions`: probably skip it.
- Emergency contacts count as sensitive under the DPA (s.2).
- Implementation: list tables via the DBAL schema manager, stream each one
  with `openspout` (already installed) into `ZipArchive` (the `zip`
  extension is loaded in the image). No new dependency.
- This does not replace `scripts/backup-db.sh`, which stays the restorable
  backup. The zip is a readable copy for people and spreadsheets.
- "Exclude files for now": once
  [volunteer document attachments](volunteer-document-attachments.md) or the
  [photo library](photo-library-with-metadata.md) exist, a follow-up card
  can add them to the archive.
