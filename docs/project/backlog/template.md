---
title: <Card title, sentence case>
created: YYYY-MM-DD
source: ronan            # ronan | edna | nickson | ponytail-audit
status: ready            # ready | needs-decision | needs-design | deferred | blocked
size: M                  # XS <1h | S half-day | M a day | L multi-day | XL → split this card
priority: next           # now | next | later
labels: []               # free list, e.g. [ops, security, ux, docs, perf, a11y]
epic:                    # optional, omit the key entirely when the card stands alone
---

# <Card title>

## Why

<One paragraph. Who asked, and what breaks or stays broken without it.>

## Done when

<Checkable acceptance criteria. This is what says where to stop — and,
per [`../README.md`](../README.md), whether the finished card lands in
`done.md` or becomes an ADR.>

## Notes & links

<Links to brainstorm files, ADRs, code paths. Prior art, gotchas, the
reasoning that would otherwise have to be re-derived.>

## Trigger

<`status: deferred` only — delete this section otherwise. The concrete
event that makes this ready: "the first report of a hung save", "a third
person signing in". Not a date.>
