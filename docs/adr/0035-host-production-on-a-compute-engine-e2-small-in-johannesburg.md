# 35. Host production in Kenya, with a provider that commits in writing to the Data Protection Act 2019

Date: 2026-10-04

## Status

Accepted

## Context

[ADR 0010](0010-build-in-ci-and-deploy-by-image-pull.md) settles how the
image reaches a server. This ADR settles where production runs, what a
provider must commit to before it is chosen, and who holds the contract.
[ADR 0017](0017-host-production-on-gandicloud-vps-in-france.md) covers
the temporary UAT box.

The main force is compliance with Kenya's Data Protection Act 2019 and its
2021 Regulations
([ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md)):

- **A server outside Kenya is a transfer that never ends.** Every day it
  runs, UCESCO must be able to prove appropriate safeguards to the Data
  Commissioner (s.48), and hold each volunteer's consent before their
  sensitive personal data is processed abroad (s.49(1)). Emergency
  contacts name family, and gender is "sex", so both are sensitive (s.2).
  The Data Commissioner may ask for that proof and may prohibit, suspend
  or condition the transfer (s.49(2), (3)).
- **UCESCO is a small NGO with no data protection officer.** Whoever
  carries a transfer file carries it for as long as the server is abroad.
  No one at UCESCO has that role.
- **UCESCO's DPIA needs a simple answer about location.** Keeping the data
  in Kenya (data localisation) settles the question instead of arguing
  it. It also anticipates s.50, under which the Cabinet Secretary may
  require some processing to run on a server in Kenya.
- **A provider's location is not enough on its own.** The DPIA needs the
  provider to stand behind it: a Kenyan company can resell servers in
  Europe, and a plan page can list "locations" without naming a facility
  ([`hosting-plan.md`](../project/hosting-plan.md) §5). Some Kenyan
  providers state publicly that they comply with the Act, which is the
  commitment UCESCO needs from its processor.
- **The controller must hold the hosting contract.** When the server sits
  in the maintainer's personal account, the chain is UCESCO → maintainer
  → provider. When UCESCO is the provider's customer:
  - UCESCO contracts directly with its processor (s.42(2)).
  - Breach notices reach UCESCO directly (s.43).
  - The infrastructure stays with UCESCO if the maintainer leaves.

The technical forces are those of
[`hosting-plan.md`](../project/hosting-plan.md) §1–§3:

- **The container is the web server.** FrankenPHP embeds Caddy and
  terminates TLS itself, so it needs the whole machine's ports, which
  rules out shared hosting and control panels.
- **The image is amd64 only.** `build-image.yml` publishes `linux/amd64`
  (§2).
- **Size.** §2 targets 2 vCPU / 2 GB, because a deploy briefly runs two
  containers side by side. 1 GB was too small; 1 vCPU / 2 GB has worked on
  UAT.
- **Staff accounts are created over SSH** with `app:user:create`, since
  the app sends no email.

## Decision

**Production runs in Kenya, on a VPS from a provider that commits in
writing to complying with the Data Protection Act 2019 and to keeping
the data in Kenya, under an account that UCESCO holds as the customer.
It runs the existing `docker compose` deployment unchanged.**

- **The selection criterion is the provider's written commitment,** given
  to UCESCO before money changes hands, that:
  - it processes UCESCO's data in compliance with the Data Protection Act
    2019, as UCESCO's processor under a contract that meets s.42(2);
  - the server, its disks and any copy the provider makes stay in Kenya,
    in a facility it names.

  A statement on a website is a lead, not the commitment. A provider
  that will not put it in writing is not chosen, whatever its price.
- **Candidates.** Servercore and Skyhost Kenya state that compliance
  publicly. They are candidates whose claims must still be confirmed in
  writing, not chosen providers. Which provider passes is tracked in
  [`choose-kenyan-production-host.md`](../project/backlog/choose-kenyan-production-host.md),
  and the pre-sales questions are in
  [`provider-questions.md`](../project/provider-questions.md).
