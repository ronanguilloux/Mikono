# Brainstorm

One file per feature, slice, or major decision, capturing the *why* before
any code is written. This is where the "context gap" gets closed, so that
resuming after a gap, or handing a task to a new agent, needs no
re-derivation of intent.

## Format

`NN-kebab-case-title.md` — two-digit sequence number, lowercase title.

Each file opens with a header block (Date, Author, Related links back to
[`CLAUDE.md`](../../CLAUDE.md) and [`../adr/`](../adr/)) followed by four
required sections:

- **Primary audience** — who reads this (future-self, a contributor, a
  teammate)
- **Desired impact** — what defines success for this specific milestone
- **The "Options Not Taken"** — at least two alternative paths and exactly
  why each was rejected
- **Constraints** — timeline pressure, technical debt, external dependencies

These files feed directly into ADRs: once a narrative here settles on a
decision, that decision gets locked in as a permanent record in
[`docs/adr/`](../adr/). An empty **Related ADRs** cell in the index below
means no decision has been locked in from that narrative — either it hasn't
settled yet, or it never will because the file is a survey rather than a
choice. Keep the cell filled the moment an ADR lands.

See [`context-capturer`](../../.claude/agents/context-capturer.md) for the
agent that drafts these.

## Index

| # | Title | Related ADRs |
| --- | --- | --- |
| [01](01-symfony-php-skills-context.md) | Staging Symfony/PHP Agent Skills | [0002](../adr/0002-stage-symfony-php-skills-in-agents-skills.md) |
| [02](02-volunteer-manager-v0.1-context.md) | UCESCO Volunteer Manager (VM) v0.1 | [0003](../adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md), [0004](../adr/0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md) |
| [03](03-test-hardening-and-next-steps.md) | Test Hardening Decisions (phase 11) | [0004](../adr/0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md) |
| [04](04-system-of-work-for-the-volunteer-manager.md) | A System of Work for the Volunteer Manager | [0012](../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md) |
| [05](05-exercising-the-app-and-the-panther-question.md) | Exercising the app: monkey testing, and the Panther question | [0007](../adr/0007-adopt-panther-for-adhoc-visual-verification.md) |
| [06](06-usage-analytics-cockpit.md) | Usage analytics: an observability cockpit | [0021](../adr/0021-read-usage-from-an-in-app-usage-screen-over-the-caddy-access-log.md) |
| [07](07-ponytail-audit.md) | Ponytail analytics: what could be simplified | |
| [08](08-off-site-encrypted-backups.md) | Off-site encrypted backups: who can decrypt, who can delete | [0017](../adr/0017-host-production-on-gandicloud-vps-in-france.md) |
| [09](09-volunteer-stays-at-branches.md) | Volunteer stays at branches | [0025](../adr/0025-model-ucesco-branches-as-a-standalone-reference-entity-seeded-by-migration.md), [0026](../adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md) |
| [10](10-programs-between-projects-and-activities.md) | Programs between projects and activities | [0030](../adr/0030-insert-programs-between-projects-and-activities.md) |
