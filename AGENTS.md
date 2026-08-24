# Mikono

A new project, currently being bootstrapped. No package manager,
framework, test runner, or CI is configured yet, and this is not yet a
git repository.

## Decision capture

Architectural decisions are recorded as they're made, not reconstructed
later:

- The narrative behind a new feature or slice — audience, desired impact,
  rejected alternatives, constraints — goes in `docs/brainstorm/` first,
  via the `context-capturer` Claude Code subagent.
- Once a decision is finalized, it's locked in as a permanent record in
  `docs/adr/`, via the `adr-scribe` Claude Code subagent. ADRs are
  immutable once accepted — a changed decision gets a new ADR that
  supersedes the old one.

Every non-trivial architectural decision (stack, structure, service
boundaries, data model) gets an ADR before or alongside the code that
implements it. See `docs/adr/README.md` and `docs/brainstorm/README.md`.

## Directory map

- `docs/adr/` — Architecture Decision Records, one immutable file per
  decision.
- `docs/brainstorm/` — narrative context behind a feature or slice,
  written before the decision it leads to is locked in.
- `.agents/skills/` — Agent Skills, source of truth, shared across
  Claude Code, Gemini CLI, and Codex. See `.agents/skills/README.md`.
- `.claude/agents/` — Claude Code-specific subagents (`adr-scribe`,
  `context-capturer`). Not portable, unlike `.agents/skills/`.

## Stack

Not yet chosen. No package manager, framework, test runner, linter, or CI
exists in this repo. Do not assume or invent commands for any of these —
update this section once a stack is picked.

Symfony/PHP-oriented Agent Skills are staged in `.agents/skills/` in
anticipation of that stack (see `.agents/skills/README.md`,
`.agents/skills/VERSIONS.md`), but that is not the same thing as the
stack being chosen — there is still no `composer.json`, no `src/`, no
Symfony application here. Coding conventions and validation-gate
commands for an actual Symfony app become real once one is scaffolded,
not before.
