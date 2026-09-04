# Hosting plan

**Last updated:** 2026-09-03

What Mikono's architecture requires of a server, and where that server
should be. A living document — it is edited in place as the answers firm
up, unlike [`docs/adr/`](../adr/).
[ADR 0003](../adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)
deliberately left hosting out of v0.1's scope; this file is where that
deferred question gets answered. The *how to ship it* half lives in
[`deployment-plan.md`](deployment-plan.md).

**Still open:** which provider. The *class* of machine is settled — a
KVM VPS with root, in Nairobi if a provider clears the bar in §5 — and
shared hosting is ruled out entirely, for reasons set out there. What
remains is the choice between two named candidates, and that becomes an
ADR once someone has run the measurements from Nairobi and Mombasa.

## 1. What the architecture forces

These are consequences of decisions already made, not preferences:

| Decision | What the host must accommodate |
| --- | --- |
| FrankenPHP with Caddy embedded | The container **is** the web server and terminates TLS itself. No nginx, Apache, or PHP-FPM in front — and nothing else on the machine may hold ports 80 or 443. |
| SQLite ([ADR 0003](../adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)) | **One machine, one container.** No load balancer, no second replica, no managed database service. All state is one file. |
| Tailwind through AssetMapper | No Node.js runtime, in the image or on the host. |
| No Messenger, Mercure, Redis, or mailer | No message broker, no cache server, and **no SMTP relay** to arrange. There is no password-reset-by-email flow: accounts are created over SSH with `bin/console app:user:create`, so shell access is part of running this app. |
| Migrations run at container start ([`docker-entrypoint.sh`](../../frankenphp/docker-entrypoint.sh)) | Deployment is self-migrating. Convenient, but it means a schema rollback is a restore-from-backup, not a `docker compose` flag. |

## 2. Sizing

**Runtime only** — the image is built in CI and pulled, per
[ADR 0010](../adr/0010-build-in-ci-and-deploy-by-image-pull.md):

- **Minimum: 1 vCPU / 1 GB RAM / 10 GB SSD.** Not an optimistic figure.
  FrankenPHP in worker mode plus `opcache.memory_consumption=256`
  ([`10-app.ini`](../../frankenphp/conf.d/10-app.ini)) sits around
  300–600 MB, and the workload is one Volunteer Manager logging
  activities.
- **Recommended: 2 vCPU / 2 GB**, purely for headroom during a deploy
  (old and new container briefly coexisting) and for `composer`-free
  console commands.
- **Disk** is dominated by images, not data: the production image is a
  `debian:13-slim` base plus the app, and the SQLite database is
  measured in megabytes. 10 GB leaves room for several image versions
  and a local backup rotation.

**Software prerequisites:** Linux **x86_64**, Docker Engine, and the
Compose v2 plugin at **2.30 or newer** — [`compose.yaml`](../../compose.yaml)
uses the long-form `ports:` syntax with a `name:` key, which older
Compose versions reject.

**x86_64 and not arm64, because of how the image is built.**
[`build-image.yml`](../../.github/workflows/build-image.yml) passes no
`platforms:` to `docker/build-push-action`, so CI builds for the runner's
architecture and publishes that alone — amd64. The application source is
architecture-neutral; *the image the server pulls* is not. An arm64 host
(AWS Graviton `t4g.*`, Ampere, an Apple-silicon test box) needs
`platforms: linux/amd64,linux/arm64` added to that workflow first, which
also roughly doubles build time. Not worth paying for while the shortlist
in §5 is x86 anyway — recorded so an arm64 instance is never chosen on
price and then found unable to start the container.

**If you build on the server instead** (the fallback path, not the
recommended one): 4 GB RAM and ~20 GB disk. Note this used to be worse:
the Panther recipe installed Chromium in `frankenphp_base`, which the
production builder stage inherits, so every production build downloaded
a browser that never shipped in the final image. That block now lives in
the `frankenphp_dev` stage only.

## 3. Network, DNS, TLS

- A domain with an **A record** (plus AAAA if the VPS has IPv6) pointing
  at the server.
