---
title: An "Ask UCESCO Africa" AI assistant
created: 2026-09-23
source: kingsley
status: needs-decision
size: L
priority: later
labels: [data, security]
---

## Why

Kingsley asked for a secure assistant connected to UCESCO's own database,
answering questions in plain language with links back to the underlying
records. The seven examples given were: how many volunteers have served;
how many children UCESCO supported in 2026; which volunteers have not been
contacted for 90 days; a report on one volunteer's work at the Bright
Achievers School library project; volunteer hours at Peggy Lucas School
this year; which volunteer reports have deadlines next month; an annual
impact summary per volunteer.

## Done when

An ADR decides, before any code:

- **Whether to build it at all**, given that four of the seven sample
  questions have no data behind them (below).
- **What leaves the server.** Sending volunteer records to a third-party
  model is a DPA 2019 and GDPR question — personal data about people in
  Kenya, hosted in France
  ([ADR 0017](../../adr/0017-host-production-on-gandicloud-vps-in-france.md)).
  A local model, a hosted one, or none at all are three different answers.
- **Who may ask.** The app has exactly two roles today, `ROLE_USER` and
  `ROLE_ADMIN`. An assistant that can read every record is at least as
  sensitive as `/usage`, which is admin-only
  ([ADR 0021](../../adr/0021-read-usage-from-an-in-app-usage-screen-over-the-caddy-access-log.md)).
- **How a wrong answer is kept from reading as fact.** The ask already
  contains the mitigation — "with links back to the underlying documents" —
  and the ADR should treat those links as mandatory, not decorative.

## Notes & links

**In priority: explore implementing an API and / or an MCP server to that these questions would be answered using an agent.

**Most of the example questions cannot be answered from today's data. That
is the finding, not a footnote:**

| Question | What is missing |
| --- | --- |
| Volunteer **hours** on a project | Not stored. `App\Enum\ActivityDuration` records half day / full day / other; `Other` contributes `0.0` days and `/reports` undercounts it today. |
| Children supported in 2026 | No beneficiary entity exists. |
| Volunteers not contacted for 90 days | No contact log exists. |
| Reporting deadlines next month | No deadline field exists. |

The three that *are* answerable — total volunteers, one volunteer's work on
one project, a per-volunteer summary — are already computed by
`src/Report/ActivitySummaryCalculator` and shown on `/reports`. They need a
filter and a saved view, not a language model.

**The cheap first rung, for the ADR to argue past if it can:** filters and
per-volunteer views on `/reports`. They answer those three questions
exactly, cost nothing in privacy surface, and would tell us whether anyone
uses them before we spend anything on the rest.

**Do not revive the lapsed-volunteer premise through this card.** "Which
volunteers haven't been contacted for 90 days" is the same question
[automated-outbound-reminders](automated-outbound-reminders.md) rejected on
evidence: volunteers who go quiet have usually finished their stint, which
is why `src/Report/QuietProjectFinder` covers projects and never
volunteers. Read its class docblock first.

The four missing data models above overlap heavily with Nickson's recap
([ucesco-meeting-requirements-raw](ucesco-meeting-requirements-raw.md)).
Split that card before pricing this one.
