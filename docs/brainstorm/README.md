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
[`docs/adr/`](../adr/).

See [`context-capturer`](../../.claude/agents/context-capturer.md) for the
agent that drafts these.

## Index

| # | Title | Related ADRs |
| --- | --- | --- |
| [01](01-symfony-php-skills-context.md) | Staging Symfony/PHP Agent Skills | [0002](../adr/0002-stage-symfony-php-skills-in-agents-skills.md) |
| [02](02-volunteer-manager-v0.1-context.md) | UCESCO Volunteer Manager (VM) v0.1 | [0003](../adr/0003-adopt-docker-frankenphp-symfony-sqlite-tailwind-for-volunteer-manager.md), [0004](../adr/0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md) |
| [03](03-test-hardening-and-next-steps.md) | Test Hardening Decisions (phase 11) | [0004](../adr/0004-adopt-phpunit-phpat-infection-panther-for-volunteer-manager-tests.md) |
