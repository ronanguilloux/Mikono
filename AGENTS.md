# Mikono

UCESCO Volunteer Manager (VM) — a Symfony 8.1 web app so UCESCO's
Volunteer Manager can track volunteers working at UCESCO's projects in
Kibera (Nairobi) and Mombasa: login-protected CRUD for Volunteers,
Projects, Activity Types, Escorts, Users and Activities (the log entries),
a home screen of daily rosters, `/reports`, and an admin `/usage` screen.
Stack decision:
[ADR 0003](docs/adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md);
narrative: `docs/brainstorm/02-volunteer-manager-v0.1-context.md`.

This file holds **conventions and traps**. The reasons behind a decision
live in its ADR — link to it rather than restating it here.

## Working rhythm (read this before starting, apply it before finishing)

- **Start a session by reading
  [`docs/project/next-steps.md`](docs/project/next-steps.md), then the one
  card you're picking up** from
  [`docs/project/backlog/`](docs/project/backlog/). `next-steps.md` is an
  ordered index, nothing more — Now / Next / Later, one link per item. The
  card holds the why, the acceptance criteria and the links. Read one card,
  not the folder.
- **A new request becomes a card**, not a line in `next-steps.md`: copy
  [`docs/project/backlog/template.md`](docs/project/backlog/template.md),
  fill the frontmatter, add one link to the index.
  [`docs/project/backlog/README.md`](docs/project/backlog/README.md) has
  the field vocabulary. Never write an item's description into
  `next-steps.md` — the index describes nothing
  ([ADR 0022](docs/adr/0022-split-next-steps-into-backlog-cards-and-a-thin-index.md)).
- **Finish an item by deleting its card** and removing its line from the
  index. Then, per [`docs/project/README.md`](docs/project/README.md): an
  architectural decision gets an ADR in [`docs/adr/`](docs/adr/) (at most a
  one-line pointer in `done.md`); anything else gets a dated entry in
  [`docs/project/done.md`](docs/project/done.md). Not both, and never
  "done" text left behind in a card or the index.
- **Research or narrative that hasn't settled into a decision** goes in
  [`docs/brainstorm/`](docs/brainstorm/), not in a card. A card growing an
  "Options considered" section wants a brainstorm file. Don't read that
  folder at session start; open a brainstorm file when you pick up the card
  that links to it.
- **This file holds conventions; the backlog does not.** Don't copy rules
  from here into a card or the index.

## Decision capture

- The narrative behind a new feature or slice — audience, desired impact,
  rejected alternatives, constraints — goes in `docs/brainstorm/` first,
  via the `context-capturer` subagent.
- A finalized decision is recorded in `docs/adr/` via the `adr-scribe`
  subagent. ADRs are **living**: each states the decision currently in
  force, and a changed decision is rewritten in place (or merged, or
  deleted) — git log is the history. No session narrative or "outstanding"
  status in an ADR; see `docs/adr/README.md`.

Every non-trivial architectural decision (stack, structure, service
boundaries, data model) gets an ADR before or alongside the code that
implements it.

## Directory map

- `docs/adr/` — Architecture Decision Records, one living file per
  decision in force.
- `docs/brainstorm/` — immutable narrative behind a feature or slice,
  written before its decision.
