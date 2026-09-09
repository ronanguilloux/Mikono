
# Brainstorm — Ponytail analytics: what could be simplified

**Date:** 2026-09-06
**Author:** ronan.guilloux@gmail.com
**Related:** [`AGENTS.md`](../../AGENTS.md),
[`docs/project/hosting-plan.md`](../project/hosting-plan.md),
[`docs/project/next-steps.md`](../project/next-steps.md),
[`docs/adr/`](../adr/)

delete: 13 skill packs for stacks this app doesn't have — API Platform ×8, Messenger, Scheduler, CQRS, Pest, cache, ports-and-adapters, strategy-pattern, ux-map, batch-processing, live-component. Nothing. 9,904 lines of agent context for zero code. .agents/skills/

delete: twig-components/ duplicates twig-component/ — same subject, two packs, both loaded. Keep one. .agents/skills/twig-components/

delete: config/reference.php, 1,691 committed lines. FrameworkBundle regenerates it at container compile (FrameworkBundle.php:224); it's an IDE-assist artifact, loaded by nothing. Gitignore it, drop the php-cs-fixer exclusion with it. config/reference.php

> **Correction (2026-09-06, on verification — shipped, see `done.md`).** The
> gitignore half was right, and better than stated: `.dockerignore` doesn't
> exclude `config/`, so those 102 KB were shipping into the production image
> too. **But "drop the php-cs-fixer exclusion with it" was wrong and would
> have broken CI.** The exclusion isn't there because the file is committed —
> it's there because the file exists *on disk in dev*, which gitignoring
> doesn't change. `PhpConfigReferenceDumpPass` runs under `kernel.debug`, and
> CI warms the dev container before `composer quality`, so on a clean checkout
> all 1,691 generated lines are back before cs-check reads them. The exclusion
> stayed, with a comment in `.php-cs-fixer.dist.php` saying why.

yagni: symfony/ux-live-component — zero AsLiveComponent, zero data-live, ADR 0003 says "installed, not yet used". Still ships eager live_controller.js + live.min.css on every page load. Cut the dep, config/bundles.php, config/routes/ux_live_component.yaml, the importmap entry and the controllers.json block. composer.json, assets/controllers.json

yagni: RosterArchive's hand-rolled type layer — rows/strings/string/nullableString/bool/date (~110 lines) plus five one-caller readonly VOs to rehydrate a repo-owned YAML file into AppStory. A checked-in fixture the maintainer writes isn't a trust boundary; Yaml::parseFile() arrays + PHP's own type errors say the same thing. src/Fixture/

> **Withdrawn (2026-09-09, on reading it properly).** 92 helper lines, not
> ~110, and six helpers not five (`strings()` was missed). More to the point
> the trade doesn't pay: the helpers are what turn a malformed archive into a
> named error instead of a half-seeded database — the class docblock says so
> — and the five VOs are what give `AppStory` typed access under PHPStan
> `max`. Swapping them for `Yaml::parseFile()` arrays buys ~200 fewer lines
> of clear code at the price of array shapes and a *larger* baseline. Not a
> simplification; dropped from the backlog.

shrink: batch_activity_form_controller.js hand-rolls a listbox — arrow-key highlight, aria-expanded, mousedown-vs-blur race, 6-result slice — over checkboxes already in the DOM. Filtering the existing labels is ~25 lines against 105. assets/controllers/batch_activity_form_controller.js:78-160

> **Inverted (2026-09-09, on checking what the 168 lines actually buy).** They
> buy working keyboard use: Up/Down/Enter/Escape (`:85-105`), the
> mousedown-vs-blur race (`:79-84` against `:143-146`), `aria-expanded`
> toggling (`:82`, `:153`), `role="option"` per row (`:135`). "Filter the
> existing labels" deletes all of it, so this was never a shrink.
>
> The real finding is the opposite: the screen-reader half is *incomplete*.
> `#volunteer-suggestions` has no `role="listbox"`, rows carry no
> `aria-selected`, and the combobox has no `aria-activedescendant` — so the
> highlight at `:108-113` is a `bg-slate-100` class that no screen reader
> ever announces. That is an addition of a few lines, and it is now the item
> in `next-steps.md`.

native: csrfTokenId()/csrfToken() + a CsrfTokenManagerInterface constructor arg, copy-pasted into six controllers, to hand a token to a template. Twig ships csrf_token('delete-escort-' ~ id); build the id in RowActions. src/Controller/ (×6)

delete: $updatedAt on Volunteer, Project, User, Activity — written by four touch() calls, read by nothing. No template, no query, no test. Four columns, four getters, four touch(), four controller lines. src/Entity/ (×4)

yagni: /activities/new — the single-volunteer form, when /activities/new-batch already handles N≥1 and is what the home screen links to everywhere. Keep ActivityFormType for /edit, drop the route, the action and templates/activity/new.html.twig. src/Controller/ActivityController.php:110-127

