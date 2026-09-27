---
title: Stand up production on a Compute Engine e2-small in Johannesburg
created: 2026-09-27
source: ronan
status: blocked
size: M
priority: now
labels: [ops]
epic: production-readiness
---

## Why

Production moves off Gandi to one Google Compute Engine VM in
`africa-south1`, before go-live
([ADR 0035](../../adr/0035-host-production-on-a-compute-engine-e2-small-in-johannesburg.md)).
The driver is UCESCO's compliance with Kenya's Data Protection Act 2019:
UCESCO, the controller, becomes the hosting customer and contracts with
Google directly, instead of the server sitting in the maintainer's
personal account. The maintainer funds it. The transfer out of Kenya
remains, and its paperwork is
[`dpa-governance-for-ucesco`](dpa-governance-for-ucesco.md).
**Blocked on UCESCO** (Edna, Kingsley, Nickson) for the account.

Nothing about the app changes: the VM runs the same `docker compose`
deployment as `srv-mikono`, by image pull
([ADR 0010](../../adr/0010-build-in-ci-and-deploy-by-image-pull.md)).
Until the box exists, the production hostname has no IP to point at.

## Done when

**Account**, created by UCESCO (ask at the
[Nickson meeting](nickson-meeting-dns-and-hosting.md)):

- A payments profile and billing account in UCESCO's name, as an
  organisation. The project sits under UCESCO's organisation or Google
  identity.
- UCESCO has accepted the Cloud Data Processing Addendum and set its
  data-protection contact in the console's privacy contacts. The
  acceptance date is recorded here.
- UCESCO keeps Organization and Billing Account Administrator. The
  maintainer holds project-level roles only.
- The funding arrangement is written down here: who pays whom, how
  often. The maintainer's card must never sit on a maintainer-owned
  profile.
- A billing budget alert at **$25/month** emails both UCESCO and the
  maintainer.

**Infrastructure**, all in `africa-south1`:

- A static external IPv4 is reserved and attached to the VM. Its
  address goes in `server.local.md`, never in this public repo.
- The VM is created with:
  - machine type `e2-small`;
  - image family `debian-13` from `debian-cloud`;
  - a 20 GB balanced boot disk;
  - the static IP.
- One VPC firewall rule opens **tcp:80, tcp:443 and udp:443** to the VM,
  by network tag.
- Port 22 is left on the default network's `default-allow-ssh` rule.

**Bootstrap and first deploy**

- [`../deployment-plan.md`](../deployment-plan.md) §3 runs through, with
  the Google deltas below.
- §6, the first deploy, succeeds on a throwaway `guilloux.org` name, with
  a valid Let's Encrypt certificate and HTTP/3 negotiated.
- §7, the backup cron, runs on the VM, and a restore drill passes there.
- **After the first stable deploy:** UCESCO's billing account buys a
  1-year resource-based commitment for the e2-small's vCPU and memory in
  `africa-south1`.
- `deployment-plan.md` and `hosting-plan.md` name the GCE admin login and
  the firewall rule in place of Gandi's, where they differ.
- `server.local.md` holds the box's facts: IP, zone, admin login, and the
  date of the first deploy.

The production hostname itself stays with
[`production-hostname-dns`](production-hostname-dns.md). This card ends
at a box that serves the app on a throwaway name.

## Notes & links

**Google deltas to the §3 runbook** (the rest applies as written):

- **The admin login is not `debian`.** It is whichever user the SSH key
  is registered under, in project or instance metadata, or through
  `gcloud compute ssh`. Create `deploy` by hand as §3 does. Do not
  declare it in metadata: the guest agent manages the users listed
  there.
- **The VPC firewall is the real gate.** §3's own `ufw` lines are
  harmless but gate nothing Docker publishes (§3 already says so). The
  stock `http-server`/`https-server` tags open TCP only. Without
  **udp:443**, HTTP/3 falls back silently, and that failure only shows
  when testing HTTP/3 itself.
- **Reserve the IP before creating the VM.** An ephemeral IP changes on
  stop/start and breaks the DNS. A reserved IP left unattached bills at
  a higher rate than one in use, so release it if the VM is deleted.
- §3's step 7 swap and §7's `cron` and `timedatectl` prerequisites are
  still needed: the GCE Debian image is minimal too.

**Cost forecast** (details in ADR 0035): about **$14.80/month, ~$178/year**
with the 1-year commitment, before VAT. With 16% Kenyan VAT it is about
$17/month; verify on the first invoice. The price does not depend on
usage. The VM is billed around the clock, so 2 or 5 staff change only
the egress, by cents.

**No nonprofit price on Google Cloud.** For Cloud, Google for Nonprofits
offers only the standard Free Tier and Free Trial
([source](https://support.google.com/nonprofits/answer/16245748)). The
programme is still worth joining for free Workspace: one Google processor
for mail, files and hosting is the shared-processor argument in ADR 0035.
Check UCESCO's eligibility.

**Deliberately left out, to add later:**

- GCE snapshot schedules: [ADR 0017](../../adr/0017-host-production-on-gandicloud-vps-in-france.md)'s
  rule stands, so backups are `backup-db.sh` plus the off-site copy;
- IPv6/AAAA;
- the Ops Agent.

**Knock-on:**

- [`off-site-encrypted-backups`](off-site-encrypted-backups.md) must
  choose its destination again.
- [`dpa-governance-for-ucesco`](dpa-governance-for-ucesco.md)'s processor
  and transfer items now name Google and South Africa.
- UAT stays on `srv-mikono`, with no real data.
