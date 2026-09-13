# 16. Admit Node.js as a test dependency and ancillary tool, never as application code

Date: 2026-09-13

## Status

Accepted

## Context

[ADR 0003](0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md)
chose `symfonycasts/tailwind-bundle` with AssetMapper so that the frontend
build needs no Node toolchain. That is a fact about the application build,
and it stays true.

It was later read as a blanket "the project never uses Node", which blocked
evaluating test tools (playwright-php) and single-file JavaScript utilities
(gremlins.js) for a reason the product owner never held. Their intent, stated
on 2026-09-05: the **application** is not built in Node; Node as a test
dependency or ancillary tool is fine.

## Decision

**Node.js is admitted as a test dependency and as an ancillary developer
tool. It is never application code, never a runtime dependency, and never
present in the production image or on the server.**

**Permitted.**

- A `require-dev` package that pulls a Node toolchain.
- Node tooling in the `frankenphp_dev` stage, in CI, or in an agent's
  scratchpad.
- JavaScript injected into a page by a test or automation script
  (gremlins.js and its kind).

**Not permitted.**

- Any Node process, binary or `node_modules` in the `frankenphp_prod` image
  or on the server.
- Anything the application needs at runtime to serve a request.
- Replacing `tailwind-bundle` with a Node-driven Tailwind build.
- A root `package.json` the application's own assets depend on. App source
  stays PHP, Twig and the Stimulus controllers AssetMapper serves.

**The testable form of the rule:** `docker compose -f compose.yaml -f
compose.prod.yaml` never requires Node, and `which node` inside the
production image fails.

This rule removes an objection; it does not choose a tool. Whether to use
playwright-php is decided in
[ADR 0007](0007-adopt-panther-for-adhoc-visual-verification.md).

## Consequences

- **Positive:** better test tools and ancillary JS utilities can be
  evaluated on their merits. The production image keeps no JS runtime.
- **Negative / trade-offs:** npm is a supply-chain surface in dev and CI
  that `composer audit`
  ([ADR 0005](0005-adopt-phpstan-php-cs-fixer-rector-composer-audit.md))
  does not inspect. A rule with a permitted side needs someone to notice
  when a test dependency drifts into a runtime one — the `which node`
  check is that tripwire.
- **Reversibility:** cheap by construction: nothing shipped depends on a
  test dependency, so removing one is deleting it and the tests that use it.

## Alternatives considered

### 1. Keep the blanket exclusion

**Rejected.** It was never the owner's decision, and it would refuse better
test tooling on the strength of a sentence about a Tailwind build.

### 2. Admit Node without qualification, production included

**Rejected.** Nothing in the app needs it, the absence of a JS runtime in
production is free size and attack-surface reduction, and an unqualified
rule would lose its only enforceable check.