- **Ports: 80/tcp, 443/tcp, 443/udp.** Port 80 is not optional — Caddy's
  ACME HTTP challenge needs it even though the app itself only serves
  HTTPS. 443/udp carries HTTP/3, which is the one piece of this stack
  that genuinely helps on a lossy mobile network.
- **`SERVER_NAME` must be the real domain.** It defaults to `localhost`
  ([`compose.yaml`](../../compose.yaml)); left at the default, Caddy
  never requests a certificate and the site serves an untrusted one.
- **The `caddy_data` volume must persist.** It holds the issued
  certificates and the ACME account key. Losing it on every deploy means
  re-issuing on every deploy, and Let's Encrypt's rate limits will
  eventually refuse.
- **No reverse proxy is configured for.** `framework.trusted_proxies` is
  unset, so putting a CDN or another proxy in front would make the app
  see the proxy's IP and mis-detect the scheme. That is a config change,
  not a drop-in.

### Getting a name without buying one

The A record and the real `SERVER_NAME` above are requirements; *paying a
registrar* is not. A free subdomain satisfies both:

- **DuckDNS** — the pick of the three: it serves A **and** AAAA records
  and has an update API, so the record can be re-pointed from a script.
- **sslip.io** — no registration at all; the hostname encodes the IP.
- **FreeDNS** (afraid.org).

Set `SERVER_NAME=mikono.duckdns.org` and everything above works
unchanged: Caddy answers the ACME HTTP-01 challenge on port 80, issues a
real certificate, and `caddy_data` persists it across deploys. Nothing in
[`compose.yaml`](../../compose.yaml) or
[`deployment-plan.md`](deployment-plan.md) §4 changes — it is a different
value for a variable that already exists.

**Rejected: a certificate on the bare IP address.** Let's Encrypt issues
those only under the short-lived 6-day profile, and it requires a
`SERVER_NAME` that is not a domain — contradicting the `SERVER_NAME` rule
above. It is written down here so it does not get rediscovered as a
shortcut. The domain question does not end at a free subdomain either;
see §6.

## 4. Storage and backup

All persistent state is two Docker named volumes:

- `db_data` → `/app/var/data/data_prod.db` — the entire application
  database.
- `caddy_data` → certificates.

Everything else (`var/cache`, `var/log`) is disposable. One consequence
worth knowing: **sessions live in `var/cache/prod/sessions`, which is not
a volume**, so every redeploy signs users out. At one user that is a
shrug; it is recorded here so nobody rediscovers it as a bug.

**Backups** use [`scripts/backup-db.sh`](../../scripts/backup-db.sh),
which snapshots the live database with SQLite's `VACUUM INTO` through
`pdo_sqlite` — no downtime, and no `sqlite3` binary needed (the slim
production image has none). It verifies the snapshot with
`PRAGMA integrity_check` before copying it out.

**An off-site copy inherits §5's residency question.** The obvious
destinations — Cloudflare R2, Backblaze B2 — are attractive because
egress is free, and neither has a Kenyan region. But that copy is the
whole volunteer database in one file: every name, contact detail and work
record the app holds, concentrated in the single artifact most worth
protecting. Shipping it abroad reopens exactly the data-transfer question
§5 picks the server's region to close. Wherever the server lands, the
backup destination has to answer the same question —
[`deployment-plan.md`](deployment-plan.md) §7 is where the destination is
actually chosen.

A backup that has never been restored is a hope, not a backup. The
restore drill is written out as steps in
[`deployment-plan.md`](deployment-plan.md) and should be run once before
the app carries real data, and after any change to the storage setup.

## 5. Where to host

The stated reason for hosting in Kenya is network latency for users in
Kibera and Mombasa. That instinct points at the right country for the
wrong reason, so it is worth setting out honestly.

### Latency is a weaker argument than it looks

- Nairobi to Europe is roughly **120–160 ms** round-trip over the
  subsea routes serving Kenya; a server in Nairobi reached through a
  local peering point is roughly **5–30 ms**. So the theoretical saving
  is on the order of 130 ms per round trip.
- But the users are on mobile networks. The access leg — phone to the
  operator's network — adds **40–100 ms** regardless of where the
  server sits, and it varies far more than the international leg does.
