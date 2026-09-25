---
title: A time limit on every copy of personal data
created: 2026-09-25
source: ronan
status: needs-decision
size: M
priority: next
labels: [data, ops]
---

## Why

s.39 allows personal data to be kept only as long as its purpose needs,
and s.34(3) wants a mechanism that enforces the limit. Some stores are
bounded: login attempts at 90 days, sessions at 8 hours, local backups at
30 days. Three are not:

- **Volunteer records** are kept forever after the volunteer's last stay.
- **The Caddy access log** rotates by size only (10 MiB × 3). It holds
  client IPs and full URIs, including the volunteer search's `?q=`
  terms, which can be a name or an email. A quiet month means those rows
  stay for months.
- **The off-site backup plan** keeps everything forever ("the remote keeps
  everything", brainstorm 08).

See [ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md) rule 6.

## Done when

- **Volunteer records:** UCESCO picks a retention period after the last
  stay ends, for example N years. Past it, a record is anonymised, reusing
  [volunteer-data-subject-requests](volunteer-data-subject-requests.md),
  either by a console command in the cron or on write, like ADR 0028.
- **Access log:** the log has a time bound as well as the size bound, for
  example Caddy's `roll_keep_for`. If a time bound isn't workable, the
  `q` value is dropped from the logged URI instead. Either way, ADR 0021
  is updated.
- **Off-site backups:** remote copies are pruned to a stated window. The
  window is recorded in the backup ADR that
  [off-site-encrypted-backups](off-site-encrypted-backups.md) produces.
  The trade-off to write down: an erased volunteer survives in backups
  until they age out.
- **Rule 1 table:** the store table in ADR 0034 rule 1 is updated to name
  the new bounds.

## Notes & links

- Log options: `CADDY_SERVER_LOG_OPTIONS` in `compose.yaml`.
- ADR 0031, the error log (30 days), must keep its window if it is
  implemented.
