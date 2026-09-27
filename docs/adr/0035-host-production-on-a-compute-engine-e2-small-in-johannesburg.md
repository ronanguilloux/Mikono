# 35. Host production on a Compute Engine e2-small in Johannesburg

Date: 2026-09-27

## Status

Accepted

## Context

[ADR 0010](0010-build-in-ci-and-deploy-by-image-pull.md) settles how the
image reaches a server. This ADR settles which server runs production and
who holds the contract for it.
[ADR 0017](0017-host-production-on-gandicloud-vps-in-france.md) keeps the
UAT box.

The main force is compliance with Kenya's Data Protection Act 2019 and its
2021 Regulations
([ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md)):

- **Changing provider does not by itself make compliance easier.** Google
  has no region in Kenya, so hosting in Johannesburg is still a transfer
  out of Kenya under s.48 and s.49 (ADR 0034 rule 3). For the proof of
  safeguards, France under the GDPR is arguably the simpler case.
- **What helps is the controller holding the hosting contract.** Until
  now the server sat in the maintainer's personal Gandi account, so the
  chain was UCESCO → maintainer → Gandi. When UCESCO is the provider's
  customer:
  - UCESCO contracts directly with its processor (s.42(2)).
  - UCESCO holds the s.48 proof itself.
  - Breach notices reach UCESCO directly (s.43).
  - The infrastructure stays with UCESCO if the maintainer leaves.
- **Why Google rather than a Gandi account in UCESCO's name:**
  - **One processor and one identity for both services.** If UCESCO uses
    Google Workspace, Google is already its processor, and the same
    organisation and accounts would govern the server. If UCESCO does not
    use Workspace, this reason does not apply.
  - **Processor documentation already exists.** Google publishes the
    Cloud Data Processing Addendum, its sub-processor lists and its audit
    reports, and UCESCO can put them straight into its transfer file.

The other forces are secondary:

- **Latency.** The users are in Kenya. Johannesburg is 60–90 ms round trip
  from Nairobi, and Paris is 120–160 ms.
- **The image is amd64 only.** `build-image.yml` publishes `linux/amd64`
  ([`hosting-plan.md`](../project/hosting-plan.md) §2). AWS's cheap
  `af-south-1` instances are arm64. Compute Engine's E2 machines are
  x86_64, and they are offered in `africa-south1`'s zones.
- **Size.** hosting-plan §2 recommends 2 vCPU / 2 GB, because a deploy
  briefly runs two containers side by side. 1 GB was too small, and the
  UAT box was resized to 1 vCPU / 2 GB on 2026-09-06.
- **No real data on UAT.** `deploy.mikono.guilloux.org` is where UCESCO
  tests and accepts the app. It holds no real data and stays up after
  production goes live. So the question was whether production shares that
  box or gets its own.

## Decision

**Production runs on a single Google Compute Engine e2-small VM in
`africa-south1` (Johannesburg), under a Google Cloud account that UCESCO
owns as the customer, and runs the existing `docker compose` deployment
unchanged. UAT stays on its own box under ADR 0017.**

- **UCESCO is the Google Cloud customer:**
  - The payments profile and the billing account are in UCESCO's name, as
    an organisation.
  - The project sits under UCESCO's organisation and Google identity: its
    Workspace if it has one, or Cloud Identity if not.
  - UCESCO holds Organization Administrator and Billing Account
    Administrator.
  - The maintainer gets project-level roles, enough to operate the VM.
    UCESCO grants them and can revoke them.
  - UCESCO accepts the Cloud Data Processing Addendum and enters its
    data-protection contact in the console's privacy contact settings.
  - **The maintainer pays the running cost, never as the customer.** How
    the money reaches UCESCO, for example as a donation, is agreed with
    UCESCO. It is never the maintainer's card on a payments profile he
    owns, because that makes him the customer and rebuilds the chain this
    ADR removes.
- **The machine:**
  - machine type `e2-small`: 2 shared vCPUs (0.5 vCPU sustained, with
    bursts), 2 GB RAM, x86_64
  - region `africa-south1`
  - image family `debian-13` from `debian-cloud` (supported until
    2030-06-30)
  - a 20 GB balanced persistent disk
  - a reserved static external IPv4 address
- **A 1-year resource-based committed use discount** covers the e2-small's
  vCPU and memory in `africa-south1`.
  - UCESCO's billing account buys it once the first production deploy is
    stable.
  - It covers CPU and RAM only. The IPv4 address and the disk stay at
    on-demand rates.
  - It is billed for the full 12 months, even if the VM is deleted or
    resized. Do not buy it while the region or machine type might still
    change.
- **Nothing in the app or image changes.** ADR 0010 applies as written:
  - CI builds `frankenphp_prod` and pushes it to GHCR, and the VM pulls it.
  - Both compose files are passed on every command.
  - `deploy.env` is root-only, and `APP_SECRET` and
    `PASSPORT_ENCRYPTION_KEY` are set at runtime.
  - A deploy is `scripts/deploy.sh`, run over SSH as `deploy`.
  - FrankenPHP/Caddy terminates TLS itself, with a Let's Encrypt HTTP-01
    certificate. Do not put a CDN or proxy such as Cloudflare in front of
    it: that breaks HTTP-01 issuance and needs `framework.trusted_proxies`,
    which the app does not set.
  - SQLite lives on the `db_data` named volume.
  - Backups are `scripts/backup-db.sh` plus an off-site copy.