- `docs/project/` — living status docs: `backlog/` (one card per open
  item), `next-steps.md` (ordered index of those cards) and `done.md` (log
  of completed work that isn't itself an ADR).
- `.agents/skills/` — Agent Skills, source of truth, shared across Claude
  Code, Gemini CLI and Codex. See `.agents/skills/README.md`.
- `.claude/agents/` — Claude Code-specific subagents (`adr-scribe`,
  `context-capturer`).
- `src/Entity/`, `src/Repository/` — Doctrine entities (`User`,
  `Volunteer`, `Project`, `Program`, `ActivityType`, `Activity`, `Escort`, `Branch`, `Stay`)
  and their repositories, each with a `countReferencingActivities()`
  delete-guard where applicable. `Branch`'s guard counts stays and projects, and its five real rows come from its migration, not the
  fixtures — tests start with them
  ([ADR 0025](docs/adr/0025-model-ucesco-branches-as-a-standalone-reference-entity-seeded-by-migration.md)). Two shapes were set by the real rosters, so don't
  "tidy" them back: `Activity::$escorts` is a **collection**
  ([ADR 0013](docs/adr/0013-record-every-escort-on-an-activity.md)) — the
  escort delete-guard is a `MEMBER OF` query, and the eager escorts join is
  to-many, so it can't carry a `LIMIT`; `Volunteer::$lastName` is
  **optional** ([ADR 0014](docs/adr/0014-make-a-volunteers-last-name-optional.md)).
  A volunteer's **active is derived, never stored**: active means a `Stay`
  covers today, and `Activity::$stay` is resolved from volunteer + date on
  every save, never a form field
  ([ADR 0026](docs/adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md)).
  A project has a required branch, and an activity's project must share its
  stay's branch — checked on activity save, stay edit and project edit, not
  by the schema, so a new write path needs the same check
  ([ADR 0027](docs/adr/0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md)).
  An activity belongs to a **program**, and its project is derived through
  it (`Activity::getProject()`, never a column). Its type must be offered by
  the program and its date covered by the program's optional dates —
  checked in `ActivityController::resolveStays()`, so a new write path
  needs the same check. Activity types stay one global list; a program
  offers a subset
  ([ADR 0030](docs/adr/0030-insert-programs-between-projects-and-activities.md)).
  `ActivityFactory` still takes a `project` attribute and builds a program
  there.
- `src/Enum/` — backed PHP enums (`ProjectOwnership`,
  `ActivityDuration`), mapped as plain strings — portable off SQLite.
- `src/Controller/`, `src/Form/`, `templates/<area>/` — one set per CRUD
  area, all with the same index/new/edit/delete shape. Reuse the
  `DataTable` TwigComponent (`src/Twig/Components/DataTable.php` +
  `templates/components/DataTable.html.twig`) and the Tailwind form theme
  (`templates/form/tailwind_theme.html.twig`, registered globally in
  `config/packages/twig.yaml`) rather than hand-styling a new area.
  `DataTable` props:
  - `pagination` — renders the controls below the table.
  - `withActions` — set `false` for a read-only table, or it grows a
    phantom empty actions column.
  - `sortState` — turns headers into sort links; leave it null for a table
    with nothing to re-order (the Reports print panel).
  - A row's optional `badges` map (column key => label) draws a pill after
    that cell's text — how `/reports` tags a future-dated "Most recent" as
    `Planned`. Never concatenate the label into `cells`: they must stay the
    plain formatted value, or sorting, number formatting and every test
    matching a cell by text see the decoration too.
  - An action with `disabledReason` instead of a `url` renders inert
    (`aria-disabled` span, reason in `title` and `sr-only`) — how Delete
    shows as unavailable on rows the delete-guard would block. The
    server-side guard in `delete()` stays regardless.
- **Every list view exports** to CSV and `.xlsx`
  ([ADR 0029](docs/adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)). A new list gets the same four pieces:
  - `COLUMNS`
  - `listQueryBuilder()`, shared by `index()` and `export()`, so a filter
    added there reaches the file too
  - `cells()`
  - an `export.{format}` route, plus `<twig:ExportMenu route="…_export" />`

  Never write a second query for the export. Anyone who can view a list can
  export it; revisit that rule if a non-staff role ever logs in.
- `src/Pagination/` — `ListPaginator`, the single place `page`, `perPage`,
  `sort` and `direction` are read, plus the `SortState` VO. Pagination:
  [ADR 0009](docs/adr/0009-adopt-knppaginatorbundle-for-list-pagination.md);
  sorting:
  [ADR 0011](docs/adr/0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md).
  A sortable column is one `SORT_MAP` entry, never a flag on the column
  definition.
- **Query parameters, app-wide:** read through `$request->query->all()`
  guarded by `is_scalar()`, and `app.request.query.all` in Twig — never
  `InputBag::get()`/`getInt()` or `app.request.query.get()`, which turn a
  malformed URL into a 400 or a 500. Every parameter falls back to a
  default
  ([ADR 0023](docs/adr/0023-degrade-malformed-query-input-to-a-default.md)).
- `src/Report/` — the app's real domain logic: `ActivitySummaryCalculator`
  (duration-to-days aggregation for `/reports`), plus
  `RosterBuilder`/`QuietProjectFinder` and their readonly VOs behind the
  home screen. `QuietProjectFinder` covers projects only, never volunteers —
  deliberate and evidence-based; read its class docblock before
  "completing" it.
- `src/Usage/` — `AccessLogReader` streams Caddy's JSON access log
  (`var/log/access.log`, the `log_data` volume) for the admin-only `/usage`
  screen, plus the `usage_event` table for gestures that send no request.
  Route-pattern labelling, the prefetch skip, the 422-before-`>= 400`
  ordering, the three-column event row and the `UsageDateRange` window
  (shared by both tables, applied while streaming, part of the cache key)
  are load-bearing:
  [ADR 0021](docs/adr/0021-read-usage-from-an-in-app-usage-screen-over-the-caddy-access-log.md).
  A missing log file must stay an empty report, never an exception — CI
  has no Caddy log and `RouteSmokeTest` walks this route.
  Sign-ins are the personal third table: `login_attempt`, written by
  `App\Security\LoginAttemptRecorder`, pruned at 90 days on each write,
  never storing a non-email identifier
  ([ADR 0028](docs/adr/0028-record-login-attempts-with-identifier-and-ip-for-90-days-admin-only.md)).
  Never move them into `usage_event`.
- `src/Factory/` — Foundry v2 factories (`PersistentObjectFactory`, real
  objects) for every entity, used by tests and dev fixtures
  (`src/Story/AppStory.php`).
- `src/Fixture/` — `RosterArchive` reads `docs/fixtures/rosters.yaml`.
  **The dev/demo dataset is never generated**
  ([ADR 0012](docs/adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)):
  grow it by adding to the archive — not to `AppStory`, never with Faker.
  The raw WhatsApp exports (`docs/fixtures/*_dumps.txt`) are gitignored
  because they name sponsored children and donors; this repo is public.
  Transcription rules: `docs/fixtures/README.md`, enforced where possible
  by `tests/Integration/Fixture/RosterArchiveTest.php`. Test factories may
  still generate.
- `tests/Functional/` — WebTestCase tests, one per controller area plus
  `SecurityControllerTest`.
- `tests/Integration/` — KernelTestCase tests for `src/Report/` services,
  plus `tests/Integration/Usage/`, which asserts what `/usage` claims
  against a committed sample log. Everything about the numbers is tested
  there; the functional test covers only the screen and its admin gate,
  because `%kernel.logs_dir%` is the same path under `test`, so a content
  assertion would read the real dev log locally and nothing in CI.

## Stack

Docker + FrankenPHP, Symfony 8.1.5, PHP 8.5.9, SQLite, Tailwind CSS v4 +
Symfony UX (Turbo/Stimulus/TwigComponent; LiveComponent installed, unused),
PHPUnit 13 + Foundry v2. No host PHP/Composer — everything runs through
Docker.

**Day to day:**

```bash
docker compose up -d --wait        # start (first run auto-bootstraps the app)
docker compose down                # stop — the SQLite data survives (named volume)
docker compose exec php bin/console <command>
docker compose exec php composer require <package>
docker compose exec php php bin/phpunit
```

App: `https://localhost` (self-signed cert). The dev login is set with
`app:user:create`; neither the account nor its password is recorded here
(this repo is public). The flags make it a one-liner — dev only, since the
password lands in shell history:

```bash
docker compose exec php bin/console app:user:create \
  --email=TEST_ACCOUNT@gmail.com --full-name="Test" --password=<new-password> --admin
```

**Optional local TLS override** (setup: `README.md`). A checkout may
carry a gitignored `compose.local.yaml` (e.g. serving a trusted mkcert cert via
`CADDY_SERVER_EXTRA_DIRECTIVES`, with `SERVER_NAME: localhost` — Caddy
refuses a `tls` directive on the plain-HTTP `php:80` site). If that file
exists, every local `docker compose` command must include it, or the
container is recreated without it:

```bash
export COMPOSE_FILE=compose.yaml:compose.override.yaml:compose.local.yaml
```

Never for production, which always passes its two `-f` files.

**That local account is for AI agents, not for a human** — an agent that
needs to log in (mainly `scripts/panther-screenshot.php`) may reset its
password with the command above without asking, and needn't preserve the
previous one. It is a local, dev-only SQLite account with nothing shared
with production.

Seed the dev data — the real August 2026 roster archive, with the last two
days anchored onto today and tomorrow so the home screen's roster panels
have something to show:

```bash
docker compose exec php bin/console foundry:load-fixtures --no-interaction
```

That command **rebuilds the schema by replaying migrations** —
`config/packages/zenstruck_foundry.yaml` sets `orm.reset.mode: migrate`.
Don't set it back to `schema`: that mode runs
`doctrine:schema:drop --full-database`, which drops the unmapped
`doctrine_migration_versions` table, after which the entrypoint's
`doctrine:migrations:migrate --all-or-nothing` replays migration 1 onto a
live schema and restart-loops the container. If you meet the loop, the
non-destructive fix is `doctrine:migrations:version --add --all` after
`doctrine:schema:validate` confirms the schema is in sync (`done.md`,
2026-09-06).

**After changing an entity:**

```bash
docker compose exec php bin/console make:migration --no-interaction
# review the generated file in migrations/ before running it
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

**Tailwind CSS** doesn't rebuild itself: run
`docker compose exec php bin/console tailwind:build` after a styling change,
or `tailwind:build --watch` in the background during template work. The
**first** build is the entrypoint's job, not yours — it runs `tailwind:build`
when `var/tailwind/*.built.css` matches nothing, which is the case on every
fresh checkout (`var/` is gitignored and `compose.override.yaml` hides it
behind an anonymous volume). Without it `base.html.twig` throws and every
page 500s, `/login` included. The glob is the environment check: production
bakes the CSS in at build time, so the step is skipped there — don't add an
`APP_ENV` branch. It also puts a cold `up -d --wait` past three minutes,
which is why `compose.override.yaml` overrides `start_period` to `5m` in dev
only (`done.md`, 2026-09-22).

**Don't reintroduce:** the base `10-app.ini` sets
`opcache.enable_file_override=1` (a prod optimization). Under FrankenPHP's
worker mode it caches `filemtime()`/`file_exists()` for the life of the
process, hiding template/PHP edits in dev until a restart.
`frankenphp/conf.d/20-app.dev.ini` turns it off for dev — leave it.

**Testing conventions** (tooling:
[ADR 0004](docs/adr/0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md)):

- PHPUnit attributes (`#[Test]`), `WebTestCase` + `#[ResetDatabase]`
  (Foundry, per-test rollback via DAMA doctrine-test-bundle).
- `allow-contrib: false` is deliberate: contrib packages are wired by hand
  (`config/bundles.php`, `config/packages/`, `phpunit.dist.xml`). Wire a
  future one the same way, never flip the flag
  ([ADR 0003](docs/adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)).
- In `WebTestCase`, `static::createClient()` must be the **first** call in
  every test — Foundry factories auto-boot the kernel, and booting it twice
  throws. Client first, then fixtures.
- Factories produce values a database round trip would return —
  `ActivityFactory` dates to midnight
  ([ADR 0024](docs/adr/0024-treat-dates-as-calendar-days-in-nairobi-time.md)).
- **Login throttling does not fire in the test environment, by design —
  don't write a test asserting that it does.** `security.yaml` throttles
  logins (5 per 15 minutes) with counters in `cache.rate_limiter`; on the
  default file-backed pool only failed logins counted and nothing reset
  them, so the suite locked itself out after a few runs.
  `config/packages/cache.yaml` puts that pool on `cache.adapter.array` under
  `when@test`, and since cache pools are reset at every request boundary of
  the test client (`disableReboot()` doesn't prevent it), throttling is
  inert in tests. Unchanged in dev and prod. See `done.md`, 2026-09-03.
- Every required `TextType`/`EmailType` field on a non-nullable `string`
  property needs `'empty_data' => ''` — otherwise a submitted empty string
  becomes `null` and 500s instead of showing a validation error. The mirror
  case: an optional field (`VolunteerFormType`'s `lastName`) has
  `required: false` and **no** `empty_data`, so "not recorded" is `null`
  only, never also `''`.
- The volunteer pickers on both activity forms list **volunteers with a current or upcoming stay
  only**, and the escort pickers **active escorts only**.
  `ActivityFormType` also backs edit, so its queries keep the activity's
  own volunteer and escorts selectable once deactivated, labelled
  `(inactive)`; any future "active only" picker on an edit form needs the
  same escape hatch, or old records become uneditable — and an expanded
  multi-select like escorts silently drops the missing ones on save.
- `tests/Functional/RouteSmokeTest.php` walks **every GET route**, so a new
  route is covered for free. Don't relax `catchExceptions(false)` or the
  2xx assertion, and add a new entity's id to its prefix map rather than
  assuming id `1`
  ([ADR 0004](docs/adr/0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md)).
- `tests/E2E/` holds one real-browser Panther smoke test
  ([ADR 0007](docs/adr/0007-adopt-panther-for-adhoc-visual-verification.md)),
  excluded from the default run:
  `docker compose exec php php bin/phpunit --testsuite="End-to-End Test Suite"`.
  The browser hits Panther's webserver in another process, so the class
  needs `#[DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback]` for
  Foundry fixtures to be visible, and because Turbo Drive makes every form
  submission asynchronous, assert only after an explicit
  `$client->wait()->until(...)`.
- **Ad-hoc visual verification** (a one-off "does this render" check, not a
  regression test): use `scripts/panther-screenshot.php` against the running
  dev app — Panther, not Playwright
  ([ADR 0007](docs/adr/0007-adopt-panther-for-adhoc-visual-verification.md)).
  Screenshots land in `var/screenshots/`, outside the dev bind mount, so
  pull them with `docker compose cp`:

  ```bash
  docker compose exec php php scripts/panther-screenshot.php \
    --login --email=ronan.guilloux@gmail.com --password=<dev-password> \
    --path=/reports --width=375 --height=812 \
    --wait-selector='header' --out=mobile-nav.png
  docker compose cp php:/app/var/screenshots/mobile-nav.png ./mobile-nav.png
  ```

- `scripts/gremlins.php` — monkey testing on the running dev app
  ([ADR 0004](docs/adr/0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md)).
  **Local container only, no override — the horde clicks Delete**; reseed
  afterwards. The `--seed` in its summary reproduces a finding.

  ```bash
  docker compose exec php php scripts/gremlins.php --login \
    --email=ronan.guilloux@gmail.com --password=<dev-password> \
    --path=/activities/new-batch --seed=1 --gremlins=500
  docker compose exec php bin/console foundry:load-fixtures --no-interaction
  ```

- `composer infection` — mutation testing, scoped in `infection.json.dist`,
  run manually (not in `composer quality`). It's two steps because
  Infection's own coverage run fails under this FrankenPHP PHP build (an
  Xdebug-restart incompatibility): coverage via plain `php bin/phpunit`
  first, then `infection --skip-initial-tests`.

**Quality checks**
([ADR 0005](docs/adr/0005-adopt-phpstan-php-cs-fixer-rector-composer-audit.md)):

```bash
docker compose exec php composer quality    # cs-check + phpstan + phpat + security-audit
docker compose exec php composer cs-fix     # auto-fix style (Symfony + PER-CS2.0)
docker compose exec php composer rector     # preview refactors — dry-run only, review before applying
```

- PHPStan level `max` with `phpstan-baseline.neon`: shrink the baseline,
  never delete it to hide errors, never raise the level without
  regenerating and committing it.
- **Never apply Rector output without reviewing the diff** — it once
  turned `User::eraseCredentials()` into a broken `serialize()` stub. There
  is deliberately no `rector-fix` script.
- `.githooks/pre-commit` runs `composer quality` — enable once per checkout
  with `git config core.hooksPath .githooks`. Bypass deliberately with
  `--no-verify`, never by disabling the hook.
- The same hook warns (non-blocking) when `docs/project/next-steps.md`
  passes 250 lines: item text is leaking into the index. Don't raise the
  cap; move the text to its card, `done.md` or an ADR. `backlog/` has no
  cap.

**Deployment**
([ADR 0010](docs/adr/0010-build-in-ci-and-deploy-by-image-pull.md),
[ADR 0017](docs/adr/0017-host-production-on-gandicloud-vps-in-france.md);
requirements in [`hosting-plan.md`](docs/project/hosting-plan.md), runbook
in [`deployment-plan.md`](docs/project/deployment-plan.md)):

- CI builds the production image and pushes it to GHCR; the server only
  pulls.
- **CI's bare `docker run -v "$PWD:/app"` is not the environment anyone
  develops in.** No `compose.override.yaml` (so no dev ini, no
  `APP_ENV`/`XDEBUG_MODE`, no named volumes), and the mount lands *on top
  of* the image's `/app`, hiding everything the build wrote outside
  `vendor/` — `var/` and `assets/vendor/` are empty, being gitignored. A
  command that works via `docker compose exec` proves nothing about CI;
  replicate the bare `docker run` against a clean clone (`done.md`,
  2026-09-04).
- `--entrypoint php` is needed for any bare `docker run` of a console
  command: the entrypoint waits for a database and fails outside compose.
- **Always pass both compose files** —
  `docker compose -f compose.yaml -f compose.prod.yaml …`. A bare
  `docker compose up -d` loads `compose.override.yaml` and runs production
  in `APP_ENV=dev` with Xdebug and a bind mount.
- **SSH as `deploy` for anything in `/opt/mikono`, never as `debian`.**
  `debian` is the host admin login (`apt`, `cron`, `sudo`) and owns nothing
  there; `deploy` owns the checkout and is in the `docker` group. As
  `debian` a deploy fails on git's "dubious ownership" and, but for the
  owner check in `scripts/deploy.sh`, would skip its pre-deploy backup.
  `deployment-plan.md` §3 is about `debian`; §6, the routine deploy, about
  `deploy`.
- `APP_SECRET` is a **runtime** variable, never a build argument — the
  image is public.
- In `frankenphp_prod_builder`, `tailwind:build` must run **before**
  `asset-map:compile`, or the image build fails. Don't reorder or drop it.
- `date.timezone` is `Africa/Nairobi`, not UTC
  ([ADR 0024](docs/adr/0024-treat-dates-as-calendar-days-in-nairobi-time.md));
  `frankenphp/conf.d/10-app.ini` is copied into the image, so changing it
  needs `docker compose build php`.
- Backups: `scripts/backup-db.sh` (host-side, hot `VACUUM INTO`, no
  downtime, no `sqlite3` binary needed).
- **Which screens get used:** open `/usage` (Settings → Usage, admin only).
  No `gtag`, Plausible or Matomo goes into this app
  ([ADR 0021](docs/adr/0021-read-usage-from-an-in-app-usage-screen-over-the-caddy-access-log.md)).
  For a question the screen doesn't answer, the log is NDJSON and `jq`
  reads it directly — keep the prefetch filter:

  ```bash
  docker compose exec php cat /app/var/log/access.log \
    | jq -r 'select(.request.headers["X-Sec-Purpose"] == null) | .request.uri' \
    | sed 's/?.*//' \
    | grep -Ev '^/(assets|brand)/|favicon' \
    | sort | uniq -c | sort -rn | head -20
  ```

  `docker compose logs php` no longer carries request lines (only the
  app's Monolog errors), because `CADDY_SERVER_LOG_OPTIONS` in
  `compose.yaml` sends them to that file.

**What's next:** [`docs/project/next-steps.md`](docs/project/next-steps.md).
**What's already been done:** [`docs/project/done.md`](docs/project/done.md).
