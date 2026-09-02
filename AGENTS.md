# Mikono

UCESCO Volunteer Manager (VM) — a Symfony 8.1 web app so UCESCO's
Volunteer Manager can track volunteers working at UCESCO's projects in
Kibera (Nairobi) and Mombasa. v0.1 scope: login-protected CRUD for
Volunteers, Projects, Activity Types, Users, and Activities (the log
entries), plus a basic per-volunteer/per-project report. See
`docs/adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md`
for the stack decision and `docs/brainstorm/02-volunteer-manager-v0.1-context.md`
for the full narrative.

## Decision capture

Architectural decisions are recorded as they're made, not reconstructed
later:

- The narrative behind a new feature or slice — audience, desired impact,
  rejected alternatives, constraints — goes in `docs/brainstorm/` first,
  via the `context-capturer` Claude Code subagent.
- Once a decision is finalized, it's locked in as a permanent record in
  `docs/adr/`, via the `adr-scribe` Claude Code subagent. ADRs are
  immutable once accepted — a changed decision gets a new ADR that
  supersedes the old one.

Every non-trivial architectural decision (stack, structure, service
boundaries, data model) gets an ADR before or alongside the code that
implements it. See `docs/adr/README.md` and `docs/brainstorm/README.md`.

## Directory map

- `docs/adr/` — Architecture Decision Records, one immutable file per
  decision.
- `docs/brainstorm/` — narrative context behind a feature or slice,
  written before the decision it leads to is locked in.
- `docs/project/` — living status docs, mutable unlike the two above:
  `next-steps.md` (forward-only — what's next, nothing already done)
  and `done.md` (growing log of completed work that isn't itself an
  ADR). See `docs/project/README.md` for the rule on which one a
  finished item goes to.
- `.agents/skills/` — Agent Skills, source of truth, shared across
  Claude Code, Gemini CLI, and Codex. See `.agents/skills/README.md`.
- `.claude/agents/` — Claude Code-specific subagents (`adr-scribe`,
  `context-capturer`). Not portable, unlike `.agents/skills/`.
- `src/Entity/`, `src/Repository/` — Doctrine entities (`User`,
  `Volunteer`, `Project`, `ActivityType`, `Activity`, `Escort`) and their
  repositories, each with a `countReferencingActivities()` delete-guard
  where applicable. Two shapes here were set by what the real rosters
  actually say, so don't "tidy" them back: `Activity::$escorts` is a
  **collection**, because the VM writes "Accompanied by Edna and Sam"
  ([ADR 0013](docs/adr/0013-record-every-escort-on-an-activity.md)) — the
  escort delete-guard is a `MEMBER OF` query, and the eager escorts join
  is to-many, so it can't carry a `LIMIT`; and `Volunteer::$lastName` is
  **optional**, because the rosters name volunteers by first name only
  ([ADR 0014](docs/adr/0014-make-a-volunteers-last-name-optional.md)).
- `src/Enum/` — backed PHP enums (`ProjectLocation`, `ProjectOwnership`,
  `ActivityDuration`), mapped as plain strings — portable off SQLite.
- `src/Controller/`, `src/Form/`, `templates/<area>/` — one set per CRUD
  area, all following the same index/new/edit/delete shape. Reuse the
  `DataTable` TwigComponent (`src/Twig/Components/DataTable.php` +
  `templates/components/DataTable.html.twig`) and the Tailwind form
  theme (`templates/form/tailwind_theme.html.twig`, registered globally
  in `config/packages/twig.yaml`) rather than hand-styling a new area.
  `DataTable` also takes an optional `pagination` (renders the controls
  below the table), `withActions` (set `false` for a read-only table,
  or it grows a phantom empty actions column) and `sortState` (turns the
  headers into sort links; leave it null for a table with nothing to
  re-order, like the Reports print panel).
- `src/Pagination/` — `ListPaginator`, the single place `page`,
  `perPage`, `sort` and `direction` are read off the query string for
  all seven list views, plus the `SortState` VO it hands to templates.
  Page sizes are whitelisted 25/50/100/All, default 25. Bad input never
  404s: an unknown size falls back to the default, `page` below 1 clamps
  to 1, `page` past the end serves the last page, and an unknown `sort`
  leaves the view's own order alone. Don't reach for
  `InputBag::getInt()` here — in Symfony 8 it throws on non-numeric
  input, which turns `?page=abc` into a 400; `sort`/`direction` are read
  through `query->all()` for the same reason, since `InputBag::get()`
  throws on `?sort[]=x`. See
  [ADR 0009](docs/adr/0009-adopt-knppaginatorbundle-for-list-pagination.md)
  and
  [ADR 0011](docs/adr/0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md).
  The controls markup lives in `templates/pagination/tailwind.html.twig`,
  included by `templates/components/PaginationBar.html.twig` — not
  rendered via `knp_pagination_render()`, which needs a translator this
  app deliberately doesn't have. Knp's *sortable* support is off for that
  same reason plus one more (it 500s on a field outside its allow list):
  `ListPaginator` passes `SORT_FIELD_PARAMETER_NAME => null` and resolves
  sorting itself. Making a column sortable is a one-line entry in that
  controller's `SORT_MAP` const (column key => DQL field(s), or array key
  on `/reports`) — the map *is* the whitelist, so nothing a reader types
  reaches DQL, and a column opts out by simply not being in it. Don't add
  a second sortability flag to the column definition. Every sort keeps the
  repository's own `ORDER BY` as a tie-break; drop that and a paginated
  sort on a low-cardinality column repeats rows across pages. The mobile
  equivalent of clickable headers is `templates/components/SortSelect.html.twig`,
  used by the Activities index because its table is inside `hidden md:block`.
