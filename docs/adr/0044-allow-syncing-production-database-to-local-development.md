# 0044. Allow syncing the production database to local development environments using a dedicated script

Date: 2026-10-17

## Status

Accepted

## Context

Occasionally, complex bugs or data inconsistencies appear in production that
cannot be reproduced using the sanitized fixtures generated from the WhatsApp
roster archive ([ADR 0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)).
To investigate and resolve these issues effectively, developers need the ability
to pull a copy of the production database into their local development
environment, where full tooling (like Xdebug) is available.

However, moving production data to a developer's machine creates friction with
two existing decisions:

1. **ADR 0012** mandates that fixtures come exclusively from the sanitized
   WhatsApp roster archive and prohibits generated or real data from being used
   as the demo database. Its goal is to keep personal data, sponsored children,
   and donors out of the public repository and the default local environment.
2. **ADR 0034** enforces compliance with Kenya's Data Protection Act 2019 (DPA).
   Production data contains sensitive personal data (e.g., emergency contacts,
   gender, encrypted passport numbers). Moving this data to a developer's
   machine engages rules on security (developer machine posture), retention
   (how long the local copy lives), and potentially cross-border transfers (if
   the developer is outside Kenya).

We need a governed mechanism to allow syncing production data for debugging
without compromising the repository's safety or UCESCO's legal compliance.

The import of UCESCO's legacy volunteer workbook
([ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md),
[card](../project/backlog/import-legacy-volunteer-workbook.md)) needs the
same copy in the other direction. The importer has to run against
production's real volunteers, lists and branches, and be checked in the
app before anything reaches production. The workbook must not travel to
the server, and the import must not run on the live app.

## Decision

**Allow syncing the production database to local development environments
using a dedicated script, subject to explicit authorization and strict data
protection controls.**

The dedicated script (`scripts/sync-prod-db.sh`, run as `make db_update`)
fetches the production SQLite database via SSH as the `deploy` user. It also
reads the production `PASSPORT_ENCRYPTION_KEY` from `/opt/mikono/deploy.env`
over the same SSH connection and writes it, mode 600, into the gitignored
`.env.dev.local`, which Symfony loads over the public dev key in `.env.dev`.
Without it the copy is unusable on the volunteer screens: every passport
number in it is sealed under the production key
([ADR 0033](0033-encrypt-passport-numbers-at-rest-with-a-runtime-sodium-key.md)),
so opening or editing any volunteer who has one fails with a server error.

This is reconciled with previous decisions as follows:

- **Reconciliation with ADR 0012 (Fixtures):** The strict prohibition in ADR
  0012 applies to data *committed to the repository* and the *default demo
  dataset* built by factories. A temporarily synced production database lives
  solely in the gitignored `var/` directory on the developer's machine. It is
  never committed, never pushed, and never used to seed new fixtures.
- **Data Privacy and Governance (ADR 0034):**
  - **Authorization:** Syncing production data is not a casual developer
    action. It requires explicit authorization from UCESCO (the data
    controller), either incident-by-incident or as a specific clause in the
    processor agreement for maintenance purposes.
  - **Secure Endpoint:** Developer machines holding production data become
    processing endpoints. They must employ full-disk encryption, strong access
    controls, and lock automatically.
  - **Cross-Border Transfers:** If the developer is located outside Kenya (e.g.,
    the maintainer in France), moving the database is a cross-border transfer
    of personal data. Under Kenya DPA s.48 and s.49(1) (ADR 0034, Rule 3),
    transferring sensitive personal data requires the data subject's explicit
    consent and proven safeguards. If UCESCO has not secured this consent for
    offshore maintenance, the database **cannot** be synced to a foreign local
    machine. Debugging must instead occur on a secure, UCESCO-owned UAT
    instance within Kenya.
  - **Retention and Destruction:** The synced database and the fetched key
    are strictly temporary. Immediately after the debugging session
    concludes, both must be destroyed, satisfying the retention bounds of
    ADR 0034: delete `.env.dev.local` (or its
    `PASSPORT_ENCRYPTION_KEY` line), replace the database with
    `make fixtures`, run `docker compose restart php` so the workers drop
    the key and the old file, and empty any trash/recycle bin that received
    either file.

**The reverse direction is allowed once, for the legacy workbook import:
a database prepared locally on a synced copy is uploaded to production as
a whole file.** Rules the upload must follow:

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
   image, sidecars removed, sessions dropped, `chown 33:0`, then
   `up -d --wait`. The entrypoint must log "Already at the latest
   version", and a profile with a passport number must open.
7. **The stopped production file is the rollback backup.** Restoring it
   through the same path undoes the upload.

The local copy and key are then destroyed as for a debugging session.

## Consequences

- **Positive:** Developers can utilize their full local toolchain (Xdebug,
  profilers) to reproduce and resolve production-only bugs accurately and
  quickly.
- **Negative / trade-offs:** Introduces significant operational and compliance
  overhead. Because the key travels with the copy, the local database is not
  protected by passport encryption: a lost or compromised developer machine
  exposes every passport number in it. Developers must ensure their machines
  are secure and rigorously delete data post-debugging. If the developer is
  outside Kenya, gathering consent or provisioning a compliant Kenyan debug
  environment is a steep prerequisite. The upload takes production offline
  between `down` and `up -d --wait`, ends every staff session, and replaces
  the sign-ins recorded on production between the sync and `down` with the
  copy's.
- **Reversibility:** Easy in code (remove the sync script), but any leaked data
  from a compromised or lost developer machine is irreversible and constitutes
  a reportable breach. The upload is undone by restoring the stopped
  production file.

## Alternatives considered

### 1. Debug strictly in production or a remote UAT mirror

**Rejected.** Debugging directly in production disrupts the VM's daily work and
risks accidental data corruption. Provisioning a remote UAT mirror in Kenya for
every debugging session is slow, incurs infrastructure costs, and deprives
developers of their local IDE integrations and profiling tools. (However, this
remains the mandatory fallback if cross-border consent is missing).

### 2. Anonymize the production database during the sync process

**Rejected.** While safer for compliance, bugs often stem from exact data
states—unexpected characters, unique relationship anomalies, or edge-case
nulls. Anonymization scripts alter the very data states needed to reproduce
the issue, rendering the synced database useless for its primary purpose.

### 3. Strictly interpret ADR 0012 and never permit local production data

**Rejected.** The inability to reproduce and fix critical bugs stalls
maintenance. Prolonged bugs harm the system's reliability and UCESCO's
operations more than a tightly governed, temporary data sync.

### 4. Run the legacy import on the production server

**Rejected.** The workbook would have to travel to the server, and the
import would run against the live app with no chance to check its result
in the app first. A local import on a fresh copy is checked before the
upload, and the upload reuses the restore path already proven on this
server.
