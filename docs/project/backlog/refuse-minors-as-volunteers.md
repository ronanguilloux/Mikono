---
title: Refuse a volunteer date of birth under 18
created: 2026-09-25
source: ronan
status: ready
size: XS
priority: next
labels: [data]
---

## Why

Processing a child's data needs a parent's or guardian's consent, plus
age verification (s.33). Mikono has neither, and it shouldn't have to:
volunteers are adults. `Volunteer::$dateOfBirth` must only be in the
past, so a minor can be recorded today without anyone noticing.
See [ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md) rule 10.

## Done when

- A date of birth less than 18 years before today (Nairobi time,
  ADR 0024) fails validation on the volunteer form, with a clear message.
- A functional test covers both sides of the boundary.
- A volunteer whose birth date isn't recorded stays valid (ADR 0032:
  optional fields).

## Notes & links

- If UCESCO ever takes volunteers under 18, this card becomes an ADR on
  guardian consent (s.27(a), s.33), not a relaxed constraint.