- **The technical requirements still apply** (hosting-plan §1–§3). A
  provider that meets the criterion but fails one of these is not chosen:
  - a KVM VPS with root access, unmanaged, with no control panel holding
    ports 80 and 443;
  - x86_64 CPUs;
  - Docker Engine with Compose 2.30 or newer, installed on a stock Debian
    or Ubuntu image;
  - inbound `80/tcp`, `443/tcp` and `443/udp` open. Without `443/udp`,
    HTTP/3 silently falls back to HTTP/2 and nothing reports an error,
    and many hosts filter UDP by default, so confirm it. Port 80 must stay
    open for HTTP-01 issuance and renewal;
  - a static public IPv4 address. An address that changes on restart
    leaves the DNS record pointing at nothing;
  - nothing in front of the box: no CDN or proxy such as Cloudflare. It
    breaks HTTP-01 issuance, needs `framework.trusted_proxies`, which the
    app does not set, and would move TLS outside Kenya;
  - 2 vCPU / 2 GB as the target size.
- **UCESCO is the customer:**
  - The account, the contract and the invoices are in UCESCO's name, as
    an organisation.
  - UCESCO signs the processor contract (s.42(2)) and names its own
    data-protection contact for breach notices (s.43).
  - The maintainer gets the access he needs to operate the box. UCESCO
    grants it and can revoke it.
  - **The maintainer never holds the account,** and his card is never its
    payment method. Either makes him the customer and rebuilds the chain
    this ADR removes. If he funds the running cost, how the money reaches
    UCESCO is agreed with UCESCO.
- **Nothing in the app or image changes.** ADR 0010 applies as written:
  - CI builds `frankenphp_prod` and pushes it to GHCR, and the server
    pulls it.
  - Both compose files are passed on every command.
  - `deploy.env` is root-only, and `APP_SECRET` and
    `PASSPORT_ENCRYPTION_KEY` are set at runtime.
  - A deploy is `scripts/deploy.sh`, run over SSH as `deploy`. The admin
    login is whichever user the provider's image sets up: wherever
    [`deployment-plan.md`](../project/deployment-plan.md) §3 says
    `debian`, use that login.
  - FrankenPHP/Caddy terminates TLS itself, with a Let's Encrypt HTTP-01
    certificate.
  - SQLite lives on the `db_data` named volume.
- **Backups are `scripts/backup-db.sh` plus an encrypted off-site copy,
  never provider snapshots.** Snapshots may be used as a convenience, but
  they are never the backup. The off-site destination is preferably in
  Kenya too. If it is abroad, it is a transfer under ADR 0034 rule 3, with
  encryption whose key the destination does not hold as the safeguard,
  and its country is named in the ADR that chooses it.
- **Do not prepay a long term while the provider might still change.** A
  commitment billed after a move turns a cheap migration into a costly
  one.
- **Real volunteer data goes only to this server.** UAT on Gandi holds
  test records only and ends once production runs here (ADR 0017).

## Consequences

- **Positive:**
  - **The production server is no longer a transfer out of Kenya.**
    UCESCO needs no s.48 proof and no s.49(1) consent *because of the
    server*. Both still apply to anything else that leaves Kenya (ADR 0034
    rule 3): UAT in France while it lasts, the off-site backup if its
    destination is abroad, and any third-party service.
  - UCESCO's DPIA can state that the data stays in Kenya, backed by its
    processor's written commitment.
  - UCESCO, the controller, holds the contract with its processor and
    receives breach notices directly. The maintainer can leave without
    taking the infrastructure with him.
  - Round trips from Kenyan networks are shorter than to Johannesburg or
    Paris. hosting-plan §5 warns that this matters less than it looks.
  - Deploys, backups and restores work exactly as documented, because
    nothing in the image or the deploy path changes.
