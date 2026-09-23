---
title: Search the volunteer list
created: 2026-09-23
source: kingsley
status: ready
size: S
priority: next
labels: [ux]
---

# Search the volunteer list

## Why

`/volunteers` offers sorting and pagination, nothing else. Finding one
person means paging through the list or sorting and guessing where they
fall. Kingsley asked for a search on the volunteers tab "in case there are
hundreds of volunteers and you are trying to find only one".

## Done when

- `/volunteers?q=<text>` lists only volunteers whose first name, last name
  or email contains the text, case-insensitively, and the index has a
  control to type it.
- It survives a sort click and page 2, and a new search resets to page 1.
- A malformed or absent value (blank, `q[]=x`, whitespace only) means no
  filter: never a 400, 404 or 500
  ([ADR 0023](../../adr/0023-degrade-malformed-query-input-to-a-default.md)).
- The "Current view" export (`/volunteers/export.csv?q=<text>`) keeps the
  filter, which it does for free if the filter is applied in
  `listQueryBuilder()`.
- `VolunteerControllerTest` covers a match, a non-match and one malformed
  value.

## Notes & links

- Read the parameter like `requestedVolunteer()` in
  `src/Controller/ActivityController.php`: `$request->query->all()` guarded
  by `is_scalar()`. Never `InputBag::get()`.
- Apply it inside `VolunteerController::listQueryBuilder()` — the single
  query behind both `index()` and `export()`, so the export inherits the
  filter with no second query
  ([ADR 0029](../../adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)).
- Copy the control's markup from `templates/activity/index.html.twig`, the
  only index with filters today. Its comment block explains why the form
  sits above both renderings (the desktop table is `hidden md:block`, so a
  control inside it is invisible on a phone) and why `page` is dropped from
  the params carried through. `data-controller="auto-submit"` already
  exists.
- A search box and a dropdown are the same shape of change; keep this card
  and [activities-branch-filter](activities-branch-filter.md) consistent.
- `lastName` is nullable ([ADR 0014](../../adr/0014-make-a-volunteers-last-name-optional.md)),
  so the DQL must not assume it is there.
- Out of scope: filtering by branch, skills or status. Those come from
  Nickson's recap
  ([ucesco-meeting-requirements-raw](ucesco-meeting-requirements-raw.md))
  and are a different card once that one is split.
