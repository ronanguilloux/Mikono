# Project status

Living, mutable status documents — current build progress, what's left,
open questions. This is deliberately separate from
[`docs/adr/`](../adr/) (immutable decision records) and
[`docs/brainstorm/`](../brainstorm/) (immutable, pre-decision
narrative): those two are append-only history and never edited in
place once written, while files here are expected to be overwritten as
the project actually progresses.

`AGENTS.md` stays stateless on purpose — it documents stable facts and
conventions (stack, commands, directory map), not "what phase are we
on." When a session needs to know current build status, it reads here
instead.

## Files

- [`next-steps.md`](next-steps.md) — current build status and what's
  left. Update this in place as work completes; don't accumulate dated
  entries the way `docs/adr`/`docs/brainstorm` do.
