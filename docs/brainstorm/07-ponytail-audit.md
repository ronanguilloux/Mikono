
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

yagni: symfony/ux-live-component — zero AsLiveComponent, zero data-live, ADR 0003 says "installed, not yet used". Still ships eager live_controller.js + live.min.css on every page load. Cut the dep, config/bundles.php, config/routes/ux_live_component.yaml, the importmap entry and the controllers.json block. composer.json, assets/controllers.json

yagni: RosterArchive's hand-rolled type layer — rows/strings/string/nullableString/bool/date (~110 lines) plus five one-caller readonly VOs to rehydrate a repo-owned YAML file into AppStory. A checked-in fixture the maintainer writes isn't a trust boundary; Yaml::parseFile() arrays + PHP's own type errors say the same thing. src/Fixture/

shrink: batch_activity_form_controller.js hand-rolls a listbox — arrow-key highlight, aria-expanded, mousedown-vs-blur race, 6-result slice — over checkboxes already in the DOM. Filtering the existing labels is ~25 lines against 105. assets/controllers/batch_activity_form_controller.js:78-160

native: csrfTokenId()/csrfToken() + a CsrfTokenManagerInterface constructor arg, copy-pasted into six controllers, to hand a token to a template. Twig ships csrf_token('delete-escort-' ~ id); build the id in RowActions. src/Controller/ (×6)

delete: $updatedAt on Volunteer, Project, User, Activity — written by four touch() calls, read by nothing. No template, no query, no test. Four columns, four getters, four touch(), four controller lines. src/Entity/ (×4)

yagni: /activities/new — the single-volunteer form, when /activities/new-batch already handles N≥1 and is what the home screen links to everywhere. Keep ActivityFormType for /edit, drop the route, the action and templates/activity/new.html.twig. src/Controller/ActivityController.php:110-127

shrink: five byte-identical _form.html.twig shells differing only by the cancel route. One anonymous component taking cancelUrl. 65 lines → 20. templates/*/_form.html.twig

delete: Escort::$isActive — a checkbox and a Status column with no reader. Both activity pickers list inactive escorts anyway, unlabelled. Either it filters or it goes; today it does nothing. src/Entity/Escort.php:25

delete: EscortRepository::findAllOrderedByName() and ActivityTypeRepository::findAllOrderedByName() — both controllers use the QueryBuilder method; nothing calls these. src/Repository/EscortRepository.php:33, src/Repository/ActivityTypeRepository.php:33

delete: ProjectFactory::partner(), ProjectFactory::inactive(), EscortFactory::inactive() — three unused factory states. src/Factory/

stdlib: CreateUserCommand's getHelper('question') + three Question objects. SymfonyStyle already has $io->ask() and $io->askHidden(). Drops two imports and some of that file's eight baseline entries. src/Command/CreateUserCommand.php:50-64

shrink: the "Other needs durationOther" rule written twice — Activity::validateDurationOther() and an identical Assert\Callback in BatchActivityFormType::configureOptions(). One constraint class, or have the batch input reuse the entity's. src/Form/BatchActivityFormType.php:110-118

yagni: knplabs/knp-paginator-bundle, honest caveat first: it's ADR 0009 and ADR 0011, and cutting it adds ~40 lines. But ListPaginator already parses page/perPage/sort/direction itself, passes SORT_FIELD_PARAMETER_NAME => null to switch the bundle's sorting off, and PaginationBar bypasses knp_pagination_render() for want of a translator. What's left is a count + a slice + a page window — Doctrine\ORM\Tools\Pagination\Paginator (already installed) and array_slice. Listing it because a dep held for a third of its surface is the shape this audit hunts; your call whether the trade is worth an ADR. src/Pagination/ListPaginator.php

net: -12,600 lines, -2 deps possible.

Two notes on scope: I left /reports walking every activity three times (three services, three findAllOrderedByDateDesc()) alone — that's performance, out of bounds here. Same for the Escort::$isActive item if you read it as a missing filter rather than a dead field: route that one through a normal review.