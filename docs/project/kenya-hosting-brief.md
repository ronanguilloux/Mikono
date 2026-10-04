# Brief for an AI agent researching Kenyan hosting providers

**Last updated:** 2026-10-04

A self-contained brief to paste into an outside AI agent so it can suggest
**new** Kenya-based providers for Mikono's production server. It restates
[`hosting-plan.md`](hosting-plan.md) §1–§5,
[ADR 0035](../adr/0035-host-production-on-a-compute-engine-e2-small-in-johannesburg.md)
and [ADR 0034](../adr/0034-comply-with-kenyas-data-protection-act-2019.md)
for a reader with no access to the repo. If those change, update this file
too. Any candidate it turns up gets [`provider-questions.md`](provider-questions.md)
before money changes hands.

Paste everything below the line.

---

**Task:** suggest hosting providers with datacentres physically located
**in Kenya** (Nairobi preferred; Mombasa acceptable) that can run the
application described below. For each provider, give the facts listed
under "What to return" and cite the provider's own pages. Leave out any
provider that fails a hard requirement.

## 1. What the app is

Mikono is a small web app run by UCESCO, a Kenyan NGO, to track volunteers
at its projects in Kibera (Nairobi) and Mombasa. Every page needs a login.
It handles volunteers, stays, projects, programs, activity logs, reports
and CSV/XLSX exports. Pages are rendered on the server (Twig + Turbo);
there's no SPA and no public API.

- **Users:** 2–5 staff, mostly on Kenyan mobile networks. Traffic is low:
  **0.5–2.5 GB/month** going out to the internet.
- **Stack:** PHP 8.5 / Symfony 8.1 in **one Docker container** running
  FrankenPHP, which embeds the Caddy web server. The database is
  **SQLite**, a single file on a Docker named volume.
- **What it doesn't use:** no external database, Redis, message queue,
  SMTP/mailer, CDN, or Node.js at runtime.
- **Delivery:** GitHub Actions builds a **linux/amd64** image and pushes
  it to GHCR. The server only runs `docker compose pull && up -d` over
  SSH. Nothing is built on the server.

## 2. Hard requirements (failing any one excludes the provider)

1. **A KVM VPS or cloud VM with full root and SSH access.** Docker doesn't
   run reliably on OpenVZ/LXC. Shared hosting and cPanel are out because
   staff accounts are created over SSH with a console command, and there's
   no password-reset email.
2. **Unmanaged, with no control panel holding ports 80/443.** The
   container is the web server and handles TLS itself.
3. **x86_64/amd64 CPUs.** The image is built for amd64 only, so it won't
   start on arm64.
4. **Docker Engine plus the Compose v2 plugin, version 2.30 or newer.** A
   stock Debian 12/13 or Ubuntu 24.04 image is fine; we install Docker
   ourselves.
5. **Inbound 80/tcp, 443/tcp and 443/udp.** Port 80 is needed for Let's
   Encrypt to issue and renew certificates (HTTP-01). 443/udp carries
   HTTP/3, which helps on unreliable mobile connections. Many hosts **block
   UDP by default**, so confirm this explicitly.
6. **A static public IPv4 address** for the customer's own DNS A record.
   An address that changes on restart won't work.
7. **No reverse proxy, CDN or anything in front that handles TLS.** A
   Cloudflare-style proxy breaks HTTP-01 and moves TLS handling outside
   Kenya.
8. **A named datacentre physically in Kenya.** The provider should name
   the facility, for example iColo/Digital Realty, PAIX, Africa Data
   Centres, Safaricom or IXAfrica. A Kenyan company reselling servers in
   Europe or the US doesn't count. Be suspicious of a plan page that lists
   many African countries as "locations" without naming any facility.
9. **A written commitment to Kenya's Data Protection Act 2019**, ideally
   with ODPC registration as a data processor and a processor agreement
   the customer can sign (s.42(2)). A Kenyan address or Kenyan law alone
   isn't enough.

## 3. Sizing

| | vCPU | RAM | Disk |
| --- | --- | --- | --- |
| Minimum | 1 | 1 GB | 10 GB SSD |
| **Target** | **2** (shared/burstable is fine) | **2 GB** | **20–40 GB SSD/NVMe** |

- At runtime the app uses about 300–600 MB of RAM (FrankenPHP worker mode
  plus a 256 MB PHP opcode cache).
- A deploy briefly runs the old and new containers at once, so 1 GB is too
  tight. A 1 vCPU / 2 GB server has worked in user acceptance testing.
