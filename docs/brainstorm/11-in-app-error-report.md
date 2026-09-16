# Brainstorm — In-app error report

**Date:** 2026-09-17
**Author:** <ronan.guilloux@gmail.com>
**Feeds:**
[ADR 0031](../adr/0031-show-production-errors-on-an-in-app-admin-screen-over-a-rotating-json-error-log.md)
**Pattern followed:**
[ADR 0021](../adr/0021-read-usage-from-an-in-app-usage-screen-over-the-caddy-access-log.md)
**Related:** [`CLAUDE.md`](../../CLAUDE.md),
[`docs/adr/`](../adr/)

---

## Primary audience

The developer, not the Volunteer Manager. This screen is for whoever has to
work out why production returned a 500.

## Desired impact

The developer can see a production error's root cause and stack trace
inside the app, after it happened, without SSH. It should stay simple and
still give enough to act on.

In hindsight, success looks like this:

- A 500 that happened yesterday can be diagnosed from Settings → Errors
  alone: exception class, root cause, route, path and trace.
- Nobody has to open a shell on the server to read `$COMPOSE logs php`.
- An error storm doesn't make the screen slow.

## The problem (2026-09-17)

Production 500s go only to stderr. In `config/packages/monolog.yaml`,
`when@prod` uses a `fingers_crossed` handler at `action_level: error`, with
404 and 405 excluded. It writes JSON to `php://stderr`, which lands in
Docker's `json-file` log (rotated at 10 MB × 5) and is read with
`$COMPOSE logs php`. The errors are captured, but nobody looks at them
without SSH.

The admin `/usage` screen (`src/Usage/AccessLogReader.php`, over the Caddy
access log) counts 5xx responses per route. It shows *that* a route failed,
never *why*.

## Direction explored

**A dedicated daily JSON error log.** `errors-YYYY-MM-DD.log` on the
`log_data` volume (`var/log`), prod only:

- A Monolog `rotating_file` handler at level `critical`. Symfony logs
  uncaught 5xx and console crashes at `critical` and 4xx at `error`, so
  404s don't flood the file.
- A JSON formatter with stack traces included. A trace frame is `file:line`
  only, with no arguments.
- Symfony's `RouteProcessor` without route parameters, and `WebProcessor`
  limited to the path, so the query string is dropped. No IP, no record
  ids.
- The existing stderr logging stays as it is.

**A reader modelled on `AccessLogReader`:**

- A missing file is an empty report and never throws, because
  `RouteSmokeTest` walks every GET route.
- Malformed lines are skipped.
- A 30-day window, filtered by each record's timestamp.
  `RotatingFileHandler` only prunes old files when it writes, so file age
  alone isn't reliable.
- Newest files first, with a line cap, so an error storm doesn't slow the
  page.

**Grouping.** The reader follows `previous` down to the root cause, because
Twig's `RuntimeError` and Doctrine wrap the real exception. The group key
is the root exception class, the project-relative file and the function.
Line numbers are left out because they shift between deploys. The screen
shows both the outer and the root exception.

**The screen.** Settings → Errors, `ROLE_ADMIN`. One `<details>` per group
with count, first seen, last seen, route, path, trace and a "copy trace"
button:

- It is not a `DataTable`, because a trace doesn't fit in a cell.
- It has no CSV/xlsx export. It is a diagnostic, not a list view, so it is
  a deliberate exception to
  [ADR 0029](../adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md).
- `/usage` links to it with the label "application errors", and `/errors`
  notes that gateway-level 5xx only appear in `/usage`.

## Review findings

An outside review of the first design, checked against this repo:

- **The counts won't reconcile.** `/errors` will never match the 5xx count
  on `/usage`. Caddy sends some 5xx responses that never reach Monolog: a
  container restart, a dead FrankenPHP worker, some out-of-memory deaths.
  There is no PHP-FPM here, because FrankenPHP runs PHP inside Caddy, so FPM
  timeouts are not a cause.
- **File ownership.** The prod image runs as `USER www-data` (`Dockerfile`),
  so `docker compose exec` is safe by default. But a console command run
  with `--user root` could create that day's file owned by root. `www-data`
  then can't open it, and Monolog would throw while handling a 500. The
  handler should set `file_permission`. The host backup script
  (`scripts/backup-db.sh`) is not a console command, so it doesn't create
  this file.
- **Verification has to use a real exception.** Calling
  `logger->critical()` from a hand-booted kernel skips `ErrorListener` and
  both processors, and `logger` is private in the prod container. Checking
  the feature honestly needs a real uncaught exception, from either an
  admin-only route or a hidden console command that throws.
- **Level.** `critical` misses errors that code catches and logs itself.
  Today `src/` has no such `logger->error()` call, so nothing is lost. To
  show a handled failure later, raise that exception class's level with
  `framework.exceptions` or `#[WithLogLevel]`; don't lower the handler's
  level. Messenger isn't installed and there is a single `php` service, so
  there are no worker logs to consider.
- **Personal data.** Doctrine `DriverException` messages carry SQL and
  bound parameters (names, contact details, phone numbers). The file
  therefore holds personal data on disk for 30 days. That is acceptable
  only because the screen is admin-only, and the ADR says so in its GDPR
  note.
- **Database outage.** Writing to the file doesn't depend on the database,
  but viewing `/errors` needs an admin login, which does. This is for
  looking at errors after the fact, not a console for use during an outage.
- **Test fixture.** The developer's global gitignore ignores `*.log`, so a
  committed sample must use `.ndjson`, like
  `tests/Integration/Usage/access-log-sample.ndjson`. With a `.log` name,
  the test would still pass against a missing file. The content is tested in
  an integration test, not the functional one, for the same
  `%kernel.logs_dir%` reason as `/usage`.
- **Smaller points.** Set `use_locking: true`, because FrankenPHP threads
  append at the same time and traces make long lines. Drop
  `channels: ['!deprecation']`, which does nothing at `critical`.

## The "Options Not Taken"

- **Sentry or GlitchTip.** Either one would send volunteer personal data to
  a third party. Doctrine exception messages carry bound parameters, and
  [ADR 0021](../adr/0021-read-usage-from-an-in-app-usage-screen-over-the-caddy-access-log.md)
  already kept third parties out of this app for the same reason.
- **A database table.** It can't record an error when the database is what
  failed. A Doctrine write inside a broken request loses the very error it
  was meant to record.
- **A menu badge or nudge.** The developer prefers to open the screen and
  check, rather than have the app flag new errors.

## Constraints

- **No SSH for routine triage.** The whole point is reading errors from
  inside the app.
- **Personal data in exception messages.** The file stays on the
  `log_data` volume and out of the repo, the screen is `ROLE_ADMIN` only,
  and trace frames carry no arguments.
- **Single `php` service, FrankenPHP worker mode.** Writes happen
  concurrently from threads, so the handler locks.
- **CI has no production log.** A missing file must stay an empty report,
  as it does for `/usage`.
- **Existing stderr logging stays.** The new handler adds to it and
  doesn't replace it.

## Open questions

- **How to verify it:** an admin-only route that throws, or a hidden
  console command that throws.
- **Retention:** 30 days is the proposal.
- **Console crashes:** whether they belong on the same screen as request
  errors.
