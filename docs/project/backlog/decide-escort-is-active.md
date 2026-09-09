---
title: Decide what Escort::$isActive is for
created: 2026-09-09
source: ponytail-audit
status: needs-decision
size: S
priority: later
labels: [ux, data]
epic: simplification
---

# Decide what Escort::$isActive is for

## Why

Today it is a checkbox and a Status column with **no reader**, and both
activity pickers list inactive escorts unlabelled.

Read it as a missing filter and it is a normal review item, not a
deletion — which is why this is `needs-decision`.

## Done when

One of:

- **it filters the pickers** — which is what the volunteer picker already
  does (`ActivityFormType`, and note that picker's escape hatch: the
  edit screen keeps the activity's *own* current selection selectable even
  once deactivated, or old records become uneditable); or
- **it goes**, field, checkbox, column and all.

## Notes & links

Settle this **before** touching `EscortFactory::inactive()`: uncalled
today, but exactly the fixture a test for that filter needs.
`ProjectFactory::inactive()` is the same shape and rides along.

Do not re-propose `ProjectFactory::partner()` — the audit listed it as
dead and it has two live callers.

- [`../../brainstorm/07-ponytail-audit.md`](../../brainstorm/07-ponytail-audit.md)
- Related read-path question:
  [`escort-display-and-reporting`](escort-display-and-reporting.md)
