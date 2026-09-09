---
title: Where escort should be read back out
created: 2026-09-09
source: ronan
status: needs-design
size: M
priority: next
labels: [ux, data]
---

# Where escort should be read back out

## Why

The write path shipped
([ADR 0013](../../adr/0013-record-every-escort-on-an-activity.md)), but
*where* escort should be read back out is genuinely open, and none of it
was part of the 2026-08-28 mockup review.

The home screen's rosters are escort's first read path — but they cover
only today and tomorrow and render escort as a text line, not a column or
a metric, so they settle neither question below.

## Done when

Both questions are answered, and whichever answer is "yes" is built:

**1. The Activities index** (`templates/activity/index.html.twig`) shows
no escort column. Worth a 6th column given the table already scrolls
horizontally on desktop — or is escort better left to the edit form?
Answering "yes" means both a 6th table column *and* a fourth line on the
mobile card.

Note escort is a **collection**
([ADR 0013](../../adr/0013-record-every-escort-on-an-activity.md)), so such
a column renders a list and **cannot** be a one-line `SORT_MAP` entry. The
honest options are an unsortable column or none.

**2. Reports** (`ActivitySummaryCalculator`, `/reports`) don't break
anything down by escort. Whether "days accompanied per escort" is a report
the VM actually wants is **unvalidated** — worth asking Edna before
building, since every escort row is also a staff workload figure.

If the two answers diverge enough to need separate work, split this card.

## Notes & links

- Related: [`decide-escort-is-active`](decide-escort-is-active.md), which
  is about the escort *picker*, not the read path.
