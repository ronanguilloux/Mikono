---
title: WhatsApp Business API / automated roster sending
created: 2026-09-09
source: ronan
status: deferred
size: L
priority: later
labels: [ops]
---

# WhatsApp Business API / automated roster sending

## Why

The manual copy-paste in the home screen's "Tomorrow's roster" takes well
under a minute today.

## Done when

An ADR exists — but **only if that manual step demonstrably becomes a
bottleneck, not preemptively.** API costs, volunteer opt-in/consent and
message-template approval all apply, and all three are real work before a
single message sends.

## Notes & links

`/usage` counts the clipboard copy as a `usage_event`, since it sends no
request of its own. That is the evidence this card would be argued from:
how often the copy actually happens.

## Trigger

The manual copy-paste demonstrably becoming a bottleneck.
