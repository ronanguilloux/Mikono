# 4. Adopt PHPUnit, PHPat, Infection, and Panther for the Volunteer Manager app's test suite

Date: 2026-08-24

## Status

Accepted

## Context

The Volunteer Manager v0.1 app needs a real test suite from the start:
functional coverage of five CRUD areas (`User`, `Volunteer`, `Project`,
`ActivityType`, `Activity`), an authentication flow, and a reporting
calculation. See
[`docs/brainstorm/02-volunteer-manager-v0.1-context.md`](../brainstorm/02-volunteer-manager-v0.1-context.md)
for the full narrative, including the worked example this suite has to
cover end-to-end.

Earlier in that same planning conversation, Pest was a tentative choice —
but the product owner explicitly reversed that decision before any code was
written, preferring PHPUnit's more standard, ecosystem-aligned tooling.
No prior ADR in `docs/adr/` ever committed to Pest, so nothing here needs
its Status flipped to `Superseded`; this ADR's Alternatives considered
section records Pest as a rejected alternative like any other, not as a
superseded decision.

This ADR builds on the stack chosen in
[ADR 0003](0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)
(Symfony 8.1, PHP 8.4, Docker+FrankenPHP), which the test tooling below runs
inside.

## Decision

Adopt PHPUnit, latest version compatible with the chosen Symfony/PHP
versions, with attribute-based test declarations, plus PHPat for scoped
architecture rules, Infection for scoped mutation testing, and Panther for
one small real-browser end-to-end smoke test.

- **PHPUnit:** latest version compatible with Symfony 8.1 / PHP 8.4, rather
  than a version hard-pinned in this ADR — verify the actual
  Composer-resolved major at implementation time. This repo's own
  pre-staged `php-modernization` skill documents a "PHPUnit 12 → 13"
  migration in its reference material, which corroborates PHPUnit 13 being
  current rather than an invented version, but the decision here is the
  policy ("latest compatible"), not a specific number.
- **Test declarations:** attribute-based (`#[Test]`, `#[DataProvider]`) —
  no PHPDoc annotations.
- **Deprecation tracking:** `symfony/phpunit-bridge`.
- **PHPat:** architecture rules layered on PHPStan, scoped narrowly at this
  app's size to one rule — `src/Entity/*` must not depend on
  `src/Controller/*` or `src/Twig/*` — kept informational/non-blocking in
  CI for v0.1, not a hard merge gate, since a five-entity CRUD app has
  little real layering to police yet.
- **Infection:** mutation testing scoped only to the app's actual
  hand-written logic — `ActivitySummaryCalculator` and the
  delete-blocked-by-dependent-record guards — not the whole app, since most
  of the codebase is Symfony/Doctrine/Form boilerplate with little
  conditional logic worth mutating.
- **Panther:** one deliberately small real-browser smoke test for the
  critical path — login, create `Volunteer`, create `Activity`, see it in
  the list and the report — including a narrow-viewport check for the
  mobile nav, rather than one Panther test per CRUD screen. The rest of the
  functional coverage stays on `WebTestCase`/`BrowserKit`.

## Consequences

- **Positive:** PHPUnit is Symfony's own default, so there is zero
  ecosystem friction. Scoped PHPat/Infection give real signal without false
  busywork on boilerplate. The one Panther test catches what a no-JS test
  client cannot — Turbo navigation, Tailwind actually rendering, and mobile
  nav behavior.
- **Negative / trade-offs:** Infection's and PHPat's narrow scope means
  most of the app's boilerplate CRUD code has no mutation-testing or
  architecture-rule coverage. This is an intentional trade-off, not an
  oversight — revisit if the app grows real domain logic beyond reporting.
- **Reversibility:** swapping to Pest later is possible, since Pest runs on
  the same PHPUnit engine via `symfony/phpunit-bridge`, but it is not
  planned.

## Alternatives considered

### 1. Pest

**Considered**, initially the tentative choice, then explicitly reversed by
the product owner in favor of PHPUnit's more standard, ecosystem-aligned
tooling, before any code was written. No ADR ever committed to Pest, so
this is recorded as a rejected alternative, not a superseded decision.

### 2. Full Infection/PHPat coverage across the whole codebase from day one

**Rejected.** Premature for a five-entity CRUD app that is mostly
Symfony/Doctrine boilerplate — full coverage would mostly measure the
framework's own generated code rather than this app's actual logic.

### 3. One Panther end-to-end test per CRUD screen

**Rejected.** Redundant with `WebTestCase` functional coverage at far
higher runtime cost, since a real browser is far slower to boot and drive
than an in-process test client, for no new signal beyond what
`WebTestCase` already provides.