- And this app is small: server-rendered Twig pages over Turbo Drive,
  a handful of navigations in a working session. One page view costs
  about one round trip once the connection is warm; the expensive part
  is the first connection's TLS handshake, which is a few round trips
  and happens once.

Put together, moving from Europe to Nairobi plausibly saves **well under
a second across an entire session** — real, but not the difference
between a usable and an unusable app. Anyone justifying the move on
latency alone should expect to be disappointed by the stopwatch.

### The stronger argument is data protection

Mikono holds personal data about Kenyan volunteers — names, contact
details, and a record of where and when they worked. Kenya's **Data
Protection Act 2019** places conditions on transferring personal data
outside the country. Hosting in Kenya makes that question disappear
instead of requiring an answer.

That is a better reason to land in Nairobi than milliseconds, and it is
what rules out the tempting Cloudflare shortcut further down.

### Shared hosting is excluded by construction

There is no Kenyan shared-hosting plan to go looking for, and the reason
has nothing to do with the Kenyan market. This architecture rules out
shared hosting by construction — four decisions already recorded above do
it, and a 2,500 KSh/year cPanel plan satisfies none of them:

| Requirement | Why shared hosting cannot meet it |
| --- | --- |
| FrankenPHP embeds Caddy (§1) | The container **is** the web server and terminates TLS itself. On shared hosting, ports 80 and 443 belong to the host — never to you. |
| Docker Engine + Compose 2.30+ (§2) | No cPanel shared plan offers it. |
| 443/udp for HTTP/3 (§3) | Not exposed on shared hosting — and §3 identifies HTTP/3 as the one part of this stack that genuinely helps on a degraded mobile network. |
| Shell access for `app:user:create` (§1) | No SMTP and no password-reset-by-email means accounts are created over SSH. Not negotiable. |

So the target is a **KVM VPS in Nairobi with root access**. Everything
below is about choosing which one.

### The real cost of in-country hosting

Latency is the argument for; operational maturity is the argument
against. Evaluate a candidate Kenyan provider on these specifically,
rather than assuming them:

- Automated snapshots and a documented restore path.
- IPv6, and an API or CLI for automation.
- Upstream network and power stability; published maintenance windows.
- Support responsiveness when the machine is down at 2am.
- Whether the price per GB of RAM is competitive enough that the
  recommended 2 GB is affordable.

### Ranking

1. **Nairobi VPS** — first choice. Best latency, and it settles the data
   protection question. Nairobi hosts carrier-neutral datacentre
   capacity (iColo/Digital Realty), so credible providers exist; the
   selection criteria above are how you tell them apart.
2. **South Africa** — roughly 60–90 ms from Nairobi, with a
   substantially more mature provider ecosystem (major clouds have South
   African regions). The fallback if no Kenyan provider clears the bar
   above; concretely, **Vultr Cloud Compute in Johannesburg** or **AWS
   `af-south-1` in Cape Town**, both of which satisfy §1–§3 without
   argument and answer all four questions below on their public
   documentation. Caveat on the second: AWS's cheap instances in that
   region are Graviton/arm64 (`t4g.*`), which the amd64-only image in §2
   cannot run as built. Reopens the cross-border data question.
3. **Europe** — cheapest and most mature, worst on both latency and data
   residency. Last resort.

**Not recommended: a European VPS behind Cloudflare.** It looks like the
clever compromise — a Nairobi edge PoP terminating TLS locally kills most
of the handshake cost — but it terminates TLS outside Kenya (reopening
the data transfer question in a worse form), breaks Caddy's HTTP-01
certificate issuance (needing Origin CA or a DNS-01 challenge), and
requires the `trusted_proxies` configuration this app does not have. It
trades a settled question for three unsettled ones.

### Candidates

Two Nairobi providers, both advertising unmanaged KVM — which is a claim
to check, not a fact, hence the questions below:

| Provider | Plan | Price | Specification |
| --- | --- | --- | --- |
| HostPinnacle | SM VPS 1 | ~1,100 KSh/month | 4 vCPU / 6 GB |
| Truehost | VPS Kenya | ~1,400 KSh/month | not recorded — ask |