- **Negative / trade-offs:**
  - **It probably costs more.** The Google Cloud plan in Johannesburg was
    about US$17 a month including VAT (Alternative 1). hosting-plan §5
    measured about 2,600–3,000 KSh a month plus VAT for 2 vCPU / 2 GB in
    Nairobi, roughly US$22–24 a month or US$260–290 a year, among
    providers that do not state DPA compliance. A provider that does may
    charge more.
  - **Kenyan providers carry a maturity risk:** snapshots and a tested
    restore path, support out of hours, `443/udp` filtering, and whether
    the datacentre is named at all. The pre-sales questions in
    `provider-questions.md` are how a candidate is checked before UCESCO
    pays.
  - **A written commitment is a claim, not a certification.** UCESCO keeps
    it in its DPIA file. It does not replace UCESCO's own duties under
    ADR 0034.
  - **Production cannot go live until UCESCO opens the account.** The
    contract and the payment are acts only UCESCO can perform.
- **Reversibility:** cheap. Moving to another box means a
  `docker compose pull` there, copying one SQLite file and changing one
  DNS record, and the restore drill in `deployment-plan.md` §7 rehearses
  most of it. A new provider must meet the same criterion, or the move
  reopens the transfer question.

## Alternatives considered

### 1. A Google Compute Engine e2-small in `africa-south1` (Johannesburg)

**Rejected.** It met every technical requirement: x86_64, 2 shared vCPUs
and 2 GB, a reserved IPv4, Debian 13, under a Google Cloud account in
UCESCO's name with the Cloud Data Processing Addendum as processor
contract. It cost about US$14.80 a month before VAT, about US$17 a month
or US$206 a year with Kenyan VAT at 16%, assuming a 1-year committed use
discount. But Google has no region in Kenya, so it is a transfer to South
Africa for as long as it runs. UCESCO would have to keep s.48 proof of
safeguards, including an argument on South Africa's POPIA that no one has
established, and gather s.49(1) consent for emergency contacts and
gender. A small NGO with no data protection officer would own that file
indefinitely, where localisation settles the question for the DPIA. The
other Compute Engine shapes looked at were no better: an e2-micro has
1 GB, too small for a deploy's two containers; the US free tier sends the
data to the US; Belgium and Paris cost the same and are transfers too.

### 2. Cloud Run

**Rejected.** It is a transfer out of Kenya like Alternative 1, and the
app cannot run there as it is: SQLite needs a persistent disk, sessions
are files
([ADR 0020](0020-keep-sessions-on-the-database-volume-in-files.md)), the
`/usage` access log would not be kept
([ADR 0021](0021-read-usage-from-an-in-app-usage-screen-over-the-caddy-access-log.md)),
there is no SSH for `app:user:create`, and a custom domain in
`africa-south1` needs a load balancer of about US$18 a month.

### 3. GandiCloud VPS in France

**Rejected for production; kept for temporary UAT only** (ADR 0017). It is
cheap and already proven with the production image, but it is a transfer
to France, with the same s.48 and s.49(1) burden as Alternative 1. In the
maintainer's personal account it also keeps the UCESCO → maintainer →
Gandi chain. A Gandi account in UCESCO's name would fix the chain but not
the transfer.

### 4. A Kenyan VPS that does not state DPA compliance

**Rejected unless it commits in writing.** Lineserve, Truehost, Hostnali
and HostPinnacle were researched in hosting-plan §5. Lineserve, Truehost
and Hostnali state a Nairobi datacentre; HostPinnacle does not, and its
price suggests a machine abroad. None states compliance with the Act, and
none has answered the pre-sales questions. Any of them that gives the
written commitment above becomes a candidate on the same terms, and its
§5 research is reused.

### 5. An account held by the maintainer

**Rejected.** It is the governance problem this ADR removes, with any
provider. The maintainer would be the customer, UCESCO would have no
contract with its processor, breach notices would reach it second-hand,
and the infrastructure would leave with him.
