---
title: Task/assignment hand-offs between users
created: 2026-09-09
source: ronan
status: deferred
size: M
priority: later
labels: [ux]
---

# Task/assignment hand-offs between users

## Why

E.g. assigning a follow-up to a colleague. Two people who speak daily do
not need the app to route work between them.

The old gate — "wait for a second `User`" — is **spent**: production now
holds three or four `User` rows, two of which sign in (Ronan and Edna).
This card carries the narrower gate that replaced it.

## Done when

Decided and, if built, shipped. The `User` entity is already scoped for
it.

## Trigger

A third person actually signing in, or Edna asking.
