# 22. Split `next-steps.md` into per-item backlog cards and a thin ordered index

Date: 2026-09-09

## Status

Accepted

## Context

[ADR 0001](0001-use-adr-and-agents-for-decision-capture.md) set up the
decision-capture half of this project's paperwork — `docs/adr/` for
finalized decisions, `docs/brainstorm/` for the narrative behind them, both
immutable and both growing. It left the forward-looking half as a single
mutable file, `docs/project/next-steps.md`. This ADR extends that system to
cover open work.

Two new sources of work arrived and had nowhere structured to go: Edna,
UCESCO's Volunteer Manager and the app's actual daily user, and Nickson,
UCESCO's IT contact. Requests from either are qualitatively different from
the ones we generate ourselves — "the app's only user asked for this" is
the strongest priority signal this project has — and a prose file has
nowhere to record that distinction.

Meanwhile `next-steps.md` had grown to 284 lines mixing unrelated kinds of
thing: production and server gates, a meeting agenda, a simplification
list from the ponytail audit, open questions for Edna, and items
deliberately deferred behind a trigger. Nothing carried metadata — no size,
no "is this ready to open, or does it need a decision first", no record of
who asked. The pre-commit hook already warns when that file passes 250
lines, a guard added because past-tense history keeps creeping back into a
file that is supposed to be forward-only; it reached 449 lines that way
once (`done.md`, 2026-09-05). That warning was treating a symptom. The real
problem is that a single prose file has no unit of work — nothing you can
create, move, or delete — so "remove it when it ships" has nothing to
operate on.

One constraint shaped the whole design: `next-steps.md` is cited from six
ADRs (0005, 0008, 0009, 0013, 0019, 0020) and six brainstorm files, and
ADRs are immutable, so those citations cannot be repointed.

## Decision

Split open work into per-item markdown **cards** in
`docs/project/backlog/`, and demote `next-steps.md` to a thin **ordered
index** of links to those cards, grouped Now / Next / Later, holding no
item text of its own.

The rule that keeps the pair from drifting is: **cards describe, the index
only orders.** A sentence about an item belongs in its card.

Cards are `docs/project/backlog/<slug>.md` — kebab-case, no sequence
number. Cards are deleted when they ship, so numbers would only leave gaps;
the filename is the stable id, which is what makes a card-to-card
cross-reference work.

Frontmatter: `title`, `created`, `source` (`ronan` | `edna` | `nickson` |
`ponytail-audit`), `status` (`ready` | `needs-decision` | `needs-design` |
`deferred` | `blocked`), `size` (XS/S/M/L/XL), `priority` (`now` | `next` |
`later`), `labels`, and an optional `epic`. Body sections: Why / Done when
/ Notes & links, plus Trigger on deferred cards only.

Three points in that shape are deliberate:

1. **`next-steps.md` was kept, not deleted.** Its twelve inbound citations
   from immutable records must keep resolving to something that still does
   what they cited it for. The file kept its path and its job narrowed.
2. **`priority` has exactly three buckets, and they are the same three
   headings as the index.** Not a P1/P2/P3 scale. Tying the field 1:1 to a
   visible ordered list is what stops it degrading into "everything is P1",
   the standard failure of a priority field on a solo backlog. If the Now
   list stops being short, that is the signal to demote something, not to
   add a fourth bucket.
3. **`size: XL` means "split this card", not "big".** That is the part of
   t-shirt sizing that pays for itself without a team's relative
   estimation behind it.

All 20 items in the old file were migrated to cards, with their reasoning
and their triggers carried over verbatim — the triggers were the most
valuable thing in there, and the least reconstructible.

The lifecycle is unchanged from the rule already in
[`docs/project/README.md`](../project/README.md): a card ships → **delete
the file** → an ADR if it was a decision, otherwise a dated entry in
`done.md`. Not both. There is deliberately no `backlog/done/` archive:
`done.md` is the changelog and git history holds the deleted card's full
text, so a third archive would just be a third place to forget to look.

## Consequences

- **Positive:** open work now has a unit that can be created, moved between
  buckets and deleted, which is what makes "remove it when it ships"
  enforceable. The frontmatter answers two questions the prose file
  couldn't: who asked for this, and is it ready to open or does it need a
  decision or a design pass first. The index stays structurally short, so
  the 250-line hook has nothing left to fire on. And a new request from
  Edna or Nickson has an obvious home — copy the template — instead of
  being appended to whichever section of the prose file looked closest.
- **Negative / trade-offs:** one more file per item, and the index has to
  be updated in the same commit as a card's creation or deletion. Nothing
  enforces that but the convention; a card can be deleted and its index
  line left behind, or the reverse. Reading the whole backlog now means
  reading 20 files rather than scrolling one — which is why `AGENTS.md`
  says to read the index and then the one card you're picking up, not the
  folder.
- **Reversibility:** cheap. Concatenating the cards back into a single
  prose file is mechanical, and `next-steps.md` already sits at the path
  that file would need.

## Alternatives considered

### 1. GitHub Issues

**Rejected.** Genuinely available — this repo is public on GitHub — but the
entire system of work here is markdown in the checkout, read by AI agents:
`docs/adr/`, `docs/brainstorm/`, `docs/project/`, `.agents/skills/` and
`.claude/agents/`, per
[ADR 0001](0001-use-adr-and-agents-for-decision-capture.md). Cards are
greppable offline and versioned in the same commit as the code
they describe. Issues would split the documentation system in two and put
the backlog behind a network call. Secondarily, Edna is not a technical
user and would not file there anyway, so the "who asked" problem that
prompted this would remain unsolved.

### 2. Keep `next-steps.md` as prose, cards only for Edna and Nickson

**Rejected.** Two mutable lists of open work drift apart — that is
precisely how the file reached 449 lines. Migrating all 20 existing items
is what makes the rule ("the index describes nothing") enforceable rather
than aspirational: with a prose section still present, every new item has a
plausible second home.

### 3. Shrink `next-steps.md` to a three-line pointer stub

**Rejected.** The twelve inbound citations from ADRs and brainstorm files
would then land on a file that no longer says what they cited it for, and
ADRs cannot be edited to repoint. Keeping the file with a narrower job
satisfies those citations; a stub does not.

### 4. A card-creation script or skill, and a `status`/`label` validator

**Rejected.** Copying `template.md` is one command, and the vocabulary in
`backlog/README.md` is short enough to read. Tooling here would be built
for a maintenance burden that hasn't been felt yet; that README is the
contract until keeping the index correct by hand actually hurts.
