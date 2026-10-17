---
title: Import the legacy volunteer workbook into production
created: 2026-10-17
source: ronan
status: ready
size: L
priority: now
labels: [data, ops]
---

## Why

UCESCO top management wants the whole pre-Mikono volunteer Google Sheet
(intro form, 2023–2026 rosters, DIRECT volunteers, ACK clinic attendance)
in production, with all the personal data it holds. Without it, the app
only knows volunteers entered since September 2026.

## Done when

Progress tracker — tick as each step lands. Detail and reasoning:
[brainstorm 15](../../brainstorm/15-legacy-workbook-import.md).

- [x] **0.** `data-to-import/` gitignored (the workbook names real people; public repo)
- [ ] **1a.** Personal-data limits removed from ADR 0034, 0037, 0028, 0044 and `AGENTS.md`
- [ ] **1b.** New nullable `Volunteer` columns + form fields + profile rows, migration, tests green
- [ ] **1b.** Committed, CI image built, deployed, migration logged on prod
- [ ] **1c.** Local checkout == deployed commit, migrations in sync
- [ ] **2.** Freeze agreed with Edna; `make db_update`; baseline fingerprint + `S0.db` kept
- [ ] **3.** `app:import:legacy-workbook` command + integration test
- [ ] **4.** Main pass (form + rosters + DIRECT): mapping and people reviewed, dry-run clean, applied → `S1.db`
- [ ] **5.** ACK pass: dates swapped and checked, names reconciled, inferred stays accepted, activities applied → `S2.db`
- [ ] **6.** Exceptions pass (no dates anywhere) reviewed by hand → `S3.db`
- [ ] **7.** Local verification gate passed (integrity, deltas, invariants, tests, browser)
- [ ] **8.** Production swapped under freeze (fingerprint unchanged, B1 kept), checked on prod
- [ ] **9.** Clean-up: prod key removed locally, `make fixtures`, `data-to-import/` deleted, ADR 0044 updated, `done.md` entry, importer deleted, this card deleted

## Notes & links

- Plan, decisions, data profile and rules: [brainstorm 15](../../brainstorm/15-legacy-workbook-import.md)
- Swap procedure: [`deployment-plan.md`](../deployment-plan.md) → "Restore drill", "Seeding a server from a local dataset"
- Follow-up: [ODPC compliance review of the imported data](odpc-compliance-review-of-imported-data.md)
