---
title: Scheduled donor digest emails
created: 2026-09-09
source: ronan
status: deferred
size: L
priority: later
labels: [ops]
---

# Scheduled donor digest emails

## Why

Needs a mailer/scheduler decision, which is new infrastructure this app
does not have.

The print-friendly view already covers the **on-demand** handoff case
without one, which is why this stays deferred rather than merely
unscheduled.

## Done when

An ADR settles the mailer and scheduler, or the card closes because the
print view keeps being enough.

## Notes & links

Shares an outbound channel with
[`automated-outbound-reminders`](automated-outbound-reminders.md) — if
either one moves, decide the channel once, for both.

## Trigger

Someone asking for the digest to arrive without being asked for.
