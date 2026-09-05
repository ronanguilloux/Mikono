# 17. Host production on GandiCloud VPS in France, not in Kenya

Date: 2026-09-05

## Status

Accepted

## Context

[ADR 0003](0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)
deferred hosting out of v0.1's scope, and
[ADR 0010](0010-build-in-ci-and-deploy-by-image-pull.md) settled *how*
the image reaches a server while explicitly leaving "the provider and
region" open, pending latency measurements from Nairobi and Mombasa.
[`docs/project/hosting-plan.md`](../project/hosting-plan.md) has been the
living answer-in-progress since: §1–§4 fix what the machine must provide,
and §5 ranks Nairobi first, South Africa second, Europe last, with four
named Kenyan candidates and five pre-sales questions to put to them.

Two things happened that make the question answerable now rather than
after that email round.

**The Kenyan evidence was never gathered.** The pre-sales email in
[`provider-questions.md`](../project/provider-questions.md) was drafted on
2026-09-04 and never sent, so no Kenyan provider has answered question 1
("which datacentre is this plan physically in?") — and hosting-plan §5 is
explicit that a provider who will not say has answered. HostPinnacle's
price is five to ten times cheaper per GB than every provider that names
Nairobi, which is what European stock costs, and its page does not say
where the machine is.

**The European candidate was tested on real hardware instead.** The pilot
`srv-mikono` — GandiCloud VPS V-R1, Debian 13 trixie, Paris SD6 — was
provisioned on 2026-09-04 and deployed on 2026-09-05, serving
`https://deploy.mikono.guilloux.org` with no real data on it (see
[`done.md`](../project/done.md), 2026-09-05). Three of hosting-plan §5's
open questions were closed by measurement rather than by a sales desk:
Let's Encrypt issuance succeeded, which also proves port 80 is reachable
inbound; Chrome negotiated `h3`, so Gandi does not filter UDP 443
(question 4); and the image pulled and ran as `linux/amd64`, which closes
§5's flagged "unconfirmed, not benign" ARM-architecture risk **for this
instance**. Against that, the four Kenyan candidates have answers to none
of the five questions.

The cost gap is the other half. Roughly €72/year for the V-R1 against
2,600–3,000 KSh/month plus VAT (~$260–290/year) for the §2-recommended
box in Nairobi — about a third. And `guilloux.org` is already at Gandi
(§3), so the domain, the DNS and the server would sit in one account with
one invoice and one support channel.

What that buys is not free, and hosting-plan §5 states the bill exactly:

> Pay ~$275/year to host in Kenya and never have to document a
> cross-border transfer, or pay ~$78/year to host in France and own that
> paperwork.

## Decision

**Mikono's production runs on GandiCloud VPS in France, and no Kenyan
hosting is pursued for now.**

Concretely:

- The pilot machine `srv-mikono` (GandiCloud VPS, Paris SD6) becomes the
  production host. Nothing in
  [ADR 0010](0010-build-in-ci-and-deploy-by-image-pull.md)'s model
  changes: CI still builds the image, the server still only pulls, and
  the pair of compose files is still the deployment unit.
- The four Kenyan candidates in hosting-plan §5 are not contacted, and
  the pre-sales email in
  [`provider-questions.md`](../project/provider-questions.md) is not
  sent. Both documents stay in the repository as the evidence this
  decision was taken against, so reopening it starts from research rather
  than from scratch.
- The off-site backup destination (hosting-plan §4,
  [`deployment-plan.md`](../project/deployment-plan.md) §7) follows the
  server into Europe rather than waiting on the region question — that
  question is now answered.
- "For now" is meant literally, not as a hedge. See *Reversibility*.

This ADR **overrides hosting-plan §5's ranking**, which put a Nairobi VPS
first. It does so on two grounds: cost, at roughly triple, and
evidence-in-hand, since one candidate has been proven on a real box and
the other four have answered nothing.

## Consequences

- **Positive:** production exists, on a machine already proven to run
  this exact image — certificate issuance, HTTP/3, migrations at
  entrypoint and a styled login page all verified on the real host rather
  than assumed. The recurring bill drops from ~$275/year to ~€72/year,
  which for a small NGO's internal tool is the difference between a line
  item worth a conversation and one that is not. Domain, DNS and server
  live in one account, so there is one provider to chase and one invoice
  to renew — a genuine simplification at one maintainer. Nothing about
  ADR 0010's deployment model needs revisiting, and the runbook in
  `deployment-plan.md` §3 has already been corrected by a real run.

