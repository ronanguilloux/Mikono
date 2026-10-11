# 0047. Count achievements with an optional indicator and quantity

Date: 2026-10-11

## Status

Accepted

## Context

UCESCO's management and donors ask for impact as numbers: "beneficiaries
reached", "children tutored", "meals served", per month, project or
branch. Nothing in the app can be summed for that. The only place a
count appears is `Program::$beneficiariesReached`, which is free text
([ADR 0030](0030-insert-programs-between-projects-and-activities.md)). A
program has no date of its own for the count, so it can't show a trend
either.

An achievement
([ADR 0038](0038-record-achievements-on-a-volunteers-stay.md)) already
records an outcome once, against a real day, a stay and a project. That
makes it the natural place to hang a number. UCESCO hasn't named the
indicators it wants to track, so the list of units has to be one the
Volunteer Manager builds. The narrative and the options
weighed are in
[`docs/brainstorm/14-dataviz-for-vm-and-management.md`](../brainstorm/14-dataviz-for-vm-and-management.md)
(option A in its schema table).

## Decision

**An achievement may carry a quantity and the `Indicator` it counts,
always as a pair, picked from a global indicator list that the VM
builds.**

- **Shape.** `Indicator` has a unique `name` ("Children reached") and an
  optional `description`. `Achievement` gains two nullable fields:
  `indicator` (`ManyToOne`) and `quantity`, a positive integer.
- **Both or neither.** `Achievement::validateIndicatorAndQuantity()`
  refuses one without the other. "12" with no unit means nothing, and a
  unit with no number counts nothing. Achievements without either stay
  valid, so achievements recorded before this decision need no
  backfill. Don't relax this to allow one field alone: a quantity whose
  unit is missing can't be summed with anything.
- **One global list, unseeded and absent from fixtures.** This is the
  same shape as `BeneficiaryGroup`
  ([ADR 0030](0030-insert-programs-between-projects-and-activities.md)).
  The VM enters indicators at `/indicators` (Settings → Indicators). The
  roster archive records none, and the dev dataset is never generated
  ([ADR 0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)).
- `/indicators` has the index/new/edit/delete and export shape of
  `/sources`
  ([ADR 0041](0041-record-where-each-volunteer-came-from-as-a-seeded-list-of-recruitment-sources.md),
  [ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)).
- **Delete guard:** an indicator can't be deleted while any achievement
  counts it. The index shows Delete as unavailable on those rows through
  the DataTable's `disabledReason`. The counts come from one batched
  query per page (`IndicatorRepository::countReferencingAchievementsFor`),
  not one query per row. The server-side guard in `delete()` stays
  regardless.
- **Display.** The stay and volunteer pages add the count after the
  project, as "· 1,200 Children reached". `/reports/achievements` gains
  two sortable columns, How many and Indicator, through the same
  `COLUMNS` and `cells()` that its CSV and XLSX export reads, so the
  export carries them too.
- **The achievement is the unit of impact, entered once.** It is never
  copied onto each volunteer's activities, where an outing with several
  volunteers would be counted once per volunteer.

## Consequences

- **Positive:** impact can be summed per month, project or branch from
  data the VM already enters, under one spelling per unit. A new
  indicator is a row, not a deploy. The numbers go out with the existing
  achievements export.
- **Negative / trade-offs:**
  - The figures are only as good as what the VM enters. Achievements stay
    optional, and so does counting one, so totals undercount until entry
    becomes a habit.
  - Achievements recorded before this decision carry no number.
  - The list can drift into near-duplicates ("Children reached",
    "Kids reached") if staff add them.
  - No chart reads these numbers yet. The first one is the
    [dataviz-first-charts](../project/backlog/dataviz-first-charts.md)
    card.
- **Not personal data.** An indicator is a unit and a quantity is a
  number; neither identifies a person. So
  [ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md) needs no
  new purpose, sensitivity, retention or transfer statement. The
  achievement's description keeps the ADR 0038 rules.
- **Whole-database export.** The admin zip picks up the `indicator` table
  and the two new `achievement` columns automatically. None holds a
  secret or file bytes, so none goes on a drop list
  ([ADR 0039](0039-export-the-whole-database-as-an-admin-only-zip-of-per-table-csvs.md)).
- **Reversibility:** cheap. One migration drops the table and the two
  columns, and only the recorded counts are lost. Nothing else refers to
  an indicator.

## Alternatives considered

### 1. A beneficiary count on `Activity`

**Rejected.** There is one `Activity` row per volunteer per day, and an
outing exists only as a derived date and project pair in
`ActivitySummaryCalculator`. A four-volunteer outing would carry its
beneficiaries four times, and nothing in the schema says which row holds
the real number. An achievement is entered once, deliberately.

### 2. A numeric field on `Program`

**Rejected.** A program has no date to put the count on, so the number
could be summed but never show a trend per month, and a program running
for months would hold one number for all of it.

### 3. A seeded indicator list

**Rejected.** UCESCO hasn't named its indicators, so a migration would
have to invent them. As with `BeneficiaryGroup`, the VM builds the list,
unlike the seeded `Branch`, `Skill` and `Source` lists, where UCESCO had
named the values.

### 4. A free-text unit next to the quantity

**Rejected.** "children" and "kids" are two strings that can't be summed
together, and summing is the purpose. A list gives each unit one
spelling.
