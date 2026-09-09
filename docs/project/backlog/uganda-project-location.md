---
title: Uganda rosters and a third ProjectLocation
created: 2026-09-09
source: ronan
status: deferred
size: S
priority: later
labels: [data]
---

# Uganda rosters and a third ProjectLocation

## Why

**Uganda is deferred, not decided.** The Kampala/Luwero rosters at the end
of August appear only as truncated headers with no volunteers, so nothing
is seeded for them.

## Done when

The question is answered: should `ProjectLocation` grow a third case?

Note the naming convention absorbs a Uganda roster without any code change
("Uganda - ..."), so this is a **scope question for the VM**, not a
modelling one. Ask what Uganda is meant to be to this app before adding an
enum case.

## Notes & links

- [ADR 0015](../../adr/0015-keep-a-projects-region-in-its-location-not-its-name.md)
  is what a third case would extend.
- `src/Enum/ProjectLocation.php`; enums are mapped as plain strings, so
  adding a case is portable off SQLite.

## Trigger

A complete Uganda roster arrives — one with volunteers on it, not a
truncated header.
