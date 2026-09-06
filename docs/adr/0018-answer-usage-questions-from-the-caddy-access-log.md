# 18. Answer usage questions from the Caddy access log, not from third-party analytics

Date: 2026-09-06

## Status

Accepted

## Context

The want is real and currently unmet: **which screens get used, which
actions actually get performed, and how the app is used in practice**
rather than how it was imagined. Nothing in the app records this today.
The original ask was Google Analytics, with JavaScript instrumentation
"here and there". The narrative behind it is in
[`docs/brainstorm/06-usage-analytics-cockpit.md`](../brainstorm/06-usage-analytics-cockpit.md).

Three things decide it.

**Half the answer is already being collected, for free.** Caddy writes an
access log for every request
([`frankenphp/Caddyfile`](../../frankenphp/Caddyfile)), and the server's
Docker daemon rotates it at 10 MB × 5 files
([`deployment-plan.md`](../project/deployment-plan.md) §6). Verified
against the running dev container on 2026-09-06: 473 access entries, and
per-screen counts one shell pipeline away. Note the brainstorm's claim
that this is `jq`-able as-is was **wrong** — the lines are Caddy's
console format with a JSON tail, so a bare `| jq` fails on every line.
The recipe below strips the prefix first.

**Every page is behind a login, so every event is an identified staff
member's behaviour**, and a URL like `/volunteers/12/edit` carries a
record identifier. Sending that stream to Google means transferring
behavioural data about named Kenyan staff to a US provider — which
reopens exactly the question
[`hosting-plan.md`](../project/hosting-plan.md) §5 spent the whole
hosting decision closing. At its real size: not a blocker, an obligation
to document. But an obligation nobody has to take on if the data never
leaves the box.

**The app has one user.** GA4's strengths — funnels, audiences,
attribution across large traffic — are precisely what a one-user,
five-area CRUD app has no use for.

## Decision

**Usage questions are answered from the Caddy access log. No third-party
analytics is added to this app, and no analytics `<script>` goes into
`templates/base.html.twig`.**

The recipe, which needs no code, no dependency and no consent
conversation:

```bash
docker compose logs php --no-log-prefix \
  | grep 'handled request' \
  | sed 's/.*handled request[[:space:]]*//' \
  | jq -r '.request.uri' | sed 's/?.*//' \
  | grep -Ev '^/(assets|brand)/|favicon' \
  | sort | uniq -c | sort -rn | head -20
```

On the server, the same pipeline with
`docker compose --env-file deploy.env -f compose.yaml -f compose.prod.yaml logs php`.

**What this deliberately cannot answer:** anything *inside* a page —
which filter got used, whether the batch form is preferred over the
single one, where someone abandoned a form. That gap is real, and it is
the only honest case for instrumenting anything.

**Reopen trigger, stated so this does not get re-litigated on a hunch:**
a second regular user, **and** a concrete question the access log cannot
answer. Both, not either. A superseding ADR then picks a tool — and it
starts from Plausible or a self-hosted Matomo, not from GA4.

## Consequences

- **Positive:** zero code, zero dependencies, zero third parties, zero
  data-protection exposure, and nothing new to run or back up beside the
  SQLite file. It works retroactively — the log already holds the last
  five rotations. It also sidesteps the Turbo Drive trap below entirely.
- **Negative / trade-offs:** no in-page behaviour, no funnels, no
  retention. Counts are polluted by asset and health-check traffic unless
  filtered, and the window is however much the 10 MB × 5 rotation holds —
  there is no long-term history. Reading it is a manual pipeline someone
  has to remember, not a dashboard.
- **Reversibility:** cheap. Nothing here forecloses instrumentation
  later; it declines to add it now. Whoever does add it inherits two
  facts recorded here: **Turbo Drive silently breaks the default `gtag`
  snippet** (navigations replace the body without a full page load, so
  `page_view` fires once per session and the cockpit looks broken for
  reasons unrelated to its configuration — page views must fire on
  `turbo:load`, custom events through Stimulus controllers), and the
  measurement ID must be an **environment variable, unset in dev, test
  and CI** because the functional tests assert on rendered page content.

## Alternatives considered

### 1. GA4 with an inline `gtag` snippet, as originally asked for

**Rejected.** Heaviest data-protection cost of the three — behavioural
data about named Kenyan staff to a US provider, with record identifiers
in the URLs — in exchange for strengths a one-user app cannot use. Its
default snippet is also the one Turbo Drive breaks silently.

### 2. Plausible, or a self-hosted Matomo

**Rejected for now, and it is the front-runner if this reopens.**
Cookieless, data out of the US, and entirely sufficient for "which
screens, which actions" at this scale. The cost is either a subscription
or a second service to run, monitor and back up next to the SQLite file —
on a 1 GB VPS that already needs a 2 GB swapfile
([`hosting-plan.md`](../project/hosting-plan.md) §2). Not worth it before
there is a question the access log cannot answer.

### 3. A small in-app event table in SQLite

**Rejected.** It keeps the data on the box, which is the right instinct,
but it is the most code of any option here — a table, a migration, a
listener, a report screen — to answer a question one `grep` already
answers for the URLs, and it would still need the Stimulus wiring for the
in-page half. If the reopen trigger fires, this deserves a second look
before a third party does.
