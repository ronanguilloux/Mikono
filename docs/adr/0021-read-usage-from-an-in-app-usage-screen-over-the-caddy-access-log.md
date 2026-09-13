# 21. Answer usage questions from the Caddy access log, read in an in-app `/usage` screen

Date: 2026-09-13

## Status

Accepted

## Context

The want is real: which screens get used, which actions get performed, and
how the app is used in practice rather than as imagined. The original ask
was Google Analytics with JavaScript instrumentation. The narrative is in
[`docs/brainstorm/06-usage-analytics-cockpit.md`](../brainstorm/06-usage-analytics-cockpit.md).

Four facts decide it:

- **Half the answer is already collected.** Caddy logs every request, and
  **FrankenPHP is Caddy**: the same process serves HTTP and runs PHP, so the
  access log is a local file the app can open — no sidecar, no shipper.
- **Every page is behind a login**, so every event is a named staff
  member's behaviour, and URLs like `/volunteers/12/edit` carry record
  identifiers. Sending that to a US provider reopens the cross-border
  transfer question [ADR 0017](0017-host-production-on-gandicloud-vps-in-france.md)
  already has to carry.
- **The app has one regular user.** Funnels, audiences and attribution —
  GA4's strengths — have nothing to work on.
- **A shell pipeline over the log is only available to whoever remembers
  it.** The answer has to be a screen.

## Decision

**No third-party analytics and no analytics `<script>` in
`templates/base.html.twig`. Usage is read at an admin-only `/usage` screen
over Caddy's own access log, plus a three-column `usage_event` table for the
few gestures that never reach the server.**

**1. The log is a rolling JSON file on its own volume.**
`CADDY_SERVER_LOG_OPTIONS` in [`compose.yaml`](../../compose.yaml) sets
`output file /app/var/log/access.log { roll_size 10MiB roll_keep 3 }` and
`format json`. It lives in compose, not in `frankenphp/Caddyfile`, because
the Caddyfile is copied into the image and would need a CI rebuild to reach
production ([ADR 0010](0010-build-in-ci-and-deploy-by-image-pull.md)), while
an env var ships with a `git pull`. The sizes are explicit because Caddy's
defaults (100 MiB × 10) are 1 GB. The file sits on a `log_data:/app/var/log`
named volume — deliberately not `db_data`, which is what backups snapshot
and restores replace. `log_data` is not backed up.

**2. `src/Usage/AccessLogReader.php` streams and aggregates per route.**
`fgets` + `json_decode`, never `file_get_contents`. `UsageController`
renders `/usage` under `ROLE_ADMIN`, reusing `DataTable`, `ListPaginator`
and the `SORT_MAP` pattern
([ADR 0011](0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md));
the aggregate is cached 60 seconds. Load-bearing details:

- **One window for the whole screen.** `App\Usage\UsageDateRange` is the
  single reader of `?range=`/`?from=`/`?to=`, and both the log table and
  `usage_event` take it, or the two tables describe different periods. The
  screen opens on `DEFAULT_PRESET` (last 7 days) and the control ticks the
  *resolved* range; malformed input falls back to that preset, not to all
  time ([ADR 0023](0023-degrade-malformed-query-input-to-a-default.md)).
  The range is applied **while streaming**, because per-route counters and
  `p95` are computed in that same pass — filtering finished rows would leave
  every number describing the whole file — and `cacheKey()` is part of the
  cache key, or one filter serves the previous one's result.

- **Each URI is labelled with its matched route pattern**
  (`/volunteers/{id}/edit`). That collapses noise, strips record
  identifiers so nothing identifying reaches the screen, and *is* the asset
  filter — `/assets/*`, `/brand/*` and `favicon` match no route.
- **Requests with `X-Sec-Purpose: prefetch` are skipped** (and counted as
  skipped): Turbo Drive prefetches on hover, and unfiltered every hover is a
  page view.
- **A rejected form submission is a 422, and must be tested before
  `>= 400`.** Symfony sets it: `AbstractController::render()` answers 422
  whenever a submitted invalid form is passed as a parameter, which every
  `new`/`edit` action here does. Test it below the `>= 400` branch and every
  rejection is swallowed as a client error.
  `AccessLogReaderTest::aRejectedSubmissionIsNotAlsoCountedAsAClientError`
  holds the order.
- The Docker `HEALTHCHECK` hits Caddy's admin port `:2019`, not the logged
  site, so the log has no health-check noise.

**3. `usage_event` has three columns: `id`, `name`, `occurred_at`.** No
user, IP, session id or free-text payload — that is what keeps it
non-personal, and adding a user column is a new data-protection decision,
not a small change. `App\Enum\UsageEventName` is the whitelist; `tryFrom()`
is the trust boundary. `POST /usage/event` checks a CSRF token and answers
`204` to everything, since a `400` would only fill the console. Most
"in-page" questions are already server round-trips (a filter is a query
string, the batch and single forms are different routes), so the enum
covers only the gestures that send no request: roster reveal, roster copy,
batch-form volunteer typeahead, activity-form abandonment. A case that
duplicates something the log counts makes the app slower and the answer no
better.

**Known blind spots:** the window is whatever 10 MiB × 3 holds and the
reader reads only the current file; mobile share comes from
`Sec-Ch-Ua-Mobile`, which Safari and Firefox don't send (the screen says
so); in dev the log includes Panther and gremlins traffic; and
`docker compose logs php` no longer carries request lines.

**Reopen trigger for third-party analytics:** a second regular user **and**
a concrete question neither the log nor `usage_event` can answer. Both. The
front-runner is then Plausible or a self-hosted Matomo, never GA4. Whoever
adds a tag inherits two traps: Turbo Drive breaks the default `gtag`
snippet (page views must fire on `turbo:load`), and the measurement ID must
be an env var unset in dev, test and CI, because functional tests assert on
rendered content.

## Consequences

- **Positive:** no third party, no consent conversation, no new service or
  dependency; the numbers are the web server's own. Route patterns make the
  screen safe to show. It works retroactively over whatever the log holds.
- **Negative / trade-offs:** short window, lost with `log_data`; no funnels
  or retention. The 422 branch ordering reads as arbitrary and isn't. One
  more table, endpoint and Stimulus controller to maintain.
- **Reversibility:** cheap and separable. `usage_event` can be dropped
  without touching `/usage`. Dropping the screen leaves the JSON log on a
  volume, which is worth keeping anyway.

## Alternatives considered

### 1. GA4 with an inline `gtag` snippet, as asked

**Rejected.** Heaviest data-protection cost — named staff behaviour and
record identifiers to a US provider — for strengths a one-user app cannot
use, and its default snippet is the one Turbo Drive silently breaks.

### 2. Plausible or self-hosted Matomo

**Rejected for now; front-runner if the trigger fires.** Either a
subscription or a second service to run and back up on a small VPS, before
there is a question the log cannot answer.

### 3. Only a documented shell pipeline over the log

**Rejected.** Collecting the answer for free and leaving it behind a
five-stage pipeline nobody remembers is the worst of both.

### 4. Keep a stderr copy of the log alongside the file

**Rejected.** Doubles log volume, needs a Caddyfile edit (and so an image
rebuild), and the console format breaks a bare `jq`.

### 5. Write the log into `db_data`

**Rejected.** Request logs must not ride in the backup that protects
volunteer records, nor complicate a restore drill.

### 6. A general-purpose client event pipe

**Rejected.** Arbitrary names, a JSON payload and a user id is in-house
analytics with the data-protection problem this ADR declines. The enum
whitelist and three columns are the point.
