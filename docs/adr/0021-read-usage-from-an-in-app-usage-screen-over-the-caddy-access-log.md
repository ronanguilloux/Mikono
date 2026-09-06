# 21. Read usage from an in-app `/usage` screen over the Caddy access log, plus a narrow client-only event table

Date: 2026-09-06

## Status

Accepted

## Context

This ADR **extends**
[ADR 0018](0018-answer-usage-questions-from-the-caddy-access-log.md); it
does not supersede it. 0018's decision — usage questions are answered
from the Caddy access log, no third-party analytics, no analytics
`<script>` in `templates/base.html.twig` — stands unchanged, and nothing
here adds `gtag`, Plausible or Matomo. 0018's reopen trigger for a
third-party tool (a second regular user **and** a concrete question the
log cannot answer — both) is untouched and still governs.

What changed is that 0018's own stated negative came due:

> Reading it is a manual pipeline someone has to remember, not a
> dashboard.

A five-stage shell pipeline is only available to whoever remembers it.
0018 also flagged its own Alternative 3, a small in-app event table, as
something that "deserves a second look before a third party does". This
ADR takes both up: it puts a reader in front of the log that 0018 chose,
and adds the smallest possible event table for the handful of gestures
that genuinely never reach the server. The narrative is in
[`docs/brainstorm/06-usage-analytics-cockpit.md`](../brainstorm/06-usage-analytics-cockpit.md).

The fact that makes this cheap: **FrankenPHP *is* Caddy.** The same
container and the same process serves HTTP and runs PHP, so the access
log is a plain local file the app can open. No sidecar, no log shipper,
no second service, no new dependency.

## Decision

**Usage is read in the app, at an admin-only `/usage` screen backed by
Caddy's own access log written as a rolling JSON file, plus a
three-column `usage_event` table for the few gestures that never reach
the server.**

Four parts.

**1. The access log becomes a rolling JSON file.** Set through
`CADDY_SERVER_LOG_OPTIONS` in [`compose.yaml`](../../compose.yaml), not
in [`frankenphp/Caddyfile`](../../frankenphp/Caddyfile): that file is
`COPY`'d into the image, so an env var ships with a `git pull` while a
Caddyfile edit would need a CI image rebuild to reach production
([ADR 0010](0010-build-in-ci-and-deploy-by-image-pull.md)). The value is
`output file /app/var/log/access.log { roll_size 10MiB roll_keep 3 }`
plus `format json`. `roll_size`/`roll_keep` are explicit because Caddy's
defaults are 100 MiB × 10 — 1 GB, on a 2 GB VPS. Rolling is Caddy's own
(lumberjack): no logrotate, no dependency.

**2. A new `log_data:/app/var/log` named volume**, so usage history
survives a redeploy. Deliberately *not* `db_data`: that volume is what
`scripts/backup-db.sh` snapshots and what a restore drill replaces, and
request logs have no business riding along. `log_data` is not backed up —
losing it loses usage history, not data.

**3. `src/Usage/AccessLogReader.php` and `/usage`.** The reader streams
the NDJSON with `fgets` + `json_decode` — never `file_get_contents`, the
file is up to 10 MiB — and aggregates per route.
`src/Controller/UsageController.php` renders `/usage` under `ROLE_ADMIN`,
linked from the Settings nav dropdown, reusing `DataTable`,
`ListPaginator` and the `/reports` `SORT_MAP` pattern
([ADR 0011](0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md)).
The aggregate is cached in `cache.app` for 60 seconds.

**URI-to-route-pattern mapping does triple duty** and is the load-bearing
idea of the reader. Each logged URI is matched against the Symfony router
and the row is labelled with the route's *path pattern*, so
`/volunteers/12/edit` becomes `/volunteers/{id}/edit`. That collapses
noise; it strips the record identifiers 0018 named as its data-protection
objection, so nothing personally identifying reaches the screen; and it
is the asset filter, because `/assets/*`, `/brand/*` and `favicon` match
no route and fall out on their own, with no hand-kept ignore list to
drift.

Three corrections the live log forced, all verified on 2026-09-06:

- **Turbo Drive prefetches on hover** (`X-Sec-Purpose: prefetch`).
  Unfiltered, hovering a link counts as a page view. The reader skips
  those and reports how many it skipped.
- **This app returns 200, not 422, on an invalid form.** Every controller
  ends an invalid submit with `return $this->render(...)`, and a grep for
  `422` across `src/` and `config/` finds nothing — so the usual
  "422 = validation friction" metric would measure nothing here. The
  substitute: every successful write in this app ends in
  `redirectToRoute()`, so a non-GET answering **exactly 200** is a
  redisplayed form, i.e. a rejected submission. That is an inference, not
  a measurement, and it becomes a plain 422 count — more accurate, not
  less — once the Turbo/422 item now in
  [`next-steps.md`](../project/next-steps.md) is done. "Exactly 200, not
  any 2xx" matters: a 204 is a successful write with nothing to say, and
  counting those made the recorder's own traffic look like a wall of
  failed submissions.
