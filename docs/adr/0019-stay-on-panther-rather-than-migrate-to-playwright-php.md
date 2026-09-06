# 19. Stay on Symfony Panther rather than migrate to playwright-php

Date: 2026-09-06

## Status

Accepted

Confirms, and does not supersede, the decision in
[ADR 0007](0007-adopt-panther-for-adhoc-visual-verification.md). ADR 0007
reached the right answer on reasoning that
[ADR 0016](0016-admit-nodejs-as-a-test-dependency-not-as-application-code.md)
has since retired; this record re-decides it on grounds that survive.

## Context

[ADR 0007](0007-adopt-panther-for-adhoc-visual-verification.md) chose
Panther over Playwright partly because "the project deliberately has no
Node". [ADR 0016](0016-admit-nodejs-as-a-test-dependency-not-as-application-code.md)
(2026-09-05) retired that premise: Node is admitted as a test dependency
and an ancillary tool, never as application code. It deliberately did not
decide what to do about Panther, leaving an open question in
`next-steps.md`.

Meanwhile Zenstruck Browser deprecated Panther in favour of
[`playwright-php/playwright`](https://packagist.org/packages/playwright-php/playwright)
(v1.4.0, August 2026), which runs the Symfony kernel **in the test
process**. That is a genuine capability difference, not a taste one:
container access, the profiler, `dama/doctrine-test-bundle` rollback, and
parallel runs — none of which Panther can offer, because its browser and
the application necessarily sit in separate processes. It is exactly why
[`tests/E2E/VolunteerManagerSmokeTest.php`](../../tests/E2E/VolunteerManagerSmokeTest.php)
carries `#[SkipDatabaseRollback]` today.

What Panther drives here is small: **one** E2E smoke test, and
[`scripts/panther-screenshot.php`](../../scripts/panther-screenshot.php)
for ad-hoc visual checks. Both work. The 197-test suite is green, and the
new [`scripts/gremlins.php`](../../scripts/gremlins.php) monkey-testing
harness rides on the same Panther client.

The narrative is in
[`docs/brainstorm/05-exercising-the-app-and-the-panther-question.md`](../brainstorm/05-exercising-the-app-and-the-panther-question.md).

## Decision

**`tests/E2E/` and the two Panther-driven scripts stay on Symfony
Panther. `playwright-php` is not adopted.**

Deprecated in a third-party wrapper is not broken, and ADR 0016 removed
an objection without supplying a reason. Every benefit the migration
offers is proportional to the size of the E2E suite, and that suite is
one test — `#[SkipDatabaseRollback]` on a single class is a documented
line in `AGENTS.md`, not a burden; there is nothing to parallelise; and
the container and profiler are already reachable from the 197 tests that
run in-process through `WebTestCase`.

**Reopen trigger:** Panther breaking on a PHP or Chromium bump and not
being fixed promptly, **or** the E2E suite outgrowing roughly three tests
such that rollback, container access and parallel runs start paying for
the migration rather than being nice to have. Either alone is enough.

## Consequences

- **Positive:** no work, no rewrite of two working files, and no npm
  supply-chain surface — which matters concretely here, because
  `composer audit` (part of `composer quality`, per
  [ADR 0005](0005-adopt-phpstan-php-cs-fixer-rector-composer-audit.md))
  cannot see npm packages, so that dependency surface would sit outside
  the project's only automated security check. The dev image stays as it
  is.
- **Negative / trade-offs:** the project stays on a component another
  part of the ecosystem has moved off, so the migration cost grows
  quietly with each E2E test added. `#[SkipDatabaseRollback]` remains a
  standing exception a newcomer has to be told about, and E2E tests
  cannot reach the container or the profiler.
- **Reversibility:** cheap, and gets cheaper the sooner it is done. Two
  files use Panther directly; a third (`gremlins.php`) uses it only as a
  browser driver and would port on the same day. ADR 0016 already cleared
  the ground, so a superseding ADR needs only the trigger above, not a
  fresh argument about Node.

## Alternatives considered

### 1. Migrate to `playwright-php` now, because Panther is deprecated

**Rejected.** "Deprecated" here is Zenstruck Browser's judgement about
its own abstraction, and this project does not use Zenstruck Browser —
it uses Panther directly. The migration's real benefits serve a test
suite this project does not have yet, and buying an npm supply-chain
surface that `composer audit` cannot inspect in order to pre-empt a
deprecation is paying a certain cost against an uncertain one.

### 2. Migrate only `tests/E2E/`, keep Panther for the ad-hoc scripts

**Rejected.** The worst of both: two browser stacks to keep working, two
sets of conventions in `AGENTS.md`, the npm surface arrives anyway, and
the one test that would migrate is the one whose `#[SkipDatabaseRollback]`
already works fine.
