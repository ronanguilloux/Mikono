# 20. Keep sessions on the database volume, in files, rather than in the cache directory

Date: 2026-09-06

## Status

Accepted

## Context

Sessions lived at Symfony's default `%kernel.cache_dir%/sessions` —
`/app/var/cache/prod/sessions`, which is inside the container image and
on no volume at all. A deploy pulls a new image and recreates the
container ([`scripts/deploy.sh`](../../scripts/deploy.sh)), so **every
deploy signed everyone out.**

This was not a discovery. It was written down three times: as a deferred
item in [`next-steps.md`](../project/next-steps.md) under "Deferred until
a second `User` account exists", in
[`hosting-plan.md`](../project/hosting-plan.md) §4, and in
[`deployment-plan.md`](../project/deployment-plan.md) §6. The deferral
was reasonable — there is one user today, UCESCO's Volunteer Manager, so
the blast radius is one person — but the fix turned out to be one line of
configuration, and a forced logout in the middle of a task is a real
annoyance when a deploy lands at the wrong moment. At that price the note
costs more to keep than to close.

## Decision

**Session files are written to `var/data/sessions`, inside the existing
`db_data` named volume, with an eight-hour idle lifetime and a
browser-session cookie.**

Three keys under `framework.session` in
[`config/packages/framework.yaml`](../../config/packages/framework.yaml):

- `save_path: '%kernel.project_dir%/var/data/sessions'` — the `db_data`
  volume declared in [`compose.yaml`](../../compose.yaml) and mounted at
  `/app/var/data`, the same one that already holds `data_prod.db`.
- `gc_maxlifetime: 28800` — eight hours of idle time, up from PHP's 1440
  (24 minutes).
- `cookie_lifetime: 0` — explicitly PHP's default, a browser-session
  cookie.

Two mechanics are worth stating, because they are why this is three lines
of YAML rather than a new component:

- **Setting `save_path` at all switches the handler.** Symfony aliases
  `session.handler` to `session.handler.native` only when neither
  `handler_id` nor `save_path` is configured; supply either and it
  defaults `handler_id` to `session.handler.native_file`
  (`NativeFileSessionHandler`), which creates the directory itself
  (`vendor/symfony/framework-bundle/DependencyInjection/FrameworkExtension.php`,
  around line 1357). No new volume, no new service, no migration, no
  ownership step.
- **PHP's own GC prunes the directory.** `session.gc_probability` 1 over
  `session.gc_divisor` 1000, as the image's php.ini already has it, so
  expired files are collected on roughly one request in a thousand and
  the volume does not grow without bound.

**The two lifetime values are split deliberately, and they are not
interchangeable.** `gc_maxlifetime` is a *sliding* idle window: the file
handler's garbage collection keys off the session file's mtime, and every
request touches it, so eight hours means eight hours of doing nothing.
`cookie_lifetime` would not slide — PHP emits `Set-Cookie` only when it
creates the session id, never on subsequent requests — so a non-zero
value there would be an absolute deadline counted from login, and would
sign a VM out mid-task at the eight-hour mark regardless of activity.
That asymmetry is the whole reason for the split.

## Consequences

- **Positive:** a deploy no longer signs anyone out. Verified in dev —
  log in via
  [`scripts/panther-screenshot.php`](../../scripts/panther-screenshot.php),
  confirm `sess_*` files in `/app/var/data/sessions`, then
  `docker compose up -d --force-recreate php`; the files are still there
  and the browser is still logged in. A VM also keeps their session
  across a working day instead of losing it after 24 idle minutes. And
  there is nothing new to operate: no extra volume in the dry-run stack,
  the restore drill or a `down -v`, no session table, no daemon.

- **Negative / trade-offs:**
  - **`db_data` now holds two kinds of thing, and only one of them is
    backed up.** [`scripts/backup-db.sh`](../../scripts/backup-db.sh)
    names the database file explicitly and prunes only `mikono-*.db`, so
    it is unaffected — but the volume can no longer be described as "the
    entire application database" without a caveat, and
    [`hosting-plan.md`](../project/hosting-plan.md) §4 now carries one.
  - **A restore leaves stale session files beside a rolled-back
    database.** Harmless in practice:
    [`security.yaml`](../../config/packages/security.yaml)'s provider
    matches on `email`, and Symfony's `AbstractToken` compares the stored
    password hash when it refreshes the user, so a stale cookie degrades
    to a logout rather than landing on the wrong account. The §7 restore
    drill in [`deployment-plan.md`](../project/deployment-plan.md) clears
    `/data/sessions` anyway.
  - **Session payloads now outlive a code deploy.** A future change to
    what the session stores — the security token's class, say — will meet
    payloads written by the previous version. Symfony discards an
    unreadable token rather than erroring, so the failure mode is a
    logout, but it is a new class of thing to think about at upgrade
    time.
  - **An unattended phone stays logged in for up to eight idle hours**
    instead of 24 minutes. Accepted deliberately: field work on a phone
    is the normal case here, and the app has a logout link.
  - **The test environment is unaffected, and therefore covers none of
    this.** `session.storage.factory.mock_file` takes
    `%kernel.cache_dir%/sessions` as a constructor argument and never
    reads `session.save_path`, so no test can regress the path, the
    lifetime, or the handler switch.

- **Reversibility:** trivial. Delete three lines of YAML and sessions go
  back to the cache directory. The stored files are ordinary PHP session
  files, and nothing else in the app knows where they live.

## Alternatives considered

### 1. A dedicated `session_data` named volume, mounted at `/app/var/sessions`

**Rejected.** It buys a tidier story — `db_data` stays strictly "the
database", and "log everyone out" becomes a `docker volume rm` — at the
price of a third volume to remember in
[`deployment-plan.md`](../project/deployment-plan.md) §10's dry-run
stack, in §7's volume-name step of the restore drill, and in every
`down -v`. This ADR supplies the tidiness in prose instead, which is
cheaper. Worth reconsidering if the off-site backup work ever wants to
snapshot a whole volume rather than the database file.

### 2. `PdoSessionHandler` on the existing SQLite database

**Rejected.** It puts a write on a single-writer database on essentially
every authenticated request — the exact limit
[ADR 0003](0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)
flagged, and that the WAL item in
[`next-steps.md`](../project/next-steps.md) still defers. It would also
make restoring yesterday's backup restore yesterday's sessions, which
nobody wants.

### 3. Leave it broken and keep the note

**Rejected.** The note had already been written three times in three
documents, which is more effort than the fix cost.

### 4. A `remember_me` firewall entry instead of a longer session

**Rejected.** It solves a different problem — surviving a browser close —
at the cost of a persistent authentication token in a cookie, and the
complaint was about deploys and idle timeouts, not browser restarts.
`cookie_lifetime` is the one-line knob if that case ever does matter.
