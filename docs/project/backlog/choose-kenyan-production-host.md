---
title: Choose a Kenyan production host that commits to the DPA 2019, and stand production up on it
created: 2026-10-04
source: ronan
status: ready
size: M
priority: now
labels: [ops, security]
epic: production-readiness
---

## Why

Production goes to a provider whose datacentre is in Kenya and which
states **in writing** that it complies with Kenya's Data Protection Act
2019 and keeps the data in Kenya
([ADR 0035](../../adr/0035-host-production-on-a-compute-engine-e2-small-in-johannesburg.md)).
Keeping the data in Kenya is what UCESCO's DPIA rests on: the production
server is then no transfer out of Kenya. The provider isn't chosen yet,
and until a box exists the production hostname has nothing to point at.

The maintainer names **Servercore** and **Skyhost Kenya** as providers
that state DPA 2019 compliance explicitly. Outside-agent research added
**Safaricom Cloud** and **Angani**, and reports that Servercore offers
S3-compatible storage in Kenya, which would also settle the off-site
backup. All of it is unverified: see
[`../hosting-plan.md`](../hosting-plan.md) §5, "Agent research". Every
claim still has to be confirmed in writing, alongside the technical
questions.

## Done when

**Selection**

- [`../provider-questions.md`](../provider-questions.md) has gone to
  Servercore, Skyhost Kenya, Safaricom Cloud, Angani and any other candidate from
  [`../kenya-hosting-brief.md`](../kenya-hosting-brief.md). The answers
  are recorded in [`../hosting-plan.md`](../hosting-plan.md) §5's table.
- The chosen provider has confirmed in writing:
  - the named Kenyan datacentre;
  - its DPA 2019 compliance and that the data stays in Kenya;
  - a processor agreement UCESCO can sign (s.42(2));
  - every hard requirement in hosting-plan §1–§3: KVM, root, unmanaged,
    x86_64, ports 80/tcp, 443/tcp and 443/udp, a static IPv4, no proxy
    in front.
- ADR 0035 names the chosen provider (via `adr-scribe`).

**Account**, created by UCESCO (ask at the
[Nickson meeting](nickson-meeting-dns-and-hosting.md)):

- The contract and billing are in UCESCO's name, as an organisation. The
  maintainer gets operator access only, which UCESCO can revoke.
- UCESCO has signed the provider's processor agreement. The date is
  recorded here.
- The funding arrangement is written down here. The maintainer may fund
  the box, but never as the customer.

**Bootstrap and first deploy**

- `uname -m` reads `x86_64` on first boot.
- [`../deployment-plan.md`](../deployment-plan.md) §3 runs through.
- §6, the first deploy, succeeds on a throwaway name, with a valid Let's
  Encrypt certificate and HTTP/3 negotiated.
- §7, the backup cron, runs on the box, and a restore drill passes there.
- `server.local.md` holds the box's facts: IP, facility, admin login and
  the date of the first deploy. These go there, never in this public
  repo.

The production hostname itself stays with
[`production-hostname-dns`](production-hostname-dns.md). This card ends
at a box that serves the app on a throwaway name.

## Notes & links

- **Compliance alone doesn't pass a provider.** A Kenyan VPS that states
  compliance but runs OpenVZ, filters UDP 443 or ships with cPanel still
  can't run the app. Check both halves.
- Price reference: hosting-plan §5 found 2,600–3,000 KSh/month plus VAT
  for 2 vCPU / 2 GB in Nairobi. The Johannesburg plan this replaces was
  about US$17/month including VAT.
- Providers already researched that don't state compliance (Lineserve,
  Truehost, Hostnali, HostPinnacle) are only back in the running if they
  commit to it in writing.

**Knock-on:**

- [`off-site-encrypted-backups`](off-site-encrypted-backups.md): a Kenyan
  destination avoids a second transfer.
- [`dpa-governance-for-ucesco`](dpa-governance-for-ucesco.md): the
  processor is this provider.
- [`decommission-uat-on-gandi`](decommission-uat-on-gandi.md) follows
  once production is live.
