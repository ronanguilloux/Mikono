---
title: Achievement anniversary reminders on the dashboard
created: 2026-09-25
source: [ronan, kingsley]
status: needs-design
size: S
priority: next
labels: [ux]
epic: dashboard-reminders
---

## Why

Kingsley's third kind of reminder: the anniversary of something a volunteer
achieved, as a reason to get back in touch with someone who has moved on.
It depends on [achievements-on-a-stay](achievements-on-a-stay.md) — no
achievement data, no anniversary.

## Done when

- The dashboard Reminders panel (from
  [dashboard-arrival-and-birthday-reminders](dashboard-arrival-and-birthday-reminders.md))
  lists every achievement whose `achievedOn` falls on **today's month and
  day, one or more whole years ago** (29 February → 28 February in a
  non-leap year).
- Each row reads as a sentence with the volunteer, the achievement, the
  project and the stay's branch, e.g.:

  > 🎉 **One year ago today**, Nadia achieved "Building a Library" as part
  > of the Project X project in Kibera. A lasting difference — worth a
  > thank-you message?

  The volunteer and project link to their pages.
- Integration tests for the date matching (1 year, several years, leap
  day, same-year achievement not shown).

## Notes & links

**Suggested copy** — pick one line per anniversary count, not at random,
so the dashboard doesn't change on reload and tests stay deterministic:

| Anniversary | Lead-in | Cheer |
| --- | --- | --- |
| 1 year | 🎉 One year ago today, … | A lasting difference — worth a thank-you message? |
| 2–4 years | 🌱 *N* years ago today, … | Still growing. Why not tell Nadia how it's going? |
| 5+ years | 🏆 *N* years ago today, … | A legacy at UCESCO — a milestone to celebrate together. |

"Last year" reads oddly when the achievement was last December, so use
"One year ago today" instead. Keep the name, not a pronoun, in the cheer —
the app doesn't record pronouns.

To settle in the design pass: whether to show upcoming anniversaries
(tomorrow) like birthdays, and whether the row offers a shortcut to the
volunteer's phone or email.
