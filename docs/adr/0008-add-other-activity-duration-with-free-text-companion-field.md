# 8. Add an `Other` `ActivityDuration` case with a free-text companion field

Date: 2026-08-28

## Status

Accepted

## Context

`Activity::duration` is an `ActivityDuration` backed enum (`HalfDay`/
`FullDay`) rendered as an expanded radio group in `ActivityFormType`. It was
introduced with the original Activity entity/CRUD area as part of v0.1 and
was never itself the subject of an ADR — half-day/full-day covered the
Volunteer Manager's initial expected cases.

In practice, volunteers log activities with durations that don't fit either
bucket — "1h", "2h", "2.5h" — and the form had no way to record them short
of picking the nearest wrong option. `ActivitySummaryCalculator`
(`src/Report/ActivitySummaryCalculator.php`) is the only piece of reporting
built on `Activity::duration` today, and it aggregates `toDays()` values;
no report currently depends on sub-day granularity. Given that, the person
requesting this fix was explicit that free text is an acceptable stopgap:
"we don't build report on durations yet, so open text value is fine for
now."

Separately, `docs/project/next-steps.md`'s P2 backlog already flagged a
structurally similar problem — `Project::partnerOrganizationName` is
conditionally required (only when the project has an external partner) but
that rule is currently enforced only via static help text, not validation.
That item was still open when this decision was made; this ADR's validation
approach (below) is the first time this codebase actually implements a
conditional-requiredness rule, and is written to be a reusable precedent
for that one and any future case like it.

## Decision

Add a third `ActivityDuration` enum case, `Other`, paired with a new
nullable `Activity::$durationOther` free-text string column
(`length: 100`), rather than replacing the enum with a structured numeric
duration model.

- `ActivityDuration::Other` renders alongside `HalfDay`/`FullDay` in the
  same radio group (`ActivityFormType`); its label is `'Other'`.
- `Activity::$durationOther` is a nullable `string` column, added via
  `migrations/Version20260828001923.php`
  (`ALTER TABLE activity ADD COLUMN duration_other VARCHAR(100) DEFAULT NULL`),
  already run against the dev database.
- At the Symfony form level, `durationOther`'s `TextType` is
  `'required' => false` — it is not a second always-required field.
  Conditional requiredness ("required only when `duration` is `Other`") is
  enforced instead by a `#[Assert\Callback]` method on the entity,
  `Activity::validateDurationOther()`, which adds a validation violation on
  the `durationOther` path only when `duration === ActivityDuration::Other`
  and the text is null or blank after trimming. This was chosen over
  `Assert\When` (an expression-language constraint) specifically for
  readability and testability: plain PHP conditional logic in a method
  under normal PHPUnit coverage, no expression-string to maintain or
  debug. This is the first conditional-validation pattern in the codebase
  and is the reference implementation the still-open
  `Project::partnerOrganizationName` backlog item
  (`docs/project/next-steps.md`) should follow when it's picked up.
- `ActivityDuration::toDays()` returns `0.0` for `Other` — free-text
  durations are not parsed into a day count. This is a known, accepted
  limitation, not a bug: `ActivitySummaryCalculator`'s aggregation
  undercounts total days for `Other` entries. Revisit only if/when
  duration-based reporting on `Other` entries becomes a real requirement —
  at that point a structured numeric-hours field would likely replace this
  free-text escape hatch rather than extend it.
- `ActivityController`'s index listing shows the free-text value for
  `Other` rows instead of the bare word "Other".
- `ActivityFactory` (Foundry) excludes `Other` from its random default
  case, since `Other` requires a companion text value the factory doesn't
  synthesize.

## Consequences

- **Positive:** closes a real data-entry gap (sub-day/hour-level durations)
  with a minimal, low-risk change — one enum case, one nullable column, one
  validation method. No existing `HalfDay`/`FullDay` behavior changes.
  Establishes a documented, tested pattern
  (`Assert\Callback` + entity method) for conditional-requiredness that the
  `Project::partnerOrganizationName` backlog item can reuse without
  re-deciding the approach.
- **Negative / trade-offs:** `Other` durations are unstructured free text —
  no unit, no numeric value, no validation of format ("2.5h" and "2 hours
  30" are equally valid). `ActivitySummaryCalculator` silently undercounts
  total days for any `Other` entry (contributes `0.0` days), which will
  understate report totals as more volunteers use the `Other` option.
  Nothing surfaces this undercount to a report viewer today.
- **Reversibility:** cheap in one direction, moderate in the other. Cheap:
  the `Other` case and column can be left in place indefinitely with no
  ongoing cost. Moderate to remove or replace: doing so once real data
  exists in `duration_other` would require a data migration decision
  (parse existing free text into a structured value, or discard it) —
  harder the longer `Other` is in active use.

## Alternatives considered

### 1. Replace the enum with a numeric hours field

**Rejected.** Over-engineering ahead of an actual need: no report in the
codebase consumes duration at finer-than-day granularity today, and the
person requesting the fix explicitly said open text is fine "for now."
A numeric-hours model would also force reworking `HalfDay`/`FullDay` (both
currently used and displayed as named buckets, not hour counts) and
`ActivitySummaryCalculator`'s `toDays()` aggregation, for a reporting need
that doesn't exist yet.

### 2. `Assert\When` (expression-language conditional constraint)

**Rejected.** Works, but trades a maintainable PHP method for an
expression string that has to be read and debugged as a mini-DSL rather
than ordinary code, with no unit-testable method of its own. Given this is
the first conditional-validation case in the project, `Assert\Callback`
was chosen to set a plain-PHP precedent for the similar, still-open
`Project::partnerOrganizationName` case rather than introduce two
different conditional-validation styles in the same codebase.

### 3. Make `durationOther` an always-required field regardless of `duration`

**Rejected.** Would force every `HalfDay`/`FullDay` activity to also carry
a meaningless free-text value, adding friction to the two most common,
already-correct cases to accommodate the least common one.

### 4. Leave `Other` durations unlisted in the index, or show the bare word "Other"

**Rejected.** Defeats the purpose of collecting the free text at all — a
Volunteer Manager reviewing the activity list needs to see "2.5h", not an
uninformative "Other" label, to get any value from the field.
