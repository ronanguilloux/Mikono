# Done

Append-only, growing log of completed work that isn't itself an
architectural decision (those live in [`docs/adr/`](../adr/) instead —
see that folder's README for the rule). Newest entries first. Add a
dated entry here whenever an item in
[`next-steps.md`](next-steps.md) is completed and isn't ADR-worthy.

## 2026-08-26 — Top nav reorder, Panther-based ad-hoc UI screenshots

Reordered the top nav to lead with the frequently-used items
(Activities, Volunteers, Reports) and moved Projects, Activity Types,
and Users into a Settings dropdown (desktop) / grouped mobile section.

Also added `scripts/panther-screenshot.php`, a standalone CLI script
for ad-hoc visual verification during UI work — logs in, navigates,
resizes the viewport, waits out Turbo Drive, clicks, and screenshots
the already-running dev app via Panther's `Client::createChromeClient()`,
no PHPUnit/Playwright/Node needed. The tool choice itself is recorded
in [ADR 0007](../adr/0007-adopt-panther-for-adhoc-visual-verification.md);
this entry is the implementation, plus two related fixes it surfaced:

- `init: true` on the `php` service (`compose.yaml`) so Docker's
  built-in `tini` reaps orphaned Chrome subprocesses left behind by
  Panther, instead of letting them accumulate as zombies.
- Granted the `Skill` tool to the `adr-scribe` and `context-capturer`
  subagents — both instructed themselves to "run the
  `ronan-markdown-lint` skill" but had no way to actually invoke it,
  silently falling back to a manual lint pass every time.

Commit: `3cd5afa`.

## 2026-08-26 — ucesco-theme brand identity, app:user:create CLI flags

UCESCO mark/logo, favicon, and brand color palette applied across
templates. The identity decision itself is recorded in
[ADR 0006](../adr/0006-adopt-ucesco-theme-for-brand-identity.md); this
entry is the implementation. Also: `app:user:create` gained
`--email`/`--full-name`/`--password` options so seeding a dev user is
a one-liner instead of interactive prompts (dev/local convenience
only — the password then lands in plaintext in shell history).

Commit: `5f23469`.

## 2026-08-26 — Panther E2E test, scoped Infection, Foundry dev fixtures

Closed out the rest of phase 11 and all of phase 12 from the original
build plan (`docs/brainstorm/02-volunteer-manager-v0.1-context.md`).
The tooling choice itself (Panther for one smoke test, Infection scoped
to the app's real logic) was already decided in
[ADR 0004](../adr/0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md) —
this entry is the implementation:

- `tests/E2E/VolunteerManagerSmokeTest.php` — one real-browser test:
  login, create a Volunteer, create an Activity, see it in the list and
  `/reports`, plus a mobile-nav check. Runs Panther's own built-in
  webserver against `public/`, not a second Docker service.
- `infection.json.dist` — mutation testing scoped to
  `src/Report/ActivitySummaryCalculator.php` and the delete-guard
  methods in the four repositories that have one. Baseline: 100%
  Mutation Code Coverage, 100% Covered Code MSI (59/59 killed).
- `src/Story/AppStory.php` — Foundry `DemoDataStory` seeding the Bright
  Achievers worked example plus extra volunteers/activities. Load with
  `docker compose exec php bin/console foundry:load-fixtures --no-interaction`.
- `AGENTS.md` sanity pass — removed the "not yet wired" caveat, documented
  the new `tests/E2E/` suite and `composer infection`.

Commits: `47a6f42`, `5f24564`.

## 2026-08-24 — Quality tooling adopted

PHPStan (level `max`), PHP-CS-Fixer, Rector (dry-run only), and
`composer audit`, aggregated into `composer quality` and enforced by a
local pre-commit hook. The tool choices themselves are recorded in
[ADR 0005](../adr/0005-adopt-phpstan-php-cs-fixer-rector-composer-audit.md);
this entry just marks the implementation done.

Commit: `7320cf1`.

## 2026-08-24 — v0.1 feature build: CRUD, auth, reports, tests

The initial build, per
[ADR 0003](../adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md):
Docker+FrankenPHP scaffolding, Doctrine/SQLite, authentication, all five
CRUD areas (Volunteers, Projects, Activity Types, Users, Activities),
the Reports view, and the 25-test functional suite (PHPUnit + Foundry +
DAMA, per [ADR 0004](../adr/0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md)).

Commits: `53f2a88`..`1ff9748` (see `git log` for the full list).
