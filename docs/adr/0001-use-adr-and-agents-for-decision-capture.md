# 1. Capture decisions as living ADRs and brainstorm files, backed by two subagents

Date: 2026-09-13

## Status

Accepted

## Context

Decisions made early in a project — stack, structure, data flow — are the
ones most easily lost, and this is a solo project worked on in bursts, often
by a fresh AI agent session with no memory of the last one. The sibling
project `Scribe` showed that `docs/adr/` plus `docs/brainstorm/` let both a
returning developer and a cold agent resume from the checkout alone; it
also showed that without an agent doing the capture, consistency depends on
discipline.

The first version of this process made ADRs immutable: a changed decision
got a new ADR superseding the old one. After three weeks and 22 records,
that produced chains a reader had to follow to learn what was decided *now*
(Panther across three ADRs, usage analytics across two), plus session
narrative explaining each correction. The records are read far more often
to answer "what holds today" than "what did we think on 2026-08-26", and git
already answers the second question.

## Decision

Keep decisions in the checkout, in two folders with different lifecycles,
each backed by a subagent:

- **`docs/brainstorm/`** — narrative before a decision: audience, impact,
  options, constraints. Written by `context-capturer`. Immutable: it records
  what was thought at the time.
- **`docs/adr/`** — one file per decision **in force**. Written and
  maintained by `adr-scribe`. Living: a changed decision is rewritten in
  place, an abandoned one is deleted, two records of one decision are
  merged. History is `git log -p docs/adr/`. The rules are in
  [`README.md`](README.md).

## Consequences

- **Positive:** decisions are greppable and dated; reading one ADR gives
  the current answer without following supersession chains. A fresh agent
  needs no re-derivation from commit messages.
- **Negative / trade-offs:** a small documentation cost per non-trivial
  decision. Editing in place means an old reasoning is only visible through
  git, and a careless rewrite can silently drop a reason that still holds —
  the "keep traps a future change must not undo" line in the template is
  the guard.
- **Reversibility:** cheap. Nothing at runtime depends on the process.

## Alternatives considered

### 1. No formal process — commit messages and memory

**Rejected.** On prior solo projects, context kept only there was
unrecoverable after a multi-month gap.

### 2. Immutable ADRs, superseded by new ones

**Rejected** after use. It preserves history the repository already
preserves, at the cost of making every reader reconstruct the current state
from a chain of records and correction prose.

### 3. One running `DECISIONS.md` log

**Rejected.** A single growing file is hard to link to per decision, and
mixes unrelated decisions in one diff.

### 4. GitHub Discussions or Issues

**Rejected.** Takes the record out of the checkout; a `git clone` should be
self-contained for an agent working offline.
