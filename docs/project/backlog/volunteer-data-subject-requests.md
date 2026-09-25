---
title: Answer a volunteer's access, correction and erasure requests
created: 2026-09-25
source: ronan
status: needs-design
size: L
priority: next
labels: [data, security]
---

## Why

A volunteer can ask for their data (s.26(b)), and receive it in a
machine-readable form within 30 days (s.38). They can have it corrected
or erased (s.40), have its processing restricted (s.34), or object to it
(s.36). Today staff can correct fields, but nothing else works:

- There is no single-volunteer export.
- A volunteer with activities **cannot be deleted at all**: the
  delete-guard refuses. So erasure is impossible for exactly the people
  most likely to ask for it.

See [ADR 0034](../../adr/0034-comply-with-kenyas-data-protection-act-2019.md) rule 5.

## Done when

- **Export:** a "Download this volunteer's data" action on the profile
  (JSON or XLSX) covers:
  - every profile field, with the passport number decrypted;
  - the photo;
  - stays;
  - activities.

  It reuses the ADR 0029 OpenSpout plumbing where it fits.
- **Anonymise:** an "Anonymise" action for a volunteer with activities.
  It:
  - clears or replaces every identifying field;
  - deletes the photo;
  - keeps the stays and activity rows, so `/reports` counts stay true.

  This is the s.39(2) and s.39(1)(d) statistics path. It needs its own
  ADR, because it changes the delete-guard rule in CLAUDE.md.
- **Restrict:** a "restricted" flag that keeps a volunteer out of pickers,
  rosters and exports while the data stays stored (s.34(2)), for a
  contested record or one kept as evidence (s.40(3)).
- **Timing:** the procedure (who answers, and within how many days) is
  written in `deployment-plan.md` or the notice. The General Regulations
  2021 deadlines (7 days for access, 14 for rectification, as reported by
  the Securiti summary) are confirmed against the gazetted text.

## Notes & links

- s.27: a guardian or an authorised person may exercise the rights on the
  volunteer's behalf.
- s.40(2): erasure must also reach third parties the data was shared
  with. That means the off-site backup copy too, so see
  [personal-data-retention](personal-data-retention.md).
