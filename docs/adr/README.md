# Architecture Decision Records

Each ADR captures one decision: its context, the choice made, and its
consequences. ADRs are immutable once accepted — to change a decision,
write a new ADR that supersedes the old one; never edit an Accepted ADR's
Decision or Consequences in place. When a new ADR supersedes a prior one,
flip the prior ADR's Status to `Superseded` and reference it from the new
one's Context.

## Format

`NNNN-kebab-case-title.md` — four-digit zero-padded number, lowercase
title.

Standard sections:

- **Status** — Proposed / Accepted / Deprecated / Superseded
- **Context** — what forces are at play
- **Decision** — the choice
- **Consequences** — positive, negative/trade-offs, reversibility
- **Alternatives considered** — what was rejected and why

Start from [`template.md`](template.md). See
[`adr-scribe`](../../.claude/agents/adr-scribe.md) for the agent that
drafts and maintains these.

## Index

| # | Title | Status |
| --- | --- | --- |
| [0001](0001-use-adr-and-agents-for-decision-capture.md) | Use ADRs and dedicated subagents to capture decisions from day one | Accepted |
| [0002](0002-stage-symfony-php-skills-in-agents-skills.md) | Stage Symfony/PHP Agent Skills in `.agents/skills/` | Accepted |
| [0003](0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md) | Adopt Docker+FrankenPHP, Symfony 8.1, SQLite, and Tailwind+Symfony UX for the Volunteer Manager app | Accepted |
| [0004](0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md) | Adopt PHPUnit, PHPat, Infection, and Panther for the Volunteer Manager app's test suite | Accepted |
| [0005](0005-adopt-phpstan-php-cs-fixer-rector-composer-audit.md) | Adopt PHPStan, PHP-CS-Fixer, Rector, and composer audit for the Volunteer Manager app | Accepted |
| [0006](0006-adopt-ucesco-theme-for-brand-identity.md) | Adopt "ucesco-theme" for the Volunteer Manager's brand identity | Accepted |
| [0007](0007-adopt-panther-for-adhoc-visual-verification.md) | Adopt Panther (not Playwright) for ad-hoc, Claude-session-driven visual verification | Accepted |
| [0008](0008-add-other-activity-duration-with-free-text-companion-field.md) | Add an `Other` `ActivityDuration` case with a free-text companion field | Accepted |
| [0009](0009-adopt-knppaginatorbundle-for-list-pagination.md) | Adopt KnpPaginatorBundle for list pagination across every index view | Accepted |
| [0010](0010-build-in-ci-and-deploy-by-image-pull.md) | Build the production image in CI and deploy by pulling it | Accepted |
| [0011](0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md) | Resolve list sorting in `ListPaginator` rather than with Knp's sortable support | Accepted |
| [0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md) | Seed fixtures from the real WhatsApp roster archive, never from generated data | Accepted |
| [0013](0013-record-every-escort-on-an-activity.md) | Record every escort on an activity, not just one | Accepted |
| [0014](0014-make-a-volunteers-last-name-optional.md) | Make a volunteer's last name optional | Accepted |
| [0015](0015-keep-a-projects-region-in-its-location-not-its-name.md) | Keep a project's region in its `location`, not in its name | Accepted |
