# 17. Host UAT on GandiCloud VPS in France

Date: 2026-10-04

## Status

Accepted

## Context

UCESCO accepts the app on a UAT deployment, `deploy.mikono.guilloux.org`.
Production runs on its own box in Kenya
([ADR 0035](0035-host-production-on-a-compute-engine-e2-small-in-johannesburg.md)).

`srv-mikono` (GandiCloud VPS, Paris) is already proven with the production
image. Let's Encrypt issuance works, so port 80 is reachable. HTTP/3
negotiates, so UDP 443 is not filtered. The image runs as `linux/amd64`.
The box was resized from 1 GB to 1 vCPU / 2 GB on 2026-09-06.
`guilloux.org` is registered at Gandi, so domain, DNS and the box are in
one account, the maintainer's personal one.

The box is in France, so any personal data on it has left Kenya
([ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md) rule 3).
It holds a few records created for testing, some of them about real
people, and no real volunteer records.

## Decision

**UAT runs on `srv-mikono`, a GandiCloud VPS in France, only until
production runs in Kenya under ADR 0035. It holds test records only, and
when it ends the box and every copy of its data are destroyed.**

- It runs the production image with the
  [ADR 0010](0010-build-in-ci-and-deploy-by-image-pull.md) deploy path,
  the same one production uses.
- **Real volunteer data is never stored on it.** Never restore or copy a
  production backup onto it, not even to debug. Only records created for
  testing go on it. The few that are personal are a transfer to France
  under ADR 0034 rule 3, which is acceptable only because they are test
  records, few, and short-lived.
- **UAT is temporary.** It ends once production is up in Kenya. It is not
  kept as a standing acceptance environment.
- **Ending UAT destroys everything that holds its data:**
  - the VPS itself;
  - its Docker volumes;
  - its local backups;
  - any off-site copy of them;
  - its DNS record, `deploy.mikono.guilloux.org`.

  The work is tracked in [`decommission-uat-on-gandi.md`](../project/backlog/decommission-uat-on-gandi.md).
- The admin login is `debian`, as
  [`deployment-plan.md`](../project/deployment-plan.md) §3 describes. A
  deploy runs as `deploy`.

## Consequences

- **Positive:** a cheap box, already proven, where UCESCO can test freely
  until production is ready. No real volunteer record can be exposed
  there, and once it is destroyed no test record is left in France.
- **Negative / trade-offs:**
  - Its few personal test records are outside Kenya while it lasts.
  - Domain, DNS and UAT are in the maintainer's personal Gandi account,
    which is acceptable only because the box holds test records and is
    temporary.
  - Once it ends there is no separate acceptance environment. A pass on
    UAT proved the app and the image, not the Kenyan provider's firewall,
    login or disk.
- **Reversibility:** cheap. A box can be rebuilt anywhere with a
  `docker compose pull` and one DNS record, since there is no data to move.

## Alternatives considered

### 1. Share production's box

**Rejected.** Real volunteer data would sit on the same disk as the
environment kept for test records. Two FrankenPHP workers, each with a
256 MB opcache, plus a deploy's overlap would use most of a 2 GB box.

### 2. Keep UAT running after production goes live

**Rejected.** It would keep personal test records in France and a second
bill in the maintainer's account with no end date. Destroying it removes
both.
