# 31. Show production errors on an in-app admin screen over a rotating JSON error log

Date: 2026-09-17

## Status

Proposed

## Context

Today a production error is logged only to stderr: Monolog's
`fingers_crossed` handler writes JSON, and that JSON ends up in the Docker
log. Nobody can read it without SSH access to the server. The narrative is
in
[`docs/brainstorm/11-in-app-error-report.md`](../brainstorm/11-in-app-error-report.md).

These facts drive the decision:

- **`/usage` shows that an error happened but not why.**
  [ADR 0021](0021-read-usage-from-an-in-app-usage-screen-over-the-caddy-access-log.md)
  counts 5xx responses per route from Caddy's access log. The log has a
  status code but no exception, message or stack trace.
- **The developer needs the root cause and the stack trace, after the
  fact, inside the app.** Nobody watches production live. The question
  usually comes up days after the error.
- **The pattern already works here.** ADR 0021 showed that a local log
  file on the `log_data` volume, read by an admin-only screen, answers the
  question with nothing new to run and no third party. The same approach
  fits errors.
- **Error messages carry personal data.** A Doctrine `DriverException`
  message contains the SQL and its bound parameters, which can include
  volunteer records. Wherever errors are stored, that data is stored too.

The status is Proposed because the decision still needs the owner's
sign-off before the code is built.

## Decision

**Production errors at level `critical` are written by a dedicated Monolog
handler to daily rotating JSON files on the `log_data` volume, and an
admin-only Settings → Errors screen reads them and groups them by root
cause.**

**1. One extra handler, prod only.** The handler is a Monolog
`rotating_file` handler at level `critical`, configured under `when@prod`.
It writes `errors-YYYY-MM-DD.log` in JSON under `%kernel.logs_dir%`, which
is the `log_data` volume. It must follow these rules:

- **The level is `critical`, and it must not be lowered.** At `critical`,
  the file receives uncaught exceptions that end in a 5xx response and
  console command crashes. It does not receive 4xx responses, which
  Symfony logs at `error`.
- **Stack traces are included.** Seeing the trace is the reason the file
  exists.
- **Symfony's `RouteProcessor` runs without route parameters.** Each
  record gets the route name but no parameter values.
- **The request context is the URL path only.** The query string is
  dropped and the client IP is not recorded.
- **`file_permission` and `use_locking` are set.** Concurrent FrankenPHP
  workers and console processes all write to the same daily file.
- **The existing stderr logging stays unchanged.** The new handler is an
  addition, not a replacement.

**2. A reader modelled on `AccessLogReader`.** It follows ADR 0021's rules:

- **A missing file gives an empty report, never an exception.** CI and a
  fresh checkout have no error files, and `RouteSmokeTest` walks every GET
  route.
- **Malformed lines are skipped.** A line cut off by a crash must not hide
  the rest of the file.
- **The window is 30 days, filtered by each record's timestamp.** Rotation
  deletes old files only when a new record is written, so after a quiet
  month older files are still on disk. The reader must not rely on
  rotation to enforce the window.
- **Records are read newest first, up to a line cap.** A burst of errors
  cannot make the screen unbounded.
- **Errors are grouped by root cause.** The reader follows the `previous`
  chain to the innermost exception. The group key combines that
  exception's class, its file path relative to the project root, and its
  function. The path must be relative so that a change of image path does
  not split one group into two. Each group shows both the outer exception
  and the root exception, because the outer one is what the request saw
  and the root one is what failed.

**3. An admin-only screen at Settings → Errors.** It shows one expandable
entry per group. Each entry has the count, first seen, last seen, route,
path, stack trace, and a button that copies the trace. On `/usage`, the
server-side error figure links to this screen with the label "application
errors".

**4. Handled failures are added by exception class, never by lowering the
level.** If a caught exception should appear on the screen, raise that
exception class to `critical` with `framework.exceptions` or
`#[WithLogLevel]`. Lowering the handler level to get one more exception
would also bring in every 4xx response.

**Traps a future change must not undo:**

- **Never run console commands with `--user root` in production.** The
  image runs as `www-data`. A command run as root that crashes creates that
  day's file owned by root, and every later write that day fails.
- **Verify with a real uncaught exception.** Booting the kernel by hand and
  calling the logger skips `ErrorListener` and the processors, so the
  resulting record does not match what production writes.

## Consequences

- **Positive:**
  - The cause of a production error can be read in the app, without SSH.
  - There is no new service and no third party. The files sit next to the
    access log on a volume that already exists.
  - Grouping by root cause turns a burst of identical failures into one
    entry with a count.
- **Negative / trade-offs:**
  - **Nothing is pushed to anyone.** Someone has to open the screen.
  - **This screen's count will never match the 5xx count on `/usage`.**
    Caddy records 5xx responses that never reach Monolog: responses during
    a restart, from a dead FrankenPHP worker, and from some out-of-memory
    deaths. The screen states this so the gap is not mistaken for a bug.
  - **The screen is unavailable while the database is down.** Admin login
    needs the database, so errors from an outage can only be investigated
    once the database is back. The files still capture those errors.
  - **Personal data is kept on disk for 30 days.** Exception messages such
    as a Doctrine `DriverException` can contain SQL with bound parameters.
    This is acceptable because the screen is admin-only and the window is
    short. The GDPR record should list it.
  - **This screen is an explicit exception to
    [ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md).**
    It has no CSV or XLSX export. It is a diagnostic view for the admin,
    not a staff list, and an export would move stack traces and the data
    in their messages outside the admin gate.
  - `log_data` is not backed up, so the files are lost if the volume is
    lost.
- **Reversibility:** Cheap. Removing the handler and the screen puts
  logging back where it is today. Error files already written can be
  deleted from the volume or left to age out.

## Alternatives considered

### 1. Sentry or GlitchTip

**Rejected.** Both group and alert well, but the third party would receive
request data, including volunteer personal data in exception messages.
That brings back the data-transfer question that ADR 0021 declined for
analytics. Self-hosted GlitchTip would also add a service to run and back
up on a small VPS.

### 2. A database table

**Rejected.** Errors cannot be recorded in the database when the database
is what failed. Also, a Doctrine write inside a request that has already
broken loses exactly the error it was meant to record. A file does not
depend on the thing that failed.

### 3. A lower handler level (`error`)

**Rejected.** Every 4xx response would be written to the file and the real
failures would be lost among them. Raising a single exception class with
`framework.exceptions` or `#[WithLogLevel]` adds the one case that matters
and nothing else.

### 4. A menu badge that prompts admins to look

**Rejected.** The developer checks the screen when they choose to. A badge
would put an alert in front of every admin, including admins who cannot act
on a stack trace.
