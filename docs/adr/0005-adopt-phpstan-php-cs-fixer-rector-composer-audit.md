# 5. Adopt PHPStan, PHP-CS-Fixer, Rector, and composer audit for the Volunteer Manager app

Date: 2026-08-24

## Status

Accepted

## Context

[ADR 0004](0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md)
covers tests. This ADR covers the static-analysis, style, refactoring and
dependency-audit layer, and where it is enforced. Contributors are one human
and AI agents, so the checks have to run without anyone remembering to.

## Decision

**PHPStan at level `max` with a baseline, PHP-CS-Fixer on `@Symfony` +
`@PER-CS2.0`, Rector in dry-run only, and `composer audit` — aggregated as
`composer quality`, run by a pre-commit hook and by CI.**

- **PHPStan:** `phpstan/phpstan` with the `-symfony`, `-doctrine` and
  `-strict-rules` extensions, level `max`, over `src/` and `tests/`.
  Pre-existing findings live in `phpstan-baseline.neon`: shrink it over
  time, never delete it to hide errors, and never raise the level without
  regenerating and committing it.
- **PHP-CS-Fixer:** `@Symfony` + `@PER-CS2.0`, `declare(strict_types=1)`
  everywhere.
- **Rector:** `withComposerBased(symfony: true, doctrine: true,
  phpunit: true)`, so rule sets follow installed packages. `composer rector`
  is dry-run only and there is no apply script: Rector output is always
  reviewed before being applied, because it can produce valid but wrong
  refactors.
- **`composer audit`:** native, no package.
- **Architecture boundaries stay PHPat-only** (ADR 0004's one rule).
- **Enforcement:** `.githooks/pre-commit` (enable with
  `git config core.hooksPath .githooks`) blocks a commit on failure, and
  `.github/workflows/ci.yml` runs the same `composer quality`
  ([ADR 0010](0010-build-in-ci-and-deploy-by-image-pull.md)), where
  `--no-verify` cannot skip it.

## Consequences

- **Positive:** type, nullability and logic bugs caught before runtime; no
  style debates; mechanical upgrades tractable; vulnerable dependencies
  surfaced; consistent use by agents and humans alike.
- **Negative / trade-offs:** level `max` needs a baseline to absorb
  framework noise. `composer audit` does not see npm packages. The hook
  can be bypassed locally with `--no-verify`, which only CI catches.
- **Reversibility:** lowering the level or dropping strict rules is a
  one-line config change.

## Alternatives considered

### 1. Deptrac for architecture boundaries

**Rejected.** The app has no Domain/Infrastructure layering, only
Entity/Controller/Twig, already covered by one PHPat rule; Deptrac would
police the same boundary with a second config. Revisit if real layers
appear.

### 2. A lower PHPStan level (6–8) to reduce initial noise

**Rejected.** On a small codebase a baseline is cheap to generate and
shrink; starting low only defers the same work.
