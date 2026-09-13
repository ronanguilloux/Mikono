# 7. Use Symfony Panther for every real-browser task, not playwright-php

Date: 2026-09-13

## Status

Accepted

## Context

Three things in this repo need a real browser rather than `WebTestCase`'s
in-process client:

- **`tests/E2E/VolunteerManagerSmokeTest.php`** — one smoke test of the
  critical path (login → create Volunteer → create Activity → see it in the
  list and `/reports` → mobile nav), catching what a no-JS client cannot:
  Turbo navigation, Tailwind actually rendering, the mobile menu.
- **`scripts/panther-screenshot.php`** — ad-hoc "does this render" checks an
  agent runs mid-task against the already-running dev app, with no PHPUnit,
  no test webserver and no fixtures.
- **`scripts/gremlins.php`** — the gremlins.js monkey-testing harness, which
  uses the same client as a browser driver.

Chromium and chromium-driver are already installed in the shared
`frankenphp_base` Docker stage (the `symfony/panther` recipe), and
`symfony/panther` is `require-dev`. That capability persists in the image
layer; anything installed in an agent's scratchpad is wiped each session.

Zenstruck Browser has deprecated Panther in favour of
`playwright-php/playwright` (v1.4.0, August 2026), which runs the Symfony
kernel in the test process and so offers container access, the profiler,
`dama/doctrine-test-bundle` rollback and parallel runs. Panther cannot:
its browser and the app sit in separate processes, which is why the E2E
class carries `#[SkipDatabaseRollback]`. Node itself is not an objection —
[ADR 0016](0016-admit-nodejs-as-a-test-dependency-not-as-application-code.md)
admits it as a test dependency.

## Decision

**All three real-browser uses stay on Symfony Panther.**

- The E2E suite stays **one** test; everything else is `WebTestCase`.
- Ad-hoc checks use Panther's `Client::createChromeClient()` directly
  (not `PantherTestCase`) in `scripts/panther-screenshot.php`: `--path`,
  optional `--login` (credentials from flags or `PANTHER_LOGIN_*` env vars,
  never persisted), `--width`/`--height`, `--wait-selector` for Turbo Drive,
  repeatable `--click`, output to `var/screenshots/`.
- Every benefit of playwright-php scales with the size of the E2E suite,
  and that suite is one test: one `#[SkipDatabaseRollback]` line is not a
  burden, there is nothing to parallelise, and the container and profiler
  are already reachable from the in-process tests. "Deprecated" is
  Zenstruck Browser's verdict on its own wrapper, which this project does
  not use.

**Reopen trigger:** Panther breaks on a PHP or Chromium bump and is not
fixed promptly, **or** the E2E suite grows past roughly three tests. Either
alone is enough.

## Consequences

- **Positive:** no new toolchain, nothing to install per session, one
  browser API shared by the test and both scripts. No npm supply-chain
  surface, which matters because `composer audit` cannot see npm packages.
- **Negative / trade-offs:** screenshots land in `var/`, which the dev bind
  mount excludes, so they need a `docker compose cp`. The project stays on a
  component the ecosystem is leaving, and the migration cost grows with each
  E2E test added. `#[SkipDatabaseRollback]` stays a standing exception, and
  E2E tests cannot reach the container or profiler.
- **Reversibility:** cheap, and cheaper the sooner it's done: two files use
  Panther directly, `gremlins.php` uses it only as a driver.

## Alternatives considered

### 1. Migrate everything to playwright-php now

**Rejected.** Its real benefits serve an E2E suite this project does not
have, in exchange for an npm surface `composer audit` cannot inspect —
a certain cost against an uncertain one.

### 2. Migrate only `tests/E2E/`, keep Panther for the scripts

**Rejected.** Two browser stacks and two sets of conventions, the npm
surface arrives anyway, and the one test that would move is the one whose
workaround already works.

### 3. One Panther test per CRUD screen

**Rejected.** Redundant with `WebTestCase` coverage at far higher runtime
cost, for no new signal.

### 4. Reuse `PantherTestCase` for ad-hoc checks

**Rejected.** Brings PHPUnit, Foundry fixtures, rollback and a built-in
webserver to a check that should point at the dev app being worked on.

### 5. Human eyeballing only

**Rejected.** A headless agent session has no display; a screenshot is the
only way it can verify rendering itself.
