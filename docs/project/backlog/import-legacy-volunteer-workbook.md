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
- [x] **1a.** Personal-data limits removed from ADR 0034, 0037, 0028, 0044 and `AGENTS.md`
- [x] **1b.** New nullable `Volunteer` columns + form fields + profile rows, migration, tests green
- [x] **1b.** Committed, CI image built, deployed, migration logged on prod
- [x] **1c.** Local checkout == deployed commit, migrations in sync
- [x] **2.** `make db_update` (2026-10-17 15:37Z); baseline `scripts/db-fingerprint.sh` + `S0.db` kept — freeze with Edna assumed (weekend)
- [x] **3.** `app:import:legacy-workbook` command + integration test (idempotency and "prod wins" also proven on the real copy)
- [x] **4.** Main pass (form + rosters + DIRECT): mapping and people reviewed, dry-run clean, applied locally → `S1.db` (251 created, 9 filled, 251 stays)
- [x] **5.** ACK pass (`--max-gap=6`): dates swapped and checked, names reconciled, 1 inferred stay, 54 activities applied locally → `S2.db`
- [x] **6.** Exceptions pass reviewed: 163 created with no stay, 26 country-label rows skipped, 2 spelling variants filled → `S3.db`
- [x] **7.** Local verification gate passed on a full replay from S0 (integrity, deltas, invariants, prod rows untouched, 537 tests, every screen 200) → `S3.db`
- [x] **8.** Production swapped 2026-10-17 under freeze: fingerprint unchanged, B1 kept on the server, sha256 identical end to end, entrypoint "Already at the latest version", counts equal to local
- [ ] **9.** Clean-up: prod key removed locally, `make fixtures`, `data-to-import/` deleted, ADR 0044 updated, `done.md` entry, importer deleted, this card deleted

## Notes & links

- Plan, decisions, data profile and rules: [brainstorm 15](../../brainstorm/15-legacy-workbook-import.md)
- Swap procedure: [`deployment-plan.md`](../deployment-plan.md) → "Restore drill", "Seeding a server from a local dataset"
- Follow-up: [ODPC compliance review of the imported data](odpc-compliance-review-of-imported-data.md)
