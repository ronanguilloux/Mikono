# Brainstorm — Exercising the app: monkey testing, and the Panther question

**Date:** 2026-09-05
**Author:** ronan.guilloux@gmail.com
**Related:** [`AGENTS.md`](../../AGENTS.md),
[`docs/project/next-steps.md`](../project/next-steps.md),
[`0007-adopt-panther-for-adhoc-visual-verification.md`](../adr/0007-adopt-panther-for-adhoc-visual-verification.md),
[`0016-admit-nodejs-as-a-test-dependency-not-as-application-code.md`](../adr/0016-admit-nodejs-as-a-test-dependency-not-as-application-code.md)

---

## Primary audience

Future-self, or an agent handed "go break the app and tell me what you
find." Also whoever eventually decides whether `tests/E2E/` stays on
Panther.

## Desired impact

Crashes found by us rather than by Edna, on an app that now has a live
pilot server. Success is not "a monkey-testing tool is installed" — it is
knowing which of the three options below is worth the dependency, and
having settled the separate Panther/Playwright question on its own
merits rather than as a side effect.

## The research (2026-09-05)

Nothing decided or installed. The want is to let an agent loose on the
app in a test environment and see what breaks.

**The cheapest useful thing needs nothing new.**
[`scripts/panther-screenshot.php`](../../scripts/panther-screenshot.php)
already logs in and drives a real Chromium against the running app
([ADR 0007](../adr/0007-adopt-panther-for-adhoc-visual-verification.md)).
Given credentials to a test environment, that is already a usable driver
for *directed* exploration — walk the batch activity form and report what
breaks. Not random, but on a five-area CRUD app that usually finds more
than randomness does.

**The actual monkey-testing library is
[gremlins.js](https://github.com/marmelab/gremlins.js)** (Marmelab, ~9k
stars, still alive). It is a single `dist` file injected into the page —
no npm install, no Node, no new PHP dependency; it documents Playwright
integration via `addInitScript()`, and the Panther equivalent is
`executeScript()`. Two things to know before running it here: **Turbo
Drive** replaces the body under the horde on every link click, which
works but makes the logs hard to read; and **the horde will click
Delete**. That is fine on fixtures, and it is exactly why this must never
be pointed at the box someone is testing on.

**A route-walking smoke test would probably find more, for less.**
[`http-smoke-testing`](https://github.com/BlueTeaNL/http-smoke-testing)
or [`Pierstoval/SmokeTesting`](https://github.com/Pierstoval/SmokeTesting)
request every route in the router and assert no 5xx — around thirty
routes here, no browser, inside the existing PHPUnit suite. A monkey
finds crashes that come from *sequences*; a route walk finds crashes that
come from *coverage*, and coverage is what leaks in an app this size.
**The trap:** `WebTestCase` swallows exceptions and renders the error
page instead of failing, so a naive crawl stays green while pages return
500. Use `$client->catchExceptions(false)` or assert status codes
explicitly.

**Separately, and more important than any of the above: Panther has been
deprecated in Zenstruck Browser** in favour of
[`playwright-php/playwright`](https://packagist.org/packages/playwright-php/playwright)
(v1.4.0, August 2026), which runs the Symfony kernel *in the test
process* — container access, profiler, DAMA rollback, and parallel runs,
none of which Panther can offer because the browser and the app sit in
different processes. That is a real improvement over what
[ADR 0007](../adr/0007-adopt-panther-for-adhoc-visual-verification.md)
and `tests/E2E/` do today.

**It requires Node.js 20+, and that is no longer an objection.**
[ADR 0016](../adr/0016-admit-nodejs-as-a-test-dependency-not-as-application-code.md)
(2026-09-05) admits Node as a test dependency and ancillary tool, never
as application code, and partially supersedes
[ADR 0007](../adr/0007-adopt-panther-for-adhoc-visual-verification.md) —
specifically the "the project never needs Node" premise and the ground on
which it rejected Playwright. The production image and the server keep no
Node at all; a test dependency is not a runtime dependency.

So the remaining question is a straight tooling judgement, unblocked:
**migrate `tests/E2E/VolunteerManagerSmokeTest.php` and
`scripts/panther-screenshot.php` to `playwright-php`, or stay on
Panther?** What migration would buy: DAMA rollback in E2E tests like
every other test in the suite (dropping `#[SkipDatabaseRollback]`),
container and profiler access, and parallel runs. What it costs: an npm
supply-chain surface that `composer audit` does not see, a bigger dev
image, and the migration itself.

Nothing forces it — deprecated is not broken, Panther works in this
codebase today, and for monkey testing specifically Playwright's
advantages matter far less than they do for a test suite. ADR 0016
deliberately did not decide this.

## The "Options Not Taken"

Nothing is rejected outright yet — that is the point of this file — but
three paths were weighed and set aside for now:

- **Install gremlins.js and run a horde first.** Rejected as the opening
  move: on a five-area CRUD app, random clicking finds less than a
  directed walk of the batch activity form, and Turbo Drive makes its
  logs hard to read. It stays the right tool for crashes that come from
  *sequences*, once coverage is handled.
- **Add a route-walking smoke package as a new dependency.** Deferred
  rather than dismissed — around thirty routes is small enough that a
  hand-written test over `router.getRouteCollection()` may beat pulling
  in `http-smoke-testing` or `Pierstoval/SmokeTesting`. Whichever wins,
  `$client->catchExceptions(false)` is the part that must not be skipped.
- **Migrate to `playwright-php` now, because Panther is deprecated in
  Zenstruck Browser.** Rejected as a reason on its own: deprecated is not
  broken, and the migration's real benefits (DAMA rollback, container and
  profiler access, parallel runs) serve the *test suite*, not the monkey
  testing this file is about. Two separate decisions, and conflating them
  would buy an npm supply-chain surface for the wrong reason.

## Constraints

- **Never point any of this at the pilot server.** The horde will click
  Delete. Fixtures only.
- [ADR 0016](../adr/0016-admit-nodejs-as-a-test-dependency-not-as-application-code.md)
  removed the Node objection but only for *test* dependencies — the
  production image and the server keep no Node at all, and anything
  decided here inherits that boundary.
- `composer audit` does not see npm packages, so a Playwright migration
  moves part of the dependency surface outside the project's existing
  security tooling.
