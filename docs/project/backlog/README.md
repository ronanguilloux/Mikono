# Backlog

One markdown card per item of open work. Copy
[`template.md`](template.md), fill it in, add a link to it in
[`../next-steps.md`](../next-steps.md).

**This folder is meant to grow.** That makes it the opposite of
`next-steps.md`, which is capped at a soft 250 lines by the pre-commit
hook precisely because it must *not*. The two have different jobs:

- **here** — the full text of every open item, one file each;
- **`next-steps.md`** — an ordered index of links to those files, grouped
  Now / Next / Later, with no item text of its own.

That division is the whole point. Two mutable lists both *describing* the
same work is how `next-steps.md` reached 449 lines once (`done.md`,
2026-09-05). If you find yourself writing a sentence about an item into
`next-steps.md`, it belongs in the card instead.

## Format

`<slug>.md` — kebab-case, no sequence number. Cards are deleted when they
ship, so numbers would only leave gaps; the filename is the stable id,
which makes `[off-site-encrypted-backups](off-site-encrypted-backups.md)`
a working cross-reference between cards.

## Frontmatter

| Field | Values | Notes |
| --- | --- | --- |
| `title` | free text | Sentence case, matches the `#` heading |
| `created` | `YYYY-MM-DD` | Absolute date, never "last week" |
| `source` | `ronan` \| `edna` \| `nickson` \| `ponytail-audit` | Who asked. A list when two people asked for the same thing |
| `status` | `ready` \| `needs-decision` \| `needs-design` \| `deferred` \| `blocked` | See below |
| `size` | `XS` \| `S` \| `M` \| `L` \| `XL` | XS <1h, S half-day, M a day, L multi-day. **`XL` means split this card**, not "big" |
| `priority` | `now` \| `next` \| `later` | Maps 1:1 onto the grouping in `next-steps.md` |
| `labels` | list | Free, small: `ops`, `security`, `ux`, `a11y`, `docs`, `perf`, `data` |
| `epic` | slug | Optional. Omit the key when the card stands alone |

**`status` is the field that decides what to do with a card, not how big
it is:**

- `ready` — decided and designed; open it and write the diff.
- `needs-decision` — the *what* is settled but the *whether* isn't. Often
  means an ADR comes before any code.
- `needs-design` — agreed it should exist; how it should look or behave is
  open.
- `deferred` — deliberately not now, with a **Trigger** section naming the
  event that changes that. Not a date.
- `blocked` — waiting on someone else (Edna, Nickson, a DNS change). Name
  who, in the card.

`priority` has three buckets and they are the same three headings as the
index, so it can't quietly degrade into "everything is P1" the way a
numeric scale does. If the Now list stops being short, that is the signal
to demote something, not to add a fourth bucket.

## When a card is finished

**Delete the file.** Then follow the rule that already governs this
folder's parent, in [`../README.md`](../README.md):

1. an architectural decision becomes an ADR in [`../../adr/`](../../adr/);
2. anything else becomes a dated entry in [`../done.md`](../done.md).

Not both. There is deliberately no `done/` subfolder here — `done.md` is
the changelog and git history holds the card's full text, so a third
archive would just be somewhere else to look.

Remove its line from `next-steps.md` in the same commit.

## Narrative goes elsewhere

A card says what to do and how you'll know it's done. Research, rejected
options and the reasoning behind a slice go in
[`../../brainstorm/`](../../brainstorm/) — link to it from **Notes &
links**. Long operational prose that already has a home
(`hosting-plan.md`, `deployment-plan.md`) stays there and gets linked too.
A card that is growing an "Options considered" section is telling you it
wants a brainstorm file.

## Privacy

This repository is public. Cards written from what Edna or Nickson ask for
must not name volunteers, sponsored children or donors — the same
constraint that keeps the raw WhatsApp exports gitignored
([ADR 0012](../../adr/0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)).
Server addresses and IDs belong in `*.local.md`, which is gitignored too.
