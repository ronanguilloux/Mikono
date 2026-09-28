---
title: Record minor volunteers with a guardian's consent
created: 2026-09-28
source: ronan
status: needs-decision
size: M
priority: next
labels: [data, security]
---

## Why

Some UCESCO volunteers are under 18. Processing a child's data needs a
parent's or guardian's consent, age verification, and care for the
child's best interests (s.33(1), (2)). Today Mikono has none of these,
yet nothing stops a minor being recorded: `Volunteer::$dateOfBirth` only
has to be in the past. Refusing minors outright was the earlier plan, but
it no longer fits the facts.
[ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md)
rule 10 ("Mikono does not record children as volunteers") is wrong as it
stands.

## Done when

- An ADR (via `adr-scribe`) rewrites ADR 0034 rule 10 in place with the
  answers below.
- When the date of birth on the volunteer form is less than 18 years
  before today, the guardian-consent fields become required. "Today" is
  the Nairobi day
  ([ADR 0024](../../adr/0024-treat-dates-as-calendar-days-in-nairobi-time.md)).
- A functional test covers both sides of the boundary.
- A volunteer with no recorded birth date stays valid (ADR 0032: optional
  fields).

## Open questions

For Edna or Kingsley:

- Is there a minimum age below which UCESCO doesn't take volunteers?
- Who counts as the guardian, and what does the app keep about them? The
  guardian's details are more family data, which s.2 counts as sensitive.
- Does consent stay on a paper form, with the app recording only that it
  exists and its date? Or is it scanned and uploaded (see
  [volunteer-document-attachments](volunteer-document-attachments.md))?
- Should any fields stay empty for a minor (passport, photo)? Does a minor
  get a shorter retention period?
- For a minor, the guardian exercises the data-subject rights (s.27(a)),
  so requests come from and go to them.

## Notes & links

- [volunteer-privacy-notice-and-consent](volunteer-privacy-notice-and-consent.md)
  designs the consent record for adults; a guardian's consent should share
  its shape.
- UCESCO-side paperwork:
  [dpa-governance-for-ucesco](dpa-governance-for-ucesco.md).
