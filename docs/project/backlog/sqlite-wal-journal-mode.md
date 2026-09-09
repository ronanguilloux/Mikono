---
title: SQLite journal mode (WAL)
created: 2026-09-09
source: ronan
status: deferred
size: XS
priority: later
labels: [ops, perf, data]
---

# SQLite journal mode (WAL)

## Why

Still the default rollback journal, and the single-writer limit
[ADR 0003](../../adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)
flagged is still real.

The old gate — "wait for a second `User`" — is **spent**: production now
holds three or four `User` rows, two of which sign in. This card carries
the narrower gate that replaced it.

**Measure before reaching for WAL**, because two things make it less
urgent and less free than it looks:

- PDO already sets a **60-second busy timeout**, so the realistic
  collision (two millisecond-long writes) resolves by waiting, invisibly.
- WAL is a trade, not a win. It does remove the contention that actually
  applies here — under `delete` an open *read* transaction blocks a
  writer, and this app is read-heavy plus Turbo prefetches on hover — but
  under WAL a transaction that reads and *then* writes while another
  connection committed in between fails **immediately**, with no busy
  timeout to retry it. A rare long hang traded for a rare instant 500.

## Done when

`PRAGMA journal_mode=WAL` has been run **once** against the production
file — it persists in the header. This is not a code change.

## Notes & links

Backups are not the obstacle: `VACUUM INTO` snapshots WAL correctly, so
`scripts/backup-db.sh` is unaffected either way.

## Trigger

The first report of a hung save, or a "database is locked" error.
