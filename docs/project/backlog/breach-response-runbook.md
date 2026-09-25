---
title: A breach-response runbook with the Act's clocks
created: 2026-09-25
source: ronan
status: ready
size: S
priority: next
labels: [ops, security, docs]
epic: production-readiness
---

## Why

s.43 sets clocks that start when someone *becomes aware* of a breach:

- **48 hours** for the processor (the maintainer) to tell UCESCO.
- **72 hours** for UCESCO to notify the Data Commissioner, with reasons
  if later.
- Affected volunteers are told in writing, unless the data was encrypted.
- A record of the facts, effects and remedial action is kept (s.43(8)).

Nobody should be working that out for the first time during an incident.
See [ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md) rule 9.

## Done when

`deployment-plan.md` has a "Personal-data breach" section that gives:

- **What counts:** a lost laptop holding an export, a leaked backup,
  unexpected admin sign-ins seen on `/usage`, a compromised server.
- **Contain:** rotate `APP_SECRET` and `PASSPORT_ENCRYPTION_KEY`, reset
  passwords, and check `login_attempt`.
- **Notify:** who tells whom, and by when (48 h and 72 h), with a
  notification template covering s.43(5)(a)–(e).
- **Record:** where the breach record lives. It is private, never in this
  public repo.

## Notes & links

- The contact point comes from
  [dpa-governance-for-ucesco](dpa-governance-for-ucesco.md).
- s.43(6): encrypted data may spare the notice to data subjects. The
  off-site copy is encrypted, and so are passport numbers (ADR 0033).
