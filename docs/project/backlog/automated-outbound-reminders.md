---
title: Automated outbound reminders
created: 2026-09-09
source: ronan
status: deferred
size: L
priority: later
labels: [ops, ux]
---

# Automated outbound reminders

## Why

Needs an outbound channel, and given the Kibera/Mombasa context **SMS via
a regional gateway** (e.g. Africa's Talking) may be more reliable than
email.

## Done when

An ADR compares SMS vs. email vs. staying purely in-app, **before** any
infrastructure is committed.

## Notes & links

**What such a reminder should be about has changed.** This was originally
framed as chasing stale *volunteers*, but the home screen shipped as
"Projects needing volunteers" precisely because volunteers who stop
appearing have usually **finished their stint** rather than lapsed.

Don't reintroduce that premise through the back door: the message worth
sending is about **quiet projects or the day's roster**, not a nudge to
volunteers who have moved on.

`src/Report/QuietProjectFinder` covers projects only and never volunteers
— that is deliberate and evidence-based. Read its class docblock before
"completing" it.

## Trigger

A decision that reminders are wanted at all — this is the kind of card
that should only start moving because Edna asks for it.
