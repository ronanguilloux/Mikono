---
title: Report login attempts on /usage, with the login and the outcome
created: 2026-09-09
source: ronan
status: needs-decision
size: M
priority: next
labels: [ops, security, data]
---

# Report login attempts on /usage, with the login and the outcome

## Why

Show sign-ins on `/usage`: who (the submitted email), when, and whether it
succeeded or failed. **Neither existing source can answer this.** The
Caddy access log has `POST /login` rows, but Symfony redirects on both
outcomes so success and failure look alike, and the log carries no
credentials — by design.

The `usage_event` table is the other half of the screen and is explicitly
*not* where this goes: `UsageEvent`'s docblock records that it has no
user, IP or session column on purpose, and that adding one is "a new
data-protection decision and needs its own ADR"
([ADR 0021](../../adr/0021-read-usage-from-an-in-app-usage-screen-over-the-caddy-access-log.md),
[ADR 0018](../../adr/0018-answer-usage-questions-from-the-caddy-access-log.md)).

## Done when

**This card is an ADR before it is a diff.** A login audit trail names an
identified colleague and, on a failed attempt, an arbitrary string
somebody typed into the email box. Decide before writing code:

- **retention** — this is the first thing on `/usage` that should expire;
- whether a failed attempt stores the submitted identifier **verbatim or
  masked**;
- whether the row records an **IP**;
- whether the screen showing it stays **admin-only** like the rest of
  `/usage`.

Then the diff, and the ADR is the record — no `done.md` entry.

## Notes & links

Mechanically it is small once decided: Symfony's `LoginSuccessEvent` and
`LoginFailureEvent` (the failure event carries the passport, hence the
attempted identifier) into a new listener and **its own table** — not
`usage_event`, whose whole point is being non-personal.

It also lands the one thing the throttler currently swallows silently:
repeated failures against a real address.

Whatever ships must take `App\Usage\UsageDateRange` like the screen's two
existing tables do, or the halves of the screen will describe different
periods.
