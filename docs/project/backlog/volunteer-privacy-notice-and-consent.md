---
title: A privacy notice and a consent record for volunteers
created: 2026-09-25
source: ronan
status: needs-design
size: M
priority: now
labels: [docs, data, ux]
epic: production-readiness
---

## Why

s.29 requires volunteers to be told certain things **before** their data
is collected: what is collected, why, their rights, who else receives it
and with what safeguards, the security measures, and whether each field
is voluntary. Nothing does that today.

s.49(1) goes further for sensitive personal data processed **outside
Kenya**: it needs the volunteer's **consent** as well as safeguards.
Emergency contacts name family members, which s.2 counts as sensitive.
Production is to be hosted in Kenya
([ADR 0035](../../adr/0035-host-production-on-a-compute-engine-e2-small-in-johannesburg.md)),
so the server alone doesn't trigger s.49(1). It applies again if any copy
of emergency contacts is processed abroad, the off-site backup in
particular ([off-site-encrypted-backups](off-site-encrypted-backups.md)).
If one is, emergency contacts can't lawfully be kept until consent exists
and can be proven: under s.32(1) the burden of proof is UCESCO's. See
[ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md)
rules 2–4.

## Done when

- **Notice text:** a privacy notice covering s.29(a)–(h) is agreed with
  UCESCO and handed to every volunteer at onboarding. Volunteers never log
  in, so it is not an in-app page, though a copy may live in the repo or
  on ucesco.org.
- **Consent record:** the volunteer record shows that the notice was
  given, with the date. This is likely a nullable date field, which needs
  a migration and its own ADR per rule 1.
- **Only if sensitive data is processed outside Kenya** (see Why):
  - the record also shows whether sensitive-data consent was given, with
    the date;
  - the emergency contacts field is disabled, or shows a warning, while
    consent isn't on record;
  - withdrawing consent (s.32(2)) clears the emergency contacts and the
    consent date.
- **Help text:** the free-text fields (`notes`, `skills`, `interests`,
  `accommodationPreference`, activity notes) say not to record health,
  religion or ethnicity (rule 2).

## Notes & links

- Consent is a conversation with UCESCO staff, not a banner:
  [brainstorm 06](../../brainstorm/06-usage-analytics-cockpit.md).
- The notice needs the contact point from
  [dpa-governance-for-ucesco](dpa-governance-for-ucesco.md).
- Existing volunteers from the roster archive have no notice on record.
  Decide whether UCESCO re-contacts them or their emergency contacts stay
  empty.
