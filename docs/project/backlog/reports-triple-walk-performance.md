---
title: /reports walks every activity three times
created: 2026-09-09
source: ponytail-audit
status: needs-design
size: M
priority: later
labels: [perf]
---

# /reports walks every activity three times

## Why

The ponytail audit **deliberately left this alone** as a performance
question rather than a simplification one, and it is still unexamined.
It is recorded here so it stops being something only the audit's author
remembers.

## Done when

Measured first, then decided. At the current data volume (102 activities
in the dev archive) three walks cost nothing, so the plausible outcomes
are "measured, fine, closed" and "measured, fold into one pass" — and the
first is the likely one. Do not restructure `ActivitySummaryCalculator`
before there is a number.

## Notes & links

- `src/Report/ActivitySummaryCalculator.php` is the app's real domain
  logic and is one of the two things `composer infection` is scoped to
  (`infection.json.dist`) — any restructuring has mutation coverage
  watching it.
- [`../../brainstorm/07-ponytail-audit.md`](../../brainstorm/07-ponytail-audit.md)
