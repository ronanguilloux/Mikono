# 1. Use ADRs and dedicated subagents to capture decisions from day one

Date: 2026-08-24

## Status

Accepted

## Context

Mikono starts from zero: no code, no dependencies, no prior architecture.
The decisions made in the first days of a project — stack choices,
structural boundaries, data flow — are exactly the ones most likely to get
lost if they aren't written down at the moment they're made, because there's
no code yet to anchor them to and no commit history to reconstruct them
from later.

This workflow has already been validated on a sibling project (`Scribe`),
where `docs/adr/` plus `docs/brainstorm/` proved effective for letting a
solo developer resume work after multi-month gaps, and for letting a fresh
Claude Code session pick up implementation from cold context using only
`CLAUDE.md` and the existing records. What Scribe never built is a
dedicated agent to do the capturing — that omission means the docs are only
as consistent as manual discipline allows.

## Decision

Adopt `docs/adr/` (immutable, one-decision-per-file architecture records)
and `docs/brainstorm/` (narrative context: audience, impact, rejected
alternatives, constraints) as the standing decision-capture process for
this project, backed by two dedicated subagents:

- `context-capturer` drafts the `docs/brainstorm/` narrative before a
  decision is locked in.
- `adr-scribe` drafts and maintains the `docs/adr/` records once a decision
  is finalized, and keeps the index current.

## Consequences

- **Positive:** decisions are greppable and dated; onboarding a
  future-self or a new agent to this codebase needs no re-derivation of
  intent from commit messages or memory.
- **Negative / trade-offs:** a small, ongoing documentation overhead per
  non-trivial decision — writing the narrative and the record takes real
  time away from shipping code.
- **Reversibility:** cheap to abandon. The process has no runtime
  dependency; stopping just means no new ADRs get written. Existing records
  remain valid as a historical log either way.

## Alternatives considered

### 1. No formal process — rely on commit messages and memory

**Rejected.** This is exactly the failure mode the process exists to avoid:
on prior solo projects, context that lived only in memory or scattered
commit messages was unrecoverable after a multi-month gap, forcing costly
re-derivation of why a given approach was chosen.

### 2. One running `DECISIONS.md` log instead of one file per decision

**Rejected.** A single growing log loses the immutable/supersede model —
there's no clean way to mark one entry as replacing another without editing
history in place, and the file becomes unwieldy to grep or link to as it
grows.

### 3. GitHub Discussions or Issues only

**Rejected.** Couples the decision history to a specific host and a remote
repository that doesn't exist yet for this project, and takes the record
out of the checkout — a `git clone` should be self-contained.
