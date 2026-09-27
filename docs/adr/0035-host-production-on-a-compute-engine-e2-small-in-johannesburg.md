# 35. Host production on a Compute Engine e2-small in Johannesburg

Date: 2026-09-27

## Status

Accepted

## Context

[ADR 0010](0010-build-in-ci-and-deploy-by-image-pull.md) settles how the
image reaches a server. This ADR settles which server runs production.
[ADR 0017](0017-host-production-on-gandicloud-vps-in-france.md) keeps the
UAT box.

The forces:

- **Latency.** The users are in Kenya. Johannesburg is 60–90 ms round trip
  from Nairobi; Paris is 120–160 ms.
- **The image is amd64 only.** `build-image.yml` publishes `linux/amd64`
  ([`hosting-plan.md`](../project/hosting-plan.md) §2). South Africa was
  first rejected partly because AWS's cheap `af-south-1` instances are
  arm64. Compute Engine's E2 machines are x86_64 and are offered in
  `africa-south1`'s zones.
- **Ownership.** Under ADR 0017, domain, DNS and server all sat in the
  maintainer's personal Gandi account. UCESCO should be able to own the
  production server, and a Google Cloud project can move under another
  billing account without rebuilding anything. Choosing the account itself
  is UCESCO's governance question, not a technical decision.
- **Size.** hosting-plan §2 recommends 2 vCPU / 2 GB because a deploy
  briefly runs two containers side by side. The 1 GB configuration was
  outgrown: the UAT box was resized to 1 vCPU / 2 GB on 2026-09-06.
- **Real data stays off UAT.** `deploy.mikono.guilloux.org` is where UCESCO
  tests and accepts the app, with no real data, and it stays up after
  production goes live. So the choice is between one box for both and a
  separate production box.

## Decision

**Production runs on a single Google Compute Engine e2-small VM in
`africa-south1` (Johannesburg), running the existing `docker compose`
deployment unchanged; UAT stays on its own box under ADR 0017.**

- **The machine:**
  - machine type `e2-small`: 2 shared vCPUs (0.5 vCPU sustained, with
    bursts), 2 GB RAM, x86_64
  - region `africa-south1`
  - image family `debian-13` from `debian-cloud` (supported until
    2030-06-30)
  - a 20 GB balanced persistent disk
  - a reserved static external IPv4 address
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
  - **A Google Cloud project whose billing account UCESCO can take over,**
    with a budget alert on it.
  - **A VPC firewall rule opening `tcp:80`, `tcp:443` and `udp:443` to the
    VM.** The stock `http-server` and `https-server` network tags open TCP
    only. Without `udp:443`, HTTP/3 silently falls back to HTTP/2 and
    nothing reports an error. Port 80 must stay open for HTTP-01 issuance
    and renewal.
  - **The admin login comes from project or instance metadata SSH keys.**
    It is the username on that key, not `debian` as on Gandi. Wherever
    [`deployment-plan.md`](../project/deployment-plan.md) §3 says
    `debian`, use that login instead.
  - **`deploy` is created by hand as in §3, never through metadata.**
    Google's guest agent gives every user it creates from a metadata key
    passwordless sudo and manages that user's `authorized_keys`. `deploy`
    owns `/opt/mikono` and needs neither.
  - **The IPv4 address is reserved, not ephemeral.** An ephemeral address
    changes when the VM stops and starts, and the DNS record then points at
    nothing. A reserved address that is no longer attached to a VM costs
    more per hour, so release it if the VM is deleted.
- **UAT and production are two boxes on two providers:** UAT is the Gandi
  box `srv-mikono`, production is the Compute Engine VM. Real volunteer data
  is never stored on the UAT disk.
- **These are left out for now.** They are optimisations, none of them
  required, and each can be added later without changing this decision:
  - **A one-year committed use discount.**
  - **Disk snapshot schedules.** Backups stay `backup-db.sh` plus the
    off-site copy. Provider snapshots are never the backup.
  - **IPv6 and an AAAA record.**
  - **The Ops Agent and Cloud Monitoring.**
  - **Cloud Run** (Alternative 1).

## Consequences

- **Positive:**
  - Round trips from Kenya take about half as long as from Paris.
  - Production has the 2 GB that hosting-plan §2 recommends, and the
    project can move to a billing account UCESCO owns.
  - Deploys, backups and restores work exactly as documented, because
    nothing in the image or the deploy path changes.
