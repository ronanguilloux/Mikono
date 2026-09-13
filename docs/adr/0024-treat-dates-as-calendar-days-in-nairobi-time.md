# 24. Treat dates as calendar days in Nairobi time

Date: 2026-09-13

## Status

Accepted

## Context

Every user of the app is in Kenya (EAT, UTC+3, no daylight saving). Much of
the app is relative to *today*: the home screen's "Today's roster" and
"Tomorrow's roster", the `Planned` badge on `/reports`, the volunteer page
and the Activities list, the batch form's date prefill, and the `/usage`
presets. All of them resolve `new \DateTimeImmutable('today')`.

With the server on UTC, the home screen showed the previous day's roster
between 00:00 and 03:00 local time. And an activity in the rosters happens
on a day, not at a time: a time component on `Activity::$date` made a
freshly created entity disagree with its own hydrated row, flipping the
`Planned` badge at random.

## Decision

**The application's clock is Nairobi local time, and an activity's date is a
calendar day with no time component.**

- `date.timezone = Africa/Nairobi` in `frankenphp/conf.d/10-app.ini`. PHP
  bundles its timezone database, so the slim production image needs no
  `tzdata`. The file is copied into the image, so changing it needs an image
  rebuild.
- "Today" is `new \DateTimeImmutable('today')`. There is no clock service;
  code that needs a testable today takes it as a parameter
  (`UsageDateRange::fromRequest($request, $today)`).
- `Activity::$date` is a `date_immutable` column. **Planned means
  `date > today`.**
- Anything that creates a date for persistence produces what a database
  round trip returns: midnight. `ActivityFactory` calls `setTime(0, 0)`.
- A day range is two midnight-inclusive bounds, with the upper bound made
  exclusive once, in the value object (`UsageDateRange`), not in each caller.
- Real instants stay datetimes: `UsageEvent::$occurredAt` and the Caddy
  log's `ts` are moments, compared as instants against those day bounds.
- The dev fixtures re-anchor the last two archive days onto today and
  tomorrow
  ([ADR 0012](0012-seed-fixtures-from-the-real-whatsapp-roster-archive.md)).

## Consequences

- **Positive:** day boundaries are right for every user with no per-user
  timezone plumbing, and "today" means the same thing on every screen.
- **Negative / trade-offs:** the app assumes one timezone. Anyone outside
  EAT, the maintainer in France included, sees Nairobi's today — the
  rosters roll over at 23:00 or 22:00 Paris time. A second-country
  deployment (the Uganda sites share EAT, so they don't count) would need
  to revisit this.
- **Reversibility:** moderate. Moving to UTC with per-user conversion
  touches every `'today'` call site and every date-relative query.

## Alternatives considered

### 1. UTC on the server, converted per user or at display

**Rejected.** The textbook default, but every user sits in one timezone with
no daylight saving, so it would add conversion to every date-relative query
for no user.

### 2. Store the activity date as a datetime

**Rejected.** The rosters are per day, and a time component is what made
entities disagree with their own rows.

### 3. A clock service (`symfony/clock`)

**Not adopted.** Passing `$today` where a test needs it has been enough; a
service would touch every call site for no current test that needs it.
