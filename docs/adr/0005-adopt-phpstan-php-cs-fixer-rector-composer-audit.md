# 5. Adopt PHPStan, PHP-CS-Fixer, Rector, and composer audit for the Volunteer Manager app

Date: 2026-08-24

## Status

Accepted

## Context

The Volunteer Manager is a Symfony 8.1 / PHP 8.5 app
([ADR 0003](0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)),
with PHPUnit, PHPat, Infection, and Panther already decided for the test
suite ([ADR 0004](0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md)).
[`docs/project/next-steps.md`](../project/next-steps.md)'s phase-11
checklist already flagged PHPStan, PHPat, Rector, and PHP-CS-Fixer as
planned but not yet installed; `composer audit` wasn't previously
mentioned anywhere in the project's docs.

This ADR covers the remaining static-analysis, style, refactoring, and
dependency-audit layer that ADR 0004 didn't: PHPStan, PHP-CS-Fixer,
Rector, and `composer audit`. PHPat itself was already decided in ADR
0004 and is not re-decided here — it's referenced below only to settle
whether it needs a companion tool (Deptrac) for architecture-boundary
enforcement.

No CI pipeline exists yet — there is no git remote and no `.github/`
directory in this repo — so a local pre-commit hook is the enforcement
mechanism instead, for both the human developer and any AI coding agent
working in this repo.

## Decision

Adopt PHPStan at level `max` with a baseline as needed, PHP-CS-Fixer on
`@Symfony` + `@PER-CS2.0`, Rector with composer-based auto-detected rule
sets in dry-run-first mode, and native `composer audit`, all enforced
locally via a git pre-commit hook rather than CI.

- **PHPStan:** `phpstan/phpstan` + `phpstan/phpstan-symfony` +
  `phpstan/phpstan-doctrine` + `phpstan/phpstan-strict-rules`, level
  `max` (PHPStan's real strictness ceiling — currently numeric level 9;
  there is no level 10), analysing both `src/` and `tests/`. If level
  `max` surfaces non-trivial pre-existing errors, use
  `--generate-baseline` (`phpstan-baseline.neon`) rather than lowering
  the level — shrink the baseline over time, never delete it to hide
  errors. This matches guidance already documented in this repo's
  pre-staged `.agents/skills/php-modernization/SKILL.md` skill (an
  imported, generic Agent Skill, not authored by this project) — this
  ADR follows that guidance rather than re-deciding it.
- **PHP-CS-Fixer:** `friendsofphp/php-cs-fixer`, ruleset `@Symfony` +
  `@PER-CS2.0`, with `declare(strict_types=1)` enforced project-wide.
- **Rector:** `rector/rector`, configured with
  `withComposerBased(symfony: true, doctrine: true, phpunit: true)` so
  it auto-detects applicable rule sets from installed packages rather
  than hardcoding per-version Symfony sets. Dry-run-first workflow:
  Rector is never auto-applied, always previewed via `--dry-run` and
  reviewed before applying — this is an existing hard guardrail in the
  pre-staged `php-modernization` skill, not a new decision here.
- **`composer audit`:** native Composer command, no package to
  install, run as part of the aggregate quality check.
- **Architecture boundary enforcement stays PHPat-only**, reaffirming
  ADR 0004 rather than superseding it. Deptrac was considered and
  explicitly rejected: this app has no real Domain/Infrastructure
  layering yet — just Entity/Controller/Twig, already covered by ADR
  0004's one PHPat rule — so adding Deptrac would police the same
  boundary twice with two separate configs, for no new signal. Revisit
  if the app grows real layered architecture.
- **Enforcement:** a local git pre-commit hook, checked into
  `.githooks/pre-commit` and activated via
  `git config core.hooksPath .githooks`, runs the aggregate quality
  check before every commit, blocking on failure. Chosen over CI
  because no CI pipeline exists yet — no git remote, no `.github/`
  directory — noted as a reversible choice, revisit once a CI pipeline
  exists.

## Consequences

- **Positive:** genuine logic, nullability, and type bugs are caught
  before runtime rather than in production or by manual review. Zero
  manual formatting debates — PHP-CS-Fixer settles style
  mechanically. Mechanical PHP/Symfony upgrades become tractable via
  Rector instead of hand-editing every call site. Dependency
  vulnerabilities are surfaced automatically via `composer audit`. The
  pre-commit hook makes consistent use non-optional for both the human
  developer and any AI-agent contributor, rather than relying on
  everyone remembering to run these tools manually.
- **Negative / trade-offs:** level `max` PHPStan on a fresh
  Symfony/Doctrine app will likely need an initial baseline to absorb
  framework-level noise, which is extra setup work up front. A local
  hook, unlike CI, doesn't protect a shared remote/PR flow, since none
  exists yet — it only protects commits made through this checkout.
  The hook can be bypassed with `--no-verify`, which is a known escape
  hatch and not to be used casually.
- **Reversibility:** switching the enforcement point from a local hook
  to CI later is cheap — the same aggregate quality check the hook
  runs can be reused verbatim in a GitHub Actions workflow once a
  remote exists. Lowering the PHPStan level or dropping the strict
  rules package is a one-line config change if the baseline ever
  becomes unmanageable, though that is not currently expected.

## Alternatives considered

### 1. Deptrac for architecture boundary enforcement

**Rejected.** Redundant with ADR 0004's PHPat choice at this app's
current size and layering — the app has no real Domain/Infrastructure
split yet, just Entity/Controller/Twig, already covered by PHPat's one
rule. Adding Deptrac would mean maintaining two separate configs to
police the same single boundary, for no new signal. Revisit if the app
grows real layered architecture.

### 2. CI-based enforcement (GitHub Actions)

**Rejected**, for now — not a rejection of CI in principle, purely a
sequencing decision. There is no git remote and no `.github/` directory
in this repo yet, so there is no CI pipeline to run checks in. A local
pre-commit hook was chosen as the interim consistency mechanism, with
the same aggregate check reusable in a CI workflow once a remote exists.

### 3. Lower PHPStan level (e.g. 6-8) to reduce initial noise

**Rejected** in favor of level `max` plus a baseline. The app is
greenfield — 12 commits, five entities — so a baseline is cheap to
generate and shrink over time, matching the project's own "start at a
high level" framing already recorded in
[`docs/project/next-steps.md`](../project/next-steps.md).
