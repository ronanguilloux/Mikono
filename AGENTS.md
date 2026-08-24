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
- `docs/project/` — living status docs (current build progress, what's
  left) — mutable, unlike the two above. Start here for "where does
  this build actually stand right now": `docs/project/next-steps.md`.
- `.agents/skills/` — Agent Skills, source of truth, shared across
  Claude Code, Gemini CLI, and Codex. See `.agents/skills/README.md`.
- `.claude/agents/` — Claude Code-specific subagents (`adr-scribe`,
  `context-capturer`). Not portable, unlike `.agents/skills/`.
- `src/Entity/`, `src/Repository/` — Doctrine entities (`User`,
  `Volunteer`, `Project`, `ActivityType`, `Activity`) and their
  repositories, each with a `countReferencingActivities()` delete-guard
  where applicable.
- `src/Enum/` — backed PHP enums (`ProjectLocation`, `ProjectOwnership`,
  `ActivityDuration`), mapped as plain strings — portable off SQLite.
- `src/Controller/`, `src/Form/`, `templates/<area>/` — one set per CRUD
  area, all following the same index/new/edit/delete shape. Reuse the
  `DataTable` TwigComponent (`src/Twig/Components/DataTable.php` +
  `templates/components/DataTable.html.twig`) and the Tailwind form
  theme (`templates/form/tailwind_theme.html.twig`, registered globally
  in `config/packages/twig.yaml`) rather than hand-styling a new area.
- `src/Report/ActivitySummaryCalculator.php` — the one piece of real
  domain logic (duration-to-days aggregation for `/reports`).
- `src/Factory/` — Foundry v2 factories (`PersistentObjectFactory`, real
  objects) for every entity, used by both tests and (eventually) dev
  fixtures.
- `tests/Functional/` — WebTestCase functional tests, one per
  controller area plus `SecurityControllerTest`.

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
Seeded VM login: `ronan.guilloux@gmail.com` / a temporary dev password
set via `app:user:create` — change it by re-running that command (it's
idempotent) rather than looking it up here.

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
  `phpunit.dist.xml` instead of flipping that global flag. If a future
  contrib package is needed, wire it the same way rather than enabling
  `allow-contrib` project-wide.
- In `WebTestCase` tests, `static::createClient()` must be the **first**
  call in every test method — Foundry factories auto-boot the kernel to
  reach Doctrine, and booting it twice throws. Create the client, then
  create fixtures, not the other way around.
- Every required `TextType`/`EmailType` form field needs
  `'empty_data' => ''` when its entity property is a non-nullable
  `string` — Symfony transforms a submitted empty string to `null` by
  default, which crashes with a 500 against a non-nullable property
  instead of showing a validation error. Already applied to every
  existing form; apply it to any new required text field too.

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

**Current build status and what's left:** see
[`docs/project/next-steps.md`](docs/project/next-steps.md) — kept up to
date as work completes, unlike this file. Don't assume Panther,
Infection, or dev fixtures are wired until that file says so.
