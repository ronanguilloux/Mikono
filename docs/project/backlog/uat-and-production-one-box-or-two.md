---
title: Decide whether UAT and production share one box
created: 2026-09-09
source: ronan
status: needs-decision
size: S
priority: now
labels: [ops]
epic: production-readiness
---

# Decide whether UAT and production share one box

## Why

UAT and production are two deployments.
[`nickson-meeting-dns-and-hosting`](nickson-meeting-dns-and-hosting.md)
does **not** settle this, so it has to be decided separately — and decided
**before the DNS exists**, because the answer is the IP the record has to
point at.

At 2 GB, sharing is no longer arithmetically impossible the way it was on
V-R1. But two FrankenPHP workers with a 256 MB opcache each, plus the
deploy overlap, is most of the box — and it would put real volunteer data
on the same disk as the environment we deliberately keep empty.

## Done when

One of the two is chosen and written down:

- **production gets its own VPS** — a second bill, a second backup cron,
  and UAT stays genuinely isolated from real data; or
- **the boxes are resized and share one**.

Either way [`production-vps-sizing`](production-vps-sizing.md) unblocks.
This is a hosting-topology decision, so it earns an ADR rather than a
`done.md` entry.

## Notes & links

- `deploy.mikono.guilloux.org` is the **UAT environment**, live with **no
  real data** (`done.md`, 2026-09-05). It is where UCESCO accepts the app,
  and it **stays** after production exists rather than being retired into
  it.
- [ADR 0017](../../adr/0017-host-production-on-gandicloud-vps-in-france.md)
  fixes the provider and the country; it does not fix the box count.
- [`../hosting-plan.md`](../hosting-plan.md) §2 has the sizing
  recommendation this trades against.
