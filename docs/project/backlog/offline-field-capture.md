---
title: Offline field capture and sync
created: 2026-09-23
source: kingsley
status: needs-decision
size: L
priority: later
labels: [ux, data]
---

## Why

Kingsley asked whether a field officer in Samburu, or anywhere without
coverage, could open the app with no internet, record volunteer activities,
capture attendance, record GPS where appropriate, save everything locally
and have it synchronise automatically when the network returns.

## Done when

An ADR compares these three, **before any code**, and picks one:

1. **Read-only offline cache.** A service worker keeps the last-viewed
   pages (today's roster, the volunteer list) readable with no network. No
   writes. Smallest by a wide margin, and covers "open the app without
   internet" literally as asked.
2. **Queued writes, no merge.** Forms submit into a local queue and replay
   on reconnect, last write wins. Covers recording an activity in the field.
   Silently loses one of two conflicting edits.
3. **Full offline CRUD with conflict resolution.** What the ask describes in
   full.

The ADR also answers whether GPS capture is wanted at all, and if so, what
is done with the coordinates — ADR 0032 deliberately strips location
metadata from photos, and this would put it back through a different door.

## Notes & links

- **Be honest about the size of 3.** This is not a feature on the current
  stack; it is a second write path. The app today is server-rendered Twig
  with Turbo and no client-side persistence. Offline writes mean a service
  worker, client-side storage, a sync protocol and conflict resolution,
  plus a second set of validation rules that must agree with the server's —
  including the branch and program checks that live in
  `ActivityController::resolveStays()`
  ([ADR 0027](../../adr/0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md),
  [ADR 0030](../../adr/0030-insert-programs-between-projects-and-activities.md)).
  "Sync when internet returns" is the hard half: two officers editing the
  same roster offline is a merge problem, not a retry.
- **Recommendation for the ADR to argue against:** ship 1, measure whether
  anyone asks for 2. A read-only cache is a few dozen lines and answers the
  first of the five bullets Kingsley listed.
- `symfony/ux-live-component` is installed and unused, and
  [cut-ux-live-component](cut-ux-live-component.md) proposes removing it.
  Settle that card first — this is the only foreseeable feature that might
  argue for keeping it, and it should have to make that argument explicitly.
- Attendance as a first-class thing ("assigned vs. actual") is not modelled
  at all yet; it arrives with
  [ucesco-meeting-requirements-raw](ucesco-meeting-requirements-raw.md).
  Offline capture of a concept the app does not have is out of order.