- `src/Report/` — the app's real domain logic:
  `ActivitySummaryCalculator` (duration-to-days aggregation for
  `/reports`), plus `RosterBuilder`/`QuietProjectFinder` and their
  readonly value objects behind the home screen. `QuietProjectFinder`
  covers projects only and never volunteers — that's deliberate and
  evidence-based, so read its class docblock before "completing" it.
- `src/Factory/` — Foundry v2 factories (`PersistentObjectFactory`, real
  objects) for every entity, used by both tests and dev fixtures
  (`src/Story/AppStory.php`).
- `src/Fixture/` — `RosterArchive` and its readonly VOs, which read
  `docs/fixtures/rosters.yaml`. **The dev/demo dataset is never
  generated**: every volunteer, escort, site, date and roster note in
  `AppStory` comes from that file, transcribed by hand from a month of
  the VM's real WhatsApp roster messages
  ([ADR 0012](docs/adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)).
  To grow the dataset, add to the archive — not to `AppStory`, and never
  with Faker. The raw WhatsApp exports (`docs/fixtures/*_dumps.txt`) are
  gitignored because they name sponsored children and donors; this repo
  is public. `docs/fixtures/README.md` has the transcription rules, and
  `tests/Integration/Fixture/RosterArchiveTest.php` enforces the ones a
  test can. Test factories may still generate — a pagination test needs
  twenty-six arbitrary escorts, not twenty-six real ones.
- `tests/Functional/` — WebTestCase functional tests, one per
  controller area plus `SecurityControllerTest`.
- `tests/Integration/` — KernelTestCase tests for `src/Report/` services
  that need the database but no HTTP layer.

## Stack

Docker + FrankenPHP, Symfony 8.1.5, PHP 8.5.9, SQLite, Tailwind CSS v4 +
Symfony UX (Turbo/Stimulus/TwigComponent; LiveComponent installed, not
yet used — see ADR 0003), PHPUnit 13 + Foundry v2 for tests. No host
PHP/Composer needed or expected — everything below runs through Docker.

**Day to day:**

```bash
docker compose up -d --wait        # start (first run auto-bootstraps the app)
docker compose down                # stop — the SQLite data survives (named volume)
docker compose exec php bin/console <command>
docker compose exec php composer require <package>
docker compose exec php php bin/phpunit
```

App: `https://localhost` (self-signed cert — accept the browser warning).
Seeded VM login: a single dev/test account and a temporary dev password,
both set via `app:user:create` — neither is recorded here (this repo is
public); re-run that command to set what you need rather than looking it
up. `--email`/`--full-name`/
`--password` (plus the existing `--admin` flag) make it a one-liner
instead of interactive prompts — dev/local convenience only, since the
password then lands in plaintext in shell history:

```bash
docker compose exec php bin/console app:user:create \
  --email=SOME_DEV_OR_TEST_ACCOUNT@gmail.com --full-name="Dave And Taste" --password=<new-password> --admin
```

**That local account is for AI agents, not for a human** — nobody signs
into the dev app with it by hand, so an agent that needs to log in (the
`scripts/panther-screenshot.php` flow below, mainly) may reset its
password with the command above without asking first, and doesn't need
to preserve whatever password was set before. It's a local, dev-only
SQLite account on `https://localhost` with seeded fixture data behind it;
nothing about it is shared with production, and no real password
belongs in this file — this repo is public.

