---
title: Filter /activities by branch
created: 2026-09-14
source: ronan
status: ready
size: S
priority: next
labels: [ux]
---

# Filter /activities by branch

## Why

Every activity now belongs to a branch
([ADR 0026](../../adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md),
[ADR 0027](../../adr/0027-tie-projects-to-a-branch-and-require-an-activitys-project-to-share-its-stays-branch.md)),
but the activity log can't be narrowed to one. Once Mombasa and Nairobi
both log activities, the VM has to scroll past one branch's rows to find
the other's.

## Done when

- `/activities?branch=<id>` lists only activities at that branch, and the
  index has a control to pick it.
- It combines with the existing `?volunteer=<id>` filter, and pagination and
  sort links keep it.
- A malformed or unknown `branch` value (blank, `abc`, `branch[]=1`, a
  deleted id) means no filter: never a 400, 404 or 500
  ([ADR 0023](../../adr/0023-degrade-malformed-query-input-to-a-default.md)).
- The "Current view" export (`/activities/export.csv?branch=<id>`) keeps the
  filter too, which it does for free if the filter is applied in
  `listQueryBuilder()`.
- `ActivityControllerTest` covers the filtered list and one malformed value.

## Notes & links

- Read the parameter like `requestedVolunteer()` in
  `src/Controller/ActivityController.php`: `$request->query->all()` guarded
  by `is_scalar()`.
- Filter on the stay's branch (`a.stay` → `s.branch`). It is the same as the
  project's branch, since ADR 0027 enforces that, but the stay is where
  ADR 0026 anchors an activity's branch.
- Out of scope: a Branch column in the table. The table already scrolls
  horizontally, and since 2026-09-16 it has a sixth column, "Accompanied by"
  ([ADR 0013](../../adr/0013-record-every-escort-on-an-activity.md)).
