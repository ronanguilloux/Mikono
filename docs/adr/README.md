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