Seed the dev data — the real August 2026 roster archive: 15 volunteers,
13 sites, 5 escorts and 90 activities, with the last two days anchored
onto today and tomorrow so the home screen's roster panels have
something to show:

```bash
docker compose exec php bin/console foundry:load-fixtures --no-interaction
```

**After changing an entity:**

```bash
docker compose exec php bin/console make:migration --no-interaction
# review the generated file in migrations/ before running it
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

**Tailwind CSS** doesn't rebuild itself automatically — either run
`docker compose exec php bin/console tailwind:build` once after a
styling change, or start `tailwind:build --watch` in the background for
a session of active template work.

**Known gotcha already fixed, don't reintroduce it:** the base
`10-app.ini` sets `opcache.enable_file_override=1` (a legitimate prod
optimization). Under FrankenPHP's persistent worker mode this silently
caches `filemtime()`/`file_exists()` results for the life of the
process, hiding template/PHP edits from the dev bind-mount until a
manual restart. `frankenphp/conf.d/20-app.dev.ini` turns it back off
for dev specifically — leave that override in place.

**Testing conventions:**

- PHPUnit, attribute-based (`#[Test]`), `WebTestCase` +
  `#[ResetDatabase]` (Foundry's PHPUnit extension, per-test rollback via
  DAMA doctrine-test-bundle).
- `dama/doctrine-test-bundle`'s Flex recipe is silently ignored
  (`composer.json` has `allow-contrib: false`, deliberately, from the
  original skeleton) — it's wired by hand in `config/bundles.php` and
  `phpunit.dist.xml` instead of flipping that global flag.
  `knplabs/knp-paginator-bundle` is the second package on this footing
  (`config/bundles.php` + `config/packages/knp_paginator.yaml`); note
  that a skipped contrib recipe also skips whatever *else* it would have
  enabled — that bundle's recipe would have turned on the translator its
  render helper needs. If a future contrib package is needed, wire it
  the same way rather than enabling `allow-contrib` project-wide.
- In `WebTestCase` tests, `static::createClient()` must be the **first**
  call in every test method — Foundry factories auto-boot the kernel to
  reach Doctrine, and booting it twice throws. Create the client, then
  create fixtures, not the other way around.
- Every required `TextType`/`EmailType` form field needs
  `'empty_data' => ''` when its entity property is a non-nullable
  `string` — Symfony transforms a submitted empty string to `null` by
  default, which crashes with a 500 against a non-nullable property
  instead of showing a validation error. Already applied to every
  existing form; apply it to any new required text field too. The mirror
  case is `VolunteerFormType`'s `lastName`, which is `required: false`
  with **no** `empty_data` — an optional field must store `null`, not an
  empty string, or "not recorded" ends up with two representations.
- The volunteer pickers on both activity forms list **active volunteers
  only** — volunteers leave after a few weeks, so the inactive ones are
  pure noise when logging attendance. `ActivityFormType` also backs the
  edit screen, so its query keeps the activity's own current volunteer
  selectable even once deactivated; any future "active only" picker on a
  form that edits existing rows needs the same escape hatch, or old
  records become uneditable.
- `tests/E2E/` holds one real-browser Symfony Panther smoke test
  (login → create Volunteer → create Activity → see it in the list and
  `/reports` → mobile-nav check). It's excluded from the default
  `docker compose exec php php bin/phpunit` run (slower, a real Chromium
  instance) — run it explicitly with
  `docker compose exec php php bin/phpunit --testsuite="End-to-End Test Suite"`.
  Since it's real, separate-process HTTP against Panther's built-in
  webserver, Foundry-created fixtures need
  `#[DAMA\DoctrineTestBundle\PHPUnit\SkipDatabaseRollback]` on the test
  class to actually commit (DAMA otherwise rolls back each test's writes,
  invisible to that other process) — and because this app uses Turbo
  Drive, every form submission is asynchronous, so assert on the
  resulting page only after an explicit `$client->wait()->until(...)`.