- Container images take up most of the disk. The database is a few MB,
  plus re-encoded JPEG photos stored inside SQLite (uploads are capped at
  10 MB). It grows slowly.

## 4. Why Kenya: data protection, not latency

The app holds personal data about volunteers. Some of it counts as
**sensitive** under **Kenya's Data Protection Act 2019**, including
emergency contacts, gender and passport numbers (which are encrypted at
rest). Sending this data out of Kenya needs proof of safeguards (s.48).
Sensitive data also needs each person's consent (s.49).

UCESCO has decided that production goes to a provider with a datacentre
in Kenya that **commits in writing to DPA 2019 compliance and to keeping
the data in Kenya**. Hosting in Kenya means the server is no transfer out
of the country, which is what UCESCO's data protection impact assessment
relies on. The alternative it replaced, Google Compute Engine in
Johannesburg, cost **about US$17/month including 16% VAT**; use that as a
price reference, not a ceiling.

**Contracting:** UCESCO, as data controller, must be the provider's direct
customer. It needs to sign a processor agreement (DPA s.42(2)) and receive
breach notices directly. Look for:

- published processor terms or a DPA
- registration with Kenya's **ODPC** (Office of the Data Protection
  Commissioner)
- invoices to a Kenyan organisation in KES, payable by M-Pesa or bank
  transfer

## 5. Nice to have (use these to rank)

- Self-service snapshots with a documented, **tested** restore that can be
  run from the control panel at 2am. This only shows maturity: our real
  backup is our own encrypted off-site copy.
- Native IPv6 (/64).
- Peering at KIXP or the Mombasa exchange, plus published upstream
  carriers and maintenance windows.
- Support hours and channels in East Africa Time, and whether help is
  available out of hours.
- An API or CLI. This doesn't matter in itself, but it's a sign of a real
  KVM cloud.
- **S3-compatible object storage in Kenya.** We still need somewhere to
  send an off-site backup (`rclone crypt`, a few MB/day, encrypted before
  upload). A destination inside Kenya avoids a second transfer abroad.
- A price at or below the Johannesburg baseline (~US$17/month incl. VAT),
  or close enough that keeping data in Kenya justifies the difference.

## 6. Already evaluated (prices excl. 16% VAT, checked 2026-09-04)

Don't present these as new. Only re-check them if you find something that
changes the picture.

| Provider | Plan | KES/month | Spec | Kenyan DC |
| --- | --- | --- | --- | --- |
| Lineserve | Cloud Server, built to order | ~2,630 | 2 vCPU / 2 GB / 40 GB | stated (`ke-1a`, Nairobi) |
| Truehost | Kenya Cloud VPS 2 | 2,800 | 1 vCPU / 2 GB / 50 GB | stated, Nairobi |
| Hostnali | KE VPS-Plus | 4,360 | 1 vCPU / 2 GB / 40 GB NVMe | stated, peered at KIXP |
| HostPinnacle | SM-VPS 1 | 1,100 | 4 vCPU / 6 GB / 100 GB | **not stated**, likely abroad |

Also already researched (2026-10-04, unverified): **Servercore**
(reported ~US$26/month incl. tax for 2 vCPU / 2 GB / 30 GB in Nairobi,
with Kenyan S3 storage), **Safaricom Cloud** (5,000–7,500 KSh/month) and
**Angani** (quote only). **Skyhost Kenya** is a named candidate not yet
researched. Excluded: HostAfrica (VPS in South Africa), Africloud
(Johannesburg), AWS Local Zone Nairobi (egress cost, no local S3).

None of these has answered our pre-sales questions in writing. We want
**other** providers, or facts that confirm or correct the ones above,
from the providers' own pages.

## 7. What to return for each provider

1. Provider, plan name and URL.
2. Datacentre facility and city, with the source.
3. Virtualisation type (KVM?), CPU architecture, root access, and whether
   the server is unmanaged.
4. Specs and monthly price in KES and USD, stating whether VAT is
   included.
5. Static IPv4 and its cost, IPv6, and whether **UDP 443** is open.
6. Snapshots and backups, and any object storage in Kenya.
7. Data-protection position: ODPC registration, processor agreement,
   certifications.
8. Support hours and channels, billing and payment methods.
9. Pass or fail against each requirement in §2, including the written DPA commitment. List any requirement the
   provider's pages don't answer: no answer is not a pass.
10. A ranked shortlist of no more than three providers, with one line
    each on why.
