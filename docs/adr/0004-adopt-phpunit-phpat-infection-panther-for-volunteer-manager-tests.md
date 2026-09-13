# 4. Test with PHPUnit, PHPat and scoped Infection, and exercise the app with a route walk and a monkey horde

Date: 2026-09-13

## Status

Accepted

## Context

The app needs a real test suite: functional coverage of every CRUD area and
the login flow, and unit coverage of the reporting calculation. Most of the
codebase is Symfony/Doctrine/Form boilerplate; the hand-written logic is
small and concentrated (`src/Report/`, the delete-guards). See
[`docs/brainstorm/02-volunteer-manager-v0.1-context.md`](../brainstorm/02-volunteer-manager-v0.1-context.md).

Tests written per screen still leave two kinds of crash uncovered. Crashes
from **coverage**: a screen nobody wrote a test for, which on an app this
size is where breakage leaks. Crashes from **sequences**: an order of
clicks and inputs nobody thought to script. The narrative is in
[`docs/brainstorm/05-exercising-the-app-and-the-panther-question.md`](../brainstorm/05-exercising-the-app-and-the-panther-question.md).

## Decision

**PHPUnit with attribute-based tests, one PHPat architecture rule, Infection
scoped to the hand-written logic, plus two exercise tools: a route walk for
coverage and a gremlins.js horde for sequences.** Real-browser tooling is
[ADR 0007](0007-adopt-panther-for-adhoc-visual-verification.md).

- **PHPUnit** (13), `#[Test]`/`#[DataProvider]` attributes, no PHPDoc
  annotations; `symfony/phpunit-bridge` for deprecations. Functional tests
  use `WebTestCase` with Foundry and DAMA rollback.
- **PHPat**, layered on PHPStan, with one rule: `src/Entity/*` must not
  depend on `src/Controller/*` or `src/Twig/*`
  (`tests/Architecture/ArchTest.php`). It runs in `composer quality`, so it
  blocks the pre-commit hook and CI
  ([ADR 0005](0005-adopt-phpstan-php-cs-fixer-rector-composer-audit.md)).
- **Infection**, scoped to `ActivitySummaryCalculator` and the delete-guard
  repository methods (`infection.json.dist`). Run manually, not part of
  `composer quality`.
- **`tests/Functional/RouteSmokeTest.php`**, hand-written, walks every GET
  route the router reports, so a route added tomorrow is covered for free —
  both that it renders and that it still requires login. Load-bearing:
  - `catchExceptions(false)`, or `WebTestCase` renders the error page and a
    500 arrives as a failed assertion with no stack trace.
  - It asserts **2xx**, not "below 500": a missing fixture 404s, and a
    `< 500` check would pass while hiding the errors the walk exists to find.
  - `{id}` comes from an explicit map of seeded entities, matched
    longest-prefix-first (`activity_type_` before `activity_`), never an
    assumed id `1` — DAMA's rollback doesn't reliably reset SQLite's rowid
    sequence.
  - It logs in as an admin, because `/users*` is `ROLE_ADMIN`.
- **`scripts/gremlins.php`** injects one pinned gremlins.js dist file into
  the running dev app through Panther and lets a seeded horde click, type
  and scroll. Run manually, after coverage is handled. Load-bearing:
  - **It refuses any host but the local container, with no override flag:**
    the horde clicks Delete.
  - Turbo Drive navigation is pinned during the run, so findings stay
    attributable to one page.
  - It reports how many events actually landed, because a clean run only
    means something if the horde wasn't inert, and it exits non-zero on a
    finding with a `--seed` that reproduces it.

## Consequences

- **Positive:** PHPUnit is Symfony's default. Scoped PHPat and Infection give
  signal on real logic without measuring framework code. The route walk
  makes "a screen nobody tested" impossible to ship broken, at no per-route
  cost.
- **Negative / trade-offs:** most CRUD code has no mutation or architecture
  coverage — revisit if real domain logic grows beyond `src/Report/`. The
  route walk requests clean URLs only, so it proves screens render, not that
  they survive malformed input
  ([ADR 0023](0023-degrade-malformed-query-input-to-a-default.md)). The horde
  mutates dev data and needs a reseed after each run.
- **Reversibility:** Pest runs on the same engine, so switching is possible;
  not planned. Both exercise tools are single files with nothing depending
  on them.

## Alternatives considered

### 1. Pest

**Rejected** by the product owner before any code was written, in favour of
PHPUnit's more standard, ecosystem-aligned tooling.

### 2. Infection and PHPat across the whole codebase

**Rejected.** For a CRUD app this mostly measures the framework's own code.

### 3. A route-walking smoke package (`http-smoke-testing`, `Pierstoval/SmokeTesting`)

**Rejected.** With around thirty routes, one hand-written test over the
router is shorter than a dependency, and the part that matters —
`catchExceptions(false)` and a strict status assertion — has to be got right
either way.

### 4. Monkey testing as the first exercise tool

**Rejected as the opening move.** On an app this size random clicking finds
less than a coverage walk, and Turbo Drive makes its logs hard to read. It
stays the tool for sequence crashes once coverage is handled.
