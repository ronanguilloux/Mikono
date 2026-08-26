# Project status

Living status documents — what's next, and what's already been done.
This is deliberately separate from [`docs/adr/`](../adr/) (immutable
decision records) and [`docs/brainstorm/`](../brainstorm/) (immutable,
pre-decision narrative): those two only ever grow, while
`next-steps.md` here is edited in place and never accumulates history
itself.

`AGENTS.md` stays stateless on purpose — it documents stable facts and
conventions (stack, commands, directory map), not "what phase are we
on" or "what did we just finish." When a session needs to know current
build status, it reads here instead.

## Files

- [`next-steps.md`](next-steps.md) — **only** what's next. Forward-looking
  exclusively: open work, open questions, what's not yet started.
  Rewritten in place each time work completes — it should never describe
  something already done.
- [`done.md`](done.md) — where completed work goes instead of staying in
  `next-steps.md`. Append-only, growing, newest entries first — a
  changelog-style memory of what's shipped.

## The rule, when something finishes

When an item in `next-steps.md` is completed, remove it from there. Then:

1. **If it was an architectural decision** (stack, structure, service
   boundaries, data model, choice of library/tool) — it gets an ADR in
   `docs/adr/` instead, via the `adr-scribe` subagent, if one doesn't
   already exist. Don't also log it in `done.md`; at most leave a
   one-line pointer to the ADR.
2. **Otherwise** (a feature slice, a fixture, a doc pass, anything that
   isn't itself a decision) — append a dated entry to `done.md`.

`next-steps.md` should read, at any point in time, as a clean forward-only
list — never a mix of done-and-not-done.
