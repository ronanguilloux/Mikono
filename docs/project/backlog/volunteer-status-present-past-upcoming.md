---
title: Volunteer status as present, upcoming or past
created: 2026-10-03
source: edna
status: ready
size: M
priority: now
labels: [data, ux]
---

## Why

Edna wants to see at a glance who is here, who is coming and who has been.
Today a volunteer is only "Active" (a stay covers today) or "Inactive", which
puts a volunteer arriving next week in the same bucket as one who left two
years ago. Both of those matter to her: she plans for the first and keeps in
touch with the second.

## Rule

Derived from stay dates on every read and never stored, like "Active" today
([ADR 0026](../../adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md)).
"Today" means the Nairobi calendar day
([ADR 0024](../../adr/0024-treat-dates-as-calendar-days-in-nairobi-time.md)).
The checks run in this order, and the first one that matches wins:

1. **Present**: a stay covers today. There can be at most one, because stays
   never overlap (`StayController` refuses that).
2. **Upcoming**: no stay covers today, and a stay starts after today. If the
   volunteer also has past stays, this still wins: the latest stay sets the
   status. Edna doesn't expect anyone to have two future stays, but nothing
   breaks if someone does.
3. **Past**: every stay ended before today.
4. **No stay**: the volunteer has no stays at all, which is the case between
   saving a new volunteer and adding their first stay. It needs its own label
   rather than being folded into Past.

## Done when

- One `App\Enum\VolunteerStatus` (`Present`, `Upcoming`, `Past`, `NoStay`,
  each with a `label()` like `App\Enum\Gender`) is computed in one place,
  `Volunteer::getStatus(\DateTimeImmutable $today)`, from `$stays`. Then
  `isActive()` becomes `getStatus($today) === Present`, so its callers (the
  activity form pickers, the `(inactive)` labels) keep working unchanged.
- **`/volunteers`**:
  - The Status column and its export show the four labels.
  - `?status=` accepts `present|upcoming|past|none`. Anything else, the old
    `active`/`inactive` included, means no filter
    ([ADR 0023](../../adr/0023-degrade-malformed-query-input-to-a-default.md)).
  - Sorting on Status orders Present → Upcoming → Past → No stay. This is a
    HIDDEN rank in `createOrderedByNameQueryBuilder()` that replaces
    `isCurrent`, and it stays the default tie-break, so present volunteers
    still list first.
  - The filter, the list and the export share one query
    ([ADR 0029](../../adr/0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)).
- **Status combined with a branch** means the *same* stay, as it does for
  `active` today: upcoming at Kibera means the upcoming stay is at Kibera, and
  past at Mombasa means a stay at Mombasa has ended.
- **The status cells don't lazy-load stays row by row**:
  `VolunteerRepository::findIdsStayingOn()` becomes a one-query
  `findStatusesOn(array $volunteers, $today): array<int, VolunteerStatus>`.
  Its callers are the index, the export, and `ProgramController`'s matches
  cells.
- **Volunteer page**: the header badge (`templates/volunteer/show.html.twig:30`)
  shows the status. The Stays panel's hint ("'Active' above means…") explains
  the four statuses.
- **`/reports`**: the Volunteers tile reads "N present · N upcoming · N engaged"
  in place of "N active".
- ADR 0026 is rewritten in place (via `adr-scribe`) to name the four statuses.
  It's the living record of how status is derived, so this goes there and not
  in `done.md`.
- Tests:
  - A unit test on `getStatus()` covers each case at its date boundaries
    (a stay ending yesterday, one starting tomorrow, past and upcoming at once,
    no stays).
  - A functional test covers each `?status=` value, the status combined with a
    branch, and a malformed value.

## Notes & links

- Code that holds "active" today:
  - `VolunteerRepository::createOrderedByNameQueryBuilder()` (the `$active`
    and `isCurrent` select)
  - `VolunteerController::requestedStatus()`, `cells()` and `SORT_MAP`
  - `templates/volunteer/index.html.twig:67-76`, the `<select>`
  - `ProgramController.php:254`
  - `ReportMetricsCalculator`, through `countStayingOn()`
- The batch form's `data-stays` narrowing and the edit form's
  "current or upcoming" picker are about *which date* is being logged, not
  about status. Leave them alone.
- [monthly-volunteer-totals](monthly-volunteer-totals.md) counts "present
  during a month" by the same stay-overlap rule. Build this card first.
