# Done

Append-only, growing log of completed work that isn't itself an
architectural decision (those live in [`docs/adr/`](../adr/) instead —
see that folder's README for the rule). Newest entries first. Add a
dated entry here whenever an item in
[`next-steps.md`](next-steps.md) is completed and isn't ADR-worthy.

## 2026-08-31 — Escort as a field on the single-activity forms

Write-path parity for `Activity::$accompaniedBy`, which until now could
only be set from the batch form: `ActivityFormType` (used by both
`/activities/new` and `/activities/{id}/edit`) gained the same optional
`accompaniedBy` `EntityType` — escorts ordered by name, `— No escort
recorded —` placeholder, label "Accompanied by" — placed between the
duration fields and `notes`. It binds to the `accompaniedBy` property
directly, where the batch form's equivalent field is called `escort`
because it's backed by `BatchActivityInput` rather than the entity. No
entity, migration, controller or template change was needed; the shared
Tailwind form theme renders it.

Two `ActivityControllerTest` cases cover it: the Bright Achievers worked
example now logs an escort and asserts it persisted, and a new
`escortCanBeSetAndClearedFromTheSingleActivityEditForm` walks the edit
round-trip — logged with no escort, corrected to one, then cleared back
to none. That second case needed a `reloadActivity()` helper that clears
the entity manager first: the manager the test holds keeps the
pre-submission object in its identity map, so a plain `find()` after a
form submission silently returned the stale escort and the clear step
looked like it had failed. The new `query_builder` closure adds one more
occurrence of the untyped-`$repo` PHPStan pattern already baselined for
this file (and for `BatchActivityFormType`), so two baseline counts went
3 → 4.

Reading escort back out — an Activities-index column, mobile-card line,
or per-escort report — remains deliberately unbuilt and undesigned; see
next-steps.md's "Escort display and reporting".

## 2026-08-31 — Batch/group activity logging form (mockup 2)

Second half of next-steps.md's item 1, now the batch/group activity
logging form itself is done too: `/activities/new-batch`
(`ActivityController::newBatch()`), backed by an unbound
`BatchActivityFormType` (`data_class` → the new `App\Dto\BatchActivityInput`,
not an entity — one submission fans out into one `Activity` per selected
volunteer, all sharing the same date/project/type/duration/escort/notes).
Matches the validated mockup: native date input with "Today"/"Tomorrow"
quick-set buttons, the same expanded duration radio group as the
single-activity form with the "Other" free-text field enabled/disabled
live, an optional single-select `accompaniedBy` (Escort) field, and the
deliberate scope increase — a chips + search-autocomplete attendee picker
(keyboard ↑/↓/Enter, muted "Inactive" pill for inactive volunteers) built
on a real `EntityType` multiple/expanded checkbox group progressively
enhanced by a new Stimulus controller
(`assets/controllers/batch_activity_form_controller.js`), so the raw
checkboxes stay the actual form-submission mechanism. "Save" and "Save
and add another" are separate submit buttons (`name="save_action"`)
read in the controller to decide the redirect target. A cross-field
"Other" duration requires free text) validation is wired as a
`Assert\Callback` on the DTO form, same message as the single-activity
form's entity-level callback. Five functional tests added to
`ActivityControllerTest`. The Activities index page also gained a "Log
group activity" button next to the existing "Log activity" one.

## 2026-08-31 — Escort entity, migration, and 6th CRUD area

