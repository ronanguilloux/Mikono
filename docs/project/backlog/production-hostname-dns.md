---
title: A production hostname that resolves
created: 2026-09-09
source: ronan
status: blocked
size: S
priority: now
labels: [ops]
epic: production-readiness
---

# A production hostname that resolves

## Why

The third gate on real data. Mechanically this is small — A/AAAA records
at the production box's IP, `SERVER_NAME` and `DEFAULT_URI` set to that
name, deploy — but **the name is not ours to create**, which is what makes
it a blocked card rather than an afternoon.

**Blocked on** [`nickson-meeting-dns-and-hosting`](nickson-meeting-dns-and-hosting.md)
for the name itself, and on
[`uat-and-production-one-box-or-two`](uat-and-production-one-box-or-two.md)
for the IP it points at.

## Done when

The production hostname resolves to the production box, `SERVER_NAME` and
`DEFAULT_URI` are set to it, and a deploy has succeeded with a valid
Let's Encrypt certificate on that name.

## Notes & links

**Bring the box up on a throwaway name first.** Let's Encrypt's
duplicate-certificate budget (five per week per hostname) no longer pits
UAT against production — two distinct names never contend for it. But it
still applies to the **production name itself**, and that name will be
UCESCO's, which makes exhausting its budget on a fumbled first deploy
considerably more awkward than burning a throwaway's.

So keep the pattern that worked in September: bring the production box up
on a spare `guilloux.org` name we control, get a clean deploy, and only
then have UCESCO point the real record at it.

- Runbook: [`../deployment-plan.md`](../deployment-plan.md).
- What a server must provide: [`../hosting-plan.md`](../hosting-plan.md).
