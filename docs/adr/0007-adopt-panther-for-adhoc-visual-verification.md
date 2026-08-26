# 7. Adopt Panther (not Playwright) for ad-hoc, Claude-session-driven visual verification

Date: 2026-08-26

## Status

Accepted

## Context

[ADR 0004](0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md)
already justified adopting Symfony Panther for the app's *formal* E2E
regression suite — `tests/E2E/VolunteerManagerSmokeTest.php`, a
PHPUnit-driven `PantherTestCase` with Foundry fixtures,
`#[SkipDatabaseRollback]`, and its own built-in test webserver. This ADR
covers a different, narrower concern that ADR 0004 does not: one-off "does
this render correctly, take a screenshot" checks that a Claude Code session
runs mid-task against the already-running dev app, with no PHPUnit run, no
test webserver, and no fixtures wanted.

A prior session verified a nav-reorder and Settings-dropdown UI change by
installing Node.js and Playwright fresh into the Claude Code session's
scratchpad directory (`npm install playwright` plus
`npx playwright install chromium`, roughly a 95MB download). That scratchpad
is wiped between Claude Code sessions, so every future session doing a
similar visual check would repeat the same install indefinitely.

The project has zero Node/npm anywhere — no `package.json` in the repo —
and `AGENTS.md` (symlinked as `CLAUDE.md`) is explicit that "No host
PHP/Composer needed or expected — everything runs through Docker." Tailwind
CSS is built via `symfonycasts/tailwind-bundle`, a pure-PHP Symfony bundle,
specifically so the project never needs Node.

Meanwhile Chromium and chromium-driver are already `apt-get install`ed in
the shared `frankenphp_base` Docker stage (`Dockerfile`, inherited by both
the dev and prod images) — this is Symfony Flex's own `symfony/panther`
recipe block (`ENV PANTHER_NO_SANDBOX=1`,
`ENV PANTHER_CHROME_ARGUMENTS='--disable-dev-shm-usage'`). `symfony/panther`,
`symfony/browser-kit`, and `symfony/css-selector` are already `require-dev`
in `composer.json`. This capability is already installed, already paid for,
and — critically — persists across Claude Code sessions because it lives in
the Docker image layer, not a session-scoped scratchpad.

No functional gap was found that would justify Playwright: screenshot
capture (`$client->takeScreenshot()`), responsive/mobile-viewport checks
(`$client->manage()->window()->setSize(new WebDriverDimension(...))`,
already exercised in the existing E2E test's mobile-nav check), and waiting
out Turbo Drive's async navigation (`$client->wait()->until(...)` /
`waitForVisibility()`, also already used in that same test) are all
first-class Panther/WebDriver API, proven working in this exact codebase
already.

## Decision

Adopt Panther's lower-level `Client::createChromeClient()` factory
(independent of `PantherTestCase`) for ad-hoc visual verification, wrapped
in a small standalone CLI script.

- `scripts/panther-screenshot.php` — run directly via
  `docker compose exec php php scripts/panther-screenshot.php ...` against
  the running dev app at `https://localhost`. No PHPUnit, no test
  webserver, no fixtures.
- Supported options: `--path` to navigate to, optional `--login` (via
  `--email`/`--password` or the `PANTHER_LOGIN_EMAIL`/
  `PANTHER_LOGIN_PASSWORD` env vars — never hardcoded, never persisted to
  disk), `--width`/`--height` for viewport resize, `--wait-selector` for a
  Turbo-Drive-safe wait, `--click` (repeatable, e.g. to open a dropdown
  before screenshotting), and it always writes to `var/screenshots/` inside
  the container.
- No new dependency, no new language/runtime, no Dockerfile change
  required.
- Documented as the standing convention in `AGENTS.md`'s (symlinked as
  `CLAUDE.md`) "Testing conventions" section, cross-referencing this ADR,
  so future sessions don't re-litigate or re-install Playwright.

## Consequences

- **Positive:** zero new toolchain — reuses infrastructure already baked
  into the Docker image, which persists across sessions and rebuilds,
  unlike a scratchpad install. Consistent API and patterns with the
  existing E2E test lower the learning cost. Keeps the project's "no Node
  anywhere" invariant intact.
- **Negative / trade-offs:** `var/` is intentionally excluded from the dev
  bind-mount (`compose.override.yaml`, for I/O performance), so screenshots
  written to `var/screenshots/` inside the container need an explicit
  `docker compose cp` to be viewable on the host — one extra command,
  documented in `AGENTS.md`. Panther/WebDriver's API is more verbose than
  Playwright's for complex multi-step interactions, should such a need ever
  arise.
- **Reversibility:** cheap — it's one standalone script, unused elsewhere;
  deleting or replacing it later affects nothing else (no test suite, no
  CI, no app code depends on it).

## Alternatives considered

### 1. Playwright + Node.js

**Rejected.** Adds a second language/runtime toolchain to a project that
deliberately has none — Tailwind is pure-PHP specifically to avoid Node.
The browser binary has no persistent cache for this project across Claude
Code sessions; it lives in a session-scoped scratchpad that's wiped each
session, so the roughly 95MB download would repeat indefinitely. It also
duplicates capability already installed and already paid for via
Panther/Chromium in the shared Docker base image, with no functional gap
it closes.

### 2. Reusing `PantherTestCase`/the formal E2E PHPUnit suite for ad-hoc checks

**Rejected.** Wrong tool for a one-off "does this already-running page
look right" check — brings unwanted PHPUnit runtime overhead, Foundry
fixture/DB-rollback machinery, and its own built-in test webserver instead
of pointing at the actual dev app being worked on.

### 3. No automation, human eyeballing only

**Rejected.** Doesn't work for a headless Claude Code session with no
display; a screenshot artifact is the only way the agent itself can verify
rendering.

### 4. Folding this into `tests/E2E/VolunteerManagerSmokeTest.php` instead of a separate script

**Rejected.** Conflates a regression-test suite with one-off exploratory
verification, and would couple ad-hoc checks to Foundry/database-rollback
semantics they don't need.
