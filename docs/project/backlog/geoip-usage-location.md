---
title: Geolocate usage with GeoIP
created: 2026-09-19
source: ronan
status: needs-decision
size: M
priority: later
labels: [ops, security, data]
---

# Geolocate usage with GeoIP

## Why

`/usage` shows which screens get used, but not where the requests come
from: Kibera, Mombasa, or abroad. Ronan wants that location, looked up
locally with [MaxMind GeoIP2-php](https://github.com/maxmind/GeoIP2-php)
against the GeoLite2 databases, with no call to an external service.
Mapping staff IP addresses to places is a privacy and data-model choice,
so it needs an ADR before any code, next to
[ADR 0028](../../adr/0028-record-login-attempts-with-identifier-and-ip-for-90-days-admin-only.md).

## Done when

- `geoip2/geoip2` is required, and one reader service opens a database
  path set by configuration.
- A missing database gives an "unknown" location, never an exception.
  CI and `RouteSmokeTest` have no database, which is the same rule as the
  missing access log in
  [ADR 0021](../../adr/0021-read-usage-from-an-in-app-usage-screen-over-the-caddy-access-log.md).
- `/usage` shows requests broken down by location. The ADR decides whether
  `login_attempt` gets a location too, and whether to keep the country or
  the city.
- The `.mmdb` files are **never committed and never baked into the
  image**:
  - `private-unversioned/` is already in `.gitignore`;
  - it also goes into `.dockerignore`, where it is missing today, so a
    local `docker compose build` would copy 74 MB into the image;
  - the GHCR image is public, and the GeoLite2 licence forbids
    redistributing the databases.
- Production gets the files once: `scp` as `deploy@` into a host directory,
  mounted read-only into the `php` container. That step is written down in
  [`deployment-plan.md`](../deployment-plan.md). One copy is enough;
  refreshing it is optional.
- The attribution the GeoLite2 licence requires is shown wherever a
  location appears.
- The decision is recorded as an ADR (via `adr-scribe`).

## Notes & links

- Local copies: `private-unversioned/GeoLite2-City.mmdb` (58.6 MB),
  `GeoLite2-Country.mmdb` (7.3 MB), `GeoLite2-ASN.mmdb` (8.3 MB).
  City or Country is probably enough; ASN (which network or ISP) is
  optional.
- Where the IPs come from: the Caddy access log read by
  `src/Usage/AccessLogReader.php`, and the `login_attempt` table
  (`App\Security\LoginAttemptRecorder`).
- The volumes for the mount are in `compose.yaml` and `compose.prod.yaml`
  (next to `db_data`/`log_data`). In dev, the bind mount already exposes
  `/app/private-unversioned`.