- **Only the infrastructure is specific to Google Cloud:**
  - **A budget alert on UCESCO's billing account.**
  - **A VPC firewall rule opening `tcp:80`, `tcp:443` and `udp:443` to the
    VM.** The stock `http-server` and `https-server` network tags open TCP
    only. Without `udp:443`, HTTP/3 silently falls back to HTTP/2 and
    nothing reports an error. Port 80 must stay open for HTTP-01 issuance
    and renewal.
  - **The admin login comes from SSH keys in project or instance
    metadata.** It is the username on that key, not `debian` as on Gandi.
    Wherever [`deployment-plan.md`](../project/deployment-plan.md) §3
    says `debian`, use that login instead.
  - **`deploy` is created by hand as in §3, never through metadata.**
    Google's "Add SSH keys to VMs" page warns that public keys added
    directly to `~/.ssh/authorized_keys` might be overwritten by the VM's
    guest agent. Google recommends adding keys through the console,
    `gcloud` or metadata. Keeping `deploy` out of metadata leaves it a user
    the agent does not manage, with its key in the `authorized_keys` that
    §3 sets up. After the VM's first restart, check that `deploy` can
    still log in.
  - **The IPv4 address is reserved, not ephemeral.** An ephemeral address
    changes when the VM stops and starts, and the DNS record then points at
    nothing. A reserved address that is not attached to a VM costs more
    per hour, so release it if the VM is deleted.
- **UAT and production are two boxes on two providers:** UAT is the Gandi
  box `srv-mikono`, production is the Compute Engine VM. Real volunteer data
  is never stored on the UAT disk.
- **These are left out for now.** They are optimisations, none of them
  required, and each can be added later without changing this decision:
  - **Disk snapshot schedules.** Backups stay `backup-db.sh` plus the
    off-site copy. Provider snapshots are never the backup.
  - **IPv6 and an AAAA record.**
  - **The Ops Agent and Cloud Monitoring.**
  - **Cloud Run** (Alternative 1).

## Consequences

- **Positive:**
  - UCESCO, the controller, holds the contract with its processor, keeps
    the transfer proof and receives breach notices directly. The
    maintainer can leave without taking the infrastructure with him.
  - Round trips from Kenya take about half as long as from Paris.
  - Production has the 2 GB that hosting-plan §2 recommends.
  - Deploys, backups and restores work exactly as documented, because
    nothing in the image or the deploy path changes.
