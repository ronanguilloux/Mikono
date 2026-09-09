---
title: Size production off the 1 GB plan
created: 2026-09-09
source: ronan
status: blocked
size: S
priority: now
labels: [ops]
epic: production-readiness
---

# Size production off the 1 GB plan

## Why

The second of three gates on real data reaching a server. The UAT box
moved to **V-R2 — 1 CPU / 2 GB** on 2026-09-06, so the swapfile is no
longer standing in for a missing gigabyte there. Production has to be
ordered on the same tier rather than on V-R1, which is
[`../hosting-plan.md`](../hosting-plan.md) §2's *minimum*, not its
recommendation.

**Blocked on** [`uat-and-production-one-box-or-two`](uat-and-production-one-box-or-two.md):
if the boxes share, this becomes a resize of the existing one rather than
a new order.

## Done when

Production runs on a tier that meets §2's recommendation, and the tier is
recorded in [`../hosting-plan.md`](../hosting-plan.md).

## Notes & links

- Note the CPU is still 1 on V-R2. The §2 recommendation is **2 vCPU /
  2 GB**, and a deploy briefly runs two containers — that overlap is the
  reason the CPU count matters, not steady-state load.
