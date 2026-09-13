# 8. Add an `Other` `ActivityDuration` case with a free-text companion field

Date: 2026-08-28

## Status

Accepted

## Context

`Activity::$duration` is an `ActivityDuration` enum (`HalfDay`/`FullDay`)
rendered as a radio group. Real activities last "1h", "2h", "2.5h", and the
form had no honest way to record them. The only consumer of duration is
`ActivitySummaryCalculator`, which aggregates `toDays()`; no report needs
sub-day granularity, and the requester said free text is fine for now.

## Decision

**Add a third case, `ActivityDuration::Other`, paired with a nullable
`Activity::$durationOther` string (length 100), rather than a structured
numeric duration.**

- `Other` sits in the same radio group as the other two.
- `durationOther` is `required: false` on the form. Conditional requiredness
  — required only when `duration` is `Other`, blank after trimming counts as
  missing — is an `#[Assert\Callback]` on the entity,
  `Activity::validateDurationOther()`. **This is the project's pattern for
  conditional validation:** a plain PHP method under normal test coverage,
  not an `Assert\When` expression string. Use it for the next such rule.
- `ActivityDuration::toDays()` returns `0.0` for `Other`. Free text is not
  parsed, so report day totals undercount `Other` entries. Accepted, not a
  bug; if duration-based reporting on these rows becomes a need, a structured
  hours field replaces this escape hatch rather than extending it.
- Lists show the free-text value for `Other` rows, never the bare word.
- `ActivityFactory` never picks `Other` at random, since it would need a
  companion value.

## Consequences

- **Positive:** closes a real data-entry gap with one enum case, one column
  and one validation method; `HalfDay`/`FullDay` behaviour is unchanged.
- **Negative / trade-offs:** unstructured text ("2.5h" and "2 hours 30" are
  both valid), and report totals silently undercount `Other` rows, with
  nothing telling the reader.
- **Reversibility:** keeping it costs nothing; replacing it once real data
  exists needs a data-migration decision (parse or discard), harder the
  longer it is used.

## Alternatives considered

### 1. Replace the enum with a numeric hours field

**Rejected.** No report needs it, and it would force reworking the named
buckets and the day aggregation for a need that doesn't exist.

### 2. `Assert\When`

**Rejected.** An expression-language string is a mini-DSL with no testable
method of its own; one plain-PHP style for conditional validation is better
than two.

### 3. Always require `durationOther`

**Rejected.** Adds friction to the two common, already-correct cases.

### 4. Show "Other" in the list instead of the text

**Rejected.** The VM needs to see "2.5h"; otherwise the field collects
nothing useful.
