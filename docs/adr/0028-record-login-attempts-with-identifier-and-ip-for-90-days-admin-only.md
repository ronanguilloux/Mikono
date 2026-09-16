# 0028. Record login attempts, with identifier and IP, for 90 days, admin-only

Date: 2026-09-16

## Status

Accepted

## Context

The admin `/usage` screen
([ADR 0021](0021-read-usage-from-an-in-app-usage-screen-over-the-caddy-access-log.md))
cannot say who signs in, or who fails to:

- **The access log does not record whether a login worked.** It has the
  `POST /login` rows, but Symfony redirects on both success and failure,
  so every row looks the same. The log carries no credentials either.
- **`usage_event` is non-personal on purpose.** It has no user, IP or
  session column, and that is why it can exist without a data-protection
  review. The `UsageEvent` docblock says that adding a user column needs
  its own ADR.
- **The login throttler hides attacks.** It allows 5 attempts per 15
  minutes. Repeated failures against a real address are blocked without
  any trace an admin can see.

Other constraints: the repo is public, the app is used by a handful of
UCESCO colleagues, and production is hosted in France
([ADR 0017](0017-host-production-on-gandicloud-vps-in-france.md)). This is
personal data, so it needs a purpose, a minimum and a retention period.

## Decision

**Every login attempt is recorded in its own `login_attempt` table, with
the submitted identifier and the client IP, kept for 90 days and shown
only to admins on `/usage`.**

**1. A separate table, never `usage_event`.** `App\Entity\LoginAttempt`
has these columns:

- `identifier`: nullable, 180 characters.
- `succeeded`: boolean.
- `ip`: nullable, 45 characters, long enough for IPv6.
- `occurredAt`: indexed.

Adding these fields to `usage_event` would remove the guarantee that
table exists for.

**2. Written by `App\Security\LoginAttemptRecorder`**, which listens to
Symfony's `LoginSuccessEvent` and `LoginFailureEvent`. Throttled attempts
also arrive as `LoginFailureEvent`, which is how throttled failures become
visible.

**3. The identifier is stored as typed, whether or not it matches an
account.** This shows repeated failures against real addresses and probes
of unknown ones. **A password is never stored.** Rules a future change
must keep:

- Only the passport's `UserBadge` identifier is read, never the request
  body.
- An identifier that fails `FILTER_VALIDATE_EMAIL` is stored as `null`
  and shown as "not an email address". This stops a password typed into
  the email box from reaching the table.
- An empty submission has no passport, so its identifier is `null`.

**4. The client IP comes from `Request::getClientIp()`.** FrankenPHP is the
edge and no trusted proxies are configured. **If a reverse proxy or CDN is
ever put in front, `trusted_proxies` must be set**, or every row will show
the proxy's IP.

**5. Rows are kept for 90 days**, which matches the longest rolling preset
on `/usage`. On each write, the recorder deletes rows older than that, so
no cron job or scheduler is needed.

**6. Shown to admins only, as a "Sign-ins" section on `/usage`.** The
section inherits `UsageController`'s `ROLE_ADMIN` gate. Rows are grouped by
(identifier, IP), with signed-in and failed counts and the last attempt.
The section is capped at 50 rows and uses the same
`App\Usage\UsageDateRange` as the other two tables, so all three cover the
same period.

## Consequences

- **Positive:** an admin can see failed and throttled attempts, including
  probes of unknown addresses and where they came from. This needs no
  third party, no new service and no scheduler. `usage_event` stays
  non-personal.
- **Negative / trade-offs:** the app now stores personal data (email
  addresses and IPs), readable by every admin. "Year to
  date" and "All time" show at most 90 days of sign-ins, and the screen
  says so. If nobody logs in, old rows stay until the next attempt. That
  delay is bounded and acceptable. A mistyped address that is still a
  valid email is stored as typed. Adding a proxy without setting
  `trusted_proxies` silently makes the IP column useless.
- **Reversibility:** cheap. Remove the listener, the section and the table
  in one migration. Nothing else reads `login_attempt`.

## Alternatives considered

### 1. Add a user column to `usage_event`

**Rejected.** `usage_event` is allowed to exist because it is
non-personal. A user column would turn every recorded gesture into
personal data to answer a question only sign-ins need.

### 2. Infer the outcome from the access log

**Rejected.** This is impossible: Symfony redirects on both success and
failure, and the log has no identifier.

### 3. Mask or hash the identifiers

**Rejected.** With only a handful of colleagues, masking makes two of them
indistinguishable, and a hash can be reversed by trying the few known
addresses. It hides nothing and makes the screen harder to read.

### 4. Store only identifiers that match an account

**Rejected by the owner.** Probing of unknown addresses is exactly what
the screen should show.

### 5. Store no IP, and match against Caddy's `client_ip` by timestamp

**Rejected.** The owner wants the IP on the screen. The access log also
rotates at 10 MiB × 3, so the match would be lost well before 90 days.

### 6. Purge with a cron job or Symfony Scheduler

**Rejected.** Deleting on write needs no scheduler, worker or host
crontab. The only cost is that old rows can stay while nobody logs in.