- **Cost forecast** (USD, before tax, on-demand, 730 h a month,
  `africa-south1`):

  | Item | Rate | Per month |
  | --- | --- | --- |
  | e2-small VM | $0.0184/h | ~$13.45 |
  | Static IPv4 in use on a standard VM | $0.005/h (free tier covers 1 h a month) | ~$3.65 |
  | 20 GB balanced persistent disk | ~$0.10–0.12/GiB-month (US rate $0.10 checked; Africa rate estimated) | ~$2.0–2.4 |
  | Internet egress, 0.5–2.5 GB a month | | < $0.50 |
  | **Total** | | **~$20 a month, ~$240 a year** |

  Sources: Google Cloud's pricing pages for Cloud Run, networking and
  disks, read on 2026-09-27. The VM's hourly rate comes from
  gcloud-compute.com, a third-party site, and was checked against the
  `us-central1` e2-micro rate of $0.0084/h.

  **The cost does not depend on usage.** The VM is billed around the
  clock. Three load cases were compared: 2 users for 30 minutes a day,
  2 users for 2 hours a day, and 5 users for 1 hour a day. They cost the
  same. Only egress changes, by a few cents.
- **Negative / trade-offs:**
  - **About three times Gandi's price.** Gandi's 1 GB box cost about €72 a
    year.
  - **The CPU is shared.** Only 0.5 vCPU is sustained, with bursts above
    that. This is enough for 2–5 users, but deploys take longer.
  - **Google, a US company, becomes UCESCO's processor.** The Google Cloud
    Data Processing Addendum provides the processor contract that
    [ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md)
    requires under s.42(2).
  - **The transfer now goes to South Africa, not France.** ADR 0034 rule 3
    applies unchanged: UCESCO keeps proof of safeguards (s.48), and
    sensitive personal data, which includes emergency contacts naming
    family, also needs the volunteer's consent (s.49(1)). The
    commensurate-law argument under s.48(b) now rests on South Africa's
    POPIA instead of the GDPR. This is governance, not a technical task,
    and not legal advice.
  - **The off-site backup destination is not settled here.** It no longer
    follows the server into Europe. The ADR that picks it names its
    country under ADR 0034 rule 3.
  - **Production and UAT sit with two providers,** so there are two bills,
    two consoles and two firewalls. A test on UAT does not prove anything
    about Google Cloud's firewall or login.
- **Reversibility:** cheap. Moving to another box
  means a `docker compose pull` there, copying one SQLite file and changing
  one DNS record. The restore drill in `deployment-plan.md` §7 rehearses
  most of it.

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

**Rejected.** About $13 a month, but it has 1 GB of RAM and 0.25 vCPU
sustained. That is the 1 GB configuration the box was already moved off:
swap becomes mandatory, and it is too small for a deploy's two containers.

### 3. A free-tier e2-micro in `us-west1`, `us-central1` or `us-east1`

**Rejected.** About $4 a month, since only the IPv4 address is billed. But
the data would go to the US, the latency from Nairobi is about 250 ms, and
it is still a 1 GB box.

### 4. An e2-small in `europe-west1` (Belgium) or `europe-west9` (Paris)

**Rejected.** The price is the same or slightly higher (Paris is $14.19 a
month for the VM), and latency is as bad as on Gandi. Johannesburg is in
the same pricing tier as Belgium, so it costs no more.

### 5. Keep production on GandiCloud VPS in France (ADR 0017)

**Rejected.** Of the forces above, Gandi meets only the need for 2 GB.
Paris is twice as far from Nairobi in round-trip time. Hosting in South
Africa no longer means arm64, because Compute Engine offers x86_64 there.
And production would stay in the maintainer's personal Gandi account. The
lower price and the proven box keep Gandi as the UAT host.

### 6. A Nairobi VPS (Lineserve, Truehost, Hostnali, HostPinnacle)

**Rejected.** Latency would be lower still, but none of the candidates has
said where its machines are or answered the other pre-sales questions in
[`provider-questions.md`](../project/provider-questions.md). At about $275
a year, hosting-plan §5's estimate for the §2-recommended box, it is not
cheaper either. hosting-plan §5 and `provider-questions.md` stay in the
repository, so revisiting this starts from the existing research.
