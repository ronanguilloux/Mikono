---
title: Automated outbound reminders
created: 2026-09-09
source: [ronan, kingsley]
status: needs-decision
size: L
priority: later
labels: [ops, ux]
---

## Why

**This card's trigger has fired.** It was deferred until someone at UCESCO
asked for reminders; Kingsley now has, naming three kinds:

- a volunteer arriving tomorrow;
- a volunteer's birthday, "to help us recognize our previous volunteers";
- anniversaries of an achievement, with a note or a current photo.

Needs an outbound channel, and given the Kibera/Mombasa context **SMS via
a regional gateway** (e.g. Africa's Talking) may be more reliable than
email.

## Done when

An ADR compares SMS vs. email vs. staying purely in-app, **before** any
infrastructure is committed, and says which of the three kinds above it
covers.

## Notes & links

**What such a reminder should be about has changed.** This was originally
framed as chasing stale *volunteers*, but the home screen shipped as
"Projects needing volunteers" precisely because volunteers who stop
appearing have usually **finished their stint** rather than lapsed.

Don't reintroduce that premise through the back door: the message worth
sending is about **quiet projects or the day's roster**, not a nudge to
volunteers who have moved on.

`src/Report/QuietProjectFinder` covers projects only and never volunteers
— that is deliberate and evidence-based. Read its class docblock before
"completing" it.

**Kingsley's three kinds do not violate that warning, and the warning
stays.** Birthday and anniversary notes go to volunteers who have already
finished — goodwill, not a nudge to come back. An arrival reminder is
about tomorrow's roster, which is what this card always said was worth
sending. Don't delete either the warning or the feature on a fast read of
the other.

**Two of the three are newly possible; one still isn't:**

- Birthdays need `Volunteer::$dateOfBirth`, which
  [ADR 0032](../../adr/0032-store-volunteer-profile-fields-as-optional-and-photos-as-re-encoded-jpeg-blobs-in-sqlite.md)
  shipped. It is optional, so the reminder covers only filled-in profiles.
- Arrivals need `Stay::$startDate`, which
  [ADR 0026](../../adr/0026-attach-volunteers-to-branches-through-dated-stays-and-derive-active-from-them.md)
  shipped.
- **Anniversaries of an achievement have no data behind them** yet —
  [achievements-on-a-stay](achievements-on-a-stay.md) adds it.

Both fields were missing when this card was first written; that is why it
said an outbound channel was the only blocker.

**The in-app half is split out.** All three kinds are shown on the
dashboard first, with no outbound channel:
[dashboard-arrival-and-birthday-reminders](dashboard-arrival-and-birthday-reminders.md)
and [achievement-anniversary-reminders](achievement-anniversary-reminders.md).
This card is now only about *sending* them (SMS or email), and its ADR can
weigh that against the in-app panel once it has been used.
