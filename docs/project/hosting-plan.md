# Hosting plan

**Last updated:** 2026-09-04

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
remains is the choice among four named candidates (§5, verified against
the providers' own pages on 2026-09-04), and that becomes an ADR once
the five pre-sales questions are answered in writing —
[`provider-questions.md`](provider-questions.md) is the email that asks
them.

## 1. What the architecture forces

These are consequences of decisions already made, not preferences:

| Decision | What the host must accommodate |
| --- | --- |
| FrankenPHP with Caddy embedded | The container **is** the web server and terminates TLS itself. No nginx, Apache, or PHP-FPM in front — and nothing else on the machine may hold ports 80 or 443. |
| SQLite ([ADR 0003](../adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)) | **One machine, one container.** No load balancer, no second replica, no managed database service. All state is one file. |
| Tailwind through AssetMapper | No Node.js runtime **in the production image or on the server**. Node as a dev/test dependency is a separate question and is not excluded (`next-steps.md`, the Panther/Playwright note). |
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

### The name: a subdomain of `guilloux.org` (decided 2026-09-04)

> **Superseded for production on 2026-09-05, still correct for UAT.**
> `deploy.mikono.guilloux.org` is not a throwaway: it is the **UAT
> environment** where UCESCO accepts the app, and it stays. Production
> will run under a **UCESCO subdomain** instead, pending the meeting with
> Nickson in September 2026 — see [`next-steps.md`](next-steps.md).
> `mikono.guilloux.org` was never created and probably will not be. The
> mechanics below still apply to whichever name production ends up with;
> what changes is that the record is UCESCO's to create, not ours, and
> two distinct hostnames never contend for the duplicate-certificate
> budget the throwaway was invented to protect.

A hostname is **required**, not optional — `SERVER_NAME` must be a real
domain or Caddy never asks for a certificate. But it does not have to be
bought: the maintainer already owns `guilloux.org`, so the name is two
DNS records and no money.

- `SERVER_NAME=mikono.guilloux.org` — the real one. An A record at the
  server's IPv4, plus AAAA if the VPS has IPv6.
- `SERVER_NAME=deploy.mikono.guilloux.org` — the throwaway the box is
  *brought up* on, per [`next-steps.md`](next-steps.md) item 0. It exists
  to keep Let's Encrypt's five-duplicate-certificates-per-week budget off
  the real hostname while the first deploy is still error-prone. Delete
  the record once the real name is live.

DNS for `guilloux.org` is at **Gandi.net** (LiveDNS), which changes
nothing technically and helps twice in practice: it has an HTTP API, so
a record can be created or re-pointed from a script rather than a web
form, and both records are plain A/AAAA entries — no CNAME flattening or
proxy layer that would interfere with Caddy's HTTP-01 challenge.

**The cutover is an addition, not a re-point.** `mikono.guilloux.org` is
a *new* A record at the same IP as `deploy.mikono.guilloux.org`, so
there is no propagation window to plan around and no TTL to lower
beforehand: create the record, change `SERVER_NAME` in `deploy.env`,
redeploy, and Caddy issues a second certificate on first request. Delete
the `deploy.` record afterwards.

This is strictly better than the free-subdomain route below: same cost
(nothing), same mechanics, and the namespace stays in a registrar
account the project controls. See §6 for the part this does *not*
settle — `guilloux.org` is the maintainer's domain, not UCESCO's.

### Getting a name without buying one — the fallback

Kept for the case where `guilloux.org` is unavailable or the project
needs a name unattached to any individual before a `.co.ke` exists. The
A record and the real `SERVER_NAME` above are requirements; *paying a
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

> **Decided on 2026-09-05: GandiCloud VPS in France — see ADR 0017.**
> Kenyan hosting is not being pursued for now, and
> [`provider-questions.md`](provider-questions.md) was never sent. The
> evaluation below is kept in full as the ADR's cited evidence and as the
> starting point if the question is ever reopened; note that it ranks
> Nairobi first, which ADR 0017 knowingly overrides on cost and on
> evidence in hand. Read it as research, not as an open question.

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
   residency. Last resort, but no longer an abstraction: the concrete
   candidate is **GandiCloud VPS** (evaluated 2026-09-04, below), and it
   is cheap enough that the ranking now costs real money to obey.

**Not recommended: a European VPS behind Cloudflare.** It looks like the
clever compromise — a Nairobi edge PoP terminating TLS locally kills most
of the handshake cost — but it terminates TLS outside Kenya (reopening
the data transfer question in a worse form), breaks Caddy's HTTP-01
certificate issuance (needing Origin CA or a DNS-01 challenge), and
requires the `trusted_proxies` configuration this app does not have. It
trades a settled question for three unsettled ones.

### Candidates

Verified against each provider's own pricing page on **2026-09-04**
(prices exclude 16% VAT; Truehost's are the three-year billing rate):

| Provider | Plan | Price/month | Spec | Nairobi? |
| --- | --- | --- | --- | --- |
| Lineserve | Cloud Server, built to order | ~2,630 KSh (2 vCPU / 2 GB / 40 GB) | à la carte: 433/vCPU, 306/GB RAM, 25/GB disk, 150/IPv4 | **Stated** — `ke-1a`, Nairobi |
| Truehost | Kenya Cloud VPS 2 | 2,800 KSh | 1 vCPU / 2 GB / 50 GB SSD | **Stated** — Nairobi |
| Truehost | Kenya Cloud VPS 3 | 5,600 KSh | 2 vCPU / 4 GB / 100 GB SSD | **Stated** — Nairobi |
| Hostnali | KE VPS-Plus | 4,360 KSh | 1 vCPU / 2 GB / 40 GB NVMe | **Stated** — Nairobi, KIXP-peered |
| HostPinnacle | SM-VPS 1 | 1,100 KSh | 4 vCPU / 6 GB / 100 GB NVMe | **Not stated — the open question** |

**The earlier reading of HostPinnacle was wrong, and the correction
changes the shape of the decision.** §5 previously treated 1,100 KSh for
4 vCPU / 6 GB as evidence that in-country hosting is cheap enough to
skip the 1 GB minimum. It is not evidence of that, because nothing on
that plan's page says where the machine is. Every provider that *does*
name Nairobi charges five to ten times more per GB of RAM, and the
providers who sell both are explicit about the split — Truehost's
Europe/USA "Cloud VPS 1" is 788 KSh for 2 GB against 2,800 KSh for the
same 2 GB in Nairobi; Hostnali's international Starter is 1,480 KSh for
8 GB against 4,360 KSh for 2 GB in Nairobi. HostPinnacle's number sits
squarely in the first column. Its VPS marketing page lists thirteen
African countries as "locations", which reads as an SEO list rather than
thirteen datacentres.

So the price question is not "which Kenyan provider is cheapest" but
**"is this plan Kenyan at all"** — and that is the same question as §5's
data-residency argument, asked about a price. It is question 1 below for
that reason.

The realistic cost of the §2 recommended 2 vCPU / 2 GB box **in Nairobi**
is 2,600–3,000 KSh/month plus VAT, roughly $22–24/month or **$260–290 a
year** — not the ~$100/year figure used elsewhere in these documents.
Two consequences: the recommended size is still affordable, but it is a
real line item worth naming to UCESCO before committing; and the
`.co.ke` domain argument in §6 gets stronger, not weaker, since a few
hundred shillings a year against ~$275 is rounding error.

**Lineserve and Hostnali are new to this list** and were not considered
in the 2026-09-01 review. Both publish answers to three of the five
questions below on their own pages — KVM, full root, native IPv6 /64,
and in Lineserve's case on-demand snapshots and scheduled backups —
which is itself a maturity signal that HostPinnacle and Truehost do not
give. Lineserve's per-unit pricing also means the box can be sized to
§2 exactly rather than to a plan tier.

### Five questions to ask before paying

Each maps to a hard requirement above, so ask them of any candidate —
most answers are not on the pricing page. Get them **in writing**; the
answer to a pre-sales email is also a sample of the support
responsiveness §5 asks you to evaluate.

1. **Which datacentre is this plan physically in?** The whole of §5
   rests on the answer. Ask for the facility, not the country — a
   Nairobi answer should be able to name iColo, PAIX, or Safaricom, and
   a provider that will not say is answering.
2. **KVM or OpenVZ?** An OpenVZ VPS does not run Docker properly.
   Require KVM (§2).
3. **Is the VPS unmanaged?** One shipped with cPanel/CloudLinux will
   fight Docker for ports 80 and 443 (§1).
4. **Is UDP traffic on port 443 filtered, inbound and outbound?** Many
   hosts block UDP by default as an anti-DNS-amplification measure.
   Without it there is no HTTP/3 (§3). The
   `curl -sI --http3` check in [`deployment-plan.md`](deployment-plan.md)
   §5 is how you confirm the answer was true.
5. **Are snapshots offered, and has anyone restored one?** The second
   half is the point — a snapshot feature nobody has exercised is worth
   what an untested backup is worth (§4). Ask, too, how long a restore
   takes and whether it can be self-served from the panel at 2am.

**Not a sixth question: an OpenStack (or any other) provisioning API.**
It is tempting because it looks like the mark of a real cloud, but it
maps to no requirement in §1–§4. This is one machine, one container and
one SQLite file: the entire infrastructure lifecycle is *create the VPS
once*, *snapshot it*, *restore it*, and *maybe resize it in a year* —
four panel clicks, not a fleet. Deployment itself is `pull` + `up -d`
over SSH ([ADR 0010](../adr/0010-build-in-ci-and-deploy-by-image-pull.md)),
which no infrastructure API touches, and the one thing genuinely worth
scripting — DNS — is Gandi's LiveDNS API, not the host's (§3).

Its real value is as **evidence, not capability**: a provider exposing an
OpenStack-compatible API is almost certainly running genuine KVM with
self-service snapshots, console access, rebuild-from-image and proper
IPv6 allocation — which are the maturity criteria above. So treat it as
a tie-breaker that corroborates the answers to questions 2 and 5, never
as a filter. Making it a requirement would eliminate candidates on a
criterion this architecture cannot spend.

A sixth question, if IPv6 is not already on the page: **is IPv6
provided?** It is
one of the maturity criteria above and often the weak point of Kenyan
VPS offerings. Lineserve, Hostnali and HostPinnacle all publish a /64;
Truehost does not say.

The email that asks all of this is drafted in
[`provider-questions.md`](provider-questions.md) — send it to all four
and compare the replies side by side.

### The European candidate, named: GandiCloud VPS

Evaluated 2026-09-04 against the five questions. It matters because it
turns tier 3 of the ranking from a shrug into a priced alternative, and
because `guilloux.org` is already at Gandi (§3) — one account, one
invoice, one support channel, DNS and server together. For a project
with one maintainer that is a real operational simplification, not a
rounding error.

**Where it passes cleanly.** Unmanaged with full root and no bundled
control panel, so nothing is holding ports 80 or 443 on delivery (§1).
IPv4 **and** IPv6 on every plan. The platform is OpenStack with the
public API exposed and Gandi documents deploying container
infrastructure over it, which is exactly the corroboration the
"not a sixth question" note below says an OpenStack API is good for —
it means KVM in practice, even though Gandi never writes the word.
Gandi owns and operates its own datacentres rather than reselling rack
space, which is a stronger chain-of-custody claim than any Kenyan
candidate has made.

**And it is not the Cloudflare anti-pattern.** A plain VPS in France is
not "a European VPS behind Cloudflare": there is no proxy terminating
TLS, Caddy's HTTP-01 challenge works untouched, and
`framework.trusted_proxies` stays unset. It carries one honest cost, not
three unsettled ones.

**Where it is weak.**

- **France, and no more precise than that** — the offer does not let you
  choose a location and the datacentre is named only as `FR-SD6`. Fine
  for chain of custody, useless if anyone ever wants a named facility on
  a form.
- **V-R1 (1 CPU / 1 GB, €6/month incl. VAT) is §2's *minimum*, not its
  recommendation.** FrankenPHP in worker mode with
  `opcache.memory_consumption=256` sits at 300–600 MB, and a deploy
  briefly runs the old and new containers at once. It will probably work
  and it will have no headroom. **Price the 2 GB tier before comparing
  anything** — the €6 figure is not the number to put next to Nairobi's
  2,600–3,000 KSh.
- **Snapshots are contradicted by Gandi's own page** (spec table says
  manual and automatic, FAQ still says "coming soon"). Live
  documentation exists, so the FAQ is probably stale — but this is
  question 5, and it is the one where an answer in writing matters. Note
  it is a maturity signal here rather than a dependency: §4's backups are
  `backup-db.sh` plus an off-site copy, never the provider's snapshots.
- ~~UDP on 443 unpublished~~ — **answered empirically on 2026-09-05:
  Gandi does not filter it.** Caddy advertised `alt-svc: h3=":443"` and
  Chrome negotiated `h3` against `deploy.mikono.guilloux.org` on the
  second load. Question 4 no longer needs asking of this provider; it
  still does of the four Kenyan ones.
- **Support is email-only, six days a week, 08:00–24:00 Paris.** That is
  better than it sounds from Nairobi — roughly 09:00–01:00 East Africa
  Time — but the maturity criterion above is specifically "when the
  machine is down at 2am", and 2am Nairobi falls outside it.
- **Architecture is not documented anywhere, and there is positive
  reason to think ARM exists in the catalogue.** Checked on 2026-09-04:
  Gandi's VPS documentation, its FAQ, the GandiCloud API reference, the
  `gandi.net/cloud` product page and the *Numérique de Confiance*
  catalogue entry all say nothing about CPU architecture. The one source
  that does — Cloud Mercato's Public Cloud Reference — states the offer
  covers "both x86 architecture and arm architecture", while admitting
  it holds no standardized samples. So this is **unconfirmed, not
  benign**: §2's amd64-only image will not start on arm64, and §2's own
  warning is precisely about an arm instance "chosen on price and then
  found unable to start the container".

  Two things keep it from being a blocker. It is a *selection* risk, not
  a compatibility one — if both architectures are offered you simply
  pick the x86 flavour, and `uname -m` on first boot settles it in one
  second (now the first line of
  [`deployment-plan.md`](deployment-plan.md) §9). And GandiCloud VPS is
  OpenStack with the public API exposed, so `openstack flavor list` and
  the images' `architecture` property answer it definitively the moment
  an account exists — the API earning its keep as evidence exactly as
  described below. Ask sales before paying; verify on the box after.

  The permanent fix, if this ever bites twice, is
  `platforms: linux/amd64,linux/arm64` in
  [`build-image.yml`](../../.github/workflows/build-image.yml) — §2 notes
  it roughly doubles build time, which is not worth paying today.

**The verdict is not technical.** GandiCloud VPS clears §1–§4 on
everything except a snapshot answer and two unasked questions. At
roughly €72/year against 2,600–3,000 KSh/month (~$275/year) in Nairobi,
it is about **a third of the cost** — so the choice it forces is the one
§5 has been circling all along, stated plainly:

> Pay ~$275/year to host in Kenya and never have to document a
> cross-border transfer, or pay ~$78/year to host in France and own that
> paperwork.

Kenya's DPA Part VI permits transfer abroad with appropriate safeguards
or consent, and France is about the easiest jurisdiction in the world to
argue a safeguard for. So the Gandi option is *defensible*, not
disqualified — but it converts a settled question into an ongoing
obligation that a small NGO with no DPO has to actually own. That is a
budget-and-governance decision for UCESCO, not an architecture decision,
and it is the sentence to put in front of them.

One thing it makes worse, and it is worth saying out loud: the domain is
already in the maintainer's personal Gandi account (§6). Putting the
server there too concentrates the name, the DNS and the machine in one
individual's account. It does not change the technical picture; it does
double the governance dependency §6 already flags.

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
- ~~**Provider and region**~~ — **closed on 2026-09-05 by ADR 0017**:
  GandiCloud VPS in France, with the Kenyan shortlist not pursued. The
  evaluation that led there is kept in §5 as that ADR's evidence.
- **Domain name — narrowed, not closed.** The DuckDNS question is
  settled and settled cheaply: `mikono.guilloux.org` (§3) costs nothing,
  keeps the namespace in an account the project controls, and needs no
  registrar decision from UCESCO to get the pilot onto a real
  certificate. What it does *not* settle is whose name it is.
  `guilloux.org` belongs to the maintainer, not to UCESCO — so the app
  is reachable only as long as one individual keeps renewing a personal
  domain, which is a governance dependency rather than a technical one,
  and it is invisible until the day it matters. That is the same
  objection §3 raised against DuckDNS, weaker in degree but not in kind.
  The coherent end state is a UCESCO-held name, and a `.co.ke` at a few
  hundred shillings against the hosting bill (§5) is rounding error.

  **This is now moving, and in the right direction.** Production will run
  under a **UCESCO subdomain** rather than under `guilloux.org` at all —
  pending feasibility, to be settled with Nickson, UCESCO's technical
  contact, in September 2026 (see [`next-steps.md`](next-steps.md)).
  `deploy.mikono.guilloux.org` stays as the UAT environment, so the
  maintainer's personal domain keeps serving the box UCESCO tests
  against, not the one holding their volunteers' data. That retires the
  domain half of this dependency; the VPS itself remains in a personal
  Gandi account, which is the half still open, and both are recorded in
  ADR 0017's Consequences.
- **SQLite journal mode.** The database runs in SQLite's default
  rollback-journal mode. Switching to WAL (plus a `busy_timeout`) would
  make concurrent readers and a writer coexist far more gracefully, and
  is a one-time `PRAGMA` on the file. Not needed at one user — ADR 0003
  already flags single-writer concurrency as the thing to revisit when a
  second `User` account appears, and this is the cheapest first move
  when it does.