- **Cost forecast** (USD, on-demand except the VM, 730 h a month,
  `africa-south1`):

  | Item | Rate | Per month |
  | --- | --- | --- |
  | e2-small VM, 1-year committed use discount | ~$0.0116/h (on-demand $0.0184/h) | ~$8.48 |
  | Static IPv4 in use on a standard VM | $0.005/h (free tier covers 1 h a month) | ~$3.65 |
  | 20 GB balanced persistent disk | ~$0.10–0.12/GiB-month (US rate $0.10 checked; Africa rate estimated) | ~$2.2 |
  | Internet egress, 0.5–2.5 GB a month | | < $0.50 |
  | **Total before VAT** | | **~$14.80 a month, ~$178 a year** |
  | **With Kenyan VAT at 16%** | | **~$17 a month, ~$206 a year** |

  - **VAT.** Kenyan VAT at 16% probably applies, because UCESCO is a
    Kenyan customer. The first invoice will show whether it does.
  - **Before the discount is bought,** the VM costs its on-demand rate,
    about $13.45 a month, which puts the total near $20 a month.
  - **Sources:** Google Cloud's pricing pages for Cloud Run, networking
    and disks, read on 2026-09-27. The VM rates come from
    gcloud-compute.com, a third-party site. Its on-demand rate was checked
    against the `us-central1` e2-micro rate of $0.0084/h.
  - **The cost does not depend on usage.** The VM is billed around the
    clock. Three load cases were compared: 2 users for 30 minutes a day,
    2 users for 2 hours a day, and 5 users for 1 hour a day. They cost the
    same. Only egress changes, by a few cents.
  - **There is no nonprofit price for Google Cloud.** Google for Nonprofits
    offers only the standard Free Tier and Free Trial for Cloud. Its
    nonprofit benefits are Workspace, Ad Grants and Maps
    (<https://support.google.com/nonprofits/answer/16245748>). Enrolling
    would give UCESCO free Workspace, which strengthens the
    shared-processor argument, if UCESCO is eligible in Kenya.
- **Negative / trade-offs:**
  - **Production cannot go live until UCESCO creates the account.** The
    payments profile, billing account and organisation are acts only
    UCESCO can perform.
  - **About two and a half times Gandi's price.** Gandi's 1 GB box cost
    about €72 a year.
  - **The discount is a 12-month commitment,** billed even if the VM moves
    or is deleted.
  - **The CPU is shared.** Only 0.5 vCPU is sustained, with bursts above
    that. This is enough for 2–5 users, but deploys take longer.
  - **Google, a US company, becomes UCESCO's processor.** The Cloud Data
    Processing Addendum provides the processor contract that ADR 0034
    requires under s.42(2).
  - **The transfer goes to South Africa, not France.** ADR 0034 rule 3
    applies unchanged: UCESCO keeps proof of safeguards (s.48), and
    sensitive personal data, which includes emergency contacts naming
    family, also needs the volunteer's consent (s.49(1)). Whether South
    Africa's POPIA supports a commensurate-law argument under s.48(b) is
    for UCESCO's transfer file to establish. This ADR does not assert it,
    and it is not legal advice.
  - **The off-site backup destination is not settled here.** It no longer
    follows the server into Europe. The ADR that picks it names its
    country under ADR 0034 rule 3.
  - **Production and UAT are with two providers,** so there are two bills,
    two consoles and two firewalls. A test on UAT does not prove anything
    about Google Cloud's firewall or login.
- **Reversibility:** cheap for the machine. Moving to another box means a
  `docker compose pull` there, copying one SQLite file and changing one DNS
  record. The restore drill in `deployment-plan.md` §7 rehearses most of
  it. An unexpired committed use discount keeps billing after a move.

## Alternatives considered

### 1. Cloud Run

**Rejected for now.** Compute would be almost free: the heaviest load case
uses about 6% of the free tier. But the app cannot run on Cloud Run as it
is:

- **SQLite needs a persistent disk.** Cloud Storage FUSE has no file
  locking, and Filestore starts at 1 TiB. The app would need Litestream
  with `max-instances=1`, or a move to Cloud SQL.
- **Sessions are files**
  ([ADR 0020](0020-keep-sessions-on-the-database-volume-in-files.md)), and
  they are lost whenever the service scales to zero.
- **The `/usage` access log is not kept**
  ([ADR 0021](0021-read-usage-from-an-in-app-usage-screen-over-the-caddy-access-log.md)),
  because the container's disk is temporary.
- **There is no SSH** to run `app:user:create`.
- **Cold starts** delay the first request after a quiet period.
- **A custom domain needs a load balancer, about $18 a month.** Domain
  mapping is only offered in a few regions, `africa-south1` is not one of
  them, and Google marks domain mapping as a Preview feature that is not
  production-ready.

A production-grade setup would cost about $26–31 a month, more than the VM.

### 2. An e2-micro in `africa-south1`

**Rejected.** About $13 a month on demand, but it has 1 GB of RAM and
0.25 vCPU sustained. That is the 1 GB configuration the box was already
moved off: swap becomes mandatory, and it is too small for a deploy's two
containers.

### 3. A free-tier e2-micro in `us-west1`, `us-central1` or `us-east1`

**Rejected.** About $4 a month, since only the IPv4 address is billed. But
the data would go to the US, the latency from Nairobi is about 250 ms, and
it is still a 1 GB box.

### 4. An e2-small in `europe-west1` (Belgium) or `europe-west9` (Paris)

**Rejected.** The price is the same or slightly higher (Paris is $14.19 a
month for the VM on demand), and the latency is as bad as on Gandi.
Johannesburg is in the same pricing tier as Belgium, so it costs no more.

### 5. Keep production in the maintainer's Gandi account (ADR 0017)

**Rejected.** It keeps the UCESCO → maintainer → Gandi chain: UCESCO has
no contract with the company that hosts its data, breach notices reach it
second-hand, and the server leaves with the maintainer. Paris is also
twice as far from Nairobi in round-trip time. The lower price and the
proven box keep Gandi as the UAT host, which holds no real data.

### 6. A Gandi account in UCESCO's name

**Rejected, though viable on data-protection grounds.** UCESCO would
contract directly with its processor just the same, and it would cost
less. It loses two things. Gandi would be a second processor with a
second identity alongside Workspace, where Google would be one processor
for both. And UCESCO would have to put together its own processor
evidence, where Google publishes an addendum, sub-processor lists and
audit reports. If UCESCO does not use Workspace, the first of these
disappears and only the documentation argument remains.

### 7. A Google Cloud account owned by the maintainer

**Rejected.** It is the Gandi governance problem again on a different
provider. The maintainer would be Google's customer, UCESCO would have no
contract with its processor, and the infrastructure would leave with him.

### 8. A Nairobi VPS (Lineserve, Truehost, Hostnali, HostPinnacle)

**Rejected.** Latency would be lower still, and the data would stay in
Kenya. But none of the candidates has said where its machines are or
answered the other pre-sales questions in
[`provider-questions.md`](../project/provider-questions.md). At about $275
a year, hosting-plan §5's estimate for the §2-recommended box, it costs
more. hosting-plan §5 and `provider-questions.md` stay in the repository,
so revisiting this starts from the existing research.
