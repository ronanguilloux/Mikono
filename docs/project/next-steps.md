# Next steps

**Last updated:** 2026-08-24, mid-phase-11 of the original 12-phase
build plan (`docs/brainstorm/02-volunteer-manager-v0.1-context.md`,
ADRs [0003](../adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)/[0004](../adr/0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md)).
This file gets overwritten as work completes — it's a snapshot, not a
history. For history, read `git log` or `docs/adr`/`docs/brainstorm`.

## App status: feature-complete and usable

All five CRUD areas (Volunteers, Projects, Activity Types, Users,
Activities), auth, and the Reports view work end to end, verified via
curl throughout the build and now also by 25 passing automated tests.
Run it: `docker compose up -d --wait`, then `https://localhost` (see
`AGENTS.md` for login and full command reference).

12 commits so far, most recent first:

```
1ff9748 Add the functional test suite (25 tests, all green)
a32e6fa Add the reporting view — v0.1's last product feature
50df711 Add Activities CRUD — the core feature
428d86b Add Users CRUD (admin-only)
814bd1c Add Projects and Activity Types CRUD
5c6cde6 Add Volunteers CRUD and the reusable DataTable component
528dd5a Add Tailwind CSS + Symfony UX, responsive base layout
593aba2 Add authentication: User entity, login/logout, deactivation guard
62e5598 Wire Doctrine ORM + SQLite
0b67194 Scaffold Symfony 8.1 skeleton via Docker auto-bootstrap
f735c2d Add Docker + FrankenPHP scaffolding for the Volunteer Manager app
53f2a88 Bootstrap decision-capture workflow and cross-agent Agent Skills
```

## Test suite: 25/25 passing

PHPUnit 13.3.1, `symfony/test-pack`, Zenstruck Foundry v2 (factories
per entity under `src/Factory/`), `dama/doctrine-test-bundle`. Seven
files under `tests/Functional/`: full CRUD lifecycle for Volunteers
(the representative case); lighter create+delete-guard tests for
Projects and Activity Types; the Bright Achievers worked example
end-to-end plus a `loggedBy`-immutability test for Activities; role
enforcement, password hashing via a real login round-trip,
deactivation, and self-delete-guard for Users; aggregate correctness
for Reports; the full auth lifecycle for Security. Run:
`docker compose exec php php bin/phpunit`.

## Remaining work

**Rest of phase 11 (test/quality tooling — none of this is installed
yet):**

- [ ] PHPStan — greenfield, no baseline needed, start at a high level
      (per the `php-modernization` skill's guidance).
- [ ] PHPat — one rule per ADR 0004: `src/Entity/*` must not depend on
      `src/Controller/*` or `src/Twig/*`. Informational, not a merge
      gate, at this app's size.
- [ ] One Panther E2E smoke test — login, create Volunteer, create
      Activity, see it in the list and `/reports`, including a
      narrow-viewport check for the mobile nav. Deliberately not one
      Panther test per CRUD screen (redundant with the functional
      suite, per ADR 0004).
- [ ] Infection, scoped only to `src/Report/ActivitySummaryCalculator.php`
      and the delete-guard methods in the five repositories — not the
      whole app, since most of the codebase is Symfony/Doctrine/Form
      boilerplate with little conditional logic worth mutating.
- [ ] `rector` and `friendsofphp/php-cs-fixer` — planned in the
      original package list, not yet `composer require`d.

**Phase 12 (not started):**

- [ ] Foundry dev fixtures — a `DemoDataStory` seeding realistic data,
      including the literal Bright Achievers example. A stub
      `src/Story/AppStory.php` already exists (auto-generated empty by
      the Foundry Flex recipe) — fill it in or replace it.
- [ ] Final sanity pass: re-read `AGENTS.md` once everything above
      lands and confirm it's still accurate; delete the "Not yet
      wired" line there once nothing is left unwired.

## Known conventions to not violate (see `AGENTS.md` for the full list)

- `static::createClient()` must be the first call in every
  `WebTestCase` test method, before any Foundry factory call.
- Every required `TextType`/`EmailType` form field needs
  `'empty_data' => ''` if its entity property is a non-nullable
  `string`.
- `opcache.enable_file_override` must stay off in
  `frankenphp/conf.d/20-app.dev.ini` (dev only) — it's a real prod
  optimization, but breaks live-reload under FrankenPHP's worker mode.
- Any future contrib Flex recipe (like `dama/doctrine-test-bundle`)
  gets wired by hand, not by flipping `composer.json`'s
  `allow-contrib` flag project-wide.
