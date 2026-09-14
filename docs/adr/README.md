# Architecture Decision Records

Each ADR states one decision **currently in force**: its context, the
choice, its consequences, and what was rejected.

ADRs are living documents. When a decision changes, rewrite its ADR in
place and bump its `Date:`; when a decision is abandoned, delete the file;
when two ADRs turn out to describe one decision, merge them. The history of
how a decision evolved lives in `git log -p docs/adr/`, not in the ADR.

What does **not** belong in an ADR:

- **How we got here** — session narrative, discarded first attempts, "this
  ADR first claimed…". That goes in [`done.md`](../project/done.md) or
  [`docs/brainstorm/`](../brainstorm/).
- **Transient status** — "outstanding as of", "not yet done". That is a
  card in [`docs/project/backlog/`](../project/backlog/).
- **Implementation inventories** — file lists, migration names, which
  templates were touched. The code and git hold those.

## Format

`NNNN-kebab-case-title.md` — four-digit zero-padded number, lowercase
title. Numbers are never reused; the file name is the stable id even when
the title is rewritten.

Standard sections:

- **Status** — Proposed / Accepted
- **Context** — the forces that make this decision necessary
- **Decision** — the choice
- **Consequences** — positive, negative/trade-offs, reversibility
- **Alternatives considered** — what was rejected and why

Start from [`template.md`](template.md). See
[`adr-scribe`](../../.claude/agents/adr-scribe.md) for the agent that
drafts and maintains these.

## Index

| # | Title | Status |
| --- | --- | --- |
| [0001](0001-use-adr-and-agents-for-decision-capture.md) | Capture decisions as living ADRs and brainstorm files, backed by two subagents | Accepted |
| [0002](0002-stage-symfony-php-skills-in-agents-skills.md) | Keep portable Symfony/PHP Agent Skills in `.agents/skills/` | Accepted |
| [0003](0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md) | Docker+FrankenPHP, Symfony 8.1, SQLite, and Tailwind+Symfony UX | Accepted |
| [0004](0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md) | PHPUnit, PHPat, scoped Infection, and a route walk plus a monkey horde to exercise the app | Accepted |
| [0005](0005-adopt-phpstan-php-cs-fixer-rector-composer-audit.md) | PHPStan, PHP-CS-Fixer, Rector and composer audit, run by hook and CI | Accepted |
| [0006](0006-adopt-ucesco-theme-for-brand-identity.md) | "ucesco-theme": brand identity taken from ucesco.org | Accepted |
| [0007](0007-adopt-panther-for-adhoc-visual-verification.md) | Symfony Panther for every real-browser task, not playwright-php | Accepted |
| [0008](0008-add-other-activity-duration-with-free-text-companion-field.md) | An `Other` `ActivityDuration` case with a free-text companion field | Accepted |
| [0009](0009-adopt-knppaginatorbundle-for-list-pagination.md) | KnpPaginatorBundle for list pagination across every index view | Accepted |
| [0010](0010-build-in-ci-and-deploy-by-image-pull.md) | Build the production image in CI and deploy by pulling it | Accepted |
| [0011](0011-resolve-list-sorting-in-listpaginator-rather-than-knp-sortable.md) | Resolve list sorting in `ListPaginator`, not with Knp's sortable support | Accepted |
| [0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md) | Seed fixtures from the real WhatsApp roster archive, never from generated data | Accepted |
| [0013](0013-record-every-escort-on-an-activity.md) | Record every escort on an activity, not just one | Accepted |
| [0014](0014-make-a-volunteers-last-name-optional.md) | Make a volunteer's last name optional | Accepted |
| [0016](0016-admit-nodejs-as-a-test-dependency-not-as-application-code.md) | Node.js as a test dependency and ancillary tool, never as application code | Accepted |
| [0017](0017-host-production-on-gandicloud-vps-in-france.md) | Host production on GandiCloud VPS in France, not in Kenya | Accepted |
| [0020](0020-keep-sessions-on-the-database-volume-in-files.md) | Keep sessions on the database volume, in files | Accepted |
| [0021](0021-read-usage-from-an-in-app-usage-screen-over-the-caddy-access-log.md) | Usage from the Caddy access log, read in an in-app `/usage` screen | Accepted |
| [0022](0022-split-next-steps-into-backlog-cards-and-a-thin-index.md) | Open work as per-item backlog cards behind a thin ordered index | Accepted |
| [0023](0023-degrade-malformed-query-input-to-a-default.md) | Degrade malformed query input to a default, never to an error | Accepted |
| [0024](0024-treat-dates-as-calendar-days-in-nairobi-time.md) | Treat dates as calendar days in Nairobi time | Accepted |
| [0025](0025-model-ucesco-branches-as-a-standalone-reference-entity-seeded-by-migration.md) | Model UCESCO branches as a standalone reference entity seeded by migration | Accepted |
| [0026](0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md) | Attach volunteers to branches through dated stays and derive active from them | Accepted |
| [0027](0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md) | Tie projects to a branch and require an activity's project to share its stay's branch | Accepted |

Missing numbers were merged on 2026-09-13: 0015 into
[0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md), 0018
into [0021](0021-read-usage-from-an-in-app-usage-screen-over-the-caddy-access-log.md),
0019 into [0007](0007-adopt-panther-for-adhoc-visual-verification.md).
