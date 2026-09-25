# 17. Host production on GandiCloud VPS in France, not in Kenya

Date: 2026-09-25

## Status

Accepted

## Context

[ADR 0010](0010-build-in-ci-and-deploy-by-image-pull.md) settled how the
image reaches a server; this ADR settles which server.
[`hosting-plan.md`](../project/hosting-plan.md) holds the requirements and
the research: §5 ranked a Nairobi VPS first, South Africa second, Europe
last, with four Kenyan candidates and five pre-sales questions (which
datacentre, KVM or not, UDP 443 filtering, snapshots, support).

The two sides were not equally known:

- **The Kenyan candidates answered nothing.** The pre-sales email in
  [`provider-questions.md`](../project/provider-questions.md) was never
  sent, and the cheapest candidate does not say where its machines are.
- **The European candidate was proven on real hardware.** `srv-mikono`
  (GandiCloud VPS, Paris) runs the production image: Let's Encrypt issuance
  works (so port 80 is reachable), HTTP/3 negotiates (UDP 443 not filtered),
  and the image runs as `linux/amd64`.

Cost: about €72/year at Gandi against roughly $275/year for the
§2-recommended box in Nairobi. `guilloux.org` is already at Gandi, so
domain, DNS and server share one account.

## Decision

**Production runs on GandiCloud VPS in France; Kenyan hosting is not pursued
for now.**

- `srv-mikono` is the production host; ADR 0010's model is unchanged.
- The Kenyan candidates are not contacted. `hosting-plan.md` §5 and
  `provider-questions.md` stay in the repository so reopening starts from
  research.
- The off-site backup destination follows the server into Europe.
- "For now" is literal: moving is cheap (see Reversibility).

## Consequences

- **Positive:** production runs on a machine already proven with this exact
  image, at about a third of the cost, with one provider for domain, DNS and
  server.
- **Negative / trade-offs:**
  - **Cross-border transfer is an ongoing obligation.** Hosting in France
    holds only under the conditions of
    [ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md) rule 3:
    UCESCO keeps proof of safeguards (s.48), and sensitive personal data,
    which includes emergency contacts naming family, also needs the
    volunteer's consent (s.49(1)). This is governance, not a technical task,
    and not legal advice.
  - **Governance concentration.** Domain, DNS and server sit in the
    maintainer's personal Gandi account, not UCESCO's.
  - **Support does not cover a Nairobi night.** Email only, 08:00–24:00
    Paris, six days a week — roughly 09:00–01:00 EAT.
  - **Provider snapshots are not trusted.** Gandi's documentation
    contradicts itself; backups are `scripts/backup-db.sh` plus an off-site
    copy, never snapshots.
  - **About 130 ms more per round trip** than Nairobi. The mobile access
    leg adds 40–100 ms regardless, and a server-rendered Turbo app makes a
    handful of navigations per session, so the cost is real but well under a
    second per working session.
  - **The off-site backup copy** — the whole database in one file, the
    artifact residency matters most for — inherits this decision.
  - Sizing the box is tracked in
    [`production-vps-sizing`](../project/backlog/production-vps-sizing.md).
- **Reversibility:** genuinely cheap. Moving to a Kenyan or South African
  VPS is a `docker compose pull` on the new box, one SQLite file copied and
  one DNS record changed; the restore drill in `deployment-plan.md` §7
  rehearses most of it.

## Alternatives considered

### 1. A Nairobi VPS (Lineserve, Truehost, Hostnali, HostPinnacle)

**Rejected**, overriding hosting-plan §5's ranking knowingly: roughly three
times the price, and not one candidate has said where its machine is or
answered the other four questions. Paying a premium for an unknown over a
box that already runs this image.

### 2. South Africa (Vultr Johannesburg, AWS `af-south-1`)

**Rejected.** Buys latency only (60–90 ms instead of 120–160) while reopening
the identical cross-border question at a higher price. AWS's cheap
`af-south-1` instances are arm64, and `build-image.yml` publishes amd64 only.

### 3. A European VPS behind Cloudflare

**Rejected.** Terminates TLS outside Kenya anyway, in a worse form for the
transfer question; breaks Caddy's HTTP-01 issuance; and needs
`framework.trusted_proxies`, which the app does not set.
