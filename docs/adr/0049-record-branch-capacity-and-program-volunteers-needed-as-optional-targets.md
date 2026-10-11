# 0049. Record branch capacity and program volunteers needed as optional targets

Date: 2026-10-11

## Status

Accepted

## Context

Two planning decisions depend on numbers the schema didn't hold. When a
volunteer asks to come, the VM has to decide whether to accept them at a
branch or send them to another one, which depends on how many people
that branch can host. When a program is being staffed, the VM has to
know how many more volunteers it needs. UCESCO's meeting recap asked for
"volunteers required/assigned" per program. The options weighed are B
and C in
[`docs/brainstorm/14-dataviz-for-vm-and-management.md`](../brainstorm/14-dataviz-for-vm-and-management.md).

UCESCO hasn't given these numbers yet, and some may never be known for
every branch or program. The five branches seeded by
[ADR 0025](0025-model-ucesco-branches-as-a-standalone-reference-entity-seeded-by-migration.md)
and every existing program have to stay valid without them.

Neither number is personal data: each is a count about a place or a
program, not about a person. So
[ADR 0034](0034-comply-with-kenyas-data-protection-act-2019.md) needs no
new purpose, sensitivity or retention statement for them.

## Decision

**Record two optional targets, `Branch::$capacity` and
`Program::$volunteersNeeded`, and set what is already booked against
each.**

- **Branch capacity.** `Branch::$capacity` is a nullable positive
  integer: how many volunteers the branch can host at once. It is on the
  branch form, and a Capacity column on `/branches` and its export
  ([ADR 0029](0029-export-every-list-view-to-csv-or-xlsx-with-openspout-open-to-all-signed-in-staff.md)).
  Null means nobody has said.
- **"Room at each branch" on the home screen.** The right column, under
  tomorrow's roster, shows one `<twig:ColumnChart>`
  ([ADR 0048](0048-draw-charts-as-server-rendered-html-and-css-from-twig-components.md))
  per active branch that has a capacity or any stay in the window. The
  window is the next 12 weeks from this week's Monday, in Nairobi
  calendar days
  ([ADR 0024](0024-treat-dates-as-calendar-days-in-nairobi-time.md)).
  The data comes from `App\Report\OccupancyLookAhead` over
  `StayRepository::findOverlappingAtActiveBranches()`.
  - **Each week counts its busiest day, not its average.** Beds run out
    on a day; an average hides the night two groups overlap.
  - **Stays alone decide it.** A volunteer's stays never overlap
    ([ADR 0026](0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md)),
    so the number of stays covering a day is the number of people there
    that day. Don't count activities instead: that would miss anyone who
    is on site with nothing logged.
  - **Capacity is the chart's dashed limit line.** Weeks above it are
    amber, and the summary says "Over capacity from the week of …" in
    words, so it never relies on colour alone. A branch with no capacity
    still shows its weeks, with "no capacity set" in the summary.
  - **It informs, never blocks.** Saving a stay that takes a branch over
    its capacity is neither refused nor warned about. Don't add a
    refusal on the strength of this field.
- **Program volunteers needed.** `Program::$volunteersNeeded` is a
  nullable positive integer on the program form, and a "Volunteers
  needed" column on `/programs` and its export.
- **Staffing on `/matches`.** Each program with a target shows "Needs N
  · B booked from today · K to find", or "fully booked" once K reaches
  zero.
  - **Booked means distinct volunteers with an activity in that program
    dated today or later** (`ProgramRepository::countBookedVolunteers()`,
    carried as `ProgramMatches::$bookedVolunteers`; K is
    `ProgramMatches::stillToFind()`, never below zero).
  - **The count ignores the page's filters.** Narrowing the candidates
    shown doesn't change how many are already booked.
  - **It changes neither the match rule nor the program order** of
    [ADR 0042](0042-match-available-volunteers-to-programs-by-skills-or-past-activity-type-at-their-branch.md).
    The target is shown next to the matches, not used to rank them.
- **Both columns are nullable in the schema**, added by plain `ALTER
  TABLE … ADD COLUMN`, so existing rows stay valid with null. They join
  the admin whole-database export automatically
  ([ADR 0039](0039-export-the-whole-database-as-an-admin-only-zip-of-per-table-csvs.md));
  neither is secret, so neither goes on its drop lists.

## Consequences

- **Positive:**
  - The VM sees, weeks ahead, which branch fills up and from when, in
    one glance on the home screen.
  - `/matches` answers "how many more does this program need?" next to
    the volunteers who could fill it.
  - Both numbers can be entered whenever UCESCO provides them, branch by
    branch and program by program, with nothing to backfill.
- **Negative / trade-offs:**
  - The targets are only as good as what the VM enters. An unset
    capacity draws no line, and an unset target shows no staffing line.
  - The home screen grows one section.
  - The look-ahead counts in PHP, about 84 days times the stays in the
    window per branch. That is fine at UCESCO's scale; a much larger
    roster would want the count pushed into the query.
  - Because over-capacity is shown but never blocked, a stay can still be
    saved past capacity without the VM noticing at that moment. A
    warning on the stay form is a possible follow-up.
- **Reversibility:** cheap. Two nullable columns, one calculator, one
  repository count and two template sections. Dropping them loses only
  the numbers the VM entered.

## Alternatives considered

### 1. Make both fields required

**Rejected.** UCESCO hasn't given the numbers yet, so every existing
branch and program would need an invented value, and an invented capacity
draws a false limit line. This is the same stance as the optional
profile fields of
[ADR 0032](0032-store-volunteer-profile-fields-as-optional-and-photos-as-re-encoded-jpeg-blobs-in-sqlite.md).

### 2. Refuse or warn when a stay exceeds capacity

**Rejected for now.** The capacities aren't known yet, and a hard block
would stop a real arrival from being recorded, leaving the roster wrong.
The look-ahead shows an overflow weeks before it happens. A non-blocking
warning on the stay form can follow once capacities are entered.

### 3. Capacity per day, or a room and bed entity

**Rejected.** Premature. One number per branch is what was asked for,
and nothing in UCESCO's planning works at room or bed level today.

### 4. "Assigned" as volunteers present at the branch, or including past activities

**Rejected.** A volunteer present at the branch isn't committed to a
given program. Past activities are done work, not staffing. Volunteers
booked from today on are the ones a planner can still move, so that is
the number set against the target.

### 5. Average occupancy per week

**Rejected.** An average smooths away the busiest day, which is the only
day that matters when beds run out. The busiest day is what the chart
shows.