- **Negative / trade-offs:**
  - **The cross-border transfer question is now an ongoing obligation
    rather than a settled one.** Mikono holds personal data about Kenyan
    volunteers; Kenya's Data Protection Act 2019 Part VI permits transfer
    abroad with appropriate safeguards or consent, and France is an easy
    jurisdiction to argue a safeguard for — so this is defensible, not
    disqualified. But somebody at UCESCO has to actually own that
    documentation, and UCESCO has no DPO. This is a budget-and-governance
    matter for UCESCO, it is not a technical task, and it is outstanding
    as of this ADR. **This is not legal advice**; it is a record of a
    known obligation and of who has to answer it.
  - **Governance concentration.** The domain, the DNS and now the
    production server all sit in one individual's personal Gandi account
    — the maintainer's, not UCESCO's. hosting-plan §6 already flagged the
    domain half of this; this decision doubles it. The app is reachable
    for exactly as long as one person keeps renewing and paying for a
    personal account.
  - **Support hours do not cover a Nairobi night.** Gandi's support is
    email-only, six days a week, 08:00–24:00 Paris — roughly 09:00–01:00
    East Africa Time. hosting-plan §5's maturity criterion is
    specifically "when the machine is down at 2am", and 2am in Nairobi
    falls outside it.
  - **The box is undersized on purpose.** V-R1 is 1 CPU / 1 GB, which
    hosting-plan §2 calls its *minimum*, not its recommendation, with a
    2 GB swapfile doing the missing gigabyte's work. Resizing to the 2 GB
    tier is outstanding and should happen before real volunteer data
    lands on it.
  - **Snapshots are unverified.** Gandi's own documentation contradicts
    itself (the spec table says manual and automatic, the FAQ still says
    "coming soon"), and nobody has restored one. This is a maturity
    signal, not a dependency: hosting-plan §4 is explicit that backups
    are `scripts/backup-db.sh` plus an off-site copy, never the
    provider's snapshots.
  - **Latency is worse, by about 130 ms per round trip** than a Nairobi
    host would be. hosting-plan §5 argues at length why that is not the
    deciding factor: the mobile access leg adds 40–100 ms regardless and
    varies more, and this is server-rendered Twig over Turbo Drive at a
    handful of navigations per session, so the saving is well under a
    second across an entire working session. Real, measurable, and not
    the difference between a usable and an unusable app — but it is a
    cost, and it is being paid.
  - **The off-site backup copy inherits this decision.** That copy is the
    whole volunteer database in one file, so it is the artifact the
    residency question matters most about; it now follows the server into
    Europe rather than being chosen independently.

- **Reversibility:** genuinely cheap, which is what makes "for now" an
  honest framing rather than a hedge. Migrating to a Kenyan or South
  African VPS is a `docker compose pull` on the new box, one SQLite file
  moved, and one DNS record re-pointed — the restore drill in
  [`deployment-plan.md`](../project/deployment-plan.md) §7 already
  rehearses most of it, and ADR 0010's model means the new host needs no
  build toolchain, just Docker. The research this ADR declines to act on
  (hosting-plan §5's candidates table, the five questions,
  `provider-questions.md`) is deliberately left intact so the move starts
  from where this decision stopped.

## Alternatives considered

### 1. A Nairobi VPS (Lineserve, Truehost, Hostnali, HostPinnacle)

**Rejected.** This was hosting-plan §5's *first* choice, and this ADR
overrides that ranking knowingly. Two reasons, both concrete. It costs
roughly triple: 2,600–3,000 KSh/month plus VAT for the §2-recommended box
against €72/year at Gandi. And there is no evidence to choose on — the
pre-sales email in
[`provider-questions.md`](../project/provider-questions.md) was drafted
and never sent, so not one of the four has answered which datacentre its
plan is physically in, whether virtualization is KVM, whether UDP 443 is
filtered, or whether anyone has ever restored one of its snapshots.
Choosing an unmeasured Kenyan provider at three times the price, over a
box that has already pulled and run this exact image, is paying a premium
for an unknown. The candidates table and the questions stay in the
repository precisely so this can be revisited on evidence.

### 2. South Africa (Vultr Johannesburg or AWS `af-south-1`)

**Rejected.** It is hosting-plan §5's tier 2 and the natural hedge, but
it buys latency only — roughly 60–90 ms from Nairobi instead of 120–160 —
while reopening the identical cross-border transfer question this ADR
already has to answer, at a higher price than Gandi. Paying more to
answer the same paperwork is the worst cell of the table. There is also a
hard technical caveat on the cheaper half of that option: AWS's
inexpensive `af-south-1` instances are Graviton/arm64 (`t4g.*`), and
[`build-image.yml`](../../.github/workflows/build-image.yml) publishes
amd64 only, so those instances cannot start the container as built
without adding `platforms: linux/amd64,linux/arm64` and roughly doubling
build time.

### 3. A European VPS behind Cloudflare

**Rejected.** hosting-plan §5 names this as an explicit anti-pattern, and
it stays rejected here. It looks like the clever compromise — a Nairobi
edge PoP terminating TLS locally would kill most of the handshake cost —
but it terminates TLS outside Kenya anyway, in a *worse* form for the
data-transfer question than a plain French VPS; it breaks Caddy's HTTP-01
issuance, which is the mechanism the pilot just proved works untouched;
and it needs the `framework.trusted_proxies` configuration this app does
not set, without which the app sees the proxy's IP and mis-detects the
scheme. It trades one settled question for three unsettled ones. A plain
VPS in France is not this: no proxy, no TLS termination in between, one
honest cost instead of three.
