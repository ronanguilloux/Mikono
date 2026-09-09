---
title: An encrypted off-site copy of the backups
created: 2026-09-09
source: ronan
status: ready
size: M
priority: now
labels: [ops, security]
epic: production-readiness
---

# An encrypted off-site copy of the backups

## Why

The daily cron runs and the restore drill passed, but every copy still
sits on the same disk as the database it protects, which is not a backup.
This is the first of three things gating real volunteer data landing on a
server.

The destination question is settled by
[ADR 0017](../../adr/0017-host-production-on-gandicloud-vps-in-france.md) —
the off-site copy follows the server into Europe — so what is left is
mechanical.

## Done when

- `rclone` is installed on the production box and a **crypt** remote is
  configured, with the encryption key held off the server.
- The push is added to the existing cron line in
  [`../deployment-plan.md`](../deployment-plan.md) §7.
- A restore has been drilled **from the off-site copy**, not from the
  local one, and the drill is written up in `done.md`.

The copy that has to exist is **production's**, not UAT's. Doing it on the
UAT box first is a free rehearsal of exactly the procedure, in the spirit
of [`../deployment-plan.md`](../deployment-plan.md) §10 — but give
production its own remote and its own key rather than sharing UAT's.

## Notes & links

- Full narrative, including who can decrypt and who can delete:
  [`../../brainstorm/08-off-site-encrypted-backups.md`](../../brainstorm/08-off-site-encrypted-backups.md).
  That file questions whether `rclone crypt` is the right mechanism rather
  than assuming it — read it before installing anything.
- The crypt layer is what keeps the destination cheaply changeable later:
  the remote holds ciphertext, and the key never goes on the server.
- Local side already exists: `scripts/backup-db.sh`, host-side hot
  `VACUUM INTO`, no downtime and no `sqlite3` binary needed.