HostPinnacle's plan is three times the 2 GB recommended in §2, which
settles the last of the maturity criteria above: the recommended size is
affordable here, so there is no reason to run this app on the 1 GB
minimum.

Prices and specifications are as quoted on 2026-09-01 and **have not been
verified against the providers' own pages**. Confirm before paying, and
replace the numbers here with what was actually quoted.

### Four questions to ask before paying

Each of these maps to a hard requirement above, so ask them of any
candidate — the answers are rarely on the pricing page:

1. **KVM or OpenVZ?** An OpenVZ VPS does not run Docker properly.
   Require KVM (§2).
2. **Is the VPS unmanaged?** One shipped with cPanel/CloudLinux will
   fight Docker for ports 80 and 443 (§1).
3. **Is UDP traffic on port 443 filtered, inbound and outbound?** Many
   hosts block UDP by default as an anti-DNS-amplification measure.
   Without it there is no HTTP/3 (§3). The
   `curl -sI --http3` check in [`deployment-plan.md`](deployment-plan.md)
   §5 is how you confirm the answer was true.
4. **Is IPv6 provided?** It is one of the maturity criteria above, and
   it is often the weak point of Kenyan VPS offerings.

### Measure before deciding

The latency figures above are estimates from general knowledge of the
region's connectivity. **They must not be treated as findings.** Before the
provider choice is locked into an ADR, run this from a device on the real
network in Nairobi and in Mombasa, on the operator the VM actually uses,
against a candidate host in each region:

```bash
# Round-trip and path quality
mtr --report --report-cycles 50 <candidate-host>

# What the user actually feels: DNS, TCP, TLS, first byte
curl -sS -o /dev/null -w \
  'dns=%{time_namelookup} conn=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total}\n' \
  https://<candidate-host>/
```

Record the results in this file. If the in-country advantage turns out to
be smaller than the provider-maturity cost, that is a legitimate reason
to choose Johannesburg — and the data protection argument then has to be
answered on its own terms rather than assumed away.

## 6. Open decisions

- **The runbook is rehearsed without a second server** (decided
  2026-09-03). An earlier plan put the first deploy on a disposable
  box abroad — outside §5's reasoning, which is entirely about personal
  data — purely to exercise the runbook. That is dropped: the rehearsal
  is available for free as a local dry run of the production stack
  ([`deployment-plan.md`](deployment-plan.md) §10), and the rest of it
  happens on the production box itself, brought up on a throwaway
  DuckDNS hostname with no real data before the real domain is pointed
  at it. See [`next-steps.md`](next-steps.md) item 0 under *Getting it
  hosted*. What the disposable box would have added beyond the rehearsal
  was a control variable — a server known to be good, so a failure could
  only be the runbook's — and that is worth less than it looks here,
  since every provider-specific surprise behind the four questions in
  §5 surfaces on the Nairobi box either way. The consequence to accept
  knowingly: the first real deploy debugs two unknowns at once, the
  runbook and the provider.
- **Provider and region** — narrowed, not settled. Shared hosting is
  eliminated (§5); the shortlist is HostPinnacle SM VPS 1 and Truehost
  VPS Kenya, subject to the four questions and the measurements above.
  Becomes an ADR once decided.
- **Domain name.** A free DuckDNS subdomain (§3) is enough to get a
  pilot onto a real certificate, and it costs nothing. But it hands the
  project's namespace to a third party — for an app holding personal data
  about Kenyan volunteers, on a server chosen largely to keep that data
  in Kenya. The day the Data Protection Act is the leading argument
  rather than a supporting one, a `.ke` domain at a few hundred shillings
  is the more coherent position than free. **This trade-off belongs in
  the Consequences section of the hosting ADR**, not only here.
- **SQLite journal mode.** The database runs in SQLite's default
  rollback-journal mode. Switching to WAL (plus a `busy_timeout`) would
  make concurrent readers and a writer coexist far more gracefully, and
  is a one-time `PRAGMA` on the file. Not needed at one user — ADR 0003
  already flags single-writer concurrency as the thing to revisit when a
  second `User` account appears, and this is the cheapest first move
  when it does.
