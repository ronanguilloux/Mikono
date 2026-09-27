# 17. Host UAT on GandiCloud VPS in France

Date: 2026-09-27

## Status

Accepted

## Context

UCESCO accepts the app on a UAT deployment, `deploy.mikono.guilloux.org`,
which stays up after production goes live. Production runs on its own box,
chosen in
[ADR 0035](0035-host-production-on-a-compute-engine-e2-small-in-johannesburg.md).

`srv-mikono` (GandiCloud VPS, Paris) is already proven with the production
image. Let's Encrypt issuance works, so port 80 is reachable. HTTP/3
negotiates, so UDP 443 is not filtered. The image runs as `linux/amd64`.
The box was resized from 1 GB to 1 vCPU / 2 GB on 2026-09-06.
`guilloux.org` is registered at Gandi, so domain, DNS and the box are in
one account.

## Decision

**UAT runs on `srv-mikono`, a GandiCloud VPS in France, and holds no real
data.**

- It runs the production image with the
  [ADR 0010](0010-build-in-ci-and-deploy-by-image-pull.md) deploy path,
  the same one production uses.
- **No real volunteer data ever reaches it.** Never restore or copy a
  production backup onto it, not even to debug. Because the box holds no
  personal data, it is not a transfer under
  [ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md).
- The admin login is `debian`, as
  [`deployment-plan.md`](../project/deployment-plan.md) §3 describes. A
  deploy runs as `deploy`.

## Consequences

- **Positive:** a cheap box, already proven, where UCESCO can test freely.
  Nothing done there can expose a volunteer.
- **Negative / trade-offs:** UAT and production are with different
  providers. A pass on UAT proves the app and the image, but not Google
  Cloud's firewall, login or disk. Domain, DNS and UAT stay in the
  maintainer's personal Gandi account. That is acceptable only because the
  box holds no personal data.
- **Reversibility:** cheap. The box can be rebuilt anywhere with a
  `docker compose pull` and one DNS record, since there is no data to move.

## Alternatives considered

### 1. Share production's VM

**Rejected.** Real volunteer data would sit on the same disk as the
environment kept empty on purpose. Two FrankenPHP workers, each with a
256 MB opcache, plus a deploy's overlap would use most of a 2 GB box.

### 2. Move UAT to Compute Engine next to production

**Rejected.** It would add a second bill of about $20 a month for a box
with no data, and replace a Gandi box that already works and costs less.
