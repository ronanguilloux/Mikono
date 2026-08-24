# Brainstorm — Test Hardening Decisions (v0.1 phase 11)

**Date:** 2026-08-24
**Author:** ronan.guilloux@gmail.com
**Related:** [`AGENTS.md`](../../AGENTS.md),
[`02-volunteer-manager-v0.1-context.md`](02-volunteer-manager-v0.1-context.md),
[`docs/adr/`](../adr/),
[`0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md`](../adr/0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md)

---

This entry differs in purpose from
[01](01-symfony-php-skills-context.md) and
[02](02-volunteer-manager-v0.1-context.md): it doesn't narrate a decision
before it's made. It's a retrospective record of two decisions made
during phase 11 ("test hardening") of the Volunteer Manager v0.1 build —
things worth preserving *why* about, even though the phase itself is
in progress. **For current build status and what's left, see
[`docs/project/next-steps.md`](../project/next-steps.md) instead — that
file is kept up to date as phases complete; this one is not.**

## Primary audience

Future-self, or this same session immediately after a context
compaction, wanting to know *why* two choices were made this phase, not
just *what* the current status is (that's `docs/project/next-steps.md`).

## Desired impact

Two things worth knowing beyond the "what," captured here because they
won't be obvious from the code alone:

- **Two real bugs the suite caught, both already fixed and committed:**
  (1) a kernel-boot-ordering bug in several test methods — calling a
  Foundry factory before `WebTestCase::createClient()` throws a
  `LogicException`, because factories auto-boot the kernel to reach
  Doctrine and `createClient()` then tries to boot it again; fixed by
  always calling `createClient()` as the literal first statement in every
  test method, factories after. (2) A genuine application bug, not a test
  artifact: submitting any required text field (`firstName`, `lastName`,
  `name`, `fullName`, `email`) as an empty string crashed the app with a
  500 instead of a friendly validation message, because Symfony's
  `TextType`/`EmailType` transform a submitted empty string to `null` by
  default, colliding with the entities' non-nullable `string` property
  types. Fixed with `'empty_data' => ''` on every required text field
  across `VolunteerFormType`, `ProjectFormType`, `ActivityTypeFormType`,
  and `UserFormType`. This second one would have hit real users clicking
  Save with a blank required field in the actual browser, not just tests.
- **A precedent set but not previously recorded anywhere:** how to handle
  a contrib Flex recipe in this repo (below, under Options Not Taken).

Test suite composition, current app status, and what's left before v0.1
is done all live in
[`docs/project/next-steps.md`](../project/next-steps.md) — that's the
place to look for the current picture; this section only covers what
happened and why.

## The "Options Not Taken"

### 1. Flipping `allow-contrib` to `true` to get DAMA's Flex recipe applied

`dama/doctrine-test-bundle`'s Symfony Flex recipe was silently ignored
during `composer require`, because this project's `composer.json` has
`"allow-contrib": false` — set deliberately by the original
`symfony/skeleton` scaffold in phase 2, to avoid auto-trusting community
Flex recipes without review.

**Rejected.** Flipping that project-wide flag just to get one bundle's
recipe applied would silently start trusting all future contrib recipes
too — a much bigger, permanent trust-surface change than manually wiring
one known, simple bundle registration. Instead, the bundle was wired by
hand: registered directly in `config/bundles.php`
(`DAMA\DoctrineTestBundle\DAMADoctrineTestBundle::class => ['test' =>
true]`) and its PHPUnit extension registered directly in
`phpunit.dist.xml`
(`<bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"
/>`), found by inspecting the installed package's source directly since
the recipe never ran to document it. This sets the precedent for any
future contrib-recipe package in this repo: wire it by hand, don't flip
`allow-contrib`.

### 2. Exhaustive create/edit/delete/validation/delete-guard coverage for every CRUD area

**Rejected.** Projects and Activity Types are structurally identical to
Volunteers (same controller/form/template pattern, already proven
working end-to-end via curl during their own build phases) — re-testing
every validation path for each would be redundant coverage of generic
Symfony Form/Doctrine behavior, not this app's own logic, for a large
amount of near-duplicate test code. Volunteers was treated as the
representative case with full coverage; lighter create+delete-guard
tests for Projects and Activity Types were judged sufficient.

## Constraints

This entry is being written specifically because the user asked to
compact the session soon and wanted decisions and next steps captured in
the repo, not just left in chat, before that happens. Partway through
writing it, it became clear `docs/brainstorm` (immutable, pre-decision
narrative) isn't the right home for *current status* — that's mutable
and goes stale the moment a phase finishes — so that content moved to a
new `docs/project/` directory (mutable, living status docs, distinct
from the immutable `docs/adr` + `docs/brainstorm` pair) instead. This
entry keeps only the genuinely historical "why." No new ADR accompanies
it: the DAMA-wiring precedent is implementation
detail of the already-recorded
[ADR 0004](../adr/0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md)
decision to adopt PHPUnit/PHPat/Infection/Panther, not a new
architectural decision in its own right.