- **The Docker `HEALTHCHECK` hits Caddy's admin port `:2019/metrics`, not
  the logged site.** Happy accident: unlike most Caddy setups, this log
  carries zero health-check noise.

**4. `usage_event`, deliberately three columns.** `id`, `name`,
`occurred_at`. No user, no IP, no session id, no free-text context
payload. That is what keeps the table non-personal and keeps 0018's
data-protection objection *answered* rather than reopened — adding a user
column later is a new data-protection decision needing its own ADR, not a
small change. `App\Enum\UsageEventName` **is the whitelist**:
`tryFrom()` is the trust boundary between a browser-supplied string and a
stored row. `POST /usage/event` also validates a CSRF token, and answers
`204` to everything — unknown name, bad token, success — because a `400`
would only fill the browser console with noise during normal use.
`assets/controllers/usage_event_controller.js` posts it.

The table stays narrow because **most "in-page" questions are actually
server round-trips** already visible in the log: the batch form and the
single form are two different routes, a filter is a query string, viewing
a report is a GET. Only four gestures are genuinely invisible — roster
reveal, roster copy to clipboard, the batch form's volunteer typeahead,
and abandoning the activity form — and those are the four enum cases.
Adding a case that duplicates something the log already counts makes the
app slower and the answer no better.

**Known blind spots, stated rather than discovered later:** the window is
only what `roll_size 10MiB` × 3 holds, and the reader reads the current
file only, not the rolled ones; mobile share comes from `Sec-Ch-Ua-Mobile`,
which Chromium sends and Safari/Firefox do not, so it under-counts and
the screen says so; in dev the log also carries Panther,
`panther-screenshot.php` and `gremlins.php` traffic; and
`docker compose logs php` **no longer carries request lines**, which
[`deployment-plan.md`](../project/deployment-plan.md) §8 previously
promised.

## Consequences

- **Positive:** no third party, no tracking script, no consent
  conversation, no new dependency and no second service — the numbers are
  the web server's own and cannot drift from reality. Route patterns mean
  the screen is safe to show without exposing records. It works
  retroactively over whatever the log already holds, and the answer is
  now a link in the nav rather than a shell pipeline someone has to
  remember.
- **Negative / trade-offs:** the window is short and dies with the
  `log_data` volume. The manual pipeline recorded in `CLAUDE.md` and
  `deployment-plan.md` §8 had to be rewritten, since `docker compose logs
  php` no longer carries request lines. Dev traffic is polluted by
  browser-automation tooling (Panther, the screenshot script, the
  gremlins horde). The "rejected submission" column is an inference from
  "non-GET answering exactly 200" until the 422 item lands. And it is one
  more table, one more endpoint and one more Stimulus controller to
  maintain.
- **Reversibility:** cheap, and separable. Dropping the `usage_event`
  half is a migration, an enum, a controller and a Stimulus file — the
  `/usage` screen keeps working without it. Dropping the whole thing
  leaves `CADDY_SERVER_LOG_OPTIONS` and `log_data`, which are worth
  keeping regardless: a rolling JSON log on a volume is better
  operationally than console-format stderr whatever reads it. Reverting
  to 0018's pipeline exactly means unsetting one env var.

## Alternatives considered

### 1. Leave ADR 0018's shell pipeline as the only reader

**Rejected.** The pipeline *is* the negative 0018 recorded about itself.
A five-stage `logs | grep | sed | jq | sort` that only a person who
remembers it can run is not really an available answer — and the whole
point of 0018 was that the answer is already being collected for free.
Collecting it and never reading it is the worst of both.

### 2. Keep the stderr copy alongside the file

Real option — Caddy 2.x supports multiple named `log` directives in one
site block. **Rejected.** It doubles log volume, it needs a Caddyfile
edit and therefore a CI image rebuild to reach production, and the stderr
copy is strictly the worse of the two: a console prefix that breaks a
bare `| jq` (the correction 0018 had to record), no `ts` field, and
Docker's `json-file` driver storing another 50 MB of it on a 2 GB VPS.

### 3. Write the log into the existing `db_data` volume

**Rejected.** `db_data` is the backed-up, off-site-shipped,
restore-drilled artifact. Access logs must not ride along in the backup
that exists to protect volunteer records, and a restore drill must not
have to reason about what else it is replacing.

### 4. A general-purpose client event pipe

Arbitrary event names, a JSON context payload, the user id. **Rejected.**
That is analytics with exactly the data-protection problem 0018 declined,
rebuilt in-house and without the consent conversation a third party would
at least have forced. The enum whitelist and the three-column row are the
point, not a limitation to be relaxed later.

### 5. Third-party analytics — GA4, Plausible, or a self-hosted Matomo

**Still rejected, on ADR 0018's reasoning unchanged.** Its reopen trigger
has not fired: there is still one regular user. Nothing here consumes
that trigger either — if it fires, the front-runner is still Plausible or
Matomo, not GA4.
