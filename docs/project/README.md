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

- [`backlog/`](backlog/) — one markdown card per open item, holding its
  full text: why, what "done" means, and links to the reasoning behind it.
  Deliberately grows. See [`backlog/README.md`](backlog/README.md) for the
  card format and frontmatter vocabulary.
- [`next-steps.md`](next-steps.md) — an **index** of those cards, ordered,
  grouped Now / Next / Later. Forward-looking exclusively, and holds no
  item text of its own: a sentence describing an item belongs in its card.
  Deliberately stays short — the pre-commit hook warns past 250 lines.
- [`done.md`](done.md) — where completed work goes once its card is
  deleted. Append-only, growing, newest entries first — a changelog-style
  memory of what's shipped.

The split between the first two matters: two mutable lists both
*describing* the same work is how `next-steps.md` reached 449 lines once.
The cards describe; the index only orders.

## The rule, when something finishes

When a card in `backlog/` is completed, **delete the card file** and
remove its line from `next-steps.md`. Then:

1. **If it was an architectural decision** (stack, structure, service
   boundaries, data model, choice of library/tool) — it gets an ADR in
   `docs/adr/` instead, via the `adr-scribe` subagent, if one doesn't
   already exist. Don't also log it in `done.md`; at most leave a
   one-line pointer to the ADR.
2. **Otherwise** (a feature slice, a fixture, a doc pass, anything that
   isn't itself a decision) — append a dated entry to `done.md`.

There is deliberately no `backlog/done/` archive: `done.md` is the
changelog and git history holds the deleted card's full text, so a third
place to look would just be a third place to forget.

`next-steps.md` and `backlog/` should read, at any point in time, as a
clean forward-only pair — never a mix of done-and-not-done.
