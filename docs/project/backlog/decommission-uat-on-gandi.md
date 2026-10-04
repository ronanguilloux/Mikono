---
title: Decommission UAT on Gandi and destroy every copy of its test data
created: 2026-10-04
source: ronan
status: blocked
size: S
priority: next
labels: [ops, security]
epic: production-readiness
---

## Why

UAT on the GandiCloud VPS in France (`srv-mikono`) is temporary
([ADR 0017](../../adr/0017-host-production-on-gandicloud-vps-in-france.md)).
It holds only records created for testing, a few of them personal. While
those records sit in France they are personal data outside Kenya, so the
box should go as soon as UAT is no longer needed. **Blocked on**
production being up in Kenya
([`choose-kenyan-production-host`](choose-kenyan-production-host.md)).

## Done when

- Every copy of the UAT database is destroyed, not just the live one:
  - the `db_data` volume;
  - the local backups written by `backup-db.sh`;
  - any off-site copy of UAT, and the crypt key that went with it;
  - any copy on a maintainer's machine.
- The VPS is deleted in the Gandi console, with its disks and snapshots.
- The `deploy.mikono.guilloux.org` DNS record is removed.
- `server.local.md` says the box is gone, with the date.
- A dated entry in [`../done.md`](../done.md) lists what was destroyed.
  ADR 0017 is deleted or rewritten, via `adr-scribe`.

## Notes & links

- Where UAT lives afterwards (on the Kenyan provider, or nowhere) is a
  separate question. Don't let it hold this card up.
