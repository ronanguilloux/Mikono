# 0044. Allow syncing the production database to local development environments using a dedicated script

Date: 2026-10-18

## Status

Accepted

## Context

Bugs and data inconsistencies show up in production that the fixture
dataset built from the WhatsApp roster archive
([ADR 0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md))
cannot reproduce: unexpected characters, relationship anomalies, edge-case
nulls, and the legacy volunteers imported from UCESCO's workbook. Features
are also built and checked more reliably against the data the VM actually
works with than against a small transcribed archive. The local environment
is where the full tooling (Xdebug, profilers) runs.

Moving production data to a maintainer's machine meets two existing
decisions:

1. **ADR 0012** keeps personal data, sponsored children and donors out of
   the public repository: fixtures come only from the transcribed archive,
   never generated and never from real records.
2. **ADR 0034** applies Kenya's Data Protection Act 2019. Production holds
   sensitive personal data (emergency contacts, gender, encrypted passport
   numbers), so a machine holding a copy is a processing endpoint, and a
   maintainer outside Kenya receiving it is a cross-border transfer.

A one-off import of legacy data needs the copy in the other direction
too: UCESCO's volunteer workbook
([brainstorm 15](../brainstorm/15-legacy-workbook-import.md),
[ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md)) and the
WhatsApp roster exports
([brainstorm 16](../brainstorm/16-whatsapp-roster-import.md)) are two
such imports. The importer has to run against production's real
volunteers, lists and branches, and be checked in the app before anything
reaches production. The source files must not travel to the server, and
the import must not run on the live app.

## Decision

**The maintainer's local development database is, by default, a copy of
the production database, fetched by `make db_update` and refreshed with it
whenever production is fresher.**

The script (`scripts/sync-prod-db.sh`, run as `make db_update`) connects
over SSH as the `deploy` user, has the server snapshot its live database
with `scripts/backup-db.sh` (`VACUUM INTO`, no downtime), downloads that
snapshot and puts it in place of the local `data_dev.db`. Over the same
SSH connection it reads the production `PASSPORT_ENCRYPTION_KEY` from
`/opt/mikono/deploy.env` and writes it, mode 600, into the gitignored
`.env.dev.local`, which Symfony loads over the public dev key in
`.env.dev`. Without it the copy is unusable on the volunteer screens:
every passport number in it is sealed under the production key
([ADR 0033](0033-encrypt-passport-numbers-at-rest-with-a-runtime-sodium-key.md)),
so opening or editing any volunteer who has one fails with a server
error. The script ends with `docker compose restart php`, so the
FrankenPHP workers drop their handles on the replaced file.

The copy and the key stay on the machine together, for as long as the
copy is the local database. There is no end-of-session destruction step.
The fixture dataset (`make fixtures`, ADR 0012) stays available for work
that needs a known, shareable dataset, but it is not the default local
database.

Rules that hold for the standing copy:

- **ADR 0012 still governs the repository.** Its prohibition covers what
  is committed and what seeds fixtures. The copy lives only in the
  gitignored `var/` (the database volume) and the key in the gitignored
  `.env.dev.local`; import source files live in the gitignored
  `data-to-import/` and `whatsapp-data-import/`. None of it is
  committed, pushed, or used to seed fixtures.
- **Authorization.** Holding production data locally needs UCESCO's
  authorization as data controller, given as a clause in the processor
  agreement for maintenance.
- **Secure endpoint.** A machine holding the copy uses full-disk
  encryption, strong access controls and an automatic lock.
- **Cross-border transfer.** A maintainer outside Kenya (the maintainer is
  in France) receiving the copy is a transfer under DPA s.48 and s.49(1)
  (ADR 0034, rule 3): sensitive personal data needs the data subjects'
  consent and proven safeguards. Without them, the copy cannot be held on
  a machine abroad, and debugging moves to a UCESCO-controlled instance in
  Kenya.
- **No real person's login is created or reset in a production copy,
  by anyone, including an agent.** Resetting an existing email changes
  that person's production password, and the change could travel back to
  production on an upload. Log in with the owner's own production account.
  An agent that must sign in (a screenshot, a smoke test) may create a
  throwaway admin on an `@example.invalid` address with a random password,
  and deletes it, with its `login_attempt` rows, before anything is
  exported; `scripts/db-fingerprint.sh` then shows the `user` table back to
  its earlier hash.

**The reverse direction is allowed for a one-off legacy-data import the
owner approves: a database prepared locally on a synced copy is uploaded
to production as a whole file.** Each upload follows every rule below:

