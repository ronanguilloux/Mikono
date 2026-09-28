# 0039. Export the whole database as an admin-only zip of per-table CSVs

Date: 2026-09-28

## Status

Accepted

## Context

The owner wants the whole database in one download. The per-list exports
([ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md))
take a dozen separate downloads for a full copy, and some tables have no
list view at all: stays, the program and skill join tables, `usage_event`,
`login_attempt`.

A bulk copy of every table is a new personal-data export, so
[ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md) requires it
to state its purpose, sensitivity, retention and transfer basis. It
carries data the per-list exports deliberately leave out: gender (sensitive,
[ADR 0037](0037-record-a-volunteers-gender-for-accommodation-pairing.md)),
emergency contacts (family details, sensitive under s.2), and sign-in
identifiers and IPs
([ADR 0028](0028-record-login-attempts-with-identifier-and-ip-for-90-days-admin-only.md)).

`scripts/backup-db.sh` already makes a restorable copy. What is missing is
a copy people can open in a spreadsheet.

## Decision

**An admin can download every table as one `.zip` holding one
`<table>.csv` per table, header row first, from Admin → Export database
(`GET /database/export.zip`).**

- **Purpose.** A readable, complete copy of UCESCO's records for its
  Volunteer Manager and owner: reconciling, auditing and reporting outside
  the app. It is not a backup and is never restored from.
- **Who.** `ROLE_ADMIN` only, unlike the per-list exports, because it
  bundles every sensitive column and the sign-in log in one file.
- **What is left out, whatever the data:**
  - the `doctrine_migration_versions` table: bookkeeping, not data;
  - the `volunteer_photo` table: uploaded files are out of scope for now;
  - `user.password`: password hashes;
  - `volunteer.passport_number_ciphertext`: useless without the runtime
    key, which never travels with a copy
    ([ADR 0033](0033-encrypt-passport-numbers-at-rest-with-a-runtime-sodium-key.md)).

  Every other table and column goes in, including gender, emergency
  contacts, `login_attempt` and `usage_event`. A new table joins the
  archive automatically; a new secret or file column must be added to
  `App\Export\DatabaseExport`'s drop lists in the same change.
- **Format.** Raw column values, CSV only, written by OpenSpout with its
  UTF-8 BOM and the same formula guard as the per-list CSVs (a leading
  apostrophe before `=`, `+`, `-`, `@`, tab or carriage return).
- **Retention of the downloaded file.** The admin who takes it keeps it
  only as long as the task needs, on a UCESCO-controlled device, and
  deletes it after. The server keeps nothing: the zip is built in a temp
  file and removed once sent.
- **Transfer.** None beyond the download to the admin. Sending the file to
  a third party is a new processing operation under ADR 0034, not covered
  here.

## Consequences

- **Positive:** a full copy in one click, including tables with no screen.
  New tables are included with no code change. No new dependency: DBAL's
  schema manager lists the tables, OpenSpout writes the CSVs, `ext-zip`
  packs them.
- **Negative / trade-offs:** any admin can take every sensitive field in
  bulk, and a downloaded file is beyond the app's control. The CSVs carry
  raw database values (ids, snake_case headers, stored enum strings), not
  the labels the screens show. Rows stream one at a time, but the archive
  is built on disk, so it briefly needs free temp space about the size of
  the data.
- **Standing rule:** a column holding a secret or a file's bytes must be
  dropped or its table skipped when it is added.
- **Reversibility:** cheap. Remove the controller, the service and the menu
  item.

## Alternatives considered

### 1. Open to all signed-in staff, like ADR 0029

**Rejected.** The per-list exports leave out gender and the sign-in log;
this one does not, so it needs the tighter gate.

### 2. Ship the SQLite file itself

**Rejected.** It is not readable in a spreadsheet, and it would carry
password hashes, passport ciphertext and photo blobs.

### 3. One `.xlsx` workbook with a sheet per table

**Not chosen.** The owner asked for a zip of CSVs; it can be added later
through the same service.
