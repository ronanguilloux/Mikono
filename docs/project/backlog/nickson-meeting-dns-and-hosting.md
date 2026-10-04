---
title: Meet Nickson about the production hostname and DNS
created: 2026-09-09
source: ronan
status: ready
size: XS
priority: now
labels: [ops, docs]
epic: production-readiness
---

## Why

**This is what gates production.** Nickson is UCESCO's technical contact,
and the production hostname is a UCESCO subdomain that only UCESCO can
create. Nothing here is code; all of it decides where the code runs.

The good news first: a UCESCO-held name is what
[`../hosting-plan.md`](../hosting-plan.md) §6 has been asking for. It
retires half of the governance concern on its own: the domain and DNS
stop depending on one individual's personal Gandi account. The server's
account is the other half
([ADR 0035](../../adr/0035-host-production-on-a-compute-engine-e2-small-in-johannesburg.md)).

## Done when

The meeting has happened, the answers below are written into this card,
and [`production-hostname-dns`](production-hostname-dns.md) and
[`choose-kenyan-production-host`](choose-kenyan-production-host.md) are unblocked or
re-scoped accordingly.

## Notes & links

**Ask about the name and the DNS:**

- What is the parent domain, who administers its DNS, and can they add a
  subdomain pointing at an IP we control? A `CNAME` is fine if an A/AAAA
  pair is not on offer.
- **What is the turnaround on a DNS change, and who can make one?** This
  is the question that matters most and the one most likely to be waved
  through. Certificates are issued by Let's Encrypt over HTTP-01, so the
  name must resolve to the box *before* the first deploy succeeds — and it
  must keep resolving, because renewal happens unattended every 60 days. A
  DNS that lives behind someone else's ticket queue is an operational
  dependency, not a one-off form to fill in.
- **Who at UCESCO can open the hosting account and sign the provider's
  processor agreement?** Production goes to a Kenyan provider that
  commits in writing to the DPA 2019 (ADR 0035). UCESCO is the customer,
  and the maintainer may fund it. Does UCESCO have a preference between
  the candidates (Servercore, Skyhost Kenya), or an existing relationship
  with a Kenyan host? The answers go in
  [`choose-kenyan-production-host`](choose-kenyan-production-host.md).

**Ask about the data-protection paperwork**, which the meeting is the
natural place to raise even though it is not Nickson's to sign. Hosting
in Kenya means the production server is no transfer out of the country,
which is what UCESCO's DPIA relies on. UCESCO still has to sign the
provider's processor agreement and own the rest of the paperwork, and
UCESCO has no DPO. See
[`dpa-governance-for-ucesco`](dpa-governance-for-ucesco.md). Not legal
advice.

The provider research lives in [`../hosting-plan.md`](../hosting-plan.md)
§5 and [`../kenya-hosting-brief.md`](../kenya-hosting-brief.md), and
[`../provider-questions.md`](../provider-questions.md) is the email to
send.
