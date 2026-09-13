# 22. Keep open work as per-item backlog cards behind a thin ordered index

Date: 2026-09-09

## Status

Accepted

## Context

[ADR 0001](0001-use-adr-and-agents-for-decision-capture.md) covers decisions
(`docs/adr/`) and their narrative (`docs/brainstorm/`). Open work lived in a
single prose file, `docs/project/next-steps.md`, which kept growing,
mixing production gates, meeting agendas, audit findings, open questions and
deferred items, with past-tense history creeping back in (it reached 449
lines once). A prose file has no unit of work — nothing to create, move or
delete — so "remove it when it ships" had nothing to act on.

Requests also started arriving from Edna (UCESCO's Volunteer Manager, the
app's daily user) and Nickson (UCESCO's IT contact). "The only user asked
for this" is the strongest priority signal this project has, and prose had
nowhere to record who asked.

## Decision

**Open work is one markdown card per item in `docs/project/backlog/`;
`next-steps.md` is only an ordered index of links to those cards, grouped
Now / Next / Later. Cards describe; the index only orders.**

- Cards are `docs/project/backlog/<slug>.md`, kebab-case, no number: cards
  are deleted when they ship, so the filename is the stable id.
- Frontmatter: `title`, `created`, `source` (`ronan` | `edna` | `nickson` |
  `ponytail-audit`), `status` (`ready` | `needs-decision` | `needs-design` |
  `deferred` | `blocked`), `size` (XS–XL), `priority` (`now` | `next` |
  `later`), `labels`, optional `epic`. Body: Why / Done when / Notes & links,
  plus Trigger on deferred cards. Vocabulary in `backlog/README.md`.
- **`priority` has exactly three values, the same as the index headings.**
  Tying the field to a visible ordered list stops it degrading into
  "everything is P1". If Now stops being short, demote something; don't add
  a bucket.
- **`size: XL` means "split this card".**
- **Lifecycle:** a card ships → delete it and its index line → an ADR if it
  was a decision, otherwise a dated entry in `done.md`. Not both. No
  `backlog/done/` archive: `done.md` is the changelog and git holds the
  deleted card.
- The pre-commit hook warns when `next-steps.md` passes 250 lines — a sign
  item text is leaking into the index.

## Consequences

- **Positive:** open work has a unit that can be created, reprioritised and
  deleted; the frontmatter records who asked and whether an item is ready to
  open. The index stays short, and a new request has an obvious home.
- **Negative / trade-offs:** one file per item, and the index must change in
  the same commit as a card's creation or deletion — only convention
  enforces it. Reading the whole backlog means many files, which is why
  agents read the index and then one card.
- **Reversibility:** cheap — concatenating cards back into one file is
  mechanical.

## Alternatives considered

### 1. GitHub Issues

**Rejected.** The whole system of work is markdown in the checkout, read by
agents offline and versioned with the code. Issues split it in two, and
Edna would not file there anyway.

### 2. Keep prose, cards only for Edna and Nickson

**Rejected.** Two mutable lists of the same work drift apart — how the file
reached 449 lines.

### 3. A card-creation script and a frontmatter validator

**Rejected.** Copying `template.md` is one command; tooling would serve a
maintenance burden not yet felt.