First half of next-steps.md's item 1: the `Escort` lookup entity
(id/name/`isActive`, same shape as `ActivityType`) plus a nullable
`Activity::$accompaniedBy` (`ManyToOne` → `Escort`) — schema captured
in `migrations/Version20260831150157.php`. Added the `EscortController`/
`EscortFormType`/`EscortRepository` (with the same
`countReferencingActivities()` delete-guard as the other four lookup
repositories) and `templates/escort/` (index/new/edit/_form, following
the `ActivityType` CRUD area's shape exactly), an `EscortFactory` for
tests and fixtures, an "Escorts" link in the Settings nav, and five
functional tests (`EscortControllerTest`). The batch/group activity
logging form (mockup 2) that consumes `accompaniedBy` is done too —
see the entry above.

**Dev-environment note (unrelated to this feature):** the local SQLite
database's `doctrine_migration_versions` table had been reset to empty
while the actual schema from all five prior migrations was still
present, so `doctrine:migrations:migrate` failed on
`Version20260824204146` with "table user already exists". Fixed by
backfilling version tracking for the five already-applied migrations
via `doctrine:migrations:version --add --range-from=... --range-to=...`
before running the new one normally. `doctrine:schema:validate`
confirms the schema is in sync after.

## 2026-08-31 — Volunteer timeline "show" page

Implemented mockup 3 from the 2026-08-28 validated screen designs
([`next-steps.md`](next-steps.md)): the one CRUD area still missing a
show view. New `volunteer_show` route (`GET /volunteers/{id}`) renders
`templates/volunteer/show.html.twig` — header with Active/Inactive
status pill and "Volunteer since" date, an "At a glance" card (email,
phone with a non-blocking "+ Add" nudge when missing, activities
logged, total days, most-recent-activity date tagged "Planned" when
future-dated), a Notes card with an inline Edit link, and a
reverse-chronological activity timeline (hollow marker on planned/
future entries, solid on past ones). `ActivityRepository` gained
`findByVolunteerOrderedByDateDesc()`, same join/order shape as
`findAllOrderedByDateDesc()`; the "at a glance" stats are computed
directly in the controller from that per-volunteer list rather than
touching `ActivitySummaryCalculator` (which aggregates across all
volunteers). Added a "View" action ahead of Edit/Delete in the
Volunteers index `DataTable`, and two functional tests
(`VolunteerControllerTest`).

## 2026-08-28 — Five System-of-Work mockups reviewed and validated

Before touching any Twig for the System of Work initiatives and the P1/P2
UX-review findings in [`next-steps.md`](next-steps.md), five key screens
were mocked up as interactive HTML Artifacts and reviewed: the
work-focused home screen, the batch/group activity logging form, the
volunteer detail/timeline page, a card-based mobile layout for the
Activities index, and a Reports dashboard (KPI tiles, top-volunteers
recognition, tabbed + paginated tables, print-friendly view). All five
are now validated; the resulting design decisions are recorded directly
in `next-steps.md` as the implementation-ready spec. No application code
changed — mockups only.

## 2026-08-28 — Activity duration "Other" option

Decision and implementation recorded in
[ADR 0008](../adr/0008-add-other-activity-duration-with-free-text-companion-field.md).

## 2026-08-27 — Dev fixtures: real UCESCO project/activity-type breadth

`src/Story/AppStory.php` gained the projects, activity types, and
activities observed in the Volunteer Manager's real nightly WhatsApp
roster messages (clinics, schools, MVETI, orphanage, beach clean-ups,
home visits, orientation, etc. — see
[`docs/brainstorm/04-system-of-work-for-the-volunteer-manager.md`](../brainstorm/04-system-of-work-for-the-volunteer-manager.md#evidence-from-real-roster-messages-2026-08-27)),
so dev fixtures and future mockups stop looking like a two-project toy
dataset ("Bright Achievers" / "Mombasa Youth Centre" only).

Commit: `4b07740`.

## 2026-08-27 — P0 UX review fixes

Implemented the six P0 (quick, low-risk) findings from the 2026-08-26
UX review in [`next-steps.md`](next-steps.md):

- Required-field indicator (`*`) in `templates/form/tailwind_theme.html.twig`'s
  `form_label` block, driven off the form view's existing `required` var.
  Fixed a follow-on regression this surfaced: the block bypassed
  Symfony's `form_label_content` fallback (humanizing the field name
  when no explicit `label` option is set), so `Activity`'s `date`,
  `volunteer`, and `project` fields — none of which set a `label`
  option — rendered with no label text at all, leaving a lone floating
  `*`. Fixed by delegating to `parent()` in a `form_label_content`
  override instead of reimplementing label rendering, and by excluding
  individual `radio`/`checkbox` choice options (via `block_prefixes`)
  from the asterisk so expanded fields like `Duration` don't repeat it
  on every option ("Half day \*", "Full day \*").
- Flash messages gained `role="alert"`/`aria-live="polite"` and an
  explicit `error`/`warning`/`success` style map in `base.html.twig`,
  replacing the old binary `label == 'error'` check.
- Visible focus ring (`focus:ring-2 focus:ring-brand-500`) restored on
  all text/select/textarea inputs, alongside the existing border-color
  swap — helps both keyboard users and outdoor/bright-sunlight phone use.
- Copy consistency: `user/index.html.twig`'s empty state now matches the
  other 4 CRUD areas' call-to-action phrasing; Activity's edit page
  title/heading and delete-confirm string now name the date and
  volunteer instead of the generic "Edit activity"/"Delete this
  activity entry?", avoiding a wrong-day accidental delete.
- Nav accessibility: `aria-expanded` on both nav toggles (wired through
  `nav_controller.js`), `aria-current="page"` on the active nav link,
  and Escape-to-close on the Settings dropdown.
- Removed the dead, redundant `sm:hidden` on the mobile nav panel in
  `base.html.twig` (already always-`hidden`, toggled by JS).

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
