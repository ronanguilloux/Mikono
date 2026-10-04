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

## Why

The daily cron runs and the restore drill passed, but every copy still
sits on the same disk as the database it protects, which is not a backup.
This is the first of three things gating real volunteer data landing on a
server.

**The destination is open.** Production goes to a Kenyan provider
([ADR 0035](../../adr/0035-host-production-on-a-compute-engine-e2-small-in-johannesburg.md)),
so the server itself is no transfer. **Prefer a destination in Kenya
too**, such as the production provider's own object storage or a second
Kenyan provider, so the backup doesn't become the one transfer left. A
destination abroad is a cross-border transfer under
[ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md)
rule 3 and needs its own safeguard. The rest is mechanical.

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
- **`PASSPORT_ENCRYPTION_KEY` must not travel with the copy.** Passport
  numbers in the `.db` are encrypted under it
  ([ADR 0033](../../adr/0033-encrypt-passport-numbers-at-rest-with-a-runtime-sodium-key.md)); a restore needs it, so it lives in the password
  manager beside `APP_SECRET`, never in the remote or next to the crypt key.
- Local side already exists: `scripts/backup-db.sh`, host-side hot
  `VACUUM INTO`, no downtime and no `sqlite3` binary needed.