1. **A weekend freeze.** The VM agrees not to use the app from the sync
   until the upload is done, so production has no writes in between.
2. **The schema is pinned.** The local checkout equals the deployed
   commit, and `doctrine:migrations:status` matches on both sides. The
   dev entrypoint migrates on every restart, so a migration not yet
   deployed would be applied to the copy and uploaded with it.
3. **A baseline at sync time.** Right after `make db_update`, a sha256 of
   every table's rows, ordered by rowid, is recorded for each table except
   `login_attempt` and `usage_event`, which any sign-in changes.
4. **The copy keeps the production passport key.** The import runs with
   the synced `.env.dev.local` in place, so every passport number written
   locally is sealed under the production key. No account is created in
   the copy, because it would be uploaded with it. Rows in `login_attempt`
   and `usage_event` written locally after the sync are deleted before
   the export.
5. **`down` first, then compare.** As `deploy`, production is stopped,
   its stopped `data_prod.db` is copied out, fingerprinted the same way
   and compared with the baseline. **Any difference aborts the upload:**
   production is started again, then re-synced and the import re-applied.
6. **The file goes through the restore path** in
   [`deployment-plan.md`](../project/deployment-plan.md) ("Restore drill",
   "Seeding a server from a local dataset"): exported with
   `scripts/backup-db.sh`, copied into the volume with the app's own
   image, sidecars removed, sessions dropped, `chown 33:0` (which needs
   `docker run --user 0`), then `up -d --wait`. The entrypoint must log
   "Already at the latest version", and a profile with a passport number
   must open. The image runs as `www-data` (uid 33), so a `docker run`
   cannot write the stopped production file into the `deploy`-owned
   `backups/`: it copies it to a temporary world-writable folder, and
   `deploy` copies it into `backups/` from there.
7. **The stopped production file is the rollback backup.** Restoring it
   through the same path undoes the upload.

## Consequences

- **Positive:** Development and debugging always run against real data,
  with the full local toolchain, so production-only bugs reproduce
  locally and features are checked against what the VM actually sees.
- **Negative / trade-offs:** A maintainer laptop permanently holds
  production personal data and the production passport key. Because the
  key sits beside the copy, the local database gets no protection from
  passport encryption: a lost or compromised machine exposes every
  passport number in it. No retention limit is set here; the
  [ODPC compliance review](../project/backlog/odpc-compliance-review-of-imported-data.md)
  will look at it. Work that needs a shareable dataset (screenshots,
  demos, monkey testing that clicks Delete) has to switch to
  `make fixtures` first. The upload takes production offline between
  `down` and `up -d --wait`, ends every staff session, and replaces the
  sign-ins recorded on production between the sync and `down` with the
  copy's.
- **Reversibility:** Making fixtures the default again is cheap: run
  `make fixtures`, delete the `PASSPORT_ENCRYPTION_KEY` line from
  `.env.dev.local` (or the file), run `docker compose restart php`, and
  empty any trash that received either file. Removing the sync is
  deleting the script. Data leaked from a lost or compromised machine
  cannot be recalled and is a reportable breach. The upload is undone by
  restoring the stopped production file.

## Alternatives considered

### 1. Debug strictly in production or a remote UAT mirror

**Rejected.** Debugging directly in production disrupts the VM's daily
work and risks accidental data corruption. A remote UAT mirror in Kenya
costs infrastructure, is slow to refresh, and keeps the maintainer away
from local IDE integrations and profiling tools. It remains the
mandatory fallback if cross-border consent is missing.

### 2. Anonymize the production database during the sync

**Rejected.** Bugs often stem from exact data states: unexpected
characters, unique relationship anomalies, edge-case nulls.
Anonymization alters the very states needed to reproduce them, and would
break the legacy import, which must match real volunteers.

### 3. Keep fixtures as the default and sync only for a debugging session

**Rejected.** Each session paid for a sync, then a destroy-and-reseed
step, and work in between ran against a dataset that no longer looks
like production since the legacy import. The owner chose real data as
the standing default and leaves the retention question to the ODPC
review.

### 4. Strictly interpret ADR 0012 and never permit local production data

**Rejected.** Bugs that only production data reproduces would stay
unfixed, and the legacy import could not be checked before reaching
production. That harms the system's reliability and UCESCO's operations
more than a governed copy on an encrypted machine.

### 5. Run a legacy import on the production server

**Rejected.** The source files would have to travel to the server, and the
import would run against the live app with no chance to check its result
in the app first. A local import on a fresh copy is checked before the
upload, and the upload reuses the restore path already proven on this
server.