- For **ad-hoc visual verification** during active UI work (a one-off
  "does this render correctly" check, not a regression test) — don't
  install Playwright/Node. Use `scripts/panther-screenshot.php`, a
  standalone script built on Panther's `Client::createChromeClient()`
  (not `PantherTestCase`) that points directly at the already-running
  dev app at `https://localhost` and saves a screenshot to
  `var/screenshots/` (already covered by the blanket `/var/` gitignore
  entry — pull it to the host with `docker compose cp`, since `var/`
  is excluded from the dev bind-mount). See
  [ADR 0007](docs/adr/0007-adopt-panther-for-adhoc-visual-verification.md).
  Example:

  ```bash
  docker compose exec php php scripts/panther-screenshot.php \
    --login --email=ronan.guilloux@gmail.com --password=<dev-password> \
    --path=/reports --width=375 --height=812 \
    --wait-selector='header' --out=mobile-nav.png
  docker compose cp php:/app/var/screenshots/mobile-nav.png ./mobile-nav.png
  ```

- `composer infection` runs Infection, mutation-testing scoped to
  `src/Report/ActivitySummaryCalculator.php` and the delete-guard methods
  in the four repositories that have one (`infection.json.dist`) — not
  part of `composer quality` (slow, run manually). It's a two-step script:
  Infection's own coverage-generating run doesn't work reliably under
  this FrankenPHP build's PHP (an Xdebug-restart incompatibility), so the
  script generates coverage via plain `php bin/phpunit` first, then runs
  `infection --skip-initial-tests` against it.

**Quality checks** (see
[ADR 0005](docs/adr/0005-adopt-phpstan-php-cs-fixer-rector-composer-audit.md)
for the full decision):

```bash
docker compose exec php composer quality    # cs-check + phpstan + phpat + security-audit
docker compose exec php composer cs-fix     # auto-fix style (Symfony + PER-CS2.0)
docker compose exec php composer rector     # preview refactors — dry-run only, review before applying
```

- PHPStan runs at level `max` against `src/` and `tests/`
  (`phpstan.dist.neon`), with pre-existing findings absorbed in
  `phpstan-baseline.neon` — shrink it over time, never delete it to
  hide errors. Never raise the level without regenerating and
  committing the baseline.
- The one architecture rule from ADR 0004 (`Entity` must not depend on
  `Controller`/`Twig`) lives in `tests/Architecture/ArchTest.php`, run
  via `composer phpat` — PHPat, not Deptrac (see ADR 0005).
- **Never apply Rector output without reviewing the diff first** —
  it can produce technically-valid but wrong refactors (e.g. it once
  turned `User::eraseCredentials()` into a broken `serialize()` stub
  instead of removing it cleanly). `composer rector` is dry-run only by
  design; there is no `rector-fix` script. This matches the
  `php-modernization` skill's existing hard guardrail.
- A local pre-commit hook (`.githooks/pre-commit`) runs `composer
  quality` before every commit — enable it once per checkout with
  `git config core.hooksPath .githooks`. Bypass deliberately with
  `git commit --no-verify`, not by disabling the hook.

**Deployment** (see
[ADR 0010](docs/adr/0010-build-in-ci-and-deploy-by-image-pull.md),
[`docs/project/hosting-plan.md`](docs/project/hosting-plan.md) for what a
server must provide, and
[`docs/project/deployment-plan.md`](docs/project/deployment-plan.md) for
the runbook):

- CI builds the production image and pushes it to GHCR
  (`.github/workflows/`); the server only pulls. Nothing is built on the
  production host.
- **Always pass both compose files** —
  `docker compose -f compose.yaml -f compose.prod.yaml …`. A bare
  `docker compose up -d` silently loads `compose.override.yaml` and would
  run production in `APP_ENV=dev` with Xdebug and a bind mount.
- `APP_SECRET` is a **runtime** variable, never a build argument: the
  published image is public, and `composer dump-env prod` bakes
  build-time environment into it.
- In the `frankenphp_prod_builder` stage, `tailwind:build` must run
  **before** `asset-map:compile`. Outside the `test` env the Tailwind
  bundle is in strict mode and throws when no built CSS exists, failing
  the whole image build. Don't reorder or drop it.
- `date.timezone` is `Africa/Nairobi` in `frankenphp/conf.d/10-app.ini`,
  not UTC — the home screen's rosters resolve
  `new \DateTimeImmutable('today')` against it, and every user is in
  Kenya. That file is copied into the image, not bind-mounted, so
  changing it needs a `docker compose build php`.
- Backups: `scripts/backup-db.sh` (host-side, hot `VACUUM INTO`, no
  downtime, no `sqlite3` binary needed).

**What's next:** see
[`docs/project/next-steps.md`](docs/project/next-steps.md) (forward-only).
**What's already been done:** see
[`docs/project/done.md`](docs/project/done.md). Panther, Infection, and
dev fixtures are all wired as described above.
