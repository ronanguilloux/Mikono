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

# Meet Nickson about the production hostname and DNS

## Why

**This is what gates production.** Nickson is UCESCO's technical contact,
and the production hostname is a UCESCO subdomain that only UCESCO can
create. Nothing here is code; all of it decides where the code runs.

The good news first: a UCESCO-held name is what
[`../hosting-plan.md`](../hosting-plan.md) §6 has been asking for. It
retires half of [ADR 0017](../../adr/0017-host-production-on-gandicloud-vps-in-france.md)'s
governance concern on its own — the domain and DNS stop depending on one
individual's personal Gandi account, leaving only the server there.

## Done when

The meeting has happened, the answers below are written into this card,
and [`production-hostname-dns`](production-hostname-dns.md) and
[`production-vps-sizing`](production-vps-sizing.md) are unblocked or
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
- Does UCESCO want the VPS itself in a UCESCO account eventually? That is
  the other half of ADR 0017's governance concern, and it is a billing
  conversation more than a technical one.

**Ask about the data-protection safeguard**, which the meeting is the
natural place to raise even though it is not Nickson's to sign: production
is in France and holds personal data about Kenyan volunteers. Kenya's Data
Protection Act 2019 Part VI permits transfer abroad with appropriate
safeguards or consent, and France is an easy jurisdiction to argue one for
— so this is defensible, not a problem to fix. But somebody at UCESCO has
to own the documentation, and UCESCO has no DPO. The sentence to put in
front of them is in [`../hosting-plan.md`](../hosting-plan.md) §5. Not
legal advice; if UCESCO has counsel, that is the sentence to show them.

**If the hosting question ever reopens**, none of the research was thrown
away: [`../hosting-plan.md`](../hosting-plan.md) §5 keeps the Nairobi
candidates table, the five pre-sales questions and the ranking that put
Kenya first, and [`../provider-questions.md`](../provider-questions.md) is
still the email to send. ADR 0017 is what a superseding ADR would have to
argue against.
