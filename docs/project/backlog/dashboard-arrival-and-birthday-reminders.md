---
title: Arrival reminders on the dashboard
created: 2026-09-25
source: [ronan, kingsley]
status: ready
size: M
priority: next
labels: [ux]
epic: dashboard-reminders
---

## Why

Kingsley asked for reminders of volunteers arriving and of volunteers'
birthdays, "to help us recognize our previous volunteers". Both have data
behind them now (`Stay::$startDate`, `Volunteer::$dateOfBirth`), and both
can be shown on the home screen with no outbound channel at all — the
Volunteer Manager opens the dashboard every morning anyway. Without it an
arrival is noticed when the volunteer is at the gate, and a birthday not
at all.

## Done when

- The dashboard has a **Reminders** panel, above or beside the rosters,
  that disappears entirely when it has nothing to say.
- **Arrivals:** every stay whose `startDate` is exactly **tomorrow**,
  **in 3 days** or **in 7 calendar days**, grouped under those three
  headings, each row naming the volunteer (linked to their page), the
  branch and the stay's dates. So each arrival is announced three times —
  a week, three days and one day ahead. A stay that starts the day after
  the same volunteer's previous stay ends is a continuation, not an
  arrival, and is left out.
- "Today" is the Nairobi calendar day
  ([ADR 0024](../../adr/0024-treat-dates-as-calendar-days-in-nairobi-time.md)),
  from an injected clock so tests can pin it.
- Integration tests for the finder (offsets, continuation stays); a
  functional test that the panel renders and is absent when empty.

## Notes & links

- Put the logic in `src/Report/` next to `RosterBuilder` and
  `QuietProjectFinder`, returning readonly VOs, the way the home screen
  already does. `DashboardController` stays thin.
- Birthday matching on month/day in SQL is awkward on SQLite and not
  portable; with a few hundred volunteers, fetching those with a date of
  birth and filtering in PHP is fine.
- Birthday reminders shipped first (`BirthdayReminderFinder`, `done.md`
  2026-09-28): arrivals can join that panel, renamed "Reminders", or sit
  beside it.
- This is the in-app half of
  [automated-outbound-reminders](automated-outbound-reminders.md). Sending
  the same reminders by SMS or email stays that card's decision.
- Achievement anniversaries join this panel later:
  [achievement-anniversary-reminders](achievement-anniversary-reminders.md).
