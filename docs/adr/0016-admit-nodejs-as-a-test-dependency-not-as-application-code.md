# 16. Admit Node.js as a test dependency and ancillary tool, never as application code

Date: 2026-09-05

## Status

Accepted

Partially supersedes
[ADR 0007](0007-adopt-panther-for-adhoc-visual-verification.md) — the
"the project never needs Node" premise in its Context, and the ground on
which its *Alternatives considered* §1 rejected Playwright. ADR 0007's
actual decision, adopting Panther for ad-hoc visual verification, still
stands and is not superseded.

## Context

This project has carried a blanket "no Node" posture since its second
week, and it turns out to have been carrying two different things under
one label.

**One of them is a fact, and it remains true.**
[ADR 0003](0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)
chose Tailwind through `symfonycasts/tailwind-bundle` and AssetMapper,
"so no Node.js toolchain is needed". That is a description of the
frontend build, and it is the *reason* that bundle was chosen over the
standard Tailwind CLI. Nothing about this ADR changes it.

**The other is a forward commitment, and it overshot.**
[ADR 0007](0007-adopt-panther-for-adhoc-visual-verification.md) wrote
that Tailwind is pure-PHP "specifically so the project never needs Node",
and rejected Playwright with "the project deliberately has none". That is
a stronger claim than ADR 0003 ever made, and on 2026-09-05 the product
owner stated the original intent directly: the exclusion meant **that the
application not be built in Node** — the source stays PHP/Symfony — not
that Node be banned as a test dependency or an ancillary tool.

Two things made the difference matter rather than remain academic:

- **Panther is now the deprecated side of that comparison.** Zenstruck
  Browser added support for `playwright-php/playwright` (v1.4.0, August
  2026) and deprecated Panther. `playwright-php` runs the Symfony kernel
  *inside the test process*, which gives an E2E test container access,
  the profiler, `dama/doctrine-test-bundle` rollback, and parallel
  execution — none of which Panther can offer, because Panther's browser
  and the application necessarily sit in separate processes. That is
  precisely why `tests/E2E/` needs `#[SkipDatabaseRollback]` today.
- **Ancillary JavaScript tooling keeps being useful and keeps being
  refused.** The monkey-testing research on the same day is one example:
  `gremlins.js` is a single file injected into a page, and the only thing
  standing against it was a sentence in an ADR about a toolchain it does
  not use.

So the constraint was blocking an evaluation for a reason its author
never held. ADRs here are immutable
([ADR 0001](0001-use-adr-and-agents-for-decision-capture.md)), so the
correction is this record rather than an edit to either file.

## Decision

**Node.js is admitted as a test dependency and as an ancillary developer
tool. It is never application code, never a runtime dependency, and never
present in the production image or on the server.**

The line is drawn where it can actually be checked, not by intention:

**Permitted.**

- A `require-dev` package that pulls a Node toolchain, `playwright-php`
  being the case in hand.
- Node tooling installed in the `frankenphp_dev` stage, in CI, or in an
  agent's scratchpad.
- Ad-hoc JavaScript injected into a page by a test or an automation
  script — `gremlins.js` and its kind.

**Not permitted.**

- Any Node process, binary, or `node_modules` in the `frankenphp_prod`
  image, or on the server.
- Anything the application needs at runtime to serve a request.
- Replacing `symfonycasts/tailwind-bundle` with a Node-driven Tailwind
  build. ADR 0003's frontend decision is untouched, and the production
  asset pipeline stays pure PHP.
- A `package.json` at the repository root that the application's own
  assets depend on. The app's source stays PHP, Twig, and the
  hand-written Stimulus controllers AssetMapper already serves.

**The testable form of this rule:** `docker compose -f compose.yaml -f
compose.prod.yaml` must never require Node, and `which node` inside the
production image must fail. If that stays true, the rule is being kept.
Verified against the running production image on `srv-mikono`,
2026-09-05: no `node`, no `npm`, no `node_modules`. That is the baseline
this rule protects, not an aspiration.

This ADR **removes an objection; it does not make a choice.** Whether to
migrate `tests/E2E/VolunteerManagerSmokeTest.php` and
`scripts/panther-screenshot.php` from Panther to `playwright-php` is a
separate decision, to be taken on its own merits when someone wants the
benefit. Panther deprecated is not Panther broken, and it works in this
codebase today.

## Consequences

- **Positive:** the better E2E tool becomes evaluable. `playwright-php`
  would let E2E tests use DAMA rollback like every other test in the
  suite, drop `#[SkipDatabaseRollback]`, reach the container and the
  profiler, and run in parallel. Ancillary JS tooling stops needing an
  argument. And the record now says what the owner actually meant, which
  is worth more than the specific unblocking.
- **Negative / trade-offs:** npm is a supply-chain surface, and it now
  sits in the dev and CI paths where `composer audit`
  ([ADR 0005](0005-adopt-phpstan-php-cs-fixer-rector-composer-audit.md))
  does not look. The dev image grows if a Node toolchain is installed
  into it. And a rule with a permitted side erodes more easily than a
  blanket ban — "never any Node" needed no judgement, whereas this needs
  someone to notice when a test dependency starts becoming a runtime one.
  The `which node` check above exists because of that.
- **Reversibility:** cheap, and asymmetric in the right direction. A test
  dependency is removable precisely because nothing the application ships
  depends on it — that is the whole content of this decision. Reverting
  means deleting a `require-dev` entry and whatever tests used it.

## Alternatives considered

### 1. Edit ADR 0007 (or ADR 0003) to remove the Node sentences

**Rejected.** ADRs here are immutable once accepted
([ADR 0001](0001-use-adr-and-agents-for-decision-capture.md),
[`README.md`](README.md)), and an amended ADR stops being a record of what
was decided and when. The ADR 0003 sentence should not be touched for a
second reason: it is not a prohibition but the *rationale* for choosing
`tailwind-bundle` over the Tailwind CLI, and it is still true. Deleting it
would leave a decision with no stated reason.

### 2. Keep the blanket exclusion

**Rejected.** It was never the decision the product owner made — it was a
stronger reading that entered the record through ADR 0007's Context. Kept
as written, it would refuse a strictly better test tool, and refuse
single-file JS utilities, on the strength of a sentence about a Tailwind
build. Nothing forces the question urgently, which is exactly why it is
worth settling now rather than under pressure.

### 3. Admit Node without qualification, production included

**Rejected.** Nothing in the app needs it, and the production image's
absence of a JavaScript runtime is a real reduction in size and in attack
surface that costs nothing to keep. The unqualified version would also
lose the only thing making this rule enforceable: a check that can fail.

### 4. Fold the `tests/E2E/` migration into this ADR

**Rejected.** Deciding "Node is permitted" and deciding "we now use
Playwright" are different judgements with different evidence, and the
second one has not been made. Bundling them would commit the project to a
migration nobody has scoped, and would make this ADR impossible to
supersede independently later.