shrink: five byte-identical _form.html.twig shells differing only by the cancel route. One anonymous component taking cancelUrl. 65 lines → 20. templates/*/_form.html.twig

> **Correction (2026-09-09, on doing it).** Four are byte-identical, not five.
> `project/_form.html.twig` is the same shell but with a bare
> `{{ form_start(form) }}`, its attributes having moved into
> `ProjectFormType` — so the component takes a `formAttr` prop as well as
> `cancelUrl`, and Projects passes `{}`. `activity/_form.html.twig` is
> genuinely different (usage-event Stimulus wiring) and was left alone.
> Shipped 2026-09-09 as `templates/components/FormShell.html.twig`.

delete: Escort::$isActive — a checkbox and a Status column with no reader. Both activity pickers list inactive escorts anyway, unlabelled. Either it filters or it goes; today it does nothing. src/Entity/Escort.php:25

delete: EscortRepository::findAllOrderedByName() and ActivityTypeRepository::findAllOrderedByName() — both controllers use the QueryBuilder method; nothing calls these. src/Repository/EscortRepository.php:33, src/Repository/ActivityTypeRepository.php:33

> **Correction (2026-09-06, on verification).** Both are genuinely uncalled,
> but this is smaller and less free-standing than it reads. The identically
> named methods on `VolunteerRepository` and `ProjectRepository` *are* live
> (`ReportMetricsCalculator.php:31-32`), so deleting two of four leaves a
> quartet whose shared docblock — "the same query without a LIMIT, for the
> callers that genuinely need every row" — describes something only half of
> them have. And the larger duplication is next door: `ActivityFormType` and
> `BatchActivityFormType` hand-roll that same ordered query inline four times
> (`ActivityFormType.php:63`, `:83`, `BatchActivityFormType.php:51`, `:75`)
> rather than calling `createOrderedByNameQueryBuilder()`. Worth doing as one
> item with the form cleanup; ~16 lines on its own.
>
> **Second correction (2026-09-09, on doing it).** Six inline copies, not
> four: the two *project* pickers (`ActivityFormType.php:57`,
> `BatchActivityFormType.php:44`) are the same plain ordered-by-name query and
> were missed. The two volunteer pickers correctly stay hand-rolled — they
> filter on `v.isActive` and order by name alone, where
> `VolunteerRepository::createOrderedByNameQueryBuilder()` leads with an
> `isActive DESC` tie-break that would sink an activity's own deactivated
> volunteer to the bottom of the `/edit` dropdown. Shipped 2026-09-09.

delete: ProjectFactory::partner(), ProjectFactory::inactive(), EscortFactory::inactive() — three unused factory states. src/Factory/

> **Correction (2026-09-06, on verification).** `ProjectFactory::partner()` is
> **not** unused — `tests/E2E/VolunteerManagerSmokeTest.php:28` and
> `tests/Functional/ActivityControllerTest.php:93` both call it, and the
> latter is in the default `bin/phpunit` run, so this bullet as written
> reddens the suite. The two `inactive()` states are uncalled, but `inactive()`
> is a repo-wide factory idiom (`VolunteerFactory`'s and `UserFactory`'s are
> both used), and `EscortFactory::inactive()` is exactly the fixture a test
> needs if the open `Escort::$isActive` item above resolves to "it filters the
> pickers". Not a free deletion — it depends on a decision not yet made.

stdlib: CreateUserCommand's getHelper('question') + three Question objects. SymfonyStyle already has $io->ask() and $io->askHidden(). Drops two imports and some of that file's eight baseline entries. src/Command/CreateUserCommand.php:50-64

shrink: the "Other needs durationOther" rule written twice — Activity::validateDurationOther() and an identical Assert\Callback in BatchActivityFormType::configureOptions(). One constraint class, or have the batch input reuse the entity's. src/Form/BatchActivityFormType.php:110-118

yagni: knplabs/knp-paginator-bundle, honest caveat first: it's ADR 0009 and ADR 0011, and cutting it adds ~40 lines. But ListPaginator already parses page/perPage/sort/direction itself, passes SORT_FIELD_PARAMETER_NAME => null to switch the bundle's sorting off, and PaginationBar bypasses knp_pagination_render() for want of a translator. What's left is a count + a slice + a page window — Doctrine\ORM\Tools\Pagination\Paginator (already installed) and array_slice. Listing it because a dep held for a third of its surface is the shape this audit hunts; your call whether the trade is worth an ADR. src/Pagination/ListPaginator.php

net: -12,600 lines, -2 deps possible.

Two notes on scope: I left /reports walking every activity three times (three services, three findAllOrderedByDateDesc()) alone — that's performance, out of bounds here. Same for the Escort::$isActive item if you read it as a missing filter rather than a dead field: route that one through a normal review.